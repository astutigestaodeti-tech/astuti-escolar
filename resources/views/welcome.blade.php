<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#13243b">
    <title>Astuti Gestão Escolar</title>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}?v={{ filemtime(public_path('css/astuti.css')) }}">
</head>
<body>
<main class="inicio">
    <div class="inicio-caixa">
        <div class="folha">
            <x-astuti-marca />
            <h1>Olá, {{ auth()->user()->name }}</h1>
            <p>Escolha a rede de ensino em que você vai trabalhar agora.</p>
            @if ($redes->isEmpty())
                    <div class="nota"><x-icon name="info" /><div><strong>Nenhuma rede liberada para a sua conta</strong><p>Peça à Secretaria Municipal de Educação que vincule a sua conta às escolas em que você trabalha.</p></div></div>
            @else
                    <ul class="redes">
                        @foreach ($redes as $rede)
                            <li><a href="{{ route(in_array($rede->slug, $teamOnlySlugs ?? [], true) ? 'team.index' : 'schools.index', ['municipio' => $rede->slug]) }}"><x-icon name="escola" /><span>{{ $rede->legal_name }}</span><x-icon name="seta" /></a></li>
                        @endforeach
                </ul>
            @endif
            <div class="inicio-acoes">
                <a class="botao secundario" href="{{ route('two-factor.settings') }}"><x-icon name="escudo" :size="18" /> Segurança da conta</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="botao secundario" type="submit"><x-icon name="sair" :size="18" /> Sair</button>
                </form>
            </div>
        </div>
    </div>
</main>
</body>
</html>
