@extends('layouts.municipal')

@section('title', 'Componentes curriculares')
@section('body-class', 'pagina-notas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/notas-boletim.css') }}?v={{ filemtime(public_path('css/notas-boletim.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Componentes curriculares</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Gestão municipal</span><h1>Componentes curriculares</h1>
            <p>Lista única da rede para o Ensino Fundamental. Educação Infantil não utiliza notas.</p></div>
    </div>

    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro" role="alert"><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

    <section class="folha" aria-labelledby="titulo-componentes">
        <div class="folha-cabecalho"><div><h2 id="titulo-componentes">Componentes da rede</h2><p>Os componentes inativos deixam de receber notas novas, mas preservam o histórico.</p></div></div>
        <div class="rolagem notas-rolagem">
            <table class="tabela notas-tabela notas-componentes-tabela">
                <thead><tr><th scope="col">Componente</th><th scope="col">Tipo</th><th scope="col">Situação</th><th scope="col">Ação</th></tr></thead>
                <tbody>
                    @foreach ($components as $component)
                        <tr>
                            <th scope="row">{{ $component->name }}</th>
                            <td>{{ $component->is_optional ? 'Opcional' : 'Obrigatório' }}</td>
                            <td>{{ $component->active ? 'Ativo' : 'Inativo' }}</td>
                            <td>
                                <form method="POST" action="{{ route('academic.components.toggle', ['municipio' => $municipality->slug, 'componente' => $component->id]) }}">
                                    @csrf
                                    <input type="hidden" name="active" value="{{ $component->active ? '0' : '1' }}">
                                    <button class="botao secundario" type="submit">{{ $component->active ? 'Desativar' : 'Ativar' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="folha notas-secao" aria-labelledby="titulo-novo-componente">
        <div class="folha-cabecalho"><div><h2 id="titulo-novo-componente">Adicionar componente</h2></div></div>
        <div class="folha-corpo">
            <form class="notas-filtros" method="POST" action="{{ route('academic.components.store', ['municipio' => $municipality->slug]) }}">
                @csrf
                <label for="nome-componente">Nome do componente
                    <input class="entrada" type="text" name="name" id="nome-componente" maxlength="120" required value="{{ old('name') }}">
                </label>
                <label class="notas-checkbox"><input type="checkbox" name="is_optional" value="1" @checked(old('is_optional') === '1')> Componente opcional</label>
                <button class="botao" type="submit">Cadastrar componente</button>
            </form>
        </div>
    </section>
@endsection
