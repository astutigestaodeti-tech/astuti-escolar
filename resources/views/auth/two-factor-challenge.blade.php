<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13243b">
    <title>Confirmar acesso | Astuti Gestão Escolar</title>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}?v={{ filemtime(public_path('css/astuti.css')) }}">
</head>
<body>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<main class="inicio" id="conteudo">
    <div class="inicio-caixa seguranca-pagina">
        <x-astuti-marca />
        <section class="folha seguranca-folha" aria-labelledby="titulo-desafio">
            <span class="seguranca-etiqueta">Verificação em duas etapas</span>
            <h1 id="titulo-desafio">Confirme que é você</h1>
            @if (! empty($reason))
                <p class="seguranca-passo" role="status">{{ $reason }}</p>
            @endif
            <p>Digite o código de 6 dígitos do seu aplicativo autenticador ou um código de recuperação ainda não utilizado.</p>

            @if ($errors->any())
                <div class="erro" role="alert"><x-icon name="alerta" :size="18" /><span>{{ $errors->first() }}</span></div>
            @endif

            <form method="POST" action="{{ route('two-factor.verify') }}" class="seguranca-form">
                @csrf
                <label class="rotulo" for="code">Código de segurança</label>
                <input class="entrada" id="code" name="code" type="text" inputmode="text" autocomplete="one-time-code" minlength="6" maxlength="16" required autofocus>
                <button class="botao" type="submit">Confirmar acesso</button>
            </form>
            <form method="POST" action="{{ route('logout') }}" class="seguranca-form">
                @csrf
                <button type="submit" class="botao secundario">Sair da conta</button>
            </form>
        </section>
    </div>
</main>
</body>
</html>
