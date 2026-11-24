@extends('layouts.municipal')

@section('title', 'Ata de resultados finais - '.$class->name)
@section('body-class', 'pagina-ata-final')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $statusNames = ['aprovado' => 'Aprovado', 'acompanhado' => 'Aprovado com acompanhamento', 'retido' => 'Retido', 'cursando' => 'Cursando'];
    $totals = ['aprovado' => 0, 'acompanhado' => 0, 'retido' => 0, 'cursando' => 0, 'pendente' => 0, 'transferido' => 0, 'abandono' => 0, 'outros' => 0];
    foreach ($data['records'] as $row) {
        if ($row['exitLabel']) {
            $bucket = in_array($row['enrollment']->status, ['transferred', 'left_network'], true) ? 'transferido' : ($row['enrollment']->status === 'abandoned' ? 'abandono' : 'outros');
        } else {
            $bucket = $row['confirmed']?->result ?? 'pendente';
        }
        $totals[$bucket]++;
    }
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/fechamento-ano.css') }}?v={{ filemtime(public_path('css/fechamento-ano.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.show', $params) }}">Turma {{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Ata de resultados finais</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Registro acadêmico</span><h1>Ata de resultados finais</h1>
            <p>Documento interno de acompanhamento e assinatura, sem dados públicos.</p></div>
        <div class="acoes-titulo">
            <button class="botao" type="button" data-action="print-report-card">Imprimir A4</button>
        </div>
    </div>

    <section class="folha ata-folha" aria-labelledby="titulo-ata">
        <div class="folha-cabecalho">
            <div><span class="contexto">{{ $municipality->legal_name }}</span>
                <h2 id="titulo-ata">Ata de resultados finais - {{ $class->schoolYear->year }}</h2>
                <p><strong>Escola:</strong> {{ $school->name }}.
                    <strong>Turma:</strong> {{ $class->name }}.
                    <strong>Etapa:</strong> {{ $class->stage }}.</p>
            </div>
        </div>
        <div class="folha-corpo">
            @if (! $data['closure']->closed_at)
                <p class="ata-alerta"><strong>PRÉVIA NÃO OFICIAL.</strong> Turma ainda aberta. Este documento não equivale à ata final assinada.</p>
            @else
                <p class="texto-ajuda">Turma fechada em {{ $data['closure']->closed_at->format('d/m/Y H:i') }}.</p>
            @endif
            <p class="texto-ajuda">Legenda de componentes: @foreach ($data['components'] as $component){{ $loop->first ? '' : '; ' }}{{ $loop->iteration }} - {{ $component->name }}@endforeach.
                Médias calculadas somente sobre notas lançadas. Dados ausentes aparecem como traço.</p>
        </div>
        <div class="rolagem ata-rolagem">
            <table class="tabela ata-tabela">
                <thead><tr>
                    <th scope="col">Nº</th>
                    <th scope="col">Aluno</th>
                    @foreach ($data['components'] as $component)
                        <th scope="col" title="{{ $component->name }}">C{{ $loop->iteration }}</th>
                    @endforeach
                    <th scope="col">Frequência</th>
                    <th scope="col">Resultado</th>
                </tr></thead>
                <tbody>
                    @forelse ($data['records'] as $row)
                        <tr>
                            <td class="numero">{{ $loop->iteration }}</td>
                            <th scope="row">{{ $row['enrollment']->student->full_name }}</th>
                            @foreach ($data['components'] as $component)
                                <td class="numero">{{ ($row['averages'][$component->id] ?? null) === null ? '-' : number_format($row['averages'][$component->id], 1, ',', '.') }}</td>
                            @endforeach
                            <td class="numero">{{ $row['frequency'] === null ? '-' : number_format($row['frequency'], 1, ',', '.').'%' }}</td>
                            <td class="ata-resultado">
                                {{ $row['exitLabel'] ?? ($row['confirmed'] ? $statusNames[$row['confirmed']->result] : 'Pendente') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $data['components']->count() + 4 }}">Nenhuma matrícula nesta turma.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="folha-corpo">
            <p class="ata-totais">
                <strong>Totais:</strong>
                aprovados {{ $totals['aprovado'] }};
                aprovados com acompanhamento {{ $totals['acompanhado'] }};
                retidos {{ $totals['retido'] }};
                cursando {{ $totals['cursando'] }};
                pendentes {{ $totals['pendente'] }};
                transferidos {{ $totals['transferido'] }};
                abandono {{ $totals['abandono'] }};
                outras situações {{ $totals['outros'] }}.
            </p>
            <div class="ata-assinaturas">
                <div><div class="ata-linha"></div><strong>Direção escolar</strong></div>
                <div><div class="ata-linha"></div><strong>Secretaria escolar</strong></div>
            </div>
            <p class="texto-ajuda">Documento interno emitido em {{ today()->format('d/m/Y') }}. A responsabilidade pela decisão e pelas assinaturas cabe à escola.</p>
        </div>
    </section>

    <script defer src="{{ asset('js/boletim-imprimir.js') }}"></script>
@endsection
