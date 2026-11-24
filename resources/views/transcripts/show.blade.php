@extends('layouts.municipal')

@section('timbre-proprio', '1')
@section('title', 'Histórico escolar emitido')
@section('body-class', 'pagina-historico-emitido')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $snapshot = $document->transcript_snapshot;
    $header = $snapshot['header'];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/historico-escolar.css') }}?v={{ filemtime(public_path('css/historico-escolar.css')) }}">
@endpush

@section('content')
    <div class="folha-titulo historico-acoes">
        <div><span class="contexto">Documento acadêmico</span><h1>Histórico escolar emitido</h1>
            <p>Esta via usa o conteúdo preservado na data da emissão e não consulta novamente as notas atuais.</p></div>
        <div class="formulario-acoes">
            <a class="botao secundario" href="{{ route('school.students.transcripts.index', $params) }}">Voltar ao histórico</a>
            <button class="botao" type="button" data-action="print-declaration">Imprimir A4</button>
        </div>
    </div>

    @if (session('status'))<div class="status-sucesso historico-acoes" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro historico-acoes" role="alert">{{ $errors->first() }}</div>@endif

    <section class="folha historico-papel" aria-labelledby="titulo-historico-impresso">
        <div class="folha-corpo">
            <x-documento.timbre :municipality="$municipality" :municipality-name="$header['municipality']"
                :school-name="$header['issuer_school']" :school-inep="$header['issuer_inep']" />
            <div class="historico-titulo-doc"><h2 id="titulo-historico-impresso">Histórico escolar - Ensino Fundamental</h2>
                <p>Emissão: {{ $document->issued_at->format('d/m/Y') }}</p></div>
            @if ($document->revoked_at)
                <p class="historico-revogado" role="alert">DOCUMENTO REVOGADO - SEM VALIDADE</p>
            @endif

            <dl class="historico-identificacao">
                <div><dt>Aluno</dt><dd>{{ $header['student'] }}</dd></div>
                <div><dt>Nascimento</dt><dd>{{ $header['birth_date'] ?: 'Não informado' }}</dd></div>
                <div><dt>Naturalidade</dt><dd>{{ $header['birthplace'] ?: 'Não informada' }}{{ $header['birth_state'] ? '/'.$header['birth_state'] : '' }}</dd></div>
                <div><dt>RA</dt><dd>{{ $header['ra'] ?: 'Não informado' }}</dd></div>
                @if ($header['student_inep'])<div><dt>INEP do aluno</dt><dd>{{ $header['student_inep'] }}</dd></div>@endif
            </dl>

            <div class="rolagem historico-rolagem historico-impressao">
                <table class="tabela historico-tabela">
                    <thead><tr>
                        <th scope="col">Ano</th>
                        <th scope="col">Etapa e escola</th>
                        <th scope="col">Município/UF</th>
                        <th scope="col">Notas ou conceitos finais</th>
                        <th scope="col">Frequência</th>
                        <th scope="col">Resultado</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($snapshot['years'] as $year)
                            <tr>
                                <th scope="row" class="historico-numero">{{ $year['year'] }}</th>
                                <td><strong>{{ $year['stage'] }}</strong><small>{{ $year['school'] }}</small></td>
                                <td>{{ $year['city'] }}{{ $year['state'] ? '/'.$year['state'] : '' }}</td>
                                <td>
                                    @forelse ($year['grades'] as $component => $grade)
                                        <span class="historico-nota">{{ $component }}: {{ $grade }}</span>
                                    @empty
                                        <span>Não informado</span>
                                    @endforelse
                                </td>
                                <td class="historico-numero">{{ $year['frequency'] === null ? '-' : number_format((float) $year['frequency'], 1, ',', '.').'%' }}</td>
                                <td>{{ $year['result'] === 'acompanhado' ? 'Aprovado com acompanhamento' : ucfirst($year['result']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($snapshot['in_progress'] !== [])
                <p><strong>Ano em curso:</strong> {{ implode(', ', $snapshot['in_progress']) }}. Não compõe o resultado final acima.</p>
            @endif
            @if ($snapshot['observations'] !== '')
                <div class="historico-observacoes"><strong>Observações:</strong><p>{{ $snapshot['observations'] }}</p></div>
            @endif
            <div class="historico-assinaturas">
                <div><span>Direção escolar</span></div>
                <div><span>Secretaria escolar</span></div>
            </div>
            <footer class="historico-verificacao">
                <div>
                    <strong>Autenticidade do documento</strong>
                    <p>Código: <span class="historico-numero">{{ $document->verification_code }}</span></p>
                    <p>Emissão: {{ $document->issued_at->format('d/m/Y H:i') }}</p>
                    <p>{{ $verificationUrl }}</p>
                    <p>Impressão digital do conteúdo (SHA-256):
                        <span class="historico-digest">{{ $document->transcript_sha256 }}</span></p>
                </div>
                <div class="historico-qr">{!! $qrSvg !!}</div>
            </footer>
            <x-documento.rodape :municipality="$municipality" :school="$school" />
        </div>
    </section>

    @if ($canRevoke && ! $document->revoked_at)
        <section class="folha historico-acoes historico-revogacao" aria-labelledby="titulo-revogar-historico">
            <div class="folha-cabecalho"><div><h2 id="titulo-revogar-historico">Revogar via emitida</h2>
                <p>Se precisar corrigir dados, revogue esta via, corrija os registros e emita outra.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('school.students.transcripts.revoke', [...$params, 'documento' => $document->id]) }}">
                    @csrf
                    <label class="rotulo" for="motivo-revogacao-historico">Motivo da revogação
                        <textarea class="entrada" id="motivo-revogacao-historico" name="reason" rows="2" minlength="10" maxlength="500" required></textarea>
                    </label>
                    <button type="submit" class="botao secundario">Revogar histórico</button>
                </form>
            </div>
        </section>
    @endif

    <script src="{{ asset('js/declaracao-imprimir.js') }}" defer></script>
@endsection
