@extends('layouts.municipal')

@section('title', 'Alerta de faltas')

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Alerta de faltas</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Busca ativa da escola</span>
            <h1>Alerta de faltas</h1>
            <p>Triagem de matrículas ativas em {{ $school->name }}{{ $year ? ' no ano letivo '.$year->year : '' }}. Consulte a ficha antes de entrar em contato.</p>
        </div>
        <span class="etiqueta">{{ $students->count() }} {{ $students->count() === 1 ? 'aluno' : 'alunos' }} em alerta</span>
    </div>

    <div class="nota alerta-faltas-privacidade">
        <x-icon name="escudo" />
        <div>
            <strong>Uso restrito à equipe de gestão escolar</strong>
            <p>Use estas informações somente para apoio à frequência. Faltas justificadas aparecem separadas e não reduzem os percentuais desta triagem, que não substituem o registro oficial de frequência.</p>
        </div>
    </div>

    <section class="folha alerta-faltas-painel" aria-labelledby="titulo-lista-alertas">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-lista-alertas">Alunos que precisam de acompanhamento</h2>
                <p>Prioridade para mais faltas não justificadas seguidas. Dados atualizados conforme as chamadas registradas.</p>
            </div>
        </div>

        <div class="folha-corpo">
        <form method="GET" action="{{ route('school.absences.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}" class="alerta-faltas-filtros">
            <div>
                <label for="alerta-turma">Turma</label>
                <select id="alerta-turma" name="turma">
                    <option value="">Todas as turmas</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass === $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="alerta-motivo">Motivo do alerta</label>
                <select id="alerta-motivo" name="motivo">
                    <option value="">Todos os motivos</option>
                    @foreach ($reasons as $key => $label)
                        <option value="{{ $key }}" @selected($selectedReason === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="botao primario">Filtrar</button>
            <a class="botao secundario" href="{{ route('school.absences.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Limpar filtros</a>
        </form>

        @if ($students->isEmpty())
            <p class="alerta-faltas-vazio">Nenhum aluno em alerta para os filtros selecionados.</p>
        @endif
        </div>
        @if ($students->isNotEmpty())
            <div class="alerta-faltas-rolagem">
                <table class="alerta-faltas-tabela">
                    <thead>
                        <tr>
                            <th scope="col">Aluno e turma</th>
                            <th scope="col">Mês</th>
                            <th scope="col">Ano</th>
                            <th scope="col">Faltas seguidas</th>
                            <th scope="col">Última presença</th>
                            <th scope="col">Justificadas</th>
                            <th scope="col">Contato principal</th>
                            <th scope="col">Motivos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $item)
                            @php
                                $guardian = $item['guardian'];
                                $phone = $guardian?->phone;
                                $digits = $phone ? preg_replace('/\D/', '', $phone) : null;
                                $whatsapp = \App\Models\Tenant\Guardian::whatsappLink($phone);
                            @endphp
                            <tr>
                                <td>
                                    <a class="alerta-faltas-aluno" href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $item['student']->id]) }}">{{ $item['student']->full_name }}</a>
                                    <span class="alerta-faltas-turma">{{ $item['schoolClass']->name }}</span>
                                </td>
                                <td>{{ $item['month_percentage'] !== null ? $item['month_percentage'].'%' : 'Sem chamada' }}</td>
                                <td>{{ $item['year_percentage'] !== null ? $item['year_percentage'].'%' : 'Sem chamada' }}</td>
                                <td>{{ $item['consecutive_absences'] }} {{ $item['consecutive_absences'] === 1 ? 'seguida' : 'seguidas' }}</td>
                                <td>{{ $item['last_presence'] ? \Illuminate\Support\Carbon::parse($item['last_presence'])->format('d/m/Y') : 'Não registrada' }}</td>
                                <td>{{ $item['justified_year'] }} {{ $item['justified_year'] === 1 ? 'justificada' : 'justificadas' }}</td>
                                <td>
                                    @if ($guardian)
                                        <span class="alerta-faltas-contato">{{ $guardian->full_name }}</span>
                                        @if ($digits)
                                            <a href="tel:+55{{ $digits }}">{{ $guardian->formatted_phone }}</a>
                                        @endif
                                        @if ($whatsapp)
                                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" aria-label="Abrir WhatsApp de {{ $guardian->full_name }}">WhatsApp</a>
                                        @endif
                                    @else
                                        <span class="alerta-faltas-turma">Não informado</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="alerta-faltas-motivos">
                                        @foreach ($item['reasons'] as $reason)
                                            <span class="alerta-faltas-etiqueta">{{ $reasons[$reason] }}</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
