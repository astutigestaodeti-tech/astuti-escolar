@extends('layouts.municipal')

@section('title', 'Sondagem de alfabetização')
@section('body-class', 'pagina-alfabetizacao')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/alfabetizacao.css') }}?v={{ filemtime(public_path('css/alfabetizacao.css')) }}">
@endpush

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id];
    $writing = \App\Models\Tenant\LiteracyAssessment::WRITING;
    $reading = \App\Models\Tenant\LiteracyAssessment::READING;
@endphp

@section('content')
    <div class="folha-titulo"><div><span class="contexto">{{ $school->name }} | {{ $class->schoolYear->year }}</span><h1>Sondagem de alfabetização</h1><p>{{ $class->name }}. Registro acadêmico restrito à gestão e aos professores vinculados.</p></div><a class="botao secundario" href="{{ route('school.classes.show', $params) }}">Voltar à turma</a></div>
    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro" role="alert">{{ $errors->first() }}</div>@endif
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Bimestre da sondagem</h2><p>Selecione o período para consultar ou lançar.</p></div></div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('school.literacy.classroom', $params) }}" class="alfabetizacao-form">
                <label>Bimestre <select class="entrada" name="bimestre">@foreach (range(1, 4) as $n)<option value="{{ $n }}" @selected($term === $n)>{{ $n }}º bimestre</option>@endforeach</select></label>
                <button class="botao" type="submit">Consultar</button>
            </form>
            @if ($closed)<p class="texto-ajuda">Ano letivo encerrado. Somente consulta.</p>
            @elseif ($periodClosed)<p class="texto-ajuda">Bimestre fechado. Correção exclusiva da gestão com justificativa.</p>
            @endif
            <p class="texto-ajuda">Sondagem pedagógica, sem nota numérica e sem alteração automática do resultado final.</p>
        </div>
    </section>
    <section class="folha">
        <div class="folha-cabecalho"><div><h2>Registros do {{ $term }}º bimestre</h2><p>Informe os níveis observados na escrita e na leitura. Sem registro permanece sem sondagem.</p></div></div>
        <div class="folha-corpo">
            @if ($enrollments->isEmpty())
                <p>Nenhuma matrícula ativa nesta turma.</p>
            @elseif ($canWrite)
                <form method="POST" action="{{ route('school.literacy.save', $params) }}">
                    @csrf
                    <input type="hidden" name="bimestre" value="{{ $term }}">
                    <div class="alfabetizacao-rolagem">
                        <table class="tabela alfabetizacao-tabela alfabetizacao-lancamento">
                            <thead><tr><th scope="col">Aluno</th><th scope="col">Escrita</th><th scope="col">Leitura</th></tr></thead>
                            <tbody>
                                @foreach ($enrollments as $entry)
                                    @php($record = $records->get($entry->id))
                                    <tr>
                                        <th scope="row">{{ $entry->student->full_name }}</th>
                                        <td><select class="entrada" name="sondagem[{{ $entry->id }}][escrita]" aria-label="Escrita de {{ $entry->student->full_name }}"><option value="">Sem sondagem</option>@foreach($writing as $key => $label)<option value="{{ $key }}" @selected(old('sondagem.'.$entry->id.'.escrita', $record?->writing) === $key)>{{ $label }}</option>@endforeach</select></td>
                                        <td><select class="entrada" name="sondagem[{{ $entry->id }}][leitura]" aria-label="Leitura de {{ $entry->student->full_name }}"><option value="">Sem sondagem</option>@foreach($reading as $key => $label)<option value="{{ $key }}" @selected(old('sondagem.'.$entry->id.'.leitura', $record?->reading) === $key)>{{ $label }}</option>@endforeach</select></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="alfabetizacao-form">
                        @if ($periodClosed)<label>Justificativa da correção <input class="entrada" name="motivo" minlength="10" maxlength="500" required></label>@endif
                        <button type="submit" class="botao">Salvar sondagem da turma</button>
                    </div>
                    <p class="texto-ajuda">Preencha os alunos avaliados e salve uma vez só. Quem ficar em "Sem sondagem" não é alterado.</p>
                </form>
            @else
                @foreach ($enrollments as $entry)
                    @php($record = $records->get($entry->id))
                    <div class="alfabetizacao-aluno">
                        <h3>{{ $entry->student->full_name }}</h3>
                        <p>{{ $record ? 'Escrita: '.$writing[$record->writing].'. Leitura: '.$reading[$record->reading].'.' : 'Sem sondagem.' }}</p>
                    </div>
                @endforeach
            @endif
        </div>
    </section>
    @include('literacy.distribution')
@endsection
