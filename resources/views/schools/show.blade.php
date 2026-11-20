@extends('layouts.municipal')

@section('title', $school->name)

@section('content')
    <a class="back" href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Voltar às escolas autorizadas</a>
    <div class="schools">
        <section class="school">
            <span class="pill">Unidade escolar</span>
            <h1>{{ $school->name }}</h1>
            <p class="muted">Os cadastros de alunos e matrículas serão disponibilizados nas próximas etapas, com permissões próprias.</p>
        </section>
    </div>
@endsection
