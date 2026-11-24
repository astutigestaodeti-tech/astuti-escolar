<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolYear;

/** Identificação da escola no leiaute de importação do Educacenso. */
final class Registro00
{
    /** @var array<int, string> campo do leiaute => atributo editável */
    public const CAMPOS = [
        3 => 'operating_status', 9 => 'district_code', 14 => 'phone_ddd',
        15 => 'phone', 16 => 'phone_other', 17 => 'email', 18 => 'regional_body_code',
        19 => 'location_zone', 20 => 'differentiated_location', 21 => 'administrative_dependency',
        49 => 'regulation', 50 => 'regulation_sphere', 51 => 'linked_unit',
        52 => 'host_school_code', 53 => 'ies_code',
    ];

    /** @var array<int, string|null> */
    public array $valores = [];

    /** @var list<Pendencia> */
    public array $pendencias = [];

    private function __construct(
        private readonly Leiaute $leiaute,
        private readonly Tabelas $tabelas,
        private readonly School $escola,
        private readonly ?SchoolCensus $censo,
        private readonly ?SchoolYear $anoLetivo,
        private readonly int $ano,
    ) {
        $this->montar();
    }

    public static function para(School $escola, ?SchoolCensus $censo, ?SchoolYear $anoLetivo, int $ano): self
    {
        return new self(Leiaute::do($ano), Tabelas::do($ano), $escola, $censo, $anoLetivo, $ano);
    }

    public function pronto(): bool
    {
        foreach ($this->pendencias as $pendencia) {
            if ($pendencia->recusa()) {
                return false;
            }
        }

        return true;
    }

    private function montar(): void
    {
        $c = $this->censo;
        $municipio = $this->tabelas->municipioPorNome($this->escola->address_city, $this->escola->address_state);
        $valores = array_fill(1, 53, null);
        $valores[1] = '00';
        $valores[2] = $this->escola->inep_code;
        if ($c?->operating_status === 1 && $this->anoLetivo?->year === $this->ano) {
            $valores[4] = $this->anoLetivo->start_date?->format('d/m/Y');
            $valores[5] = $this->anoLetivo->end_date?->format('d/m/Y');
        }
        $valores[6] = Texto::normalizar($this->escola->name);
        $valores[7] = $this->escola->postal_code === null ? null : str_replace('-', '', $this->escola->postal_code);
        $valores[8] = $municipio === null ? null : (string) $municipio;
        $valores[10] = Texto::normalizar($this->escola->address_street);
        $valores[11] = Texto::normalizar($this->escola->address_number);
        $valores[12] = Texto::normalizar($this->escola->address_complement);
        $valores[13] = Texto::normalizar($this->escola->address_district);
        foreach (self::CAMPOS as $numero => $atributo) {
            $valor = $c?->{$atributo};
            $valores[$numero] = $valor === null ? null : (string) $valor;
        }
        $valores[17] = Texto::normalizar($valores[17]);
        $this->valores = $valores;

        if ($c === null) {
            $this->pendencias[] = new Pendencia('00', null, 'Os dados da escola para o Censo ainda não foram preenchidos.');
        }

        foreach ($this->leiaute->campos('00') as $numero => $campo) {
            // Sem a tela "Escola no Censo" preenchida, uma pendência só cobre os campos dela.
            if ($c === null && array_key_exists($numero, self::CAMPOS)) {
                continue;
            }
            foreach (ValidadorDeCampo::conferir($campo, $valores[$numero]) as $problema) {
                $this->recusa($numero, "{$campo->nome}: {$problema}.");
            }
        }
        if ($municipio === null) {
            $this->recusa(8, 'O município e a UF do endereço da escola não foram encontrados na tabela do Inep.');
        } elseif ($valores[2] !== null && substr($valores[2], 0, 2) !== substr((string) $municipio, 0, 2)) {
            // Campo 2, regra 2.
            $this->recusa(2, 'O código Inep da escola precisa corresponder à UF do município.');
        }
        // Campo 6, regra 1; campo 11, regra 1 (mais restritiva que o tamanho da coluna).
        if ($valores[6] !== null && mb_strlen($valores[6]) < 4) {
            $this->recusa(6, 'O nome da escola precisa ter pelo menos 4 caracteres.');
        }
        if ($valores[11] !== null && mb_strlen($valores[11]) > 10) {
            $this->recusa(11, 'O número do endereço deve ter até 10 caracteres.');
        }
        if ($c !== null) {
            $this->conferirRegras($c, $municipio);
        }

        // A existência na base externa e a permissão do informante só o Inep confere.
        if ($valores[2] !== null) {
            $this->aviso(2, 'O Inep confirma a existência, a situação e o acesso à escola no processamento.');
        }
        if ($valores[7] !== null) {
            $this->aviso(7, 'O Inep confirma o CEP na base DNE para o município.');
        }
    }

