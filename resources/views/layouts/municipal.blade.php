<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#13243b">
    <title>@yield('title', 'Escolas') | Astuti Gestão Escolar</title>
    <link rel="preload" href="{{ asset('fonts/atkinson-hyperlegible-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/source-serif-4-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}?v={{ filemtime(public_path('css/astuti.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/documento-oficial.css') }}?v={{ filemtime(public_path('css/documento-oficial.css')) }}">
    @stack('head')
</head>
<body class="@yield('body-class')">
@php
    // Brasão próprio do município, quando existir em public/images/brasao-{slug}.webp.
    $brasao = 'images/brasao-'.$municipality->slug.'.webp';
    $temBrasao = preg_match('/\A[a-z0-9-]+\z/', (string) $municipality->slug) === 1 && is_file(public_path($brasao));
    $nomeCurto = trim(preg_replace('/^Prefeitura Municipal de\s+/iu', '', (string) $municipality->legal_name));
    $partes = preg_split('/\s+/u', $nomeCurto) ?: [];
    $monograma = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[count($partes) - 1] ?? '', 0, 1));
    $usuario = auth()->user();
    $nomesPapeis = ['municipal_manager' => 'Gestão municipal', 'school_director' => 'Direção escolar', 'school_secretary' => 'Secretaria escolar', 'teacher' => 'Professor(a)', 'psicologo' => 'Psicologia', 'assistente_social' => 'Serviço Social', 'pedagogo_equipe' => 'Pedagogia da equipe'];
    $papelUsuario = $usuario
        ? \App\Models\Platform\Membership::query()
            ->where('user_id', $usuario->id)->where('municipality_id', $municipality->id)->where('status', 'active')
            ->pluck('role')->unique()->sortBy(fn ($papel) => array_search($papel, array_keys($nomesPapeis), true))
            ->map(fn ($papel) => $nomesPapeis[$papel] ?? null)->filter()->first()
        : null;
    $iniciaisUsuario = mb_strtoupper(collect(preg_split('/\s+/u', trim((string) ($usuario?->name ?? ''))) ?: [])->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    $painelAtivo = request()->routeIs('network.panel.*');
    $relatorioSecretariaAtivo = request()->routeIs('secretariat.monthly.*');
    $dadosPrefeituraAtivo = request()->routeIs('institution.*');
    $equipeAtiva = request()->routeIs('team.*');
    $censoAtivo = request()->routeIs('census.readiness.*');
    $integralAtivo = request()->routeIs('integral.*');
    $alfabetizacaoAtiva = request()->routeIs('literacy.*') || request()->routeIs('school.literacy.*');
    $buscaAtivaAtivo = request()->routeIs('active-search.*') || request()->routeIs('school.active-search.*');
    $alimentacaoAtiva = request()->routeIs('meals.*') || request()->routeIs('school.meals.*');
    $equidadeAtiva = request()->routeIs('equity.vaar.*');
    $listaEsperaAtiva = request()->routeIs('waitlist.*');
    $escolasAtivo = request()->routeIs('schools.*') || request()->routeIs('school.classes.*') || request()->routeIs('school.students.*') || request()->routeIs('school.absences.*') || request()->routeIs('school.pendencies.*') || request()->routeIs('school.transport.*');
    $parametrosMunicipio = ['municipio' => $municipality->slug];
    $parametrosEscola = [...$parametrosMunicipio, 'escola' => request()->route('escola')];
    $voltarFallback = match (request()->route()?->getName()) {
        'schools.index' => route('home'),
        'waitlist.index' => route('schools.index', $parametrosMunicipio),
        'waitlist.create', 'waitlist.show' => route('waitlist.index', $parametrosMunicipio),
        'waitlist.enroll.form' => route('waitlist.show', [...$parametrosMunicipio, 'pedido' => request()->route('pedido')]),
        'network.panel.index' => route('schools.index', $parametrosMunicipio),
        'team.index' => route('home'),
        'team.show', 'team.document' => route('team.index', $parametrosMunicipio),
        'team.indicators' => route('network.panel.index', $parametrosMunicipio),
        'secretariat.monthly.index', 'institution.edit' => route('network.panel.index', $parametrosMunicipio),
        'literacy.network', 'literacy.settings' => route('network.panel.index', $parametrosMunicipio),
        'school.literacy.panel' => route('schools.show', $parametrosEscola),
        'school.literacy.classroom', 'school.literacy.save' => route('school.classes.show', [...$parametrosEscola, 'turma' => request()->route('turma')]),
        'active-search.network', 'active-search.parameters' => route('network.panel.index', $parametrosMunicipio),
        'school.active-search.index', 'school.active-search.detail', 'school.active-search.save', 'school.active-search.contact', 'school.active-search.issue', 'school.active-search.document', 'school.active-search.revoke' => route('schools.show', $parametrosEscola),
        'school.active-search.class' => route('school.classes.show', [...$parametrosEscola, 'turma' => request()->route('turma')]),
        'census.readiness.index', 'equity.vaar.index', 'integral.index', 'meals.menu', 'meals.report' => route('network.panel.index', $parametrosMunicipio),
        'meals.menu.save', 'meals.menu.copy' => route('meals.menu', $parametrosMunicipio),
        'school.meals.index', 'school.meals.save', 'school.meals.menu', 'school.meals.kitchen', 'school.meals.allergies', 'school.meals.report' => route('schools.show', $parametrosEscola),
        'integral.school', 'integral.hours.update' => route('schools.show', $parametrosEscola),
        'integral.target', 'integral.revenue', 'integral.expense.store', 'integral.expense.evidence' => route('integral.index', $parametrosMunicipio),
        'equity.vaar.school' => route('schools.show', $parametrosEscola),
        'equity.vaar.condition', 'equity.vaar.evidence' => route('equity.vaar.index', $parametrosMunicipio),
        'academic.components.index', 'academic.periods.index' => route('schools.index', $parametrosMunicipio),
        'schools.show' => route('schools.index', $parametrosMunicipio),
        'school.staff.index', 'school.staff.create', 'school.staff.store', 'school.staff.edit', 'school.staff.update', 'school.staff.remove.form', 'school.staff.destroy',
        'school.classes.index', 'school.students.index', 'school.enrollments.index', 'school.absences.index', 'school.pendencies.index', 'school.transport.routes.index' => route('schools.show', $parametrosEscola),
        'school.classes.show', 'school.classes.create', 'school.classes.store', 'school.classes.edit', 'school.classes.update'
            => route('school.classes.index', $parametrosEscola),
        'school.classes.teachers.store', 'school.classes.teachers.end',
        'school.classes.grades.index', 'school.classes.grades.store', 'school.classes.grades.correct',
        'school.classes.renewal.index', 'school.classes.renewal.store',
        'school.classes.year-end.index', 'school.classes.year-end.minutes',
        'school.classes.year-end.confirm', 'school.classes.year-end.close', 'school.classes.year-end.reopen',
        'school.classes.attendance.index', 'school.classes.attendance.store', 'school.classes.attendance.correct', 'school.classes.attendance.monthly'
            => route('school.classes.show', [...$parametrosEscola, 'turma' => request()->route('turma')]),
        'school.students.show', 'school.students.create' => route('school.students.index', $parametrosEscola),
        'school.students.declarations.create', 'school.students.declarations.store', 'school.students.declarations.show', 'school.students.declarations.revoke'
            => route('school.enrollments.index', $parametrosEscola),
        'school.students.edit', 'school.students.report-card.show',
        'school.students.guardians.unlink.form', 'school.students.guardians.unlink',
        'school.students.pickups.create', 'school.students.pickups.store', 'school.students.pickups.edit', 'school.students.pickups.update',
        'school.students.pickups.remove.form', 'school.students.pickups.remove',
        'school.enrollments.transfer.form', 'school.enrollments.end.form', 'school.enrollments.delete-registration.form'
            => route('school.students.show', [...$parametrosEscola, 'aluno' => request()->route('aluno')]),
        'school.imports.students.index', 'school.imports.transport.index' => route('schools.show', $parametrosEscola),
        'school.imports.students.preview' => route('school.imports.students.index', $parametrosEscola),
        'school.imports.transport.preview' => route('school.imports.transport.index', $parametrosEscola),
        default => route('schools.index', $parametrosMunicipio),
    };
@endphp
<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<div class="app">
    <aside class="lateral" aria-label="Menu principal">
        <div class="municipio">
            @if ($temBrasao)
                <img class="municipio-brasao" src="{{ asset($brasao) }}" alt="Brasão de {{ $nomeCurto }}" width="64" height="62">
            @else
                <span class="municipio-monograma" aria-hidden="true">{{ $monograma }}</span>
            @endif
            <div>
                <div class="municipio-nome">{{ $municipality->legal_name }}</div>
                <div class="municipio-orgao">Secretaria Municipal de Educação</div>
            </div>
        </div>

        <nav class="menu" aria-label="Módulos">
            <span class="menu-titulo">Gestão escolar</span>
            @can('viewNetworkPanel', [\App\Models\Tenant\School::class, $municipality])
                <a class="menu-item {{ $painelAtivo ? 'ativo' : '' }}" href="{{ route('network.panel.index', $parametrosMunicipio) }}" @if ($painelAtivo) aria-current="page" @endif>
                    <x-icon name="indicadores" /><span>Painel da rede</span>
                </a>
                <a class="menu-item {{ $censoAtivo ? 'ativo' : '' }}" href="{{ route('census.readiness.index', $parametrosMunicipio) }}" @if ($censoAtivo) aria-current="page" @endif>
                    <x-icon name="conferir" /><span>Conferência do Censo</span>
                </a>
                <a class="menu-item {{ request()->routeIs('pedagogical.*') ? 'ativo' : '' }}" href="{{ route('pedagogical.network', $parametrosMunicipio) }}" @if (request()->routeIs('pedagogical.*')) aria-current="page" @endif>
                    <x-icon name="indicadores" /><span>Gestão pedagógica</span>
                </a>
                <a class="menu-item {{ $equidadeAtiva ? 'ativo' : '' }}" href="{{ route('equity.vaar.index', $parametrosMunicipio) }}" @if ($equidadeAtiva) aria-current="page" @endif>
                    <x-icon name="indicadores" /><span>Equidade e VAAR</span>
                </a>
                <a class="menu-item {{ $integralAtivo ? 'ativo' : '' }}" href="{{ route('integral.index', $parametrosMunicipio) }}" @if ($integralAtivo) aria-current="page" @endif>
                    <x-icon name="turmas" /><span>Tempo integral</span>
                </a>
                <a class="menu-item {{ $relatorioSecretariaAtivo ? 'ativo' : '' }}" href="{{ route('secretariat.monthly.index', $parametrosMunicipio) }}" @if ($relatorioSecretariaAtivo) aria-current="page" @endif>
                    <x-icon name="indicadores" /><span>Relatório mensal</span>
                </a>
                <a class="menu-item {{ $alfabetizacaoAtiva ? 'ativo' : '' }}" href="{{ route('literacy.network', $parametrosMunicipio) }}" @if ($alfabetizacaoAtiva) aria-current="page" @endif>
                    <x-icon name="indicadores" /><span>Alfabetização</span>
                </a>
                <a class="menu-item {{ $buscaAtivaAtivo ? 'ativo' : '' }}" href="{{ route('active-search.network', $parametrosMunicipio) }}" @if ($buscaAtivaAtivo) aria-current="page" @endif>
                    <x-icon name="indicadores" /><span>Busca ativa</span>
                </a>
                <a class="menu-item {{ $alimentacaoAtiva ? 'ativo' : '' }}" href="{{ route('meals.menu', $parametrosMunicipio) }}" @if ($alimentacaoAtiva) aria-current="page" @endif>
                    <x-icon name="turmas" /><span>Alimentação escolar</span>
                </a>
                <a class="menu-item {{ $equipeAtiva ? 'ativo' : '' }}" href="{{ route('team.indicators', $parametrosMunicipio) }}" @if ($equipeAtiva) aria-current="page" @endif>
                    <x-icon name="indicadores" /><span>Equipe multiprofissional</span>
                </a>
                <a class="menu-item {{ $dadosPrefeituraAtivo ? 'ativo' : '' }}" href="{{ route('institution.edit', $parametrosMunicipio) }}" @if ($dadosPrefeituraAtivo) aria-current="page" @endif>
                    <x-icon name="escola" /><span>Dados da Prefeitura</span>
                </a>
            @endcan
            @if ($usuario && app(\App\Multiprofessional\TeamAccess::class)->isTeam($usuario, $municipality))
                <a class="menu-item {{ $equipeAtiva ? 'ativo' : '' }}" href="{{ route('team.index', $parametrosMunicipio) }}" @if ($equipeAtiva) aria-current="page" @endif>
                    <x-icon name="alunos" /><span>Equipe multiprofissional</span>
                </a>
            @endif
            @can('viewAny', [\App\Models\Tenant\School::class, $municipality])
            <a class="menu-item {{ $escolasAtivo ? 'ativo' : '' }}" href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}" @if ($escolasAtivo) aria-current="page" @endif>
                <x-icon name="escola" />
                <span>Escolas</span>
            </a>
            @endcan
            @can('viewWaitlist', [\App\Models\Tenant\School::class, $municipality])
                <a class="menu-item {{ $listaEsperaAtiva ? 'ativo' : '' }}" href="{{ route('waitlist.index', $parametrosMunicipio) }}" @if ($listaEsperaAtiva) aria-current="page" @endif>
                    <x-icon name="alunos" /><span>Lista de espera</span>
                </a>
            @endcan
        </nav>

        <div class="lateral-rodape">
            <x-astuti-marca />
            <p>Acesso restrito às unidades autorizadas para a sua conta.</p>
        </div>
    </aside>

    <div class="area">
        <header class="topo">
            <nav class="trilha" aria-label="Você está em">
                @hasSection('trilha')
                    @yield('trilha')
                @else
                    <span aria-current="page">{{ $nomeCurto }}</span>
                @endif
            </nav>
            <div class="topo-acoes">
                @if ($usuario)
                    <span class="usuario">
                        <span class="usuario-iniciais" aria-hidden="true">{{ $iniciaisUsuario }}</span>
                        <span class="usuario-texto">{{ $usuario->name }}<small>{{ $papelUsuario ? $papelUsuario.', '.$nomeCurto : $nomeCurto }}</small></span>
                    </span>
                @endif
                <a class="botao-sair" href="{{ route('two-factor.settings') }}" aria-label="Segurança da conta"><x-icon name="escudo" :size="17" /><span>Segurança da conta</span></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="botao-sair" type="submit"><x-icon name="sair" :size="17" /><span>Sair</span></button>
                </form>
            </div>
        </header>

        <main class="conteudo" id="conteudo">
            @if (app()->environment('local'))
                <div class="aviso-demo" role="note"><x-icon name="alerta" /><span><strong>Ambiente de desenvolvimento.</strong> Use somente dados fictícios.</span></div>
            @endif

            @unless ($ocultarVoltar ?? false)
                <x-voltar-anterior :fallback="$voltarFallback" :municipio="$municipality->slug" />
            @endunless

            {{-- Qualquer tela impressa sai com o timbre oficial (brasão, Prefeitura e Secretaria).
                 Documentos com timbre próprio declaram @section('timbre-proprio'). --}}
            @unless (View::hasSection('timbre-proprio'))
                <div class="so-impressao timbre-impressao"><x-documento.timbre :municipality="$municipality" /></div>
            @endunless

            @yield('content')

            @unless (View::hasSection('timbre-proprio'))
                <div class="so-impressao timbre-impressao-rodape"><x-documento.rodape :municipality="$municipality" :aviso="false" /></div>
            @endunless

            <footer class="rodape"><span>© {{ date('Y') }} Astuti Gestão Escolar</span><span>As permissões são conferidas a cada acesso.</span></footer>
            @php
                $versao = \App\Support\SystemInfo::version();
            @endphp
            <p class="rodape-versao">
                @if ($versao['commit'])
                    Versão {{ $versao['commit'] }}@if ($versao['branch']) ({{ $versao['branch'] }})@endif, atualizada em {{ $versao['updated_at']->format('d/m/Y') }} às {{ $versao['updated_at']->format('H:i') }}
                @else
                    Versão não identificada
                @endif
                @if (app()->environment('local'))
                    @foreach (\App\Support\SystemInfo::databases() as $banco)
                        <span>{{ $banco }}</span>
                    @endforeach
                @endif
            </p>
        </main>
    </div>
</div>
</body>
</html>
