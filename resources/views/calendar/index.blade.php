@extends('layouts.municipal')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $monthParams = array_merge($params, ['ano' => $year?->id]);
    $weekdays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
@endphp

@section('title', 'Calendário escolar')

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $params) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Calendário escolar</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Planejamento letivo</span>
            <h1>Calendário escolar</h1>
            <p>Feriados, recessos, dias não letivos, reposições e eventos da rede e da escola.</p>
        </div>
        <a class="botao secundario" href="{{ route('schools.show', $params) }}">Voltar à escola</a>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif

    <section class="folha" aria-labelledby="calendario-resumo">
        <div class="folha-cabecalho"><div><h2 id="calendario-resumo">Ano letivo e dias previstos</h2><p>Regra: segunda a sexta, menos interrupções registradas, mais dias extras.</p></div></div>
        <div class="folha-corpo">
            @if ($years->isEmpty())
                <p class="calendario-aviso">Ainda não há ano letivo cadastrado nesta rede.</p>
            @else
                <form class="calendario-filtros" method="GET" action="{{ route('school.calendar.index', $params) }}">
                    <div class="formulario-campo">
                        <label class="rotulo" for="calendario-ano">Ano letivo</label>
                        <select id="calendario-ano" name="ano">
                            @foreach ($years as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $year->id)>{{ $option->year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="calendario-mes">Mês</label>
                        <input id="calendario-mes" name="mes" type="month" value="{{ $month->format('Y-m') }}">
                    </div>
                    <button class="botao secundario" type="submit">Consultar</button>
                </form>
                <p class="calendario-total"><strong>{{ $plannedDays }} dias letivos previstos</strong> entre {{ $year->start_date?->format('d/m/Y') }} e {{ $year->end_date?->format('d/m/Y') }}.</p>
                @if ($plannedDays < 200)
                    <p class="calendario-aviso" role="status"><strong>Atenção: abaixo de 200 dias letivos previstos.</strong> Confira o calendário e as reposições, conforme o mínimo da LDB.</p>
                @endif
                <p class="texto-ajuda">Feriados e recessos devem ser lançados no calendário. Eventos não alteram a contagem. Se houver interrupção e dia extra na mesma data, a interrupção prevalece.</p>
            @endif
        </div>
    </section>

    @if ($year)
        <section class="folha" aria-labelledby="calendario-mes-titulo">
            <div class="folha-cabecalho">
                <div><h2 id="calendario-mes-titulo">{{ ucfirst($month->locale('pt_BR')->translatedFormat('F Y')) }}</h2><p>Azul identifica os dias letivos extras; âmbar, dias sem aula.</p></div>
            </div>
            <div class="folha-corpo">
                <nav class="calendario-navegacao" aria-label="Navegação mensal">
                    <a class="botao secundario" href="{{ route('school.calendar.index', array_merge($monthParams, ['mes' => $month->copy()->subMonth()->format('Y-m')])) }}">Mês anterior</a>
                    <a class="botao secundario" href="{{ route('school.calendar.index', array_merge($monthParams, ['mes' => $month->copy()->addMonth()->format('Y-m')])) }}">Próximo mês</a>
                </nav>
                <div class="calendario-grade" aria-label="Dias de {{ $month->format('m/Y') }}">
                    @foreach ($weekdays as $weekday)
                        <span class="calendario-semana">{{ $weekday }}</span>
                    @endforeach
                    @foreach ($days as $day)
                        @php
                            $records = $byDate->get($day->toDateString(), collect());
                            $closed = $records->whereIn('kind', ['holiday', 'recess', 'non_school_day'])->isNotEmpty();
                            $extra = $records->contains('kind', 'extra_school_day');
                        @endphp
                        <div class="calendario-dia {{ $day->month !== $month->month ? 'fora-mes' : '' }} {{ $closed ? 'dia-sem-aula' : ($extra ? 'dia-extra' : '') }}" aria-label="{{ $day->format('d/m/Y') }}">
                            <strong>{{ $day->format('d') }}</strong>
                            @foreach ($records as $record)
                                <span class="calendario-evento">{{ \App\Models\Tenant\SchoolCalendarDay::KINDS[$record->kind] }}: {{ $record->description }}</span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($canManage)
            <section class="folha" aria-labelledby="calendario-novo">
                <div class="folha-cabecalho"><div><h2 id="calendario-novo">Adicionar data</h2><p>O lançamento da rede aparece em todas as escolas; o lançamento local só nesta escola.</p></div></div>
                <div class="folha-corpo">
                    @if ($errors->any())
                        <div class="nota" role="alert"><strong>Confira os campos.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <form class="calendario-formulario" method="POST" action="{{ route('school.calendar.store', $params) }}">
                        @csrf
                        <input type="hidden" name="school_year_id" value="{{ $year->id }}">
                        <div class="formulario-campo">
                            <label class="rotulo" for="calendario-data">Data</label>
                            <input id="calendario-data" type="date" name="date" required min="{{ $year->start_date?->toDateString() }}" max="{{ $year->end_date?->toDateString() }}" value="{{ old('date', $month->format('Y-m-d')) }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="calendario-tipo">Tipo</label>
                            <select id="calendario-tipo" name="kind" required>
                                @foreach (\App\Models\Tenant\SchoolCalendarDay::KINDS as $code => $label)
                                    <option value="{{ $code }}" @selected(old('kind') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="calendario-escopo">Abrangência</label>
                            <select id="calendario-escopo" name="scope" required>
                                <option value="school">Somente esta escola</option>
                                @if ($isMunicipalManager)
                                    <option value="municipality" @selected(old('scope') === 'municipality')>Toda a rede municipal</option>
                                @endif
                            </select>
                        </div>
                        <div class="formulario-campo calendario-descricao">
                            <label class="rotulo" for="calendario-descricao">Descrição</label>
                            <input id="calendario-descricao" type="text" name="description" maxlength="120" required value="{{ old('description') }}" placeholder="Ex.: Recesso escolar">
                            <small class="texto-ajuda">Não inclua nomes de alunos ou dados pessoais na descrição.</small>
                        </div>
                        <button type="submit" class="botao primario">Adicionar data</button>
                    </form>
                </div>
            </section>
        @endif

        <section class="folha" aria-labelledby="calendario-lista">
            <div class="folha-cabecalho"><div><h2 id="calendario-lista">Datas registradas</h2><p>Lançamentos da rede e da unidade no ano letivo {{ $year->year }}.</p></div></div>
            <div class="folha-corpo">
                @forelse ($entries as $entry)
                    <article class="calendario-linha">
                        <div>
                            <strong>{{ $entry->date->format('d/m/Y') }} · {{ \App\Models\Tenant\SchoolCalendarDay::KINDS[$entry->kind] }}</strong>
                            <p>{{ $entry->description }}</p>
                            <small>{{ $entry->school_id === null ? 'Toda a rede' : 'Esta escola' }}</small>
                        </div>
                        @if ($canManage && ($entry->school_id !== null || $isMunicipalManager))
                            <form method="POST" action="{{ route('school.calendar.destroy', array_merge($params, ['dia' => $entry->id])) }}">
                                @csrf
                                @method('DELETE')
                                <button class="botao secundario" type="submit" aria-label="Remover {{ $entry->description }} de {{ $entry->date->format('d/m/Y') }}">Remover</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="calendario-aviso">Nenhuma data especial registrada neste ano letivo.</p>
                @endforelse
            </div>
        </section>
    @endif
@endsection
