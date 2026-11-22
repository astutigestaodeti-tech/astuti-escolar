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
            <div><dt>Ano letivo</dt><dd class="numero">{{ $class->schoolYear->year }}</dd></div>
        </dl>

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

    @if ($canManage)
        <section class="folha chamada-historico" aria-labelledby="titulo-chamadas-anteriores">
            <div class="folha-cabecalho">
                <div>
                    <h2 id="titulo-chamadas-anteriores">Chamadas anteriores</h2>
                    <p>Para corrigir uma chamada anterior, informe o motivo e confirme novamente o segundo fator.</p>
                </div>
            </div>
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
                            @php($student = $entry['student'])
                            @php($contact = $entry['primaryContact'])
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
