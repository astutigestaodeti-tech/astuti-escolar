@extends('layouts.municipal')

@section('title', 'Fechamento do ano - '.$class->name)
@section('body-class', 'pagina-fechamento-ano')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $statusNames = ['aprovado' => 'Aprovado', 'retido' => 'Retido', 'cursando' => 'Cursando'];
    $closure = $data['closure'];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/fechamento-ano.css') }}?v={{ filemtime(public_path('css/fechamento-ano.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.show', $params) }}">Turma {{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Fechamento do ano</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Ensino Fundamental</span>
            <h1>Fechamento do ano letivo</h1>
            <p>{{ $school->name }}. Turma {{ $class->name }}, {{ $class->schoolYear->year }}.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.classes.year-end.minutes', $params) }}">Visualizar ata de resultados finais</a>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="erro" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="folha" aria-labelledby="titulo-resultados">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-resultados">Resultados por matrícula</h2>
                <p>A sugestão não é a decisão. A direção ou a secretaria confirma cada aluno, individualmente ou em lote.</p>
            </div>
        </div>
        <div class="folha-corpo">
            <p><strong>Situação:</strong>
                @if ($closure->closed_at)
                    <span class="etiqueta ok">Ano fechado em {{ $closure->closed_at->format('d/m/Y H:i') }}</span>
                @else
                    <span class="etiqueta conferir">Ano ainda aberto</span>
                @endif
            </p>
            <p><strong>Períodos:</strong> {{ $data['periods']->whereNotNull('closed_at')->count() }} de {{ $data['periods']->count() }} fechados.
                @unless ($data['allPeriodsClosed'])
                    <span>Todos os períodos precisam ser fechados antes de encerrar a turma.</span>
                @endunless
            </p>
            <p class="texto-ajuda">Média de referência: {{ number_format($data['minimum'], 1, ',', '.') }}. Frequência mínima: {{ number_format($data['attendanceMinimum'], 1, ',', '.') }}%. Dados insuficientes geram sugestão "Cursando", não aprovação.</p>
            @if ($canConfirm && ! $closure->closed_at)
                <form method="POST" action="{{ route('school.classes.year-end.confirm', $params) }}" class="fechamento-acoes">
                    @csrf
                    <input type="hidden" name="all" value="1">
                    <button class="botao" type="submit">Confirmar todas as sugestões pendentes</button>
                    <span class="texto-ajuda">Confirma apenas os alunos pendentes, sem sobrescrever decisões já tomadas.</span>
                </form>
            @elseif (! $canConfirm && ! $closure->closed_at)
                <p class="texto-ajuda">Sua conta pode consultar os resultados, mas somente direção ou secretaria escolar da unidade pode confirmá-los.</p>
            @endif
        </div>
        <div class="rolagem fechamento-rolagem">
            <table class="tabela fechamento-tabela">
                <thead><tr>
                    <th scope="col">Aluno</th>
                    <th scope="col">Matrícula</th>
                    <th scope="col">Médias por componente</th>
                    <th scope="col">Frequência</th>
                    <th scope="col">Sugestão e justificativa</th>
                    <th scope="col">Resultado confirmado</th>
                    @if ($canConfirm && ! $closure->closed_at)<th scope="col">Confirmar ou alterar</th>@endif
                </tr></thead>
                <tbody>
                    @foreach ($data['records'] as $row)
                        @php
                            $enrollment = $row['enrollment'];
                            $confirmed = $row['confirmed'];
                        @endphp
                        <tr>
                            <th scope="row">{{ $enrollment->student->full_name }}</th>
                            <td class="numero">{{ $class->schoolYear->year }}</td>
                            <td>
                                <ul class="fechamento-medias">
                                    @foreach ($data['components'] as $component)
                                        <li><span>{{ $component->name }}</span>
                                            <strong class="numero">{{ ($row['averages'][$component->id] ?? null) === null ? '-' : number_format($row['averages'][$component->id], 1, ',', '.') }}</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="numero">{{ $row['frequency'] === null ? '-' : number_format($row['frequency'], 1, ',', '.').'%' }}</td>
                            <td>
                                @if ($row['exitLabel'])
                                    <strong>{{ $row['exitLabel'] }}</strong>
                                    <small>Preservada a situação da matrícula, sem cálculo de resultado final.</small>
                                @else
                                    <strong>{{ $statusNames[$row['suggestion']] }}</strong>
                                    <small>{{ $row['reason'] }}</small>
                                @endif
                            </td>
                            <td class="fechamento-situacao">
                                @if ($row['exitLabel'])
                                    {{ $row['exitLabel'] }}
                                @elseif ($confirmed)
                                    <strong>{{ $statusNames[$confirmed->result] }}</strong>
                                    <small>Confirmado em {{ $confirmed->confirmed_at->format('d/m/Y H:i') }}</small>
                                @else
                                    <span class="etiqueta conferir">Pendente</span>
                                @endif
                            </td>
                            @if ($canConfirm && ! $closure->closed_at)
                                <td>
                                    @if ($enrollment->status === 'active')
                                        <form class="fechamento-confirmar" method="POST" action="{{ route('school.classes.year-end.confirm', $params) }}">
                                            @csrf
                                            <input type="hidden" name="enrollment" value="{{ $enrollment->id }}">
                                            <label for="resultado-{{ $enrollment->id }}">Decisão
                                                <select id="resultado-{{ $enrollment->id }}" name="result" required>
                                                    @foreach ($statusNames as $code => $label)
                                                        <option value="{{ $code }}" @selected(($confirmed?->result ?? $row['suggestion']) === $code)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label for="motivo-{{ $enrollment->id }}">Motivo se diferente da sugestão
                                                <textarea id="motivo-{{ $enrollment->id }}" name="reason" rows="2" maxlength="500" placeholder="Obrigatório quando alterar a sugestão">{{ $confirmed?->override_reason }}</textarea>
                                            </label>
                                            <button class="botao secundario" type="submit">Confirmar decisão</button>
                                        </form>
                                    @else
                                        <span>Sem decisão</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($canConfirm && ! $closure->closed_at)
        <section class="folha fechamento-secao" aria-labelledby="titulo-encerrar-ano">
            <div class="folha-cabecalho"><div>
                <h2 id="titulo-encerrar-ano">Encerrar o ano da turma</h2>
                <p>Todos os períodos devem estar fechados e todas as matrículas ativas precisam ter resultado confirmado.</p>
            </div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('school.classes.year-end.close', $params) }}" class="fechamento-acoes">
                    @csrf
                    <label for="motivo-encerramento">Motivo do fechamento (mínimo 10 caracteres)
                        <textarea class="entrada" id="motivo-encerramento" name="reason" rows="2" minlength="10" maxlength="500" required></textarea>
                    </label>
                    <p class="texto-ajuda">Exige segundo fator recente. O fechamento bloqueia notas e decisões até reabertura autorizada.</p>
                    <button class="botao" type="submit" @disabled(! $data['allPeriodsClosed'])>Fechar ano letivo da turma</button>
                </form>
            </div>
        </section>
    @endif

    @if ($canReopen && $closure->closed_at)
        <section class="folha fechamento-secao" aria-labelledby="titulo-reabrir-ano">
            <div class="folha-cabecalho"><div>
                <h2 id="titulo-reabrir-ano">Reabertura excepcional</h2>
                <p>Somente gestão municipal, com justificativa, segundo fator recente e auditoria.</p>
            </div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('school.classes.year-end.reopen', $params) }}" class="fechamento-acoes">
                    @csrf
                    <label for="motivo-reabertura">Motivo da reabertura
                        <textarea class="entrada" id="motivo-reabertura" name="reason" rows="2" minlength="10" maxlength="500" required></textarea>
                    </label>
                    <button class="botao secundario" type="submit">Reabrir ano letivo</button>
                </form>
            </div>
        </section>
    @endif
@endsection
