<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Acesso seguro | Astuti Gestão Escolar</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #17324c; background: #f0f5f7; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: radial-gradient(circle at 8% 7%, #d4ece8 0, transparent 33%), radial-gradient(circle at 90% 88%, #dce8fb 0, transparent 35%), #f5f8fb; }
        main { width: min(100%, 450px); background: white; padding: 44px; border: 1px solid #e2eaf0; border-radius: 24px; box-shadow: 0 20px 55px rgba(14, 44, 78, .09); }
        .brand { display: flex; align-items: center; gap: 13px; margin-bottom: 42px; }
        .logo { height: 44px; width: 44px; border-radius: 13px; background: linear-gradient(145deg, #167f8b, #0d456c); color: white; display: grid; place-items: center; font-weight: 800; font-size: 22px; }
        .brand strong { display: block; color: #153956; font-size: 18px; letter-spacing: -.035em; }
        .brand small { display: block; color: #5e778a; font-size: 12px; letter-spacing: .07em; text-transform: uppercase; margin-top: 3px; }
        h1 { font-size: 30px; letter-spacing: -.045em; margin: 0 0 8px; }
        p { color: #60788a; line-height: 1.5; margin: 0 0 30px; }
        label { display: block; margin: 18px 0 7px; font-size: 14px; font-weight: 700; }
        input { width: 100%; border: 1px solid #cfdde6; border-radius: 12px; padding: 13px 14px; font: inherit; color: #14354f; background: white; }
        input:focus { outline: 3px solid #c2e4e5; border-color: #197884; }
        button { width: 100%; margin-top: 26px; border: none; border-radius: 12px; padding: 14px 18px; background: #0d576c; color: white; font: inherit; font-weight: 750; cursor: pointer; }
        button:hover { background: #0b475a; }
        button:focus-visible { outline: 3px solid #7fdee1; outline-offset: 3px; }
        .error { padding: 12px 14px; border: 1px solid #efc4c4; background: #fff5f5; color: #8b3030; border-radius: 10px; font-size: 14px; }
        footer { text-align: center; margin-top: 32px; color: #8194a2; font-size: 12px; }
        @media (max-width: 500px) { main { padding: 28px 22px; border-radius: 18px; } h1 { font-size: 26px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; } }
    </style>
</head>
<body>
<main>
    <div class="brand" aria-label="Astuti Gestão Escolar">
        <div class="logo" aria-hidden="true">A</div>
        <div><strong>Astuti Escolar</strong><small>Gestão pública de educação</small></div>
    </div>
    <h1>Bem-vindo de volta</h1>
    <p>Acesse com sua conta institucional autorizada.</p>

    @if ($errors->any())
        <div class="error" role="alert">Não foi possível acessar. Confira seus dados e tente novamente.</div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <label for="email">E-mail institucional</label>
        <input id="email" name="email" type="email" autocomplete="username" required maxlength="255" value="{{ old('email') }}" autofocus>
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Acessar</button>
    </form>

    <footer>Astuti Gestão Escolar - acesso restrito</footer>
</main>
</body>
</html>
