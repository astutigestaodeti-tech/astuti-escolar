@extends('layouts.municipal')

@section('title', 'Escolas autorizadas')

@section('trilha')
    <span>{{ $municipality->legal_name }}</span>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Escolas</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Rede municipal</span>
            <h1>Escolas autorizadas</h1>
            <p>Você está vendo somente as unidades liberadas para a sua conta.</p>
        </div>
        <span class="marcador"><x-icon name="escola" :size="16" /> {{ $schools->count() }} {{ $schools->count() === 1 ? 'unidade' : 'unidades' }}</span>
    </div>

    @if ($schools->isEmpty())
        <div class="folha vazio">
            Nenhuma escola está liberada para a sua conta. Peça à Secretaria Municipal de Educação que inclua as unidades em que você trabalha.
        </div>
    @else
        <div class="grade">
            @foreach ($schools as $school)
                <a class="folha cartao" href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
                    <div class="cartao-topo"><x-iniciais :texto="$school->name" /><span class="etiqueta ok">Ativa</span></div>
                    <h2>{{ $school->name }}</h2>
                    <span class="cartao-acao">Abrir escola <x-icon name="seta" :size="16" /></span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="nota"><x-icon name="escudo" /><div><strong>Acesso conferido a cada abertura.</strong><p>As permissões são verificadas em cada acesso, inclusive ao abrir um endereço diretamente.</p></div></div>
@endsection
