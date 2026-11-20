@extends('layouts.municipal')

@section('title', 'Turmas de '.$school->name)

@section('content')
    <a class="back" href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Voltar à escola</a>
    <span class="eyebrow" style="display: block; margin-top: 25px">Organização acadêmica</span>
    <h1>Turmas de {{ $school->name }}</h1>
    <p class="muted">Esta consulta exibe somente as turmas ativas da unidade autorizada.</p>

    <div class="schools">
        @forelse ($classes as $class)
            <article class="school">
                <span class="pill">Ano letivo {{ $class->schoolYear->year }}</span>
                <strong>{{ $class->name }}</strong>
                <p class="muted">{{ $class->stage }} · {{ $class->modality }}</p>
                <p class="muted">Turno: {{ $class->shift }}. {{ $class->capacity !== null ? $class->capacity.' vagas' : 'Capacidade não informada' }}.</p>
            </article>
        @empty
            <p class="muted">Nenhuma turma ativa cadastrada nesta escola.</p>
        @endforelse
    </div>

    <p class="note">Alterações em anos e turmas permanecem indisponíveis até a ativação da autenticação reforçada e auditoria.</p>
@endsection
