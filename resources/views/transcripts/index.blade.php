@extends('layouts.municipal')

@section('title', 'Histórico escolar do Ensino Fundamental')
@section('body-class', 'pagina-historico-escolar')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/historico-escolar.css') }}?v={{ filemtime(public_path('css/historico-escolar.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $params) }}">Ficha do aluno</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Histórico escolar</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Ensino Fundamental</span>
            <h1>Histórico escolar</h1>
            <p>{{ $student->full_name }}. Os anos encerrados na rede aparecem automaticamente. Digite somente os anos anteriores.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.students.show', $params) }}">Voltar à ficha</a>
    </div>

    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="erro" role="alert">
            <strong>Verifique os campos do histórico.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="folha" aria-labelledby="titulo-preview-historico">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-preview-historico">Prévia dos anos escolares</h2>
                <p>Um registro por ano, em ordem cronológica. A rede tem prioridade sobre registros digitados.</p>
            </div>
        </div>
        <div class="folha-corpo">
            @forelse ($data['warnings'] as $warning)
                <p class="historico-alerta" role="note">{{ $warning }}</p>
            @empty
                <p class="texto-ajuda">Nenhuma sobreposição encontrada.</p>
            @endforelse
            @if ($data['inProgress'] !== [])
                <p class="texto-ajuda"><strong>Ano em curso:</strong> {{ implode(', ', $data['inProgress']) }}.
                    Turmas ainda abertas não entram nas médias ou resultados finais do histórico.</p>
            @endif
        </div>
        <div class="rolagem historico-rolagem">
            <table class="tabela historico-tabela">
                <thead><tr>
                    <th scope="col">Ano</th>
                    <th scope="col">Etapa e unidade escolar</th>
                    <th scope="col">Município e UF</th>
                    <th scope="col">Médias ou conceitos</th>
                    <th scope="col">Frequência</th>
                    <th scope="col">Resultado</th>
                    <th scope="col">Origem</th>
                </tr></thead>
                <tbody>
                    @forelse ($data['years'] as $year)
                        <tr>
                            <th scope="row" class="historico-numero">{{ $year['year'] }}</th>
                            <td><strong>{{ $year['stage'] }}</strong><small>{{ $year['school'] }}</small></td>
                            <td>{{ $year['city'] }}{{ $year['state'] ? '/'.$year['state'] : '' }}</td>
                            <td>
                                @forelse ($year['grades'] as $component => $grade)
                                    <span class="historico-nota">{{ $component }}: <strong>{{ $grade }}</strong></span>
                                @empty
                                    <span>Não informado</span>
                                @endforelse
                            </td>
                            <td class="historico-numero">{{ $year['frequency'] === null ? '-' : number_format((float) $year['frequency'], 1, ',', '.').'%' }}</td>
                            <td>{{ $year['result'] === 'acompanhado' ? 'Aprovado com acompanhamento' : ucfirst($year['result']) }}</td>
                            <td>{{ $year['source'] === 'rede' ? 'Sistema' : 'Anterior' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Nenhum ano fechado ou anterior cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canIssue)
        <section class="folha historico-secao" aria-labelledby="titulo-anos-anteriores">
            <div class="folha-cabecalho">
                <div><h2 id="titulo-anos-anteriores">Histórico: anos anteriores</h2>
                    <p>Cadastre uma única vez os anos estudados fora desta rede. Notas e conceitos são opcionais.</p>
                </div>
            </div>
            <div class="folha-corpo">
                @include('transcripts.previous-form', ['record' => null, 'suffix' => 'novo'])
            </div>
            @if ($data['previous']->isNotEmpty())
                <div class="folha-corpo">
                    <h3>Anos anteriores cadastrados</h3>
                    @foreach ($data['previous'] as $record)
                        <details class="historico-registro">
                            <summary>{{ $record->school_year }} - {{ $record->school_stage }} - {{ $record->school_name }} (editar)</summary>
                            @include('transcripts.previous-form', ['suffix' => $record->id])
                            <form action="{{ route('school.students.transcripts.previous.archive', [...$params, 'registro' => $record->id]) }}"
                                  method="POST" class="historico-arquivar">
                                @csrf
                                <label class="rotulo" for="arquivar-{{ $record->id }}">Motivo obrigatório para excluir o ano anterior
                                    <textarea class="entrada" id="arquivar-{{ $record->id }}" name="reason" rows="2" minlength="10" maxlength="500" required></textarea>
                                </label>
                                <button type="submit" class="botao secundario">Excluir registro com justificativa</button>
                            </form>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="folha historico-secao" aria-labelledby="titulo-emitir-historico">
            <div class="folha-cabecalho">
                <div><h2 id="titulo-emitir-historico">Emitir histórico verificável</h2>
                    <p>Um clique gera documento com código único, QR e uma cópia imutável do conteúdo.</p>
                </div>
            </div>
            <div class="folha-corpo">
                @if ($data['years'] === [])
                    <p>Cadastre os anos anteriores ou conclua o fechamento de uma turma antes de emitir.</p>
                @else
                    <form method="POST" action="{{ route('school.students.transcripts.issue', $params) }}">
                        @csrf
                        <label class="rotulo" for="observacoes-historico">Observações adicionais (opcional)
                            <textarea class="entrada" id="observacoes-historico" name="observations" rows="3" maxlength="2000">{{ old('observations') }}</textarea>
                        </label>
                        <p class="texto-ajuda">A emissão exige segundo fator. Correções posteriores não modificam as vias já emitidas.</p>
                        <button type="submit" class="botao">Emitir histórico escolar</button>
                    </form>
                @endif
            </div>
        </section>
    @else
        <div class="nota historico-secao" role="note"><x-icon name="info" />
            <p>A gestão municipal consulta o histórico. Edição e emissão ficam reservadas à direção e à secretaria escolar desta unidade.</p>
        </div>
    @endif

    <section class="folha historico-secao" aria-labelledby="titulo-historicos-emitidos">
        <div class="folha-cabecalho"><div><h2 id="titulo-historicos-emitidos">Históricos emitidos</h2>
            <p>Vias anteriores preservadas, incluindo documentos revogados.</p></div></div>
        <div class="folha-corpo">
            @forelse ($documents as $document)
                <div class="historico-documento">
                    <div><strong>Histórico de {{ $document->issued_at->format('d/m/Y H:i') }}</strong>
                        <small>{{ $document->revoked_at ? 'Revogado' : 'Válido' }}</small></div>
                    <a class="botao secundario" href="{{ route('school.students.transcripts.show', [...$params, 'documento' => $document->id]) }}">Visualizar via emitida</a>
                </div>
            @empty
                <p>Nenhum histórico escolar emitido nesta unidade.</p>
            @endforelse
        </div>
    </section>
@endsection
