<?php

namespace App\Educacenso;

/**
 * Regras da V5 que dependem do conjunto de registros de uma escola.
 * Não consulta o banco, não serializa e não consulta a base do Inep.
 */
final class RegrasDaEscola
{
    /** @return list<array{pendencia: Pendencia, origem: array, regra: string}> */
    public function conferir(array $registros): array
    {
        $tipos = [];
        foreach ($registros as $linha) {
            $tipos[$linha['registro']][] = $linha;
        }
        $saida = [];
        $adicionar = function (string $regra, array $linha, ?int $campo, string $mensagem) use (&$saida): void {
            $saida[] = ['regra' => $regra, 'origem' => $linha['origem'],
                'pendencia' => new Pendencia($linha['registro'], $campo, $mensagem)];
        };
        $escola = $tipos['00'][0] ?? ['registro' => '00', 'valores' => [], 'origem' => ['tipo' => 'escola']];
        $situacao = $escola['valores'][3] ?? null;
        $ativa = $situacao === '1';

        // Regras gerais 18 a 21 e 24.
        if (in_array($situacao, ['2', '3'], true)) {
            foreach (['10', '20', '50', '60'] as $tipo) {
                foreach ($tipos[$tipo] ?? [] as $linha) {
                    $adicionar('18', $linha, null, 'Registro não permitido para escola paralisada ou extinta.');
                }
            }
        }
        if ($ativa) {
            foreach (['00', '10', '20', '30', '40', '50', '60'] as $tipo) {
                if (empty($tipos[$tipo])) {
                    $adicionar('19', $escola, null, "Escola em atividade precisa de pelo menos um registro {$tipo}.");
                }
            }
        }
        if (count($tipos['00'] ?? []) !== 1 || count($tipos['10'] ?? []) > 1) {
            $adicionar('20', $escola, null, 'Deve haver um único registro 00 e no máximo um registro 10 por escola.');
        }
        if (count($tipos['40'] ?? []) > 3) {
            $adicionar('21', $escola, null, 'A escola tem mais de três vínculos de gestor.');
        }
        if (count($tipos['20'] ?? []) > 1500) {
            $adicionar('24', $escola, null, 'A escola ultrapassa o limite de 1.500 turmas.');
        }

        // Código próprio e CPF únicos no 30. A identidade local é o cadastro,
        // nunca se fundem dois cadastros distintos apenas por CPF ou código.
        $pessoas = [];
        $codigos = [];
        $cpfs = [];
        foreach ($tipos['30'] ?? [] as $linha) {
            $v = $linha['valores'];
            $chave = $this->pessoa($linha);
            $pessoas[$chave] = $linha;
            foreach ([3 => &$codigos, 5 => &$cpfs] as $campo => &$indice) {
                $valor = $v[$campo] ?? null;
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (isset($indice[$valor])) {
                    $adicionar($campo === 3 ? '30.3' : '30.CPF', $linha, $campo, $campo === 3
                        ? 'Código próprio de pessoa repetido nesta escola.' : 'CPF repetido em cadastros de pessoa desta escola.');
                }
                $indice[$valor] = $linha;
            }
            unset($indice);
        }

        $turmas = [];
        $assinaturas = [];
        foreach ($tipos['20'] ?? [] as $linha) {
            $v = $linha['valores'];
            $turmas[$this->turma($linha)] = $linha;
            // Regra 25: todos os campos da assinatura oficial, não apenas o nome.
            if (($v[3] ?? null) !== null) {
                $assinatura = json_encode(array_map(fn ($n) => $v[$n] ?? null, [5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 21, 22, 24]));
                if (isset($assinaturas[$assinatura])) {
                    $adicionar('25', $linha, null, 'Turmas com nome, mediação, horários, tipo, local, classe especial e etapa iguais.');
                }
                $assinaturas[$assinatura] = $linha;
            }
        }
        $porTurma = [];
        $porPessoa = [];
        $gestores = [];
        foreach ($tipos['40'] ?? [] as $linha) {
            $chave = $this->pessoa($linha);
            if (isset($gestores[$chave])) {
                $adicionar('40.3', $linha, 3, 'A pessoa tem mais de um vínculo de gestor nesta escola.');
            }
            $gestores[$chave] = true;
        }
        foreach (['50', '60'] as $tipo) {
            foreach ($tipos[$tipo] ?? [] as $linha) {
                $t = $this->turma($linha);
                $p = $this->pessoa($linha);
                // CPF é usado somente para detectar conflito entre cadastros;
                // não substitui o código próprio nem é exibido nas mensagens.
                $cpf = $pessoas[$p]['valores'][5] ?? null;
                $identidade = $cpf ? 'cpf:'.$cpf : $p;
                $porPessoa[$identidade][] = $linha;
                if (! isset($turmas[$t])) {
                    $adicionar($tipo.'.5', $linha, 5, 'Vínculo sem turma correspondente na montagem desta escola.');

                    continue;
                }
                $porTurma[$t][$tipo][] = $linha;
            }
        }

        foreach ($turmas as $id => $linha) {
            $alunos = $porTurma[$id]['60'] ?? [];
            $profissionais = $porTurma[$id]['50'] ?? [];
            if ($alunos === []) {
                $adicionar('26', $linha, null, 'A turma não tem vínculo de aluno no registro 60.');
            }
            if ($profissionais === []) {
                $adicionar('27', $linha, null, 'A turma não tem vínculo de profissional no registro 50.');
            }
            // Campo 7 do 50, regras 7 e 8.
            if ($profissionais !== [] && count(array_filter($profissionais, fn ($p) => in_array($p['valores'][7] ?? null, ['4', '6'], true))) === count($profissionais)) {
                $adicionar('50.7.7', $linha, null, 'A turma não pode ter somente intérpretes de Libras ou tutores EAD.');
            }
            foreach ($profissionais as $profissional) {
                if (($profissional['valores'][7] ?? null) !== '4') {
                    continue;
                }
                $atende = false;
                $incerto = false;
                foreach ([...$alunos, ...$profissionais] as $outro) {
                    if ($this->pessoa($outro) === $this->pessoa($profissional)) {
                        continue;
                    }
                    $p = $pessoas[$this->pessoa($outro)]['valores'] ?? [];
                    if (in_array('1', [$p[21] ?? null, $p[22] ?? null, $p[23] ?? null], true)) {
                        $atende = true;
                    }
                    if (! isset($p[17]) || ($p[17] === '1' && (! isset($p[21], $p[22], $p[23])))) {
                        $incerto = true;
                    }
                }
                if (! $atende && ! $incerto) {
                    $adicionar('50.7.8', $profissional, 7, 'A função 4 exige vínculo compatível na turma. Confira os cadastros do Censo.');
                }
            }
            // Registro 30, campo 17, regra 2: conferir cada vínculo real.
            if (($linha['valores'][14] ?? null) === '5' || ($linha['valores'][22] ?? null) === '1') {
                foreach ($alunos as $aluno) {
                    $p = $pessoas[$this->pessoa($aluno)] ?? null;
                    if ($p !== null && ($p['valores'][17] ?? null) === '0') {
                        $adicionar('30.17.2', $p, 17, 'Dados do campo 17 incompatíveis com o tipo de turma do vínculo.');
                    }
                }
            }
        }

        foreach ($porPessoa as $vinculos) {
            $vistos = [];
            $escolarizacao = $comum = $semEtapa = 0;
            foreach ($vinculos as $i => $linha) {
                $id = $this->turma($linha);
                if ($id !== '' && isset($vistos[$id])) {
                    $adicionar('30', $linha, 5, 'A pessoa tem mais de um vínculo na mesma turma.');
                }
                $vistos[$id] = true;
                $t = $turmas[$id]['valores'] ?? [];
                if ($linha['registro'] === '60') {
                    $etapa = $t[24] ?? null;
                    if ($etapa !== null && in_array($t[14] ?? null, ['6', '9'], true)) {
                        $escolarizacao++;
                        if (! in_array($etapa, ['39', '40', '64', '68'], true)) {
                            $comum++;
                        }
                    } elseif ($etapa === null && in_array($t[14] ?? null, ['4', '5'], true)) {
                        $semEtapa++;
                    }
                }
                foreach (array_slice($vinculos, 0, $i) as $outro) {
                    $outroId = $this->turma($outro);
                    if ($id === $outroId || ! isset($turmas[$id], $turmas[$outroId])) {
                        continue;
                    }
                    if ($this->coincidem($linha['registro'], $t, $outro['registro'], $turmas[$outroId]['valores'])) {
                        $adicionar('31', $linha, 5, 'Horários coincidentes entre vínculos da pessoa, conforme a tabela do Inep.');
                    }
                }
            }
            $aluno = collect($vinculos)->first(fn ($l) => $l['registro'] === '60');
            if ($aluno !== null) {
                foreach (['32' => [$escolarizacao, 4], '33' => [$comum, 2], '34' => [$semEtapa, 4]] as $regra => [$total, $max]) {
                    if ($total > $max) {
                        $adicionar((string) $regra, $aluno, 5, "Limite de vínculos por aluno ultrapassado (regra geral {$regra}: máximo {$max}).");
                    }
                }
            }
        }

        return $saida;
    }

