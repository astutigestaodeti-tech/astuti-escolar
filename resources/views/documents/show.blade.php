@extends('layouts.municipal')

@section('body-class', 'impressao-declaracao')
@section('timbre-proprio', '1')
@section('title', \App\Models\Tenant\IssuedDocument::DECLARATIONS[$document->kind])

@section('content')
    @php
        $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
        $label = \App\Models\Tenant\IssuedDocument::DECLARATIONS[$document->kind];
        $snapshot = $document->declaration_snapshot;
        $paperSchool = $snapshot === null ? $school : new \App\Models\Tenant\School($snapshot['school']);
        $paperProfile = $snapshot === null ? null : new \App\Models\Tenant\InstitutionProfile($snapshot['institution']);
    @endphp
    <div class="folha-titulo declaracao-acoes">
        <div><span class="contexto">Documento escolar</span><h1>{{ $label }}</h1>
            <p>Documento emitido em {{ $document->issued_at->format('d/m/Y') }}. A consulta pública usa apenas dados mínimos.</p></div>
        <div class="formulario-acoes">
            <a class="botao secundario" href="{{ route('school.students.declarations.create', [...$params, 'tipo' => $document->kind]) }}">Voltar às declarações</a>
            <button type="button" class="botao primario" data-action="print-declaration">Imprimir</button>
        </div>
    </div>

    @if (session('status'))<div class="status-sucesso declaracao-acoes" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro declaracao-acoes" role="alert">{{ $errors->first() }}</div>@endif

    <section class="folha declaracao-papel" aria-labelledby="titulo-declaracao-papel">
        <div class="folha-cabecalho declaracao-cabecalho-tela"><h2 id="titulo-declaracao-papel">Via para impressão</h2></div>
        <div class="folha-corpo">
            <x-documento.timbre :municipality="$municipality" :municipality-name="$document->snapshot_municipality_name"
                :school-name="$document->snapshot_school_name" :school-inep="$paperSchool->inep_code" :perfil="$paperProfile" />

            <h2 class="declaracao-titulo">{{ $label }}</h2>
            @if ($document->revoked_at)<p class="declaracao-revogada" role="alert">DOCUMENTO REVOGADO - sem validade</p>@endif

            @if ($document->kind === 'attendance_declaration')
                <p class="declaracao-texto">Declaramos, para os devidos fins, que <strong>{{ $document->snapshot_student_name }}</strong>, no ano letivo de {{ $document->snapshot_school_year }}, etapa {{ $document->snapshot_stage }}, turma {{ $document->snapshot_class_name }}, apresentou frequência registrada de <strong>{{ number_format($snapshot['percentage'], 1, ',', '.') }}%</strong> no período de {{ \Illuminate\Support\Carbon::parse($snapshot['start'])->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($snapshot['end'])->format('d/m/Y') }}.</p>
                <p class="declaracao-texto">Dias com chamada registrada: {{ $snapshot['total'] }}. Presenças: {{ $snapshot['present'] }}. Faltas não justificadas: {{ $snapshot['absent'] }}. Faltas justificadas: {{ $snapshot['justified'] }}. O percentual considera as presenças sobre o total de marcações, incluindo as faltas justificadas no denominador.</p>
            @elseif ($document->kind === 'transfer_declaration')
                <p class="declaracao-texto">Declaramos, para os devidos fins, que <strong>{{ $document->snapshot_student_name }}</strong>, matriculado(a) nesta unidade no ano letivo de {{ $document->snapshot_school_year }}, etapa {{ $document->snapshot_stage }}, turma {{ $document->snapshot_class_name }}, teve sua matrícula encerrada por {{ $snapshot['status'] === 'transferred' ? 'transferência na rede' : 'transferência para fora da rede' }} em {{ \Illuminate\Support\Carbon::parse($snapshot['ended_on'])->format('d/m/Y') }}.</p>
                <p class="declaracao-texto">O histórico escolar será encaminhado pela unidade emissora conforme os procedimentos da rede. Esta declaração não substitui o histórico nem comprova matrícula na unidade de destino.</p>
            @elseif ($document->kind === 'completion_declaration')
                <p class="declaracao-texto">Declaramos, para os devidos fins, que <strong>{{ $document->snapshot_student_name }}</strong> concluiu a série/turma {{ $document->snapshot_class_name }}, etapa {{ $document->snapshot_stage }}, referente ao ano letivo de {{ $document->snapshot_school_year }}, com resultado confirmado de aprovação, no fechamento de {{ \Illuminate\Support\Carbon::parse($snapshot['closed_on'])->format('d/m/Y') }}.</p>
                <p class="declaracao-texto">Esta declaração comprova a conclusão do ano/série indicado e não certifica, isoladamente, a conclusão de toda a etapa de ensino.</p>
            @else
            <p class="declaracao-texto">Declaramos, para os devidos fins, que <strong>{{ $document->snapshot_student_name }}</strong> encontra-se com matrícula registrada nesta unidade escolar, no ano letivo de <strong>{{ $document->snapshot_school_year }}</strong>@if ($document->snapshot_stage), na etapa <strong>{{ $document->snapshot_stage }}</strong>@endif @if ($document->snapshot_class_name), turma <strong>{{ $document->snapshot_class_name }}</strong>@endif @if ($document->snapshot_shift), no turno <strong>{{ $document->snapshot_shift }}</strong>@endif.</p>
            @if ($document->purpose)<p class="declaracao-texto">Finalidade: {{ $document->purpose }}.</p>@endif
            @endif
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
            <x-documento.rodape :municipality="$municipality" :school="$paperSchool" :perfil="$paperProfile" :aviso="$snapshot === null" />
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
