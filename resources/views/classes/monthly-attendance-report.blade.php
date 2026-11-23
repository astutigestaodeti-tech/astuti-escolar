@extends('layouts.municipal')

@section('body-class', 'impressao-frequencia')
@section('title', 'Relatório mensal de frequência - '.$class->name)

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $monthLabel = ucfirst($month->locale('pt_BR')->translatedFormat('F')).' de '.$month->format('Y');
@endphp

@push('head')
    <script type="module" src="{{ asset('js/monthly-attendance-print.js') }}"></script>
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.show', $params) }}">Turma {{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Frequência mensal</span>
@endsection

@section('content')
    <div class="relatorio-frequencia">
        <div class="relatorio-mensal-acoes">
            <a class="botao secundario" href="{{ route('school.classes.show', $params) }}">Voltar à turma</a>
            <div class="relatorio-mensal-meses" aria-label="Navegar pelos meses do ano letivo">
                @if ($previousMonth)
                    <a class="botao secundario" href="{{ route('school.classes.attendance.monthly', [...$params, 'mes' => $previousMonth]) }}">Mês anterior</a>
                @endif
                <strong>{{ $monthLabel }}</strong>
                @if ($nextMonth)
                    <a class="botao secundario" href="{{ route('school.classes.attendance.monthly', [...$params, 'mes' => $nextMonth]) }}">Próximo mês</a>
                @endif
            </div>
            <button type="button" class="botao primario" data-action="print-monthly-attendance">Imprimir</button>
        </div>

        <header class="relatorio-mensal-cabecalho">
            <p class="relatorio-mensal-rede">{{ $municipality->legal_name }} - Secretaria Municipal de Educação</p>
            <h1>Relatório mensal de frequência</h1>
            <dl>
                <div><dt>Escola</dt><dd>{{ $school->name }}</dd></div>
                <div><dt>Turma</dt><dd>{{ $class->name }}</dd></div>
                <div><dt>Etapa</dt><dd>{{ $class->stage }}</dd></div>
                <div><dt>Ano letivo</dt><dd>{{ $class->schoolYear->year }}</dd></div>
                <div><dt>Mês</dt><dd>{{ $monthLabel }}</dd></div>
                <div><dt>Professor(a)</dt><dd>________________________________</dd></div>
            </dl>
        </header>

        <p class="relatorio-mensal-legenda">
            <strong>Legenda:</strong> • Presente, F Falta, J Falta justificada, vazio Sem marcação.
            O percentual considera presenças sobre todas as marcações, inclusive justificadas.
        </p>

        @if ($sessions->isEmpty())
            <div class="relatorio-mensal-vazio" role="status">Nenhuma chamada registrada neste mês.</div>
        @else
            <div class="relatorio-mensal-rolagem">
                <table class="relatorio-mensal-tabela">
                    <thead>
                        <tr>
                            <th scope="col" class="relatorio-mensal-nome">Aluno</th>
                            @foreach ($sessions as $session)
                                <th scope="col" class="relatorio-mensal-dia" title="{{ $session->attendance_date->format('d/m/Y') }}">{{ $session->attendance_date->format('d') }}</th>
                            @endforeach
                            <th scope="col" title="Presenças">P</th>
                            <th scope="col" title="Faltas">F</th>
                            <th scope="col" title="Faltas justificadas">J</th>
                            <th scope="col" class="relatorio-mensal-percentual">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <th scope="row" class="relatorio-mensal-nome">
                                    {{ $student['name'] }}
                                    @if ($student['endedAt'])
                                        <small>{{ $student['endedLabel'] }} em {{ $student['endedAt']->format('d/m/Y') }}</small>
                                    @endif
                                </th>
                                @foreach ($sessions as $session)
                                    <td class="relatorio-mensal-marcacao {{ $student['outside'][$session->id] ? 'relatorio-fora-vinculo' : '' }}"
                                        @if ($student['outside'][$session->id]) title="Fora do período de matrícula" @endif>{{ $student['marks'][$session->id] }}</td>
                                @endforeach
                                <td>{{ $student['totals']['present'] }}</td>
                                <td>{{ $student['totals']['absent'] }}</td>
                                <td>{{ $student['totals']['justified'] }}</td>
                                <td>{{ $student['percentage'] !== null ? $student['percentage'].'%' : 'Sem registro' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total de alunos por dia</th>
                            @foreach ($sessions as $session)
                                <td>{{ $dailyTotals[$session->id] }}</td>
                            @endforeach
                            <td colspan="4">Marcações registradas</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <div class="relatorio-mensal-assinaturas">
            <div><span>Assinatura do(a) professor(a)</span></div>
            <div><span>Assinatura da direção</span></div>
        </div>
    </div>
@endsection
