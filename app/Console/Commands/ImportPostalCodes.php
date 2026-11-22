<?php

namespace App\Console\Commands;

use App\Address\PostalCodeBook;
use App\Audit\AuditTrail;
use App\Models\Platform\Municipality;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Carrega a base local de CEPs de uma Prefeitura a partir de um CSV.
 * Exemplo: php artisan astuti:ceps-importar monte-sereno base_ceps.csv --fonte="Cadastro imobiliario 2026"
 */
class ImportPostalCodes extends Command
{
    protected $signature = 'astuti:ceps-importar {municipio : slug da Prefeitura} {arquivo? : CSV cep;logradouro;bairro;cidade;uf[;zona]}
        {--fonte= : origem dos dados, registrada em cada CEP}
        {--ficticio : carrega a base ficticia do kit Monte Sereno (somente demonstracao)}';

    protected $description = 'Carrega ou atualiza a base local de CEPs usada para preencher enderecos.';

    public function handle(PostalCodeBook $book, TenantContext $context, AuditTrail $audit): int
    {
        $municipality = Municipality::query()->where('slug', $this->argument('municipio'))->first();
        if ($municipality === null) {
            $this->error('Prefeitura nao encontrada.');

            return self::FAILURE;
        }

        $fictitious = (bool) $this->option('ficticio');
        $path = $fictitious ? database_path(PostalCodeBook::FICTITIOUS_FILE) : $this->argument('arquivo');
        $source = $fictitious ? 'Kit Monte Sereno (ficticio)' : trim((string) $this->option('fonte'));
        if (! is_string($path) || $path === '' || $source === '') {
            $this->error('Informe o arquivo e a fonte (--fonte), ou use --ficticio.');

            return self::FAILURE;
        }

        try {
            $result = $context->runFor($municipality, fn (): array => $book->import($path, $source));
            // A base de CEPs não é dado escolar: o registro fica na auditoria central.
            $audit->record('platform', 'postal_codes.imported', 'postal_code_base', $municipality->id, null, [
                'source' => $source,
                'count' => $result['gravados'],
            ]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['gravados'].' CEP(s) carregados de '.$result['lidos'].' linha(s). Fonte: '.$source.'.');

        return self::SUCCESS;
    }
}
