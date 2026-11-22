@extends('layouts.municipal')

@section('title', $school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $school->name }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Unidade escolar</span>
            <h1>{{ $school->name }}</h1>
            <p>Dados da unidade, turmas e consulta de alunos.</p>
        </div>
        <span class="etiqueta ok">Ativa</span>
    </div>

    <div class="ficha-escola">
        <section class="folha" aria-labelledby="titulo-dados">
            <div class="folha-cabecalho"><div><h2 id="titulo-dados">Dados da unidade</h2><p>Informações cadastradas pela Secretaria.</p></div></div>
            <dl>
                <div><dt>Nome</dt><dd>{{ $school->name }}</dd></div>
                <div><dt>Código INEP</dt><dd class="numero">{{ $school->inep_code ?: 'Não informado' }}</dd></div>
                <div><dt>Situação</dt><dd>Em funcionamento</dd></div>
            </dl>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-turmas">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-turmas">Turmas</h2>
            <p>Turmas ativas da escola, com etapa, turno e vagas.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.classes.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar turmas desta escola <x-icon name="seta" :size="16" /></a>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-alunos">
            <span class="acao-icone"><x-icon name="alunos" /></span>
            <h2 id="titulo-alunos">Alunos</h2>
            <p>Consulte os alunos com vínculo ativo nesta unidade escolar.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar alunos desta escola <x-icon name="seta" :size="16" /></a>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-matriculas">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-matriculas">Matrículas</h2>
            <p>Consulte os vínculos ativos, anos letivos e datas de início desta escola.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.enrollments.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar matrículas desta escola <x-icon name="seta" :size="16" /></a>
        </section>
        @can('manageStudents', [$school, $municipality])
            <section class="folha cartao" aria-labelledby="titulo-importar-alunos">
                <span class="acao-icone"><x-icon name="alunos" /></span>
                <h2 id="titulo-importar-alunos">Importar alunos</h2>
                <p>Envie uma planilha, confira repetições e grave os vínculos com auditoria.</p>
                <a class="botao secundario" style="margin-top:12px" href="{{ route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Importar alunos desta escola <x-icon name="seta" :size="16" /></a>
            </section>
            <section class="folha cartao" aria-labelledby="titulo-importar-transporte">
                <span class="acao-icone"><x-icon name="turmas" /></span>
                <h2 id="titulo-importar-transporte">Transporte escolar</h2>
                <p>Conferir a lista de transporte e atualizar somente matrículas existentes.</p>
                <a class="botao secundario" style="margin-top:12px" href="{{ route('school.imports.transport.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Importar lista de transporte <x-icon name="seta" :size="16" /></a>
            </section>
        @endcan
    </div>

    @can('manageStudents', [$school, $municipality])
        <div class="nota"><x-icon name="escudo" /><div><strong>Alterações protegidas</strong><p>Importações e alterações de alunos e matrículas pedem o código do segundo fator e ficam registradas no histórico.</p></div></div>
    @else
        <div class="nota"><x-icon name="info" /><div><strong>Somente consulta</strong><p>Sua conta pode consultar os dados desta escola, mas não alterar alunos e matrículas.</p></div></div>
    @endcan
@endsection
