@extends('layouts.municipal')

@section('title', 'Boletim de '.$student->full_name)
@section('body-class', 'pagina-boletim')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/notas-boletim.css') }}?v={{ filemtime(public_path('css/notas-boletim.css')) }}">
@endpush

@php
    $studentParams = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
@endphp

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $studentParams) }}">Ficha do aluno</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Boletim</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Ensino Fundamental</span><h1>Boletim escolar</h1>
            <p>Consulta parcial da vida escolar, com notas por período e frequência registrada na chamada.</p></div>
        <div class="acoes-titulo"><button class="botao" type="button" data-action="print-report-card">Imprimir boletim A4</button></div>
    </div>

    <section class="folha boletim-folha" aria-labelledby="titulo-boletim">
        <div class="folha-cabecalho">
            <div><span class="contexto">Secretaria Municipal de Educação</span>
                <h2 id="titulo-boletim">Boletim parcial do ano letivo {{ $enrollment->schoolYear->year }}</h2>
                <p>{{ $municipality->legal_name }}. {{ $school->name }}.</p>
            </div>
        </div>
        <div class="folha-corpo">
            <dl class="boletim-identificacao">
                <div><dt>Aluno</dt><dd>{{ $student->full_name }}</dd></div>
                <div><dt>Turma</dt><dd>{{ $enrollment->schoolClass?->name ?? 'Não informada' }}</dd></div>
                <div><dt>Ano letivo</dt><dd>{{ $enrollment->schoolYear->year }}</dd></div>
                <div><dt>Média de referência</dt><dd>{{ number_format($data['minimum'], 1, ',', '.') }}</dd></div>
            </dl>
            <p class="texto-ajuda">A média por componente considera apenas os períodos com notas lançadas. Sem nota não significa zero. As informações não indicam aprovação ou reprovação final.</p>
        </div>

        <div class="rolagem boletim-rolagem">
            <table class="tabela boletim-tabela">
                <thead>
                    <tr><th scope="col">Componente curricular</th>
                        @foreach ($data['periods'] as $period)
                            <th scope="col">{{ $period->number }}º {{ $period->kind }}<small>{{ $period->closed_at ? 'Fechado' : 'Aberto' }}</small></th>
                        @endforeach
                        <th scope="col">Média parcial</th><th scope="col">Situação parcial</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['rows'] as $row)
                        <tr>
                            <th scope="row">{{ $row['component']->name }}</th>
                            @foreach ($data['periods'] as $period)
                                <td class="numero">{{ ($row['marks'][$period->id] ?? null) === null ? '-' : number_format($row['marks'][$period->id], 1, ',', '.') }}</td>
                            @endforeach
                            <td class="numero {{ $row['below'] ? 'boletim-abaixo' : '' }}">{{ $row['mean'] === null ? '-' : number_format($row['mean'], 1, ',', '.') }}</td>
                            <td class="{{ $row['below'] ? 'boletim-abaixo' : '' }}">{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="folha-corpo">
            <div class="boletim-resumo">
                <p><strong>Frequência anual registrada:</strong>
                    @if ($data['frequency'] === null)
                        Ainda não há chamadas registradas.
                    @else
                        {{ number_format($data['frequency'], 1, ',', '.') }}% ({{ $data['present'] }} presenças em {{ $data['sessions'] }} registros)
                    @endif
                </p>
                @if ($data['openPeriods']->isNotEmpty())
                    <p><strong>Períodos ainda abertos:</strong>
                        {{ $data['openPeriods']->map(fn ($p) => $p->number.'º '.$p->kind)->implode(', ') }}.
                    </p>
                @else
                    <p>Todos os períodos avaliativos foram fechados. A situação oficial da matrícula depende das etapas posteriores de avaliação.</p>
                @endif
            </div>
            <p class="boletim-rodape">Documento de acompanhamento escolar, emitido em {{ today()->format('d/m/Y') }}. Uso interno e da família.</p>
        </div>
    </section>

    <script src="{{ asset('js/boletim-imprimir.js') }}" defer></script>
@endsection
