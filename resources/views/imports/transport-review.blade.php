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
    <div class="folha-titulo"><div>
        <span class="contexto">{{ $school->name }} - Ano letivo {{ $year->year }}</span>
        <h1>Conferir transporte</h1>
        <p>{{ count($rows) }} matrícula(s) localizada(s). Confira a situação anterior e a nova antes de gravar.</p>
    </div></div>

    <div class="nota"><x-icon name="info" /><div><strong>Nenhuma alteração foi feita ainda.</strong><p>A confirmação usa seu segundo fator e expira após uma hora. Se uma matrícula mudar nesse intervalo, o lote será recusado integralmente.</p></div></div>

    @php
        $labels = ['active' => 'Ativo', 'not_requested' => 'Não utiliza', 'cancelled' => 'Cancelado'];
    @endphp

    <form method="post" action="{{ route('school.imports.transport.confirm', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
        @csrf
        <input type="hidden" name="lote" value="{{ $token }}">
        <section class="folha" aria-labelledby="linhas-transporte">
            <div class="folha-cabecalho"><div><h2 id="linhas-transporte">Conferência da lista</h2><p>Dados da matrícula atual, sem criar registros novos.</p></div></div>
            <div class="rolagem">
                <table class="tabela conf-tabela">
                    <thead><tr><th scope="col">Aluno</th><th scope="col">RA</th><th scope="col">Atual</th><th scope="col">Proposta</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><strong>{{ $row['name'] }}</strong><span class="conf-origem">Linha {{ $row['line'] }} do CSV</span></td>
                                <td class="conf-numero">{{ $row['ra'] }}</td>
                                <td>{{ $labels[$row['current_status']] ?? 'Não informado' }}</td>
                                <td><strong>{{ $labels[$row['desired_status']] }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <div class="conf-acoes">
            <button class="botao" type="submit">Confirmar alterações no transporte</button>
            <a class="botao secundario" href="{{ route('school.imports.transport.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Cancelar e voltar</a>
        </div>
    </form>

    <div class="nota conf-lgpd"><x-icon name="info" /><div><strong>LGPD e auditoria</strong><p>O CSV original não é armazenado. Somente a situação de transporte da matrícula é alterada e auditada. Nenhum cancelamento é reativado automaticamente.</p></div></div>
@endsection
