@extends('layouts.municipal')

@section('title', 'Escolas autorizadas')

@section('content')
    <span class="eyebrow">Rede municipal</span>
    <h1>Escolas autorizadas</h1>
    <p class="muted">Você está visualizando somente as unidades disponíveis para sua conta.</p>
    <div class="schools">
        @forelse ($schools as $school)
            <a class="school" href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
                <span class="pill">Unidade escolar</span>
                <strong>{{ $school->name }}</strong>
            </a>
        @empty
            <p class="muted">Nenhuma escola disponível para sua conta.</p>
        @endforelse
    </div>
    <p class="note">As permissões são verificadas em cada acesso, inclusive ao abrir um endereço diretamente.</p>
@endsection