    private function pessoa(array $linha): string
    {
        return $linha['origem']['pessoa'] ?? (string) ($linha['valores'][3] ?? '');
    }

    private function turma(array $linha): string
    {
        return $linha['origem']['turma'] ?? (string) ($linha['valores'][$linha['registro'] === '20' ? 3 : 5] ?? '');
    }

    /**
     * Tabela regras_horarios_coincidentes.csv, itens 3.1 a-d.
     * Aluno x aluno: interseção em qualquer dia, exceto curricular x AEE.
     * Aluno x profissional: todos os horários do profissional contidos nos do aluno.
     * Profissional x profissional: não se verifica.
     */
    private function coincidem(string $r1, array $a, string $r2, array $b): bool
    {
        if ($r1 === '50' && $r2 === '50') {
            return false;
        }
        if (($a[6] ?? null) !== '1' || ($b[6] ?? null) !== '1'
            || ! in_array($a[14] ?? null, ['4', '5', '6', '9'], true) || ! in_array($b[14] ?? null, ['4', '5', '6', '9'], true)) {
            return false;
        }
        if ($r1 === '60' && $r2 === '60') {
            if (($a[14] === '5' && in_array($b[14], ['6', '9'], true)) || ($b[14] === '5' && in_array($a[14], ['6', '9'], true))) {
                return false;
            }
            foreach (range(7, 13) as $dia) {
                $x = $this->horario($a[$dia] ?? null);
                $y = $this->horario($b[$dia] ?? null);
                if ($x && $y && $x[0] < $y[1] && $y[0] < $x[1]) {
                    return true;
                }
            }

            return false;
        }
        [$aluno, $profissional] = $r1 === '60' ? [$a, $b] : [$b, $a];
        $temHorario = false;
        foreach (range(7, 13) as $dia) {
            $p = $this->horario($profissional[$dia] ?? null);
            $s = $this->horario($aluno[$dia] ?? null);
            if ($p === null) {
                continue;
            }
            $temHorario = true;
            if ($s === null || $p[0] < $s[0] || $p[1] > $s[1]) {
                return false;
            }
        }

        return $temHorario;
    }

    private function horario(?string $valor): ?array
    {
        if ($valor === null || preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]-([01][0-9]|2[0-3]):[0-5][0-9]$/D', $valor) !== 1) {
            return null;
        }
        $partes = explode('-', $valor);

        return $partes[0] < $partes[1] ? $partes : null;
    }
}
