@extends('layouts.municipal')

@php
    $parametrosEscola = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $percentual = $summary['total'] > 0 ? (int) floor($summary['complete'] * 100 / $summary['total']) : 100;
    // Para cada pendência, a tela onde ela se resolve.
    $atalho = function (string $tipo, array $aluno) use ($parametrosEscola): array {
        $parametrosAluno = [...$parametrosEscola, 'aluno' => $aluno['id']];
        return match ($tipo) {
            'repetido' => [route('school.students.index', [...$parametrosEscola, 'busca' => $aluno['name']]), 'Comparar os cadastros'],
            'responsavel' => [route('school.students.guardians.create', $parametrosAluno), 'Adicionar responsável'],
            'turma' => [route('school.students.edit', [...$parametrosAluno, 'matricula' => $aluno['enrollment']]), 'Escolher turma'],
            'nascimento', 'endereco' => [route('school.students.edit', $parametrosAluno), 'Editar cadastro'],
            'transporte' => [route('school.students.show', $parametrosAluno).'#titulo-endereco-aluno', 'Conferir transporte'],
            'foto' => [route('school.students.show', $parametrosAluno), 'Adicionar foto'],
            'mapa' => [route('school.students.geo.edit', $parametrosAluno), 'Marcar no mapa'],
        };
    };
@endphp

@section('title', 'Pendências do cadastro - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $parametrosEscola) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Pendências do cadastro</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Qualidade do cadastro</span>
            <h1>Pendências do cadastro</h1>
            <p>O que falta completar nos alunos com matrícula ativa em {{ $school->name }}. Cada pendência leva direto à tela onde ela se resolve.</p>
        </div>
    </div>

    <section class="folha" aria-labelledby="titulo-progresso">
        <div class="folha-cabecalho"><div><h2 id="titulo-progresso">Cadastro completo</h2><p>{{ $summary['complete'] }} de {{ $summary['total'] }} {{ $summary['total'] === 1 ? 'aluno' : 'alunos' }} sem nenhuma pendência.</p></div></div>
        <div class="folha-corpo">
            <div class="turma-barra" role="progressbar" aria-label="Alunos com cadastro completo" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentual }}" aria-valuetext="{{ $percentual }}% dos alunos com cadastro completo">
                <div class="turma-barra-preenchimento" style="width: {{ $percentual }}%"></div>
            </div>
            <nav class="pendencias-filtros" aria-label="Filtrar por pendência">
                <a href="{{ route('school.pendencies.index', $parametrosEscola) }}" @class(['pendencia-filtro', 'ativo' => $selectedType === null]) @if ($selectedType === null) aria-current="true" @endif>Todas <span class="numero">{{ $summary['withPendencies'] }}</span></a>
                @foreach ($types as $tipo => $rotulo)
                    @if ($summary['byType'][$tipo] > 0)
                        <a href="{{ route('school.pendencies.index', [...$parametrosEscola, 'tipo' => $tipo]) }}" @class(['pendencia-filtro', 'ativo' => $selectedType === $tipo]) @if ($selectedType === $tipo) aria-current="true" @endif>{{ $rotulo }} <span class="numero">{{ $summary['byType'][$tipo] }}</span></a>
                    @endif
                @endforeach
            </nav>
            @if (! $schoolMapped)
                <p class="texto-ajuda">Marque a escola no mapa para o sistema conferir também a distância e o transporte de cada aluno. <a href="{{ route('schools.geo.edit', $parametrosEscola) }}">Marcar a escola no mapa</a>.</p>
            @endif
        </div>
    </section>

    <section class="folha pendencias-lista" aria-labelledby="titulo-lista-pendencias">
        <div class="folha-cabecalho"><div>
            <h2 id="titulo-lista-pendencias">{{ $selectedType ? $types[$selectedType] : 'Alunos com pendências' }}</h2>
            <p>Primeiro quem está sem contato com a família, depois quem tem mais pendências.</p>
        </div></div>
        @if ($students->isEmpty())
            <div class="folha-corpo"><p class="pendencias-vazio">Nenhuma pendência {{ $selectedType ? 'deste tipo' : '' }}. O cadastro desta escola está em dia.</p></div>
        @else
            <div class="rolagem">
                <table class="tabela pendencias-tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">Turma</th><th scope="col">O que falta</th></tr></thead>
                    <tbody>
                    @foreach ($students as $aluno)
                        <tr>
                            <td><a class="pendencias-aluno" href="{{ route('school.students.show', [...$parametrosEscola, 'aluno' => $aluno['id']]) }}">{{ $aluno['name'] }}</a></td>
                            <td>{{ $aluno['class'] ?? 'Sem turma' }}</td>
                            <td>
                                <ul class="pendencias-itens">
                                    @foreach ($aluno['pendencies'] as $tipo)
                                        @php [$url, $acao] = $atalho($tipo, $aluno); @endphp
                                        <li><span @class(['etiqueta', 'conferir' => in_array($tipo, ['repetido', 'responsavel', 'turma', 'transporte'], true), 'neutra' => ! in_array($tipo, ['repetido', 'responsavel', 'turma', 'transporte'], true)])>{{ $types[$tipo] }}</span> <a href="{{ $url }}">{{ $acao }}</a></li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($hidden > 0)
                <div class="folha-corpo"><p class="texto-ajuda">Mostrando os primeiros {{ $students->count() }}. Use os filtros para ver os outros {{ $hidden }}.</p></div>
            @endif
        @endif
    </section>
@endsection
