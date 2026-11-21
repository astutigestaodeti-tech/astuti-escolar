@extends('layouts.municipal')

@section('title', 'Conferir importação - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Conferir importação</span>
@endsection

@section('content')
    <a class="voltar" href="{{ route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Voltar à tela anterior</a>
    <div class="folha-titulo">
        <div>
            <span class="contexto">{{ $school->name }} - Ano letivo {{ $year->year }}</span>
            <h1>Conferir antes de importar</h1>
            <p>{{ count($rows) }} alunos identificados. Confira cada repetição de nome antes de gravar.</p>
        </div>
    </div>

    <div class="nota"><x-icon name="info" /><div><strong>Nada foi gravado ainda.</strong><p>As pendências não são preenchidas automaticamente. A conferência vale por uma hora e só pode ser confirmada pela mesma conta.</p></div></div>

    <form method="post" action="{{ route('school.imports.students.confirm', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
        @csrf
        <input type="hidden" name="lote" value="{{ $token }}">
        <section class="folha" aria-labelledby="linhas-importacao">
            <div class="folha-cabecalho"><div><h2 id="linhas-importacao">Alunos identificados</h2><p>Uma linha por aluno. As repetidas foram agrupadas para sua confirmação.</p></div></div>
            <div style="overflow-x:auto">
                <table style="width:100%">
                    <thead><tr><th>Aluno</th><th>Nascimento</th><th>RA</th><th>Turma</th><th>Conferência</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr>
                                <td><strong>{{ $row['name'] }}</strong><small style="display:block">{{ implode('; ', $row['sources']) }}</small></td>
                                <td>{{ $row['birth_date'] ? \Carbon\Carbon::parse($row['birth_date'])->format('d/m/Y') : 'Pendente' }}</td>
                                <td>{{ $row['ra'] ?? 'Pendente' }}</td>
                                <td>{{ $row['class_name'] ?? 'Conferir turma' }}</td>
                                <td>
                                    @foreach ($row['confirmation'] as $issue)
                                        <p role="alert">{{ $issue['message'] }}</p>
                                        @if ($issue['field'] === 'duplicidade')
                                            <label><input type="checkbox" name="confirmados[]" value="{{ $index }}"> Confirmo que são registros da mesma pessoa.</label>
                                        @else
                                            <strong>Corrija a planilha e envie novamente.</strong>
                                        @endif
                                    @endforeach
                                    @if (count($row['pending']) > 0)
                                        <small>{{ count($row['pending']) }} informação(ões) pendente(s).</small>
                                    @elseif (count($row['confirmation']) === 0)
                                        <span>Pronto</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:18px">
            <button class="botao" type="submit">Confirmar e gravar {{ count($rows) }} alunos</button>
            <a class="botao secundario" href="{{ route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Cancelar e voltar</a>
        </div>
    </form>

    <div class="nota" style="margin-top:20px"><x-icon name="info" /><div><strong>LGPD e auditoria</strong><p>Somente nome, nascimento, RA e turma serão cadastrados. Nenhuma data de matrícula será inventada. A operação inteira será desfeita se houver conflito ou falha de auditoria.</p></div></div>
@endsection
