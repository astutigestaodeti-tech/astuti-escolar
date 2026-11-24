@extends('layouts.municipal')

@section('title', 'Transporte por rota - '.$school->name)
@section('body-class', 'pagina-transporte-rotas')

@php
    $parametrosEscola = ['municipio' => $municipality->slug, 'escola' => $school->id];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/transporte-rotas.css') }}?v={{ filemtime(public_path('css/transporte-rotas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $parametrosEscola) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Transporte por rota</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Transporte escolar</span>
            <h1>Transporte por rota</h1>
            <p>Lista para motorista e monitor, com os alunos que usam o transporte nesta escola.</p>
        </div>
    </div>

    <section class="folha transporte-filtros" aria-labelledby="titulo-filtros-transporte">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-filtros-transporte">Preparar a lista</h2><p>Selecione a rota e o turno antes de imprimir.</p></div>
        </div>
        <div class="folha-corpo">
            <form method="GET" action="{{ route('school.transport.routes.index', $parametrosEscola) }}" class="transporte-filtro-form">
                <label for="filtro-rota">Rota
                    <select id="filtro-rota" name="rota">
                        <option value="">Todas as rotas</option>
                        @foreach ($availableRoutes as $route)
                            <option value="{{ $route === '' ? $withoutRoute : $route }}" @selected($selectedRoute === ($route === '' ? $withoutRoute : $route))>{{ $route === '' ? 'Rota não informada (pendência)' : $route }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="filtro-turno">Turno
                    <select id="filtro-turno" name="turno">
                        <option value="">Todos os turnos</option>
                        @foreach ($availableShifts as $shift)
                            <option value="{{ $shift }}" @selected($selectedShift === $shift)>{{ $shift === '' ? 'Turno não informado' : $shift }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="transporte-filtro-acoes">
                    <button class="botao primario" type="submit">Aplicar filtros</button>
                    <a class="botao secundario" href="{{ route('school.transport.routes.index', $parametrosEscola) }}">Limpar filtros</a>
                </div>
            </form>
            <div class="transporte-resumo">
                <strong>{{ $total }} {{ $total === 1 ? 'aluno' : 'alunos' }}</strong> em <strong>{{ $groups->count() }} {{ $groups->count() === 1 ? 'rota' : 'rotas' }}</strong> na lista selecionada.
                <button class="botao secundario" type="button" data-action="print-transport-routes" @disabled($groups->isEmpty())>Imprimir lista</button>
                <a class="botao secundario" href="{{ route('school.census.transport.edit', $parametrosEscola) }}">Transporte no Censo</a>
            </div>
        </div>
    </section>

    @forelse ($groups as $route => $students)
        @php
            $missingRoute = $route === '';
            $turns = $students->map(fn ($entry) => trim((string) ($entry->schoolClass?->shift ?? '')) ?: 'Não informado')->unique()->implode(', ');
        @endphp
        <section class="folha transporte-rota" aria-labelledby="titulo-rota-{{ $loop->iteration }}">
            <div class="folha-cabecalho transporte-rota-cabecalho">
                <div>
                    <p class="transporte-escola">{{ $school->name }}</p>
                    <h2 id="titulo-rota-{{ $loop->iteration }}">{{ $missingRoute ? 'Rota não informada' : $route }}</h2>
                    <p>Turno: {{ $turns }} | Data: {{ $printedOn }}</p>
                    @if ($missingRoute)
                        <p class="transporte-pendencia"><strong>Pendência:</strong> definir a rota antes de entregar ao motorista.</p>
                    @endif
                </div>
                <span class="etiqueta {{ $missingRoute ? 'conferir' : 'neutra' }}">{{ $students->count() }} {{ $students->count() === 1 ? 'aluno' : 'alunos' }}</span>
            </div>
            <div class="folha-corpo transporte-rota-corpo">
                <div class="rolagem">
                    <table class="tabela transporte-tabela">
                        <thead><tr>
                            <th scope="col">Aluno</th>
                            <th scope="col">Turma</th>
                            <th scope="col">Turno</th>
                            <th scope="col">Ponto de embarque</th>
                            <th scope="col">Veículo adaptado</th>
                            <th scope="col">Responsável principal</th>
                            <th scope="col">Telefone</th>
                            <th scope="col">Observação do motorista</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($students as $enrollment)
                                @php
                                    $contact = $enrollment->student?->primaryGuardianLink?->guardian;
                                @endphp
                                <tr>
                                    <td>{{ $enrollment->student?->full_name ?: 'Nome não informado' }}</td>
                                    <td>{{ $enrollment->schoolClass?->name ?: 'Sem turma' }}</td>
                                    <td>{{ $enrollment->schoolClass?->shift ?: 'Não informado' }}</td>
                                    <td>{{ trim((string) ($enrollment->transport_stop ?? '')) ?: 'Não informado' }}</td>
                                    <td>{{ $enrollment->transport_adapted_vehicle ? 'Sim' : 'Não' }}</td>
                                    <td>{{ $contact?->full_name ?: 'Não informado' }}</td>
                                    <td class="numero">{{ $contact?->formatted_phone ?: 'Não informado' }}</td>
                                    <td class="transporte-observacao" aria-label="Em branco para anotação à mão"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="transporte-assinatura">Responsável pela entrega: <span aria-hidden="true"></span></div>
                <p class="transporte-restricao">Uso restrito ao transporte escolar. Não compartilhe nem fotografe.</p>
            </div>
        </section>
    @empty
        <section class="folha" aria-labelledby="titulo-sem-rota">
            <div class="folha-cabecalho"><div><h2 id="titulo-sem-rota">Nenhum aluno encontrado</h2></div></div>
            <div class="folha-corpo"><p>Não há alunos que utilizem transporte com os filtros selecionados.</p></div>
        </section>
    @endforelse

    <script src="{{ asset('js/transporte-rotas.js') }}" defer></script>
@endsection
