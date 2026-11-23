@extends('layouts.municipal')

@section('body-class', 'impressao-declaracao')
@section('title', 'Declaração de matrícula')

@section('content')
    @php($params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
    <div class="folha-titulo declaracao-acoes">
        <div><span class="contexto">Documento escolar</span><h1>Declaração de matrícula</h1>
            <p>Documento emitido em {{ $document->issued_at->format('d/m/Y') }}. A consulta pública usa apenas dados mínimos.</p></div>
        <div class="formulario-acoes">
            <a class="botao secundario" href="{{ route('school.students.show', $params) }}">Voltar à ficha</a>
            <button type="button" class="botao primario" data-action="print-declaration">Imprimir</button>
        </div>
    </div>

    @if (session('status'))<div class="status-sucesso declaracao-acoes" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro declaracao-acoes" role="alert">{{ $errors->first() }}</div>@endif

    <section class="folha declaracao-papel" aria-labelledby="titulo-declaracao-papel">
        <div class="folha-cabecalho declaracao-cabecalho-tela"><h2 id="titulo-declaracao-papel">Via para impressão</h2></div>
        <div class="folha-corpo">
            <header class="declaracao-cabecalho">
                @php
                    $brasao = 'images/brasao-'.$municipality->slug.'.webp';
                    $temBrasao = preg_match('/\A[a-z0-9-]+\z/', (string) $municipality->slug) === 1 && is_file(public_path($brasao));
                @endphp
                @if ($temBrasao)<img src="{{ asset($brasao) }}" alt="Brasão municipal" width="76" height="76">@endif
                <div><strong>{{ $document->snapshot_municipality_name }}</strong>
                    <p>Secretaria Municipal de Educação</p>
                    <p>{{ $document->snapshot_school_name }}</p>
                </div>
            </header>

            <h2 class="declaracao-titulo">Declaração de matrícula</h2>
            @if ($document->revoked_at)<p class="declaracao-revogada" role="alert">DOCUMENTO REVOGADO - sem validade</p>@endif

            <p class="declaracao-texto">Declaramos, para os devidos fins, que <strong>{{ $document->snapshot_student_name }}</strong> encontra-se com matrícula registrada nesta unidade escolar, no ano letivo de <strong>{{ $document->snapshot_school_year }}</strong>@if ($document->snapshot_stage), na etapa <strong>{{ $document->snapshot_stage }}</strong>@endif @if ($document->snapshot_class_name), turma <strong>{{ $document->snapshot_class_name }}</strong>@endif @if ($document->snapshot_shift), no turno <strong>{{ $document->snapshot_shift }}</strong>@endif.</p>
            @if ($document->purpose)<p class="declaracao-texto">Finalidade: {{ $document->purpose }}.</p>@endif
            <p class="declaracao-data">{{ $document->issued_at->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY') }}.</p>

            <div class="declaracao-assinatura"><span>Assinatura da direção</span></div>

            <footer class="declaracao-rodape">
                <div class="declaracao-verificacao">
                    <strong>Verificação de autenticidade</strong>
                    <p>Código: <span class="numero">{{ $document->verification_code }}</span></p>
                    <p>Emissão: {{ $document->issued_at->format('d/m/Y H:i') }}</p>
                    <p>Endereço: <span class="declaracao-url">{{ $verificationUrl }}</span></p>
                </div>
                <div class="declaracao-qr">{!! $qrSvg !!}</div>
            </footer>
        </div>
    </section>

    @unless ($document->revoked_at)
        <section class="folha declaracao-revogacao declaracao-acoes" aria-labelledby="titulo-revogacao">
            <div class="folha-cabecalho"><div><h2 id="titulo-revogacao">Revogar declaração</h2><p>O papel continuará verificável, porém marcado como revogado.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('school.students.declarations.revoke', [...$params, 'documento' => $document->id]) }}">
                    @csrf
                    <label class="rotulo" for="motivo">Motivo da revogação (mínimo 10 caracteres) *</label>
                    <textarea class="entrada" id="motivo" name="motivo" rows="2" minlength="10" maxlength="500" required>{{ old('motivo') }}</textarea>
                    <p class="texto-suave">A revogação exige confirmação recente do segundo fator. O motivo não aparece na página pública nem na auditoria em claro.</p>
                    <div class="formulario-acoes"><button class="botao secundario" type="submit">Revogar documento</button></div>
                </form>
            </div>
        </section>
    @endunless

    <script type="module" src="{{ asset('js/declaracao-imprimir.js') }}"></script>
@endsection