    private function conferirRegras(SchoolCensus $c, ?int $municipio): void
    {
        // Campos 4 e 5, regra 1: limites expressos no leiaute de 2026.
        if ($c->operating_status === 1) {
            $inicio = $this->anoLetivo?->start_date?->toDateString();
            $fim = $this->anoLetivo?->end_date?->toDateString();
            if ($this->anoLetivo?->year !== $this->ano || $inicio === null || $inicio < '2025-05-29' || $inicio > '2026-05-27') {
                $this->recusa(4, 'Informe no cadastro do ano letivo de 2026 a data inicial entre 29/05/2025 e 27/05/2026.');
            }
            if ($fim === null || $inicio === null || $fim <= $inicio || $fim < '2026-05-28' || $fim >= '2027-05-26') {
                $this->recusa(5, 'A data final deve ser posterior à inicial, a partir de 28/05/2026 e anterior a 26/05/2027.');
            }
        }
        // Campo 9, regra 2; campo 18, regra 2: tabelas oficiais disponíveis localmente.
        if ($municipio !== null && $c->district_code !== null && ! array_key_exists($c->district_code, $this->tabelas->distritos($municipio))) {
            $this->recusa(9, 'Escolha um distrito do município da escola na tabela do Inep.');
        }
        if ($c->regional_body_code !== null && ! array_key_exists($c->regional_body_code, $this->tabelas->orgaosRegionais((string) $this->escola->address_state))) {
            $this->recusa(18, 'Escolha um órgão regional da UF da escola na tabela do Inep.');
        }
        // Campo 14, regras 1 e 2; campos 15 e 16, regras 1 e 2.
        $temTelefone = $c->phone !== null || $c->phone_other !== null;
        if (($c->phone_ddd !== null) !== $temTelefone) {
            $this->recusa(14, 'Informe o DDD junto de pelo menos um telefone, ou deixe os três campos vazios.');
        }
        if ($c->phone_ddd !== null) {
            $this->aviso(14, 'O Inep confirma se o DDD corresponde ao município.');
        }
        foreach ([15 => $c->phone, 16 => $c->phone_other] as $campo => $telefone) {
            if ($telefone !== null && (preg_match('/^(?:[0-9]{8}|9[0-9]{8})$/D', $telefone) !== 1 || preg_match('/^([0-9])\1+$/D', $telefone) === 1)) {
                $this->recusa($campo, 'Informe 8 dígitos, ou 9 começando por 9, sem repetir um único dígito.');
            }
        }
        if ($c->phone !== null && $c->phone === $c->phone_other) {
            $this->recusa(16, 'O outro telefone deve ser diferente do telefone principal.');
        }
        // Campo 20, regra 2; campo 21, regra 2.
        if ($c->location_zone === 1 && $c->differentiated_location === 1) {
            $this->recusa(20, 'Área de assentamento não pode ser informada com zona urbana.');
        }
        if ($municipio === 5300108 && $c->administrative_dependency === 3) {
            $this->recusa(21, 'O município de Brasília não admite dependência municipal no leiaute.');
        }
        if ($c->administrative_dependency === 4) {
            $this->recusa(21, 'Escola privada: fora do escopo desta versão.');
        }
        // A issue #166 reserva os campos 22 a 48. A V5 exige 22 a 25 para
        // escolas públicas, e 33 e 34 para escolas em atividade. Não inventar
        // respostas nem declarar pronta uma escola com esses campos nulos.
        if (in_array($c->administrative_dependency, [1, 2, 3], true)) {
            $this->recusa(22, 'Os campos 22 a 25 (órgãos públicos vinculados) são exigidos pelo leiaute e ainda não estão no escopo do cadastro.');
        }
        if ($c->operating_status === 1) {
            $this->recusa(33, 'Os campos 33 e 34 (parcerias ou convênios) são exigidos pelo leiaute e ainda não estão no escopo do cadastro.');
        }
        // Campo 49, regra 1; campo 51, regra 1.
        foreach ([49 => $c->regulation, 51 => $c->linked_unit] as $campo => $valor) {
            if ($c->operating_status === 1 && $valor === null) {
                $this->recusa($campo, 'Informe este campo para escola em atividade.');
            } elseif ($c->operating_status !== 1 && $valor !== null) {
                $this->recusa($campo, 'Este campo só se aplica a escola em atividade.');
            }
        }
        // Campo 50, regras 1 a 5: combinações por dependência e município.
        $esferas = match ($c->administrative_dependency) {
            1 => [1, 6],
            2 => [2],
            3 => [2, 3, 4],
            4 => $municipio === 5300108 ? [2] : [2, 3, 4],
            default => [],
        };
        if ($c->regulation_sphere !== null && ! in_array($c->regulation_sphere, $esferas, true)) {
            $this->recusa(50, 'A esfera de regulamentação não corresponde à dependência administrativa da escola.');
        } elseif ($esferas !== [] && $c->regulation_sphere === null) {
            $this->recusa(50, 'Informe a esfera de regulamentação correspondente à dependência administrativa.');
        }
        // Campo 52, regras 1 a 4; campo 53, regras 1 a 3.
        foreach ([52 => [1, $c->host_school_code], 53 => [2, $c->ies_code]] as $campo => [$vinculo, $codigo]) {
            if (($c->linked_unit === $vinculo) !== ($codigo !== null)) {
                $this->recusa($campo, $vinculo === 1 ? 'Informe a escola sede somente quando a unidade estiver vinculada a uma escola de educação básica.' : 'Informe o código da IES somente quando a unidade ofertar educação superior.');
            }
        }
        if ($c->host_school_code !== null) {
            if ($c->host_school_code === $this->escola->inep_code) {
                $this->recusa(52, 'A escola sede precisa ser diferente da própria escola.');
            } else {
                $this->aviso(52, 'Confirme se a escola sede está ativa e pertence à mesma dependência administrativa e UF; o Inep também verifica o vínculo.');
            }
        }
        if ($c->ies_code !== null) {
            $this->aviso(53, 'O Inep confirma o código, a situação e a esfera da IES.');
        }
    }

    private function recusa(int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('00', $campo, $mensagem);
    }

    private function aviso(int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('00', $campo, $mensagem, Pendencia::AVISO);
    }
}
