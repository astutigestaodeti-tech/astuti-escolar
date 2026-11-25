@extends('layouts.municipal')

@section('title', 'Imprimir ficha cadastral')
@section('body-class', 'ficha-print-configuracao')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/navegacao-registros.css') }}?v={{ filemtime(public_path('css/navegacao-registros.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ficha-impressao.css') }}?v={{ filemtime(public_path('css/ficha-impressao.css')) }}">
    <script defer src="{{ asset('js/ficha-impressao.js') }}?v={{ filemtime(public_path('js/ficha-impressao.js')) }}"></script>
@endpush

@section('content')
    @php($params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
    <div class="folha-titulo">
        <div><span class="contexto">Documento administrativo restrito</span><h1>Imprimir ficha do aluno</h1><p>{{ $student->full_name }} | {{ $school->name }}</p></div>
        <a class="botao secundario" href="{{ route('school.students.show', $params) }}">Voltar à ficha</a>
    </div>
    @if (session('print_notice'))
        <div class="nota" role="status"><x-icon name="info" /><div>{{ session('print_notice') }}</div></div>
    @endif
    <section class="folha ficha-print-config" aria-labelledby="ficha-print-selecao">
        <div class="folha-cabecalho"><div><h2 id="ficha-print-selecao">Selecione as informações</h2><p>Nome e RA identificam o documento. Marque somente o necessário para a finalidade administrativa.</p></div></div>
        <form method="POST" action="{{ route('school.students.print.preview', $params) }}" class="ficha-print-form">
            @csrf
            @isset($selectionError)
                <div class="ficha-print-erro" role="alert">{{ $selectionError }}</div>
            @endisset
            <fieldset class="ficha-print-fieldset">
                <legend>Informações cadastrais</legend>
                <div class="ficha-print-opcoes">
                    @foreach (\App\Http\Controllers\StudentRegistrationPrintController::GROUPS as $key => $label)
                        @continue(in_array($key, \App\Http\Controllers\StudentRegistrationPrintController::SENSITIVE, true))
                        <label class="ficha-print-opcao"><input type="checkbox" name="grupos[]" value="{{ $key }}" @checked(! isset($selectedGroups) || in_array($key, $selectedGroups, true))> <span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            @if ($canIncludeSensitive)
                <fieldset class="ficha-print-fieldset ficha-print-sensiveis">
                    <legend>Informações sensíveis (opcionais)</legend>
                    <p>Inclua apenas se indispensáveis à finalidade indicada. Não são incluídos laudos ou registros técnicos da equipe multiprofissional.</p>
                    <div class="ficha-print-opcoes">
                        @foreach (\App\Http\Controllers\StudentRegistrationPrintController::SENSITIVE as $key)
                            <label class="ficha-print-opcao"><input type="checkbox" name="grupos[]" value="{{ $key }}" data-print-sensitive @checked(in_array($key, $selectedGroups ?? [], true))> <span>{{ \App\Http\Controllers\StudentRegistrationPrintController::GROUPS[$key] }}</span></label>
                        @endforeach
                    </div>
                    <label class="ficha-print-finalidade" for="ficha-print-finalidade">Finalidade (obrigatória ao selecionar informações sensíveis)</label>
                    <select class="entrada" id="ficha-print-finalidade" name="finalidade" aria-describedby="ficha-print-finalidade-ajuda">
                        <option value="">Selecione a finalidade</option>
                        @foreach (\App\Http\Controllers\StudentRegistrationPrintController::PURPOSES as $key => $label)
                            <option value="{{ $key }}" @selected(($selectedPurpose ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p id="ficha-print-finalidade-ajuda">Escolha a finalidade antes de visualizar a ficha com informações sensíveis.</p>
                </fieldset>
            @endif
            <div class="nota"><x-icon name="escudo" /><div><strong>Uso administrativo restrito</strong><p>A geração da prévia é registrada na auditoria. Guarde o papel ou PDF com acesso restrito. Não compartilhe por canais não autorizados.</p></div></div>
            <div class="ficha-print-botoes">
                <a class="registro-nav-botao registro-nav-neutro" href="{{ route('school.students.show', $params) }}">Cancelar</a>
                <button class="registro-nav-botao registro-nav-roxo" type="submit"><x-icon name="imprimir" :size="16" /> Visualizar ficha para impressão</button>
            </div>
        </form>
    </section>
@endsection
