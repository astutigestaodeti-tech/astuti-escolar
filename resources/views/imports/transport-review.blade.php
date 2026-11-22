@extends('layouts.municipal')

@section('title', 'Conferir transporte - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Conferir transporte</span>
@endsection

@section('content')
    @php
        $labels = ['active' => 'Ativo', 'not_requested' => 'Não utiliza', 'cancelled' => 'Cancelado'];
        $muda = count(array_filter($rows, fn ($row) => $row['will_change']));
        $porNome = count(array_filter($rows, fn ($row) => ($row['match'] ?? 'ra') !== 'ra'));
        $foraTotal = count($skipped);
    @endphp

    <div class="folha-titulo"><div>
        <span class="contexto">{{ $school->name }} - Ano letivo {{ $year->year }}</span>
        <h1>Conferir transporte</h1>
        <p>{{ count($rows) + $foraTotal }} {{ count($rows) + $foraTotal === 1 ? 'linha lida' : 'linhas lidas' }} no arquivo. Nada foi gravado ainda.</p>
    </div></div>

    <dl class="conf-resumo" aria-label="Resumo da conferência">
        <div class="conf-resumo-item pronto"><dt>Vão mudar</dt><dd>{{ $muda }}</dd></div>
        <div class="conf-resumo-item ignorado"><dt>Já estão assim (sem mudança)</dt><dd>{{ count($rows) - $muda }}</dd></div>
        @if ($porNome > 0)
            <div class="conf-resumo-item confirmar"><dt>Achados pelo nome (confira)</dt><dd>{{ $porNome }}</dd></div>
        @endif
        <div class="conf-resumo-item corrigir"><dt>Fora desta escola ou não aplicados</dt><dd>{{ $foraTotal }}</dd></div>
    </dl>

    <form method="post" action="{{ route('school.imports.transport.confirm', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
        @csrf
        <input type="hidden" name="lote" value="{{ $token }}">
        <section class="folha" aria-labelledby="linhas-transporte">
            <div class="folha-cabecalho"><div><h2 id="linhas-transporte">Alunos desta escola</h2><p>Situação e detalhes do transporte que serão gravados. Campos vazios não apagam dados já existentes. Nenhum aluno é criado.</p></div></div>
            <div class="rolagem">
                <table class="tabela conf-tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">RA</th><th scope="col">Achado por</th><th scope="col">Atual</th><th scope="col">Depois de gravar</th><th scope="col">Linha</th><th scope="col">Ponto de embarque</th><th scope="col">Veículo adaptado</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($porRa = ($row['match'] ?? 'ra') === 'ra')
                            <tr class="conf-linha {{ $porRa ? 'pronto' : 'confirmar' }}">
                                <td><strong>{{ $row['name'] }}</strong><span class="conf-origem">Linha {{ $row['line'] }} do arquivo</span></td>
                                <td class="conf-numero">@if ($row['ra']){{ $row['ra'] }}@else<span class="conf-falta">Sem RA</span>@endif</td>
                                <td>{{ $porRa ? 'RA' : (($row['match'] ?? '') === 'nome_data' ? 'Nome e data de nascimento' : 'Só o nome') }}</td>
                                <td>{{ $labels[$row['current_status']] ?? 'Não informado' }}</td>
                                <td>
                                    <span class="conf-selo {{ $row['will_change'] ? 'pronto' : 'ignorado' }}">{{ $labels[$row['desired_status']] }}</span>
                                    @unless ($row['will_change'])
                                        <small class="conf-origem">Sem mudança</small>
                                    @endunless
                                </td>
                                <td>{{ $row['transport_route'] ?? 'Não informada' }}</td>
                                <td>{{ $row['transport_stop'] ?? 'Não informado' }}</td>
                                <td>
                                    @if ($row['transport_adapted_vehicle'] === true)
                                        Veículo adaptado: Sim
                                    @elseif ($row['transport_adapted_vehicle'] === false)
                                        Veículo adaptado: Não
                                    @else
                                        Não informado
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($porNome > 0)
            <div class="conf-pedido conf-confirmacao">
                <label class="conf-marca">
                    <input type="checkbox" name="conferido_nomes" value="1" required>
                    <span>{{ $porNome === 1 ? 'Conferi o aluno achado pelo nome e confirmo que é o da lista.' : 'Conferi os '.$porNome.' alunos achados pelo nome e confirmo que são os da lista.' }}</span>
                </label>
            </div>
        @endif

        <div class="conf-acoes">
            <button class="botao" type="submit">Gravar transporte de {{ count($rows) }} {{ count($rows) === 1 ? 'aluno' : 'alunos' }}</button>
            <a class="botao secundario" href="{{ route('school.imports.transport.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Cancelar</a>
        </div>
    </form>

    @if ($foraTotal > 0)
        <section class="folha conf-fora" aria-labelledby="linhas-fora">
            <div class="folha-cabecalho"><div><h2 id="linhas-fora">Linhas que ficam de fora</h2><p>Estas linhas não serão gravadas. Numa lista da Secretaria, é normal aparecerem alunos de outras escolas.</p></div></div>
            <div class="rolagem">
                <table class="tabela conf-tabela">
                    <thead><tr><th scope="col">Linha</th><th scope="col">Aluno no arquivo</th><th scope="col">Escola no arquivo</th><th scope="col">Motivo</th></tr></thead>
                    <tbody>
                        @foreach ($skipped as $item)
                            <tr class="conf-linha ignorado">
                                <td class="conf-numero">{{ $item['line'] }}</td>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['school'] ?? 'Não informada' }}</td>
                                <td>{{ $item['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="nota conf-lgpd"><x-icon name="escudo" /><div><strong>Como a gravação funciona</strong><p>Só atualiza situação, linha, ponto e veículo adaptado das matrículas acima. Campos vazios preservam os valores existentes. Todas as mudanças são gravadas juntas, ou nenhuma, se algo falhar. A confirmação usa seu segundo fator e vale por uma hora. O arquivo não é guardado no servidor.</p></div></div>
@endsection
