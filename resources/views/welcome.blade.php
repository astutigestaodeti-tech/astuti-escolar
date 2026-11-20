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
            @auth
                <h1>Olá, {{ auth()->user()->name }}</h1>
                <p>Escolha a rede de ensino em que você vai trabalhar agora.</p>
                @if ($redes->isEmpty())
                    <div class="nota"><x-icon name="info" /><div><strong>Nenhuma rede liberada para a sua conta</strong><p>Peça à Secretaria Municipal de Educação que vincule a sua conta às escolas em que você trabalha.</p></div></div>
                @else
                    <ul class="redes">
                        @foreach ($redes as $rede)
                            <li><a href="{{ route('schools.index', ['municipio' => $rede->slug]) }}"><x-icon name="escola" /><span>{{ $rede->legal_name }}</span><x-icon name="seta" /></a></li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inicio-acoes">
                    @csrf
                    <button class="botao secundario" type="submit"><x-icon name="sair" :size="18" /> Sair</button>
                </form>
            @else
                <h1>Gestão escolar para redes municipais</h1>
                <p>Matrículas, turmas e documentos das escolas públicas, com menos redigitação e conferência humana em cada etapa.</p>
                <div class="inicio-acoes">
                    <a class="botao" href="{{ route('login') }}">Entrar no sistema</a>
                    @if (app()->environment('local'))
                        <a class="botao secundario" href="{{ route('demo') }}">Ver a demonstração de Monte Sereno</a>
                    @endif
                </div>
            @endauth
        </div>
    </div>
</main>
</body>
</html>
