@extends('layouts.municipal')

@php
    $parametrosEscola = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $parametrosTurma = [...$parametrosEscola, 'turma' => $class->id];
@endphp

@section('title', 'Turma '.$class->name.' - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $parametrosEscola) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.classes.index', $parametrosEscola) }}">Turmas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $class->name }}</span>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('css/tempo-integral.css') }}?v={{ filemtime(public_path('css/tempo-integral.css')) }}">
@endpush

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Organização acadêmica</span>
            <h1>Turma {{ $class->name }}</h1>
            <p>Alunos com matrícula ativa nesta turma e ano letivo.</p>
        </div>
        <div class="acoes-titulo">
            <span class="marcador"><x-icon name="turmas" :size="16" /> {{ $occupied }} {{ $occupied === 1 ? 'aluno' : 'alunos' }}</span>
            <a class="botao" href="{{ route('school.classes.attendance.index', $parametrosTurma) }}">{{ $todaySessionExists ? 'Corrigir chamada de hoje' : 'Fazer chamada de hoje' }}</a>
            <a class="botao secundario" href="{{ route('school.classes.attendance.monthly', $parametrosTurma) }}">Relatório do mês</a>
            @if (! app(\App\Academics\GradeAccess::class)->isMunicipalManager(auth()->user(), $municipality) && (app(\App\Academics\PedagogicalAccess::class)->directs(auth()->user(), $municipality, $school) || app(\App\Academics\GradeAccess::class)->isAssignedTeacher(auth()->user(), $municipality, $school, $class)))
                <a class="botao secundario" href="{{ route('pedagogical.council.index', $parametrosTurma) }}">Conselho de classe</a>
            @endif
            @if($canManage || app(\App\Academics\GradeAccess::class)->isAssignedTeacher(auth()->user(), $municipality, $school, $class))
                <a class="botao secundario" href="{{ route('school.active-search.class', $parametrosTurma) }}">Alertas da turma</a>
            @endif
            @if (str_contains(\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($class->stage)), 'fundamental'))
                <a class="botao secundario" href="{{ route('school.classes.grades.index', $parametrosTurma) }}">Notas por período</a>
                @if (\App\Academics\Literacy::grade($class) !== null && app(\App\Academics\GradeAccess::class)->canWrite(auth()->user(), $municipality, $school, $class))
                    <a class="botao secundario" href="{{ route('school.literacy.classroom', $parametrosTurma) }}">Sondagem de alfabetização</a>
                @endif
            @endif
            @if (app(\App\Academics\GradeAccess::class)->isElementary($class) || \App\Academics\RenewalStage::supported($class))
                @if ($canManage)
                    <a class="botao secundario" href="{{ route('school.classes.year-end.index', $parametrosTurma) }}">Fechamento do ano</a>
                @endif
                <a class="botao secundario" href="{{ route('school.classes.year-end.minutes', $parametrosTurma) }}">Ata de resultados finais</a>
            @endif
            @if ($canRenew)
                <a class="botao secundario" href="{{ route('school.classes.renewal.index', $parametrosTurma) }}">Renovar matrículas para {{ $class->schoolYear->year + 1 }}</a>
            @endif
            <a class="botao secundario" href="{{ route('school.classes.index', $parametrosEscola) }}">Todas as turmas</a>
        </div>
    </div>

    <section class="folha turma-resumo" aria-labelledby="titulo-resumo-turma">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-resumo-turma">Dados da turma</h2><p>Informações de organização e ocupação de vagas.</p></div>
        </div>
        <dl class="turma-dados">
            <div><dt>Nome</dt><dd>{{ $class->name }}</dd></div>
            <div><dt>Etapa</dt><dd>{{ $class->stage }}</dd></div>
            <div><dt>Turno</dt><dd>{{ $class->shift }}</dd></div>
            <div><dt>Jornada diária</dt><dd>{{ $class->daily_hours === null ? 'Não informada' : number_format((float) $class->daily_hours, 1, ',', '.').' horas' }}</dd></div>
            <div><dt>Ano letivo</dt><dd class="numero">{{ $class->schoolYear->year }}</dd></div>
        </dl>

        @can('viewNetworkPanel', [\App\Models\Tenant\School::class, $municipality])
            <div class="folha-corpo">
                <form action="{{ route('integral.hours.update', [...$parametrosTurma]) }}" method="POST" class="integral-jornada-form">
                    @csrf
                    <label for="daily-hours-{{ $class->id }}">Jornada diária em horas (gestão municipal)
                        <input class="entrada" id="daily-hours-{{ $class->id }}" type="number" name="daily_hours"
                            min="0.5" max="16" step="0.1" value="{{ $class->daily_hours }}" placeholder="Ex.: 7.0">
                    </label>
                    <button class="botao secundario" type="submit">Salvar jornada</button>
                    <p class="texto-ajuda">Integral a partir de 7 horas diárias. Remova o valor para voltar à classificação provisória pelo turno.</p>
                </form>
            </div>
        @endcan

        <div class="turma-ocupacao">
            <div class="turma-ocupacao-titulo">
                <strong>Vagas ocupadas</strong>
                @if ($capacity !== null)
                    <span class="numero">{{ $occupied }} de {{ $capacity }} vagas ocupadas ({{ $occupancyPercentage }}%)</span>
                @else
                    <span class="numero">{{ $occupied }} {{ $occupied === 1 ? 'aluno matriculado' : 'alunos matriculados' }}. Vagas não informadas.</span>
                @endif
            </div>
            @if ($capacity !== null)
                <div class="turma-barra" role="progressbar" aria-label="Ocupação da turma" aria-valuemin="0" aria-valuemax="{{ $capacity }}" aria-valuenow="{{ min($occupied, $capacity) }}" aria-valuetext="{{ $occupied }} de {{ $capacity }} vagas ocupadas">
                    <div class="turma-barra-preenchimento {{ $aboveCapacity ? 'turma-barra-excedida' : '' }}" style="width: {{ $visualPercentage }}%"></div>
                </div>
            @endif
        </div>

        @if ($aboveCapacity)
            <div class="nota turma-alerta" role="alert">
                <x-icon name="alerta" :size="18" />
                <p>Turma acima da capacidade: {{ $occupied }} alunos para {{ $capacity }} vagas.</p>
            </div>
        @endif
    </section>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif

    <section class="folha" aria-labelledby="titulo-professores-turma">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-professores-turma">Professores da turma</h2>
                <p>Atribuições docentes, disciplina e período de atuação nesta turma.</p>
            </div>
        </div>
        @if ($assignments->isEmpty())
            <div class="vazio">Nenhum professor atribuído a esta turma.</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Professor(a)</th><th scope="col">Disciplina</th><th scope="col">Período</th><th scope="col">Tipo</th>@if ($canManage)<th scope="col">Ação</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($assignments as $assignment)
                            <tr>
                                <td><strong>{{ $assignment->staffMember?->full_name ?? 'Cadastro arquivado' }}</strong></td>
                                <td>{{ $assignment->subject ?: 'Não informada' }}</td>
                                <td>{{ $assignment->ends_on ? $assignment->starts_on->format('d/m/Y').' a '.$assignment->ends_on->format('d/m/Y') : 'Desde '.$assignment->starts_on->format('d/m/Y') }}</td>
                                <td>{{ $assignment->is_main ? 'Principal' : 'Demais docentes' }}</td>
                                @if ($canManage)
                                    <td>
                                        @if ($assignment->staffMember && ! $assignment->staffMember->trashed())
                                            <a href="{{ route('school.classes.teachers.census.edit', [...$parametrosTurma, 'atribuicao' => $assignment->id]) }}">Atribuição no Censo</a>
                                        @endif
                                        @if ($assignment->ends_on === null)
                                            <details class="atribuicao-encerrar">
                                                <summary>Encerrar atribuição</summary>
                                                <form method="POST" action="{{ route('school.classes.teachers.end', [...$parametrosTurma, 'atribuicao' => $assignment->id]) }}">
                                                    @csrf
                                                    <label class="rotulo" for="ends-on-{{ $assignment->id }}">Último dia na turma</label>
                                                    <input class="entrada" id="ends-on-{{ $assignment->id }}" type="date" name="ends_on" value="{{ old('ends_on', today()->toDateString()) }}" required>
                                                    <button class="botao secundario" type="submit">Encerrar</button>
                                                </form>
                                            </details>
                                        @else
                                            Encerrada
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canManage)
            <div class="folha-cabecalho"><div><h3>Adicionar professor à turma</h3><p>Apenas servidores ativos com função Professor(a) nesta escola.</p></div></div>
            <div class="folha-corpo">
            @if ($errors->any())
                <div class="erro" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @if ($availableTeachers->isEmpty())
                <p>Não há professores ativos cadastrados. <a href="{{ route('school.staff.create', $parametrosEscola) }}">Cadastrar servidor</a>.</p>
            @else
                <form method="POST" action="{{ route('school.classes.teachers.store', $parametrosTurma) }}">
                    @csrf
                    <div class="formulario-grade">
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="staff_member_id">Professor(a) *</label>
                            <select class="entrada" name="staff_member_id" id="staff_member_id" required>
                                <option value="">Selecione um professor</option>
                                @foreach ($availableTeachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected(old('staff_member_id') === $teacher->id)>{{ $teacher->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="subject">Disciplina (opcional)</label>
                            <input class="entrada" name="subject" id="subject" maxlength="80" value="{{ old('subject') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="starts_on">Início *</label>
                            <input class="entrada" type="date" name="starts_on" id="starts_on" value="{{ old('starts_on', today()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="formulario-campo">
                        <label><input type="checkbox" name="is_main" value="1" @checked(old('is_main') === '1')> Professor(a) principal desta turma</label>
                        <p class="texto-ajuda">Só pode haver uma atribuição principal em aberto por turma. Encerre a anterior antes de substituir.</p>
                    </div>
                    <button class="botao primario" type="submit">Atribuir professor</button>
                </form>
            @endif
            </div>
        @endif
    </section>

    @if ($canManage)
        <section class="folha chamada-historico" aria-labelledby="titulo-chamadas-anteriores">
            <div class="folha-cabecalho">
                <div>
                    <h2 id="titulo-chamadas-anteriores">Chamadas anteriores</h2>
                    <p>Para corrigir uma chamada anterior, informe o motivo e confirme novamente o segundo fator.</p>
                </div>
            </div>
            <div class="folha-corpo">
            <form class="chamada-consulta-data" method="GET" action="{{ route('school.classes.attendance.index', $parametrosTurma) }}">
                <label for="chamada-data">Consultar data</label>
                <input id="chamada-data" type="date" name="data" max="{{ today()->toDateString() }}" required>
                <button class="botao secundario" type="submit">Abrir chamada</button>
            </form>
            @if ($previousDates->isNotEmpty())
                <div class="chamada-datas">
                    @foreach ($previousDates as $day)
                        <a href="{{ route('school.classes.attendance.index', [...$parametrosTurma, 'data' => $day->attendance_date->toDateString()]) }}">{{ $day->attendance_date->format('d/m/Y') }}</a>
                    @endforeach
                </div>
            @endif
            </div>
        </section>
    @endif

    <section class="folha" aria-labelledby="titulo-alunos-turma">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-alunos-turma">Alunos da turma</h2>
                <p>Idade em 31/03/{{ $class->schoolYear->year }}. Para Pré I e II e do 1º ao 9º ano, idades diferentes da esperada pedem conferência humana; turmas de série não identificada não são classificadas automaticamente.</p>
            </div>
        </div>
        @if ($roster->isEmpty())
            <div class="vazio">Ainda não há alunos com matrícula ativa nesta turma.</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Aluno</th>
                            <th scope="col">Nascimento</th>
                            <th scope="col">Idade em {{ $cutoff->format('d/m/Y') }}</th>
                            <th scope="col">Frequência no mês</th>
                            <th scope="col">Contato principal</th>
                            <th scope="col">Ficha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roster as $entry)
                            @php
                                $student = $entry['student'];
                            @endphp
                            @php
                                $contact = $entry['primaryContact'];
                            @endphp
                            <tr>
                                <td><strong>{{ $student->full_name }}</strong></td>
                                <td class="numero">{{ $student->birth_date?->format('d/m/Y') ?? 'Não informada' }}</td>
                                <td>
                                    <span class="numero">{{ $entry['age'] !== null ? $entry['age'].' anos' : 'Não calculada' }}</span>
                                    @if ($entry['needsAgeReview'])
                                        <span class="etiqueta conferir">Conferir idade</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($entry['frequency']['percentage'] !== null)
                                        <strong class="numero">{{ $entry['frequency']['percentage'] }}%</strong>
                                        <span class="turma-frequencia-detalhe">{{ $entry['frequency']['present'] }} de {{ $entry['frequency']['sessions'] }} chamadas</span>
                                        @if ($entry['frequency']['alert'])
                                            <span class="etiqueta conferir">{{ $entry['frequency']['alert'] }}</span>
                                        @endif
                                    @else
                                        <span class="turma-frequencia-detalhe">Sem chamadas</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($contact)
                                        <strong>{{ $contact->full_name }}</strong>
                                        <span class="turma-telefone"><a href="tel:+55{{ preg_replace('/\D/', '', (string) $contact->phone) }}">{{ $contact->formatted_phone }}</a></span>
                                    @else
                                        Não informado
                                    @endif
                                </td>
                                <td><a href="{{ route('school.students.show', [...$parametrosEscola, 'aluno' => $student->id]) }}">Ver ficha</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
