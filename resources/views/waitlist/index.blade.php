@extends('layouts.municipal')

@section('title', 'Lista de espera de vagas')
@section('body-class', 'pagina-lista-espera')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/lista-espera.css') }}?v={{ filemtime(public_path('css/lista-espera.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Lista de espera</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Educação infantil</span>
            <h1>Lista de espera de vagas</h1>
            <p>Fila por etapa e escola de preferência. A posição considera os critérios justificados de prioridade e, depois, o horário do protocolo.</p>
        </div>
        @if ($canCreate)
            <a class="botao" href="{{ route('waitlist.create', ['municipio' => $municipality->slug]) }}">Novo pedido de vaga</a>
        @endif
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif

    <section class="folha" aria-labelledby="titulo-fila-espera">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-fila-espera">Fila das escolas</h2>
                <p>Somente unidades de educação infantil autorizadas para a sua conta. Sem consulta pública nesta etapa.</p>
            </div>
        </div>
        <div class="folha-corpo">
            <form method="GET" class="lista-espera-filtros" action="{{ route('waitlist.index', ['municipio' => $municipality->slug]) }}">
                <div>
                    <label for="filtro-escola">Escola</label>
                    <select name="escola" id="filtro-escola">
                        <option value="">Todas as escolas autorizadas</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected(($filters['escola'] ?? '') === $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtro-etapa">Etapa</label>
                    <select name="etapa" id="filtro-etapa">
                        <option value="">Todas as etapas</option>
                        @foreach ($stages as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['etapa'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtro-situacao">Situação</label>
                    <select name="situacao" id="filtro-situacao">
                        <option value="abertos" @selected($status === 'abertos')>Aguardando e convocados</option>
                        <option value="todos" @selected($status === 'todos')>Todas, inclusive encerradas</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtro-ano">Ano de ingresso</label>
                    <input type="number" id="filtro-ano" name="ano" min="2020" max="2100" value="{{ $year }}">
                </div>
                <button class="botao secundario" type="submit">Filtrar fila</button>
            </form>
            <p class="texto-ajuda lista-espera-ajuda">Posição 1 é a primeira da fila para aquela escola, etapa e ano. Mais critérios comprovados têm precedência. No empate, vale o pedido mais antigo. Convocados continuam na fila até serem atendidos ou desistirem.</p>
        </div>

        <form class="busca" role="search" method="GET" action="{{ route('waitlist.index', ['municipio' => $municipality->slug]) }}">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="busca-lista-espera">Buscar por criança, responsável ou protocolo</label>
                <input type="search" id="busca-lista-espera" name="busca" maxlength="120" placeholder="Criança, responsável ou protocolo" autocomplete="off" value="{{ $search }}">
            </div>
            @foreach (['escola', 'etapa', 'situacao'] as $key)
                @if (filled($filters[$key] ?? null))<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif
            @endforeach
            <input type="hidden" name="ano" value="{{ $year }}">
            <button class="botao" type="submit"><x-icon name="busca" :size="16" /> Pesquisar</button>
        </form>

        @if ($items->isEmpty())
            <div class="folha-corpo"><p>Nenhum pedido encontrado para os filtros selecionados.</p></div>
        @else
            <div class="rolagem lista-espera-rolagem">
                <table class="tabela lista-espera-tabela">
                    <thead><tr>
                        <th scope="col">Posição</th>
                        <th scope="col">Protocolo</th>
                        <th scope="col">Criança</th>
                        <th scope="col">Responsável</th>
                        <th scope="col">Escola</th>
                        <th scope="col">Etapa</th>
                        <th scope="col">Prioridades</th>
                        <th scope="col">Pedido</th>
                        <th scope="col">Situação</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td class="numero"><strong>{{ $item->queue_position ?? '-' }}</strong></td>
                                <td class="numero"><a href="{{ route('waitlist.show', ['municipio' => $municipality->slug, 'pedido' => $item->id]) }}">{{ $item->protocol }}</a></td>
                                <td>{{ $item->child_name }}</td>
                                <td>{{ $item->guardian_name }}</td>
                                <td>{{ $item->school_name }} <small class="lista-ra">Preferência {{ $item->preference_order }}</small></td>
                                <td>{{ $stages[$item->stage] ?? $item->stage }}</td>
                                <td class="numero">{{ $item->priority_count }}</td>
                                <td class="numero">{{ \Illuminate\Support\Carbon::parse($item->requested_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $statuses[$item->status] ?? $item->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())
                <div class="folha-corpo">{{ $items->links() }}</div>
            @endif
        @endif
    </section>
    <div class="nota"><x-icon name="escudo" /><div><strong>Dados protegidos</strong><p>A justificativa de prioridade fica restrita à gestão autorizada e não aparece na tabela da fila, nas exportações ou na auditoria.</p></div></div>
@endsection
