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
</head>
<body>
@php
    // Brasão próprio do município, quando existir em public/images/brasao-{slug}.webp.
    $brasao = 'images/brasao-'.$municipality->slug.'.webp';
    $temBrasao = preg_match('/\A[a-z0-9-]+\z/', (string) $municipality->slug) === 1 && is_file(public_path($brasao));
    $nomeCurto = trim(preg_replace('/^Prefeitura Municipal de\s+/iu', '', (string) $municipality->legal_name));
    $partes = preg_split('/\s+/u', $nomeCurto) ?: [];
    $monograma = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[count($partes) - 1] ?? '', 0, 1));
    $usuario = auth()->user();
    $nomesPapeis = ['municipal_manager' => 'Gestão municipal', 'school_director' => 'Direção escolar', 'school_secretary' => 'Secretaria escolar', 'teacher' => 'Professor(a)'];
    $papelUsuario = $usuario
        ? \App\Models\Platform\Membership::query()
            ->where('user_id', $usuario->id)->where('municipality_id', $municipality->id)->where('status', 'active')
            ->pluck('role')->unique()->sortBy(fn ($papel) => array_search($papel, array_keys($nomesPapeis), true))
            ->map(fn ($papel) => $nomesPapeis[$papel] ?? null)->filter()->first()
        : null;
    $iniciaisUsuario = mb_strtoupper(collect(preg_split('/\s+/u', trim((string) ($usuario?->name ?? ''))) ?: [])->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    $escolasAtivo = request()->routeIs('schools.*') || request()->routeIs('school.classes.*') || request()->routeIs('school.students.*');
    $parametrosMunicipio = ['municipio' => $municipality->slug];
    $parametrosEscola = [...$parametrosMunicipio, 'escola' => request()->route('escola')];
    $voltarFallback = match (request()->route()?->getName()) {
        'schools.index' => route('home'),
        'schools.show' => route('schools.index', $parametrosMunicipio),
        'school.classes.index', 'school.students.index', 'school.enrollments.index' => route('schools.show', $parametrosEscola),
        'school.students.show', 'school.students.create' => route('school.students.index', $parametrosEscola),
        'school.students.edit',
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
            <a class="menu-item {{ $escolasAtivo ? 'ativo' : '' }}" href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}" @if ($escolasAtivo) aria-current="page" @endif>
                <x-icon name="escola" />
                <span>Escolas</span>
            </a>
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

            @yield('content')

            <footer class="rodape"><span>© {{ date('Y') }} Astuti Gestão Escolar</span><span>As permissões são conferidas a cada acesso.</span></footer>
        </main>
    </div>
</div>
</body>
</html>
