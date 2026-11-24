<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\StaffManagerCensus;
use App\Models\Tenant\StaffMember;

final class Registro40
{
    /** @var array<int, string|null> */
    public array $valores;

    /** @var list<Pendencia> */
    public array $pendencias = [];

    public static function para(School $escola, StaffMember $servidor, ?StaffManagerCensus $vinculo, ?SchoolCensus $censo, ?int $ano = null): self
    {
        $registro = new self;
        $leiaute = Leiaute::do($ano ?? Tabelas::anoMaisRecente());
        $registro->valores = [
            1 => '40', 2 => $escola->inep_code, 3 => CodigoCenso::dePessoa($servidor->id), 4 => null,
            5 => $vinculo?->position === null ? null : (string) $vinculo->position,
            6 => $vinculo?->access_criterion === null ? null : (string) $vinculo->access_criterion,
            7 => $vinculo?->employment_status === null ? null : (string) $vinculo->employment_status,
        ];
        foreach ($leiaute->campos('40') as $numero => $campo) {
            foreach (ValidadorDeCampo::conferir($campo, $registro->valores[$numero]) as $problema) {
                $registro->recusa($numero, "{$campo->nome}: {$problema}.");
            }
        }
        if ($vinculo === null) {
            $registro->recusa(5, 'Confirme o cargo de gestão no Censo.');
        }
        // Campo 6, regras 1 a 3; campo 7, regra 1.
        $diretor = $vinculo?->position === 1;
        $atividade = $censo?->operating_status;
        $dependencia = $censo?->administrative_dependency;
        $publica = in_array($dependencia, [1, 2, 3], true);
        if ($diretor && ($atividade === null || $dependencia === null)) {
            $registro->aviso(6, 'Confirme a situação e a dependência administrativa no registro 00.');
        }
        if (! $diretor || in_array($atividade, [2, 3], true)) {
            if ($vinculo?->access_criterion !== null) {
                $registro->recusa(6, 'Critério de acesso só se aplica ao diretor de escola em atividade.');
            }
            if ($vinculo?->employment_status !== null) {
                $registro->recusa(7, 'Situação funcional só se aplica ao diretor de escola pública em atividade.');
            }
        } elseif ($atividade === 1 && $vinculo?->access_criterion === null) {
            $registro->recusa(6, 'Informe o critério de acesso do diretor.');
        }
        if (($publica && $vinculo?->access_criterion === 1) || ($dependencia === 4 && in_array($vinculo?->access_criterion, [4, 5, 6], true))) {
            $registro->recusa(6, 'Critério incompatível com a dependência administrativa.');
        }
        if ($diretor && $atividade === 1 && $publica && $vinculo?->employment_status === null) {
            $registro->recusa(7, 'Informe a situação funcional do diretor de escola pública.');
        }
        if ($dependencia === 4 && $vinculo?->employment_status !== null) {
            $registro->recusa(7, 'Situação funcional deve ficar vazia na escola privada.');
        }
        // Campos 3 e 4 e regra geral 21 (correspondência com o 30, gestor único e limite por escola)
        // valem para o arquivo inteiro: conferidos na geração, não por servidor.

        return $registro;
    }

    public function pronto(): bool
    {
        return ! collect($this->pendencias)->contains(fn (Pendencia $p): bool => $p->recusa());
    }

    private function recusa(int $campo, string $mensagem): void
    {
        // Uma recusa por campo: a primeira explica onde corrigir.
        foreach ($this->pendencias as $pendencia) {
            if ($pendencia->campo === $campo && $pendencia->recusa()) {
                return;
            }
        }
        $this->pendencias[] = new Pendencia('40', $campo, $mensagem);
    }

    private function aviso(int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('40', $campo, $mensagem, Pendencia::AVISO);
    }
}
