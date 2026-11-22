@extends('layouts.municipal')

@section('title', 'Conferir importação - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Conferir importação</span>
@endsection

@section('content')
    @php
        // Confirmações que a pessoa pode marcar nesta tela; qualquer outra exige corrigir a planilha.
        $checkable = [
            'duplicidade' => ['name' => 'confirmados[]', 'label' => 'Confirmo que são registros da mesma pessoa.'],
            'homonimo' => ['name' => 'homonimos[]', 'label' => 'É outra pessoa. Autorizo cadastrar um novo aluno.'],
            'leitura' => ['name' => 'leituras[]', 'label' => 'Conferi nome, data e RA com o documento.'],
        ];
        // Dados que esta etapa não grava (o transporte tem importação própria).
        $laterLabels = [
            'cep' => 'CEP',
            'endereco' => 'informações de endereço ausentes ou incompletas',
            'transporte' => 'uso de transporte escolar',
            'observacao' => 'observações da escola',
            'censo' => 'dados do Censo fora das opções (sexo, cor/raça, nacionalidade ou naturalidade)',
            'cpf' => 'CPF do aluno inválido ou repetido',
        ];
        $censusLabels = [
            'sex' => \App\Http\Controllers\StudentRecordController::SEX_LABELS,
            'race_color' => \App\Http\Controllers\StudentRecordController::RACE_LABELS,
            'nationality' => \App\Http\Controllers\StudentRecordController::NATIONALITY_LABELS,
        ];

        $counts = ['pronto' => 0, 'confirmar' => 0, 'corrigir' => 0, 'ignorado' => 0];
        $later = [];
        $view = [];
        foreach ($rows as $index => $row) {
            $fields = array_column($row['confirmation'], 'field');
            $pendingFields = array_column($row['pending'], 'field');
            foreach (array_unique($pendingFields) as $field) {
                if (isset($laterLabels[$field])) {
                    $later[$field] = ($later[$field] ?? 0) + 1;
                }
            }

            if (in_array('ja_cadastrado', $fields, true)) {
                $status = 'ignorado';
            } elseif (array_diff($fields, array_keys($checkable)) !== []) {
                $status = 'corrigir';
            } elseif ($fields !== []) {
                $status = 'confirmar';
            } else {
                $status = 'pronto';
            }
            $counts[$status]++;

            $address = $row['address'] ?? [];
            $addressParts = array_values(array_filter([
                $address['address_street'] ?? null,
                $address['address_number'] ?? null,
                $address['address_district'] ?? null,
                $address['postal_code'] ?? null,
                ($address['residence_zone'] ?? null) === 'urban' ? 'Zona urbana' : (($address['residence_zone'] ?? null) === 'rural' ? 'Zona rural' : null),
            ], fn ($value): bool => $value !== null && $value !== ''));
            // O CPF não aparece na tela: basta saber que veio e foi conferido.
            $census = $row['census'] ?? [];
            $censusParts = array_values(array_filter([
                isset($census['social_name']) ? 'Nome social: '.$census['social_name'] : null,
                $censusLabels['sex'][$census['sex'] ?? ''] ?? null,
                isset($census['race_color']) ? 'Cor/raça '.mb_strtolower($censusLabels['race_color'][$census['race_color']] ?? '') : null,
                $censusLabels['nationality'][$census['nationality'] ?? ''] ?? null,
                isset($census['birthplace_city']) ? 'Natural de '.$census['birthplace_city'].(isset($census['birthplace_state']) ? '/'.$census['birthplace_state'] : '') : null,
                isset($census['cpf']) ? 'CPF válido' : null,
            ]));
            $view[$index] = ['status' => $status, 'row' => $row, 'address_label' => implode(' · ', $addressParts), 'census_label' => implode(' · ', $censusParts)];
        }
        $toSave = count($rows) - $counts['ignorado'];
        $statusText = [
            'pronto' => 'Pronto para gravar',
            'confirmar' => 'Confirme abaixo',
            'corrigir' => 'Corrija na planilha',
            'ignorado' => 'Já cadastrado',
        ];
    @endphp

    <div class="folha-titulo">
        <div>
            <span class="contexto">{{ $school->name }} - Ano letivo {{ $year->year }}</span>
            <h1>Conferir antes de importar</h1>
            <p>{{ count($rows) }} {{ count($rows) === 1 ? 'aluno encontrado' : 'alunos encontrados' }} no arquivo. Nada foi gravado ainda.</p>
        </div>
    </div>

    <dl class="conf-resumo" aria-label="Resumo da conferência">
        <div class="conf-resumo-item pronto"><dt>Prontos para gravar</dt><dd>{{ $counts['pronto'] }}</dd></div>
        <div class="conf-resumo-item confirmar"><dt>Precisam da sua confirmação</dt><dd>{{ $counts['confirmar'] }}</dd></div>
        @if ($counts['corrigir'] > 0)
            <div class="conf-resumo-item corrigir"><dt>Corrigir na planilha</dt><dd>{{ $counts['corrigir'] }}</dd></div>
        @endif
        <div class="conf-resumo-item ignorado"><dt>Já cadastrados (serão ignorados)</dt><dd>{{ $counts['ignorado'] }}</dd></div>
    </dl>

    @if ($later !== [])
        <div class="nota conf-depois"><x-icon name="info" /><div>
            <strong>Fica para completar depois</strong>
            <p>A importação aproveita também os campos de endereço e do Censo informados na planilha. Os dados abaixo estão ausentes ou incompletos e não impedem a gravação:
                @foreach ($later as $field => $total)
                    {{ $laterLabels[$field] }} ({{ $total }} {{ $total === 1 ? 'aluno' : 'alunos' }}){{ $loop->last ? '.' : ';' }}
                @endforeach
            </p>
        </div></div>
    @endif

    <form method="post" action="{{ route('school.imports.students.confirm', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
        @csrf
        <input type="hidden" name="lote" value="{{ $token }}">
        <section class="folha" aria-labelledby="linhas-importacao">
            <div class="folha-cabecalho"><div><h2 id="linhas-importacao">Alunos do arquivo</h2><p>Na mesma ordem da planilha ou do documento. Alunos repetidos aparecem uma vez só.</p></div></div>
            <div class="rolagem">
                <table class="tabela conf-tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">Nascimento</th><th scope="col">RA</th><th scope="col">Turma</th><th scope="col">Situação</th></tr></thead>
                    <tbody>
                        @foreach ($view as $index => $item)
                            @php($row = $item['row'])
                            <tr class="conf-linha {{ $item['status'] }}">
                                <td>
                                    <strong>{{ $row['name'] }}</strong>
                                    <span class="conf-origem">{{ implode('; ', $row['sources']) }}</span>
                                    @if ($item['address_label'] !== '')
                                        <span class="conf-origem">Endereço do arquivo: {{ $item['address_label'] }}</span>
                                    @endif
                                    @if ($item['census_label'] !== '')
                                        <span class="conf-origem">Censo: {{ $item['census_label'] }}</span>
                                    @endif
                                </td>
                                <td class="conf-numero">
                                    @if ($row['birth_date'])
                                        {{ \Carbon\Carbon::parse($row['birth_date'])->format('d/m/Y') }}
                                    @else
                                        <span class="conf-falta">Sem data</span>
                                    @endif
                                </td>
                                <td class="conf-numero">
                                    @if ($row['ra'])
                                        {{ $row['ra'] }}
                                    @else
                                        <span class="conf-falta">Sem RA</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['class_name'])
                                        {{ $row['class_name'] }}
                                    @else
                                        <span class="conf-falta">Sem turma</span>
                                    @endif
                                </td>
                                <td class="conf-situacao">
                                    <span class="conf-selo {{ $item['status'] }}">{{ $statusText[$item['status']] }}</span>
                                    @if ($item['status'] === 'ignorado')
                                        <p class="conf-explica">O aluno e o RA existentes não serão recriados.</p>
                                        @if ($row['address_target_id'] ?? null)
                                            <label class="conf-marca">
                                                <input type="checkbox" name="completar_enderecos[]" value="{{ $index }}">
                                                <span>Completar somente os campos vazios deste aluno (endereço e dados do Censo), sem substituir os já preenchidos.</span>
                                            </label>
                                        @endif
                                    @else
                                        @foreach ($row['confirmation'] as $issue)
                                            @if (isset($checkable[$issue['field']]))
                                                <div class="conf-pedido">
                                                    <p>
                                                        @if ($issue['field'] === 'duplicidade')
                                                            Aparece {{ count($row['sources']) }} vezes no arquivo.
                                                        @elseif ($issue['field'] === 'leitura' && preg_match('/\((\d+)%\)/', $issue['message'], $leitura) === 1)
                                                            Leitura automática incerta ({{ $leitura[1] }}% de confiança).
                                                        @else
                                                            {{ $issue['message'] }}
                                                        @endif
                                                    </p>
                                                    <label class="conf-marca">
                                                        <input type="checkbox" name="{{ $checkable[$issue['field']]['name'] }}" value="{{ $index }}">
                                                        <span>{{ $checkable[$issue['field']]['label'] }}</span>
                                                    </label>
                                                </div>
                                            @else
                                                <p class="conf-explica conf-erro">{{ $issue['message'] }}</p>
                                            @endif
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <div class="conf-acoes">
            <button class="botao" type="submit">Gravar {{ $toSave }} {{ $toSave === 1 ? 'aluno' : 'alunos' }}</button>
            <a class="botao secundario" href="{{ route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Cancelar</a>
        </div>
        @if ($counts['confirmar'] > 0)
            <p class="conf-lembrete">Antes de gravar, marque as {{ $counts['confirmar'] === 1 ? 'confirmação pedida' : $counts['confirmar'].' confirmações pedidas' }} na coluna "Situação".</p>
        @endif
    </form>

    <div class="nota conf-lgpd"><x-icon name="escudo" /><div><strong>Como a gravação funciona</strong><p>Alunos já cadastrados continuam ignorados para novas inclusões. Só com sua marcação os campos vazios de endereço e do Censo podem ser completados. CPF inválido, repetido ou já usado por outro aluno nunca é gravado. Tudo é gravado junto, ou nada, se houver falha. Nenhum RA é reaproveitado e nenhum dado é inventado. A conferência vale por uma hora e só pode ser confirmada por esta conta.</p></div></div>
@endsection
