<?php

namespace Tests\Unit\Operacao;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Os scripts de operação rodam em servidores; um erro de sintaxe ou um
 * comando destrutivo esquecido custa caro. Estes testes não executam nada.
 */
class ScriptsOperacaoTest extends TestCase
{
    public static function scripts(): array
    {
        $root = dirname(__DIR__, 3).'/scripts/operacao';
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'sh') {
                $files[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
            }
        }
        ksort($files);

        return $files;
    }

    #[DataProvider('scripts')]
    public function test_script_tem_sintaxe_valida_de_bash(string $path): void
    {
        exec('bash -n '.escapeshellarg($path).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));

        exec('command -v shellcheck', $found, $hasShellcheck);
        if ($hasShellcheck === 0) {
            exec('shellcheck -S error '.escapeshellarg($path).' 2>&1', $lint, $lintCode);
            $this->assertSame(0, $lintCode, implode("\n", $lint));
        }
    }

    public function test_atualizacao_da_vps_de_desenvolvimento_so_roda_em_local_e_nao_apaga_dados(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3).'/scripts/operacao/vps/atualizar-desenvolvimento.sh');

        $this->assertStringContainsString('[[ "$ambiente" == local ]]', $script);
        $this->assertStringContainsString('git merge --ff-only origin/main', $script);
        $this->assertStringContainsString('--untracked-files=no', $script);
        foreach (['migrate:fresh', 'db:wipe', 'migrate:reset', 'DROP ', 'reset --hard', 'push', 'rm -rf'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script, $forbidden);
        }
    }

    public function test_verificacao_do_servidor_so_le_e_nao_mostra_segredos(): void
    {
        $script = dirname(__DIR__, 3).'/scripts/operacao/vps/verificar-ambiente.sh';
        $projeto = sys_get_temp_dir().'/astuti-verificar-'.bin2hex(random_bytes(4));
        mkdir($projeto);
        file_put_contents($projeto.'/artisan', "<?php\n");
        file_put_contents($projeto.'/.env', implode("\n", [
            'APP_ENV=production',
            'APP_DEBUG=true',
            'APP_KEY=base64:chave-ficticia-que-nao-pode-aparecer',
            'APP_URL=https://escola.exemplo.invalid',
            'SESSION_SECURE_COOKIE=false',
            'DB_PASSWORD=senha-ficticia-que-nao-pode-aparecer',
            '',
        ]));
        chmod($projeto.'/.env', 0644);
        $antes = md5_file($projeto.'/.env').decoct(fileperms($projeto.'/.env'));

        try {
            exec('ASTUTI_VERIFICAR_SEM_SISTEMA=1 ASTUTI_VERIFICAR_SEM_REDE=1 bash '.escapeshellarg($script)
                .' --detalhes --projeto='.escapeshellarg($projeto).' 2>&1', $linhas, $codigo);
            $saida = implode("\n", $linhas);

            $this->assertSame(1, $codigo, $saida);
            $this->assertStringContainsString('FALHA    .env legivel por qualquer usuario', $saida);
            $this->assertStringContainsString('FALHA    APP_DEBUG=true em producao', $saida);
            $this->assertStringContainsString('FALHA    SESSION_SECURE_COOKIE nao esta true', $saida);
            $this->assertStringContainsString('OK       APP_KEY definida', $saida);
            foreach (['chave-ficticia', 'senha-ficticia', 'exemplo.invalid', 'DB_PASSWORD'] as $segredo) {
                $this->assertStringNotContainsString($segredo, $saida, $segredo);
            }
            clearstatcache();
            $this->assertSame($antes, md5_file($projeto.'/.env').decoct(fileperms($projeto.'/.env')));
            $this->assertSame(['.env', 'artisan'], array_values(array_diff(scandir($projeto), ['.', '..'])));
        } finally {
            @unlink($projeto.'/.env');
            @unlink($projeto.'/artisan');
            @rmdir($projeto);
        }

        $fonte = file_get_contents($script);
        foreach (['migrate', 'DROP ', 'rm -', 'sed -i', 'tee ', 'ufw allow', 'ufw enable', 'ufw delete', 'systemctl restart', 'systemctl reload', 'cat "$env"', 'cat .env'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fonte, $proibido);
        }
    }

    public static function scriptsQueAlteram(): array
    {
        return [
            'preparar-base' => ['preparar-base.sh', 'PREPARAR-BASE'],
            'ajustar-acesso' => ['ajustar-acesso.sh', 'AJUSTAR-ACESSO'],
        ];
    }

    #[DataProvider('scriptsQueAlteram')]
    public function test_script_que_altera_o_servidor_so_simula_sem_a_confirmacao(string $arquivo, string $confirmacao): void
    {
        $script = dirname(__DIR__, 3).'/scripts/operacao/vps/'.$arquivo;
        $fonte = file_get_contents($script);

        exec('bash '.escapeshellarg($script).' 2>&1', $linhas, $codigo);
        $saida = implode("\n", $linhas);
        $this->assertSame(0, $codigo, $saida);
        $this->assertStringStartsWith('SIMULACAO: nada sera alterado.', $saida);
        $this->assertStringContainsString('Fim da simulacao. Nada foi alterado.', $saida);
        $this->assertStringNotContainsString('  + ', $saida, 'nenhum comando real na simulacao');

        exec('bash '.escapeshellarg($script).' --confirmar=SIM 2>&1', $ignorado, $codigoErrado);
        $this->assertSame(2, $codigoErrado, 'confirmacao diferente da exata e recusada');

        $this->assertStringContainsString('--confirmar='.$confirmacao.') real=1', $fonte);
        foreach (['migrate', 'DROP ', 'createdb', 'psql', 'rm -rf', '.env', 'allow 5432', 'allow 80', 'allow 443', 'curl -fsSL', '| bash', '| sh'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fonte, $proibido);
        }
    }

    public function test_ajuste_de_desempenho_confere_antes_de_aplicar_e_nao_toca_em_dados(): void
    {
        $script = dirname(__DIR__, 3).'/scripts/operacao/vps/ajustar-desempenho.sh';
        $fonte = (string) file_get_contents($script);

        exec('bash '.escapeshellarg($script).' --confirmar=SIM 2>&1', $ignorado, $codigoErrado);
        $this->assertSame(2, $codigoErrado, 'confirmacao diferente da exata e recusada');
        $this->assertStringContainsString('--confirmar=AJUSTAR-DESEMPENHO) modo=aplicar', $fonte);
        $this->assertStringContainsString('--desfazer=AJUSTAR-DESEMPENHO) modo=desfazer', $fonte);
        // Testa a configuracao do PHP e confere o valor efetivo antes de recarregar.
        $this->assertLessThan(strpos($fonte, 'systemctl reload "$servico_fpm"'."\n    printf '  PHP-FPM recarregado"), strpos($fonte, 'if ! "$php_fpm" -t; then'));
        $this->assertStringContainsString('pm\\.max_children = ${filhos}', $fonte);
        // So arquivos proprios: nada de editar www.conf, postgresql.conf, .env ou dados.
        foreach (['sed -i', 'www.conf"', '>>/etc', 'ALTER SYSTEM', 'migrate', 'DROP ', 'rm -rf', '.env', 'ufw', 'sshd'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fonte, $proibido);
        }
    }

    public function test_teste_de_carga_so_le_e_recusa_producao(): void
    {
        $script = dirname(__DIR__, 3).'/scripts/operacao/vps/teste-de-carga.py';
        exec('python3 -m py_compile '.escapeshellarg($script).' 2>&1', $saida, $codigo);
        $this->assertSame(0, $codigo, implode("\n", $saida));
        $fonte = (string) file_get_contents($script);
        $this->assertStringContainsString('valores.get("APP_ENV") == "production"', $fonte);
        $this->assertStringContainsString('getpass.getpass', $fonte, 'senha digitada, nunca em argumento');
        $this->assertStringNotContainsString('--senha', $fonte);
        // Durante a carga so ha GET: o unico POST e o do login (e do codigo do segundo fator).
        $this->assertSame(2, preg_match_all('/sessao\.pedir\([^)\n]*\{"/', $fonte));
    }

    public function test_preparar_base_nao_mexe_em_ssh_nem_firewall(): void
    {
        $fonte = file_get_contents(dirname(__DIR__, 3).'/scripts/operacao/vps/preparar-base.sh');
        foreach (['ufw ', 'sshd_config', 'systemctl reload ssh', 'authorized_keys'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fonte, $proibido);
        }
    }

    public function test_ajustar_acesso_valida_antes_de_recarregar_e_libera_o_ssh_antes_do_firewall(): void
    {
        $fonte = file_get_contents(dirname(__DIR__, 3).'/scripts/operacao/vps/ajustar-acesso.sh');

        $this->assertLessThan(strpos($fonte, 'executar systemctl reload ssh'), strpos($fonte, 'if ! sshd -t; then'));
        $this->assertStringContainsString('rm -f "$arquivo"', $fonte, 'apaga o arquivo novo se o sshd recusar');
        $this->assertLessThan(strpos($fonte, 'executar ufw --force enable'), strpos($fonte, 'executar ufw allow "${porta}/tcp"'));
        $this->assertLessThan(strpos($fonte, 'executar ufw --force enable'), strpos($fonte, 'ufw show added'));
        $this->assertStringContainsString('authorized_keys', $fonte);
        $this->assertStringContainsString('SEGUNDA sessao SSH', $fonte);
        $this->assertStringNotContainsString('sshd_config"', $fonte, 'nao edita o sshd_config original');
    }

    public function test_backup_confere_antes_de_dar_como_pronto_e_so_apaga_backups_proprios(): void
    {
        $script = dirname(__DIR__, 3).'/scripts/operacao/vps/backup.sh';
        $fonte = (string) file_get_contents($script);

        exec('bash '.escapeshellarg($script).' --confirmar=SIM 2>&1', $ignorado, $codigo);
        $this->assertSame(2, $codigo, 'confirmacao diferente da exata e recusada');
        exec('bash '.escapeshellarg($script).' --manter=2 2>&1', $ignorado, $codigo);
        $this->assertSame(1, $codigo, 'retencao menor que 3 e recusada');

        $this->assertStringContainsString('--confirmar=FAZER-BACKUP) modo=backup', $fonte);
        $this->assertStringContainsString('printf \'SIMULACAO: nada sera alterado.\n\'', $fonte);
        // Cada copia e conferida antes de o backup ser marcado como pronto.
        $this->assertLessThan(strpos($fonte, 'ULTIMO-BACKUP-OK'), strpos($fonte, 'pg_restore --list'));
        $this->assertLessThan(strpos($fonte, 'ULTIMO-BACKUP-OK'), strpos($fonte, 'sha256sum --quiet -c SHA256SUMS'));
        $this->assertStringContainsString('.incompleto', $fonte);
        // A retencao so apaga pastas criadas por este script.
        $this->assertSame(1, substr_count($fonte, 'rm -rf'));
        $this->assertLessThan(strpos($fonte, 'rm -rf'), strpos($fonte, "grep -qx 'astuti-backup 1'"));
        $this->assertStringContainsString('(( n > manter )) || continue', $fonte);
        // Chave privada nunca na VPS; .env nunca vai no backup.
        $this->assertStringContainsString('AGE-SECRET-KEY', $fonte);
        foreach (['"$raiz/.env"', 'DROP ', 'dropdb', 'migrate', 'pg_dumpall', 'psql -c', 'ufw ', 'sshd'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fonte, $proibido);
        }
    }

    public function test_ensaio_de_restauracao_so_usa_bancos_novos_e_so_apaga_os_que_criou(): void
    {
        $script = dirname(__DIR__, 3).'/scripts/operacao/vps/ensaiar-restauracao.sh';
        $fonte = (string) file_get_contents($script);

        exec('bash '.escapeshellarg($script).' --confirmar=SIM 2>&1', $ignorado, $codigo);
        $this->assertSame(2, $codigo);

        $this->assertStringContainsString('--confirmar=ENSAIAR-RESTAURACAO) real=1', $fonte);
        $this->assertStringContainsString('alvo="astuti_ensaio_${sufixo}_${i}"', $fonte);
        $this->assertStringContainsString("CREATE DATABASE \${alvo} TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR'", $fonte);
        $this->assertStringContainsString('pg_restore --no-owner --no-acl --exit-on-error -d "$alvo"', $fonte);
        // Integridade antes de restaurar; apagar so bancos de ensaio desta execucao.
        $this->assertLessThan(strpos($fonte, 'CREATE DATABASE'), strpos($fonte, 'sha256sum --quiet -c SHA256SUMS'));
        $this->assertSame(1, substr_count($fonte, 'dropdb'));
        $this->assertLessThan(strpos($fonte, 'dropdb'), strpos($fonte, '[[ "$alvo" =~ ^astuti_ensaio_[0-9]+_[0-9]+$ ]] || continue'));
        $this->assertStringContainsString('for alvo in "${CRIADOS[@]}"', $fonte);
        foreach (['DROP ', 'migrate', '.env', 'pg_restore --create', 'pg_restore -C'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fonte, $proibido);
        }
    }
}
