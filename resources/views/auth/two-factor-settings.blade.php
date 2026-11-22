<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13243b">
    <title>Proteção da conta | Astuti Gestão Escolar</title>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}?v={{ filemtime(public_path('css/astuti.css')) }}">
</head>
<body>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<main class="inicio" id="conteudo">
    <div class="inicio-caixa seguranca-pagina">
        <x-astuti-marca />
        <x-voltar-anterior :fallback="route('home')" />
        <section class="folha seguranca-folha" aria-labelledby="titulo-seguranca">
            <span class="seguranca-etiqueta">Segurança da conta</span>
            <h1 id="titulo-seguranca">Proteja seu acesso</h1>
            <p>Antes de cadastrar ou alterar alunos, confirme um código do seu aplicativo autenticador.</p>

            @if (session('status'))
                <div class="seguranca-sucesso" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="erro" role="alert"><x-icon name="alerta" :size="18" /><span>{{ $errors->first() }}</span></div>
            @endif

            @if (session('two_factor_recovery_codes'))
                <div class="seguranca-recuperacao" role="status">
                    <h2>Códigos de recuperação</h2>
                    <p>Guarde estes códigos em local seguro. Eles aparecem uma única vez, e cada um pode ser usado apenas uma vez.</p>
                    <ul>
                        @foreach (session('two_factor_recovery_codes') as $code)
                            <li><code>{{ $code }}</code></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($user->two_factor_confirmed_at)
                <div class="seguranca-sucesso" role="status">Segundo fator ativo. Sua conta está pronta para confirmar alterações escolares.</div>
                <p>Ao entrar no sistema, você informa o código uma vez. Ele vale enquanto você estiver usando o sistema (até 12 horas). Ações delicadas, como excluir um cadastro ou desativar o segundo fator, pedem o código de novo se ele foi digitado há mais de 15 minutos.</p>
                <a class="botao secundario seguranca-link" href="{{ route('two-factor.challenge') }}">Confirmar novo código</a>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="seguranca-form">
                    @csrf
                    <h2>Desativar segundo fator</h2>
                    <p>Para desativar, confirme seu código novamente e informe a senha da conta. As alterações escolares ficarão bloqueadas.</p>
                    <label class="rotulo" for="password">Senha atual</label>
                    <input class="entrada" id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="botao secundario" type="submit">Desativar proteção</button>
                </form>
            @elseif ($user->two_factor_pending_secret)
                <div class="seguranca-passo">Etapa 2 de 2: confirmar seu aplicativo</div>
                <ol class="seguranca-passos">
                    <li>No aplicativo autenticador do celular, toque em adicionar conta e escolha <strong>ler QR Code</strong>.</li>
                    <li>Aponte a câmera para o código abaixo.</li>
                    <li>Digite o código de 6 dígitos que aparecer no aplicativo para concluir.</li>
                </ol>
                @if ($qrCode)
                    <figure class="seguranca-qr">{!! $qrCode !!}</figure>
                @endif
                <p class="seguranca-aviso">Já tinha cadastrado o Astuti no aplicativo antes? Apague só a conta Astuti antiga (as dos outros sistemas continuam) e leia este código de novo. A conta antiga gera números que não valem mais.</p>
                <details class="seguranca-manual">
                    <summary>A câmera não funcionou? Digite a chave</summary>
                    <p>No aplicativo, escolha inserir a chave manualmente, tipo <strong>baseado em tempo (TOTP)</strong>, com 6 dígitos a cada 30 segundos.</p>
                    <div class="seguranca-chave" aria-label="Chave do autenticador"><code>{{ $user->two_factor_pending_secret }}</code></div>
                </details>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="seguranca-form">
                    @csrf
                    <label class="rotulo" for="code">Código de 6 dígitos</label>
                    <input class="entrada" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus>
                    <button class="botao" type="submit">Ativar proteção</button>
                </form>
            @else
                <div class="seguranca-passo">Etapa 1 de 2: validar sua identidade</div>
                <p>Você precisará de um aplicativo autenticador compatível com códigos TOTP. Depois de informar sua senha, exibiremos uma chave para cadastrar no aplicativo.</p>
                <form method="POST" action="{{ route('two-factor.begin') }}" class="seguranca-form">
                    @csrf
                    <label class="rotulo" for="password">Senha atual</label>
                    <input class="entrada" id="password" name="password" type="password" autocomplete="current-password" required autofocus>
                    <button class="botao" type="submit">Começar configuração</button>
                </form>
            @endif
        </section>
        <p class="seguranca-rodape">Ambiente de desenvolvimento: utilize apenas contas e dados fictícios.</p>
    </div>
</main>
</body>
</html>
