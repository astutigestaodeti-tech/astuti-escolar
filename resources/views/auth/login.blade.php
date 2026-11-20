<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#13243b">
    <title>Acesso seguro | Astuti Gestão Escolar</title>
    <link rel="preload" href="{{ asset('fonts/atkinson-hyperlegible-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/source-serif-4-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}?v={{ filemtime(public_path('css/astuti.css')) }}">
</head>
<body>
<div class="acesso">
    <section class="acesso-lado" aria-label="Astuti Gestão Escolar">
        <x-astuti-marca />
        <div>
            <p class="acesso-frase">A secretaria da escola trabalha melhor quando não precisa digitar tudo duas vezes.</p>
            <p class="acesso-apoio">Gestão escolar para redes municipais de ensino: matrículas, turmas e documentos, com conferência humana em cada etapa.</p>
        </div>
        <ul class="acesso-garantias">
            <li><x-icon name="escudo" :size="18" /><span>Cada município tem a sua própria base de dados.</span></li>
            <li><x-icon name="conferir" :size="18" /><span>A importação de documentos com conferência humana está em desenvolvimento.</span></li>
            <li><x-icon name="relogio" :size="18" /><span>Todo acesso é conferido conforme as permissões da sua conta.</span></li>
        </ul>
    </section>

    <main class="acesso-form">
        <div class="acesso-caixa">
            <x-astuti-marca class="acesso-marca-movel" />
            <h1>Entrar no sistema</h1>
            <p>Use a conta institucional fornecida pela Secretaria de Educação.</p>

            @if ($errors->any())
                <div class="erro" role="alert"><x-icon name="alerta" :size="18" /><span>Não foi possível acessar. Confira o e-mail e a senha e tente novamente.</span></div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <label class="rotulo" for="email">E-mail institucional</label>
                <input class="entrada" id="email" name="email" type="email" autocomplete="username" required maxlength="255" value="{{ old('email') }}" autofocus>
                <label class="rotulo" for="password">Senha</label>
                <input class="entrada" id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="botao" type="submit">Acessar</button>
            </form>

            <p class="acesso-rodape">Acesso restrito a servidores autorizados. Esqueceu a senha? Procure a Secretaria Municipal de Educação.</p>
        </div>
    </main>
</div>
</body>
</html>
