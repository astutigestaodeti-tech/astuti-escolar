@extends('layouts.municipal')

@php
    use App\Models\Tenant\ClassCouncil;
    use App\Models\Tenant\ClassCouncilStudent;
    use App\Models\Tenant\LiteracyAssessment;
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $status = $council?->status ?? 'not_started';
    $editable = $period !== null && $status !== 'completed';
@endphp

@section('title', 'Conselho de classe')
@section('body-class', 'pagina-gestao-pedagogica')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/gestao-pedagogica.css') }}?v={{ filemtime(public_path('css/gestao-pedagogica.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('school.classes.show', $params) }}">{{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Conselho de classe</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Gestão pedagógica</span><h1>Conselho de classe</h1><p>{{ $school->name }} | {{ $class->name }}</p></div>
        <a class="botao secundario" href="{{ route('school.classes.show', $params) }}">Voltar à turma</a>
    </div>
    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="erro" role="alert"><strong>Revise os campos do conselho.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="folha">
        <div class="folha-corpo">
            <form class="pedagogico-filtros" method="GET" action="{{ route('pedagogical.council.index', $params) }}">
                <label>Período <select class="entrada" name="periodo" @disabled($periods->isEmpty())>
                    @foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->number }}º {{ $p->kind }}</option>@endforeach
                </select></label>
                <button class="botao secundario" @disabled($periods->isEmpty())>Consultar</button>
            </form>
            @if ($period === null)<p>Cadastre os períodos acadêmicos do ano para iniciar o conselho.</p>@else
                <p><strong>{{ ClassCouncil::STATUSES[$status] }}</strong> | {{ $period->starts_on->format('d/m/Y') }} a {{ $period->ends_on->format('d/m/Y') }}</p>
                @if ($council?->closed_at)<p>Conclusão: {{ $council->closed_at->format('d/m/Y H:i') }}</p>@endif
                @if ($director && $council?->reopened_at)<p>Última reabertura: {{ $council->reopened_at->format('d/m/Y H:i') }}. Motivo: {{ $council->reopen_reason }}</p>@endif
            @endif
        </div>
    </section>
    @if ($period !== null)
        <form method="POST" action="{{ route('pedagogical.council.save', $params) }}" class="pedagogico-secao">
            @csrf
            <input type="hidden" name="periodo" value="{{ $period->id }}">
            <section class="folha">
                <div class="folha-cabecalho"><div><h2>Reunião</h2><p>Use apenas informações pedagógicas necessárias. Não informe laudos, diagnósticos ou relatos familiares.</p></div></div>
                <div class="folha-corpo pedagogico-campos">
                    <label>Data <input type="date" class="entrada" name="meeting_date" value="{{ $council?->meeting_date?->toDateString() }}" @disabled(! $director || ! $editable)></label>
                    <label>Participantes (cargos e nomes dos profissionais) <textarea class="entrada" name="participants" rows="2" maxlength="500" @disabled(! $director || ! $editable)>{{ $council?->participants }}</textarea></label>
                    <label>Encaminhamentos da turma <textarea class="entrada" name="class_actions" rows="3" maxlength="1000" @disabled(! $director || ! $editable)>{{ $council?->class_actions }}</textarea></label>
                </div>
            </section>
            <section class="folha pedagogico-secao">
                <div class="folha-cabecalho"><div><h2>Alunos do período</h2><p>Notas lançadas, faltas (incluindo justificadas) e sondagem de alfabetização quando disponível.</p></div></div>
                <div class="folha-corpo">
                    @forelse ($rows as $row)
                        @php
                            $e = $row['enrollment'];
                            $r = $row['record'];
                        @endphp
                        <article class="pedagogico-aluno">
                            <h3>{{ $e->student?->full_name }}</h3>
                            <p><strong>Faltas:</strong> {{ $row['absences'] }}</p>
                            <ul class="pedagogico-notas">
                                @foreach ($row['components'] as $component)
                                    <li>{{ $component->name }}: {{ $row['grades']->get($component->id)?->grade ?? 'Sem nota' }}</li>
                                @endforeach
                            </ul>
                            @if ($row['literacy'])
                                <p><strong>Alfabetização:</strong> {{ LiteracyAssessment::WRITING[$row['literacy']->writing] ?? 'Não informada' }} | {{ LiteracyAssessment::READING[$row['literacy']->reading] ?? 'Não informada' }}</p>
                            @endif
                            <div class="pedagogico-campos">
                                <label>Parecer pedagógico de {{ $e->student?->full_name }}
                                    <textarea class="entrada" name="students[{{ $e->id }}][opinion]" rows="2" maxlength="500" @disabled(! $editable)>{{ $r?->opinion }}</textarea>
                                </label>
                                <label>Encaminhamento
                                    <select class="entrada" name="students[{{ $e->id }}][referral]" @disabled(! $director || ! $editable)>
                                        <option value="">Não registrado</option>
                                        @foreach (ClassCouncilStudent::REFERRALS as $key => $label)
                                            <option value="{{ $key }}" @selected($r?->referral === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                @if ($director)
                                    <label>Observação opcional <textarea class="entrada" name="students[{{ $e->id }}][observation]" rows="2" maxlength="500" @disabled(! $editable)>{{ $r?->observation }}</textarea></label>
                                @endif
                            </div>
                            @if ($e->status === 'active' && $r?->referral === 'team')
                                <a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $e->student_id]) }}#equipe-multiprofissional">Abrir solicitação à equipe multiprofissional</a>
                            @elseif ($director && $e->status === 'active' && $r?->referral === 'active_search')
                                <a href="{{ route('school.active-search.detail', ['municipio' => $municipality->slug, 'escola' => $school->id, 'matricula' => $e->id]) }}">Abrir ficha de busca ativa</a>
                            @endif
                        </article>
                    @empty
                        <p>Nenhum aluno vinculado à turma neste período.</p>
                    @endforelse
                    @if ($editable)<button class="botao" type="submit">Salvar conselho</button>@endif
                </div>
            </section>
        </form>
        @if ($director && $council)
            <section class="folha pedagogico-secao">
                <div class="folha-corpo">
                    <p class="texto-ajuda">Concluir ou reabrir exige segundo fator confirmado nos últimos 15 minutos. <a href="{{ route('two-factor.challenge') }}">Confirmar novamente</a>, se necessário.</p>
                    <form method="POST" action="{{ route('pedagogical.council.transition', $params) }}" class="pedagogico-campos">
                        @csrf
                        <input type="hidden" name="periodo" value="{{ $period->id }}">
                        @if ($status === 'completed')
                            <input type="hidden" name="acao" value="reopen">
                            <label>Motivo da reabertura <textarea name="motivo" class="entrada" minlength="10" maxlength="500" required></textarea></label>
                            <button class="botao secundario">Reabrir conselho</button>
                        @else
                            <input type="hidden" name="acao" value="complete">
                            <p>Salve as alterações antes de concluir. Após concluir, a edição fica bloqueada até reabertura motivada.</p>
                            <button class="botao">Concluir conselho</button>
                        @endif
                    </form>
                </div>
            </section>
        @endif
    @endif
@endsection
