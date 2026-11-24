@extends('layouts.municipal')

@section('title', 'Renovação de matrículas')
@section('body-class', 'pagina-renovacao')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $results = \App\Academics\EnrollmentRenewal::RESULTS;
    $reasons = \App\Academics\EnrollmentRenewal::REASONS;
    $summary = session('renewal_summary');
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/renovacao-matriculas.css') }}?v={{ filemtime(public_path('css/renovacao-matriculas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.show', $params) }}">Turma {{ $class->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Renovação de matrículas</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">{{ $school->name }}</span><h1>Renovar matrículas para {{ $data['nextNumber'] }}</h1>
            <p>{{ $class->name }} | {{ $class->schoolYear->year }}. Confira o destino de cada aluno e grave toda a turma de uma vez.</p></div>
        <a class="botao secundario" href="{{ route('school.classes.show', $params) }}">Voltar à turma</a>
    </div>

    @if ($errors->any())
        <div class="erro" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if (is_array($summary))
        <section class="folha">
            <div class="folha-cabecalho"><h2>Resumo da renovação</h2></div>
            <div class="folha-corpo" role="status">
                <p><strong>{{ $summary['renewed'] }} matrícula(s) renovada(s).</strong> {{ array_sum($summary['skipped']) }} ficaram de fora.</p>
                @if ($summary['skipped'] !== [])
                    <ul>@foreach ($summary['skipped'] as $reason => $count)<li>{{ $reasons[$reason] }}: {{ $count }}</li>@endforeach</ul>
                @endif
                @foreach ($summary['warnings'] as $warning)
                    <p class="renovacao-aviso"><strong>Capacidade excedida:</strong> {{ $data['classes']->get($warning['class_id'])?->name ?? 'Turma de destino' }}: {{ $warning['occupied'] }} matrículas para {{ $warning['capacity'] }} vagas. A renovação foi registrada; confira a organização da turma.</p>
                @endforeach
            </div>
        </section>
    @endif

    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Condições para renovar</h2><p>Somente resultados confirmados de ano fechado. A matrícula anterior não é alterada.</p></div></div>
        <div class="folha-corpo">
            @unless ($data['closed'])
                <p class="renovacao-aviso">O ano letivo desta turma ainda não está fechado. Conclua o fechamento antes de renovar.</p>
            @endunless
            @if ($data['nextYear'] === null)
                <p class="renovacao-aviso">O ano letivo {{ $data['nextNumber'] }} não está cadastrado ou está encerrado. Solicite o cadastro de um ano planejado ou em andamento.</p>
            @elseif ($data['classes']->isEmpty())
                <p class="renovacao-aviso">Não há turmas abertas nesta escola ou nas demais autorizadas para {{ $data['nextNumber'] }}. Cadastre as turmas do ano seguinte.</p>
            @else
                <p>Aprovados e aprovados com acompanhamento avançam uma série; retidos permanecem na mesma série. O turno da turma de origem tem preferência. Só aparecem destinos da etapa e modalidade correspondentes nas escolas sob sua gestão. Destino em outra escola é apenas sugerido: selecione-o expressamente.</p>
                <p class="texto-ajuda">Na educação infantil, a continuidade respeita a data de corte da lista de espera. Na multisseriada, a série precisa estar na matrícula ou no histórico. Turmas sem série identificável e saídas não são renovadas automaticamente. Se a série individual estiver ausente, confira a matrícula e o histórico antes de renovar. A aprovação no 9º ano exige outra providência. O transporte do ano anterior não é copiado.</p>
            @endif
        </div>
    </section>

    @if ($data['ready'])
        <form method="POST" action="{{ route('school.classes.renewal.store', $params) }}" id="renovacao-turma">
            @csrf
            <input type="hidden" name="ano_destino" value="{{ $data['nextYear']->id }}">
            <section class="folha">
                <div class="folha-cabecalho"><div><h2>Alunos e turmas de destino</h2><p>Troque o destino sugerido ou escolha Não renovar para desmarcar o aluno. Matrículas já existentes no ano seguinte não são duplicadas.</p></div></div>
                <div class="rolagem renovacao-rolagem">
                    <table class="tabela renovacao-tabela">
                        <thead><tr><th scope="col">Aluno</th><th scope="col">Resultado confirmado</th><th scope="col">Turma de destino ou situação</th></tr></thead>
                        <tbody>
                            @forelse ($data['rows'] as $row)
                                <tr>
                                    <th scope="row">{{ $row['entry']->student->full_name }}</th>
                                    <td>{{ \App\Academics\RenewalStage::isInfant($class) && $row['result'] === 'aprovado' ? 'Continuidade confirmada' : ($results[$row['result']] ?? 'Não confirmado') }}
                                        @if ($row['target'] !== null)<br><small class="texto-ajuda">Destino: {{ \App\Academics\RenewalStage::LABELS[$row['target']] }}</small>@endif</td>
                                    <td>
                                        @if ($row['reason'] !== null)
                                            <span class="etiqueta neutra">{{ $reasons[$row['reason']] }}</span>
                                        @else
                                            @if ($row['crossSchool'])
                                                <p class="texto-ajuda">Sugestão em outra escola: {{ $data['classes']->get($row['suggestion'])->school->name }} | {{ $data['classes']->get($row['suggestion'])->name }}. Confirme escolhendo o destino.</p>
                                            @endif
                                            <label class="sr-only" for="destino-{{ $row['entry']->id }}">Turma de destino de {{ $row['entry']->student->full_name }}</label>
                                            <select class="entrada renovacao-destino" id="destino-{{ $row['entry']->id }}" name="destinos[{{ $row['entry']->id }}]">
                                                <option value="">Não renovar</option>
                                                @foreach ($row['choices'] as $choice)
                                                    @php
                                                        $occupied = (int) $data['counts']->get($choice->id, 0);
                                                    @endphp
                                                    <option value="{{ $choice->id }}" @selected(old('destinos.'.$row['entry']->id, $row['crossSchool'] ? null : $row['suggestion']) === $choice->id)>{{ $choice->school->name }} | {{ $choice->name }} | {{ $choice->shift }} | {{ $occupied }}/{{ $choice->capacity ?? 'sem limite informado' }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Nenhuma matrícula nesta turma.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="folha-corpo renovacao-confirmar">
                    <p class="texto-ajuda">Confira a seleção e a capacidade abaixo. A confirmação exige segundo fator recente.</p>
                    <button type="submit" class="botao" @disabled($data['rows']->every(fn ($row) => $row['reason'] !== null))>Renovar matrículas da turma</button>
                </div>
            </section>
            <section class="folha">
                <div class="folha-cabecalho"><div><h2>Capacidade das turmas de destino</h2><p>A previsão acompanha a seleção acima. Exceder a capacidade gera aviso, mas não impede gravar.</p></div></div>
                <div class="folha-corpo">
                    @foreach ($data['classes'] as $destination)
                        @php
                            $occupied = (int) $data['counts']->get($destination->id, 0);
                            $suggested = $data['rows']->where('suggestion', $destination->id)->where('crossSchool', false)->count();
                        @endphp
                        <p class="renovacao-capacidade" data-class="{{ $destination->id }}" data-occupied="{{ $occupied }}" data-capacity="{{ $destination->capacity ?? '' }}">
                            <strong>{{ $destination->school->name }} | {{ $destination->name }} | {{ $destination->shift }}</strong>:
                            <span data-projected>{{ $occupied + $suggested }}</span> previstas / {{ $destination->capacity ?? 'capacidade não informada' }}.
                            <span class="renovacao-aviso" data-capacity-warning @if ($destination->capacity === null || $occupied + $suggested <= $destination->capacity) hidden @endif>Capacidade excedida.</span>
                        </p>
                    @endforeach
                </div>
            </section>
        </form>
        <script defer src="{{ asset('js/renovacao-matriculas.js') }}"></script>
    @endif
@endsection
