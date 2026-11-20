<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Escolas') | Astuti Gestão Escolar</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #17324c; background: #f4f7fa; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        header { background: #123e55; color: white; padding: 21px max(20px, calc((100vw - 1050px) / 2)); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        header strong { font-size: 19px; }
        header small { display: block; margin-top: 4px; color: #b3d9dc; }
        .exit { border: 1px solid #779fae; border-radius: 9px; padding: 9px 15px; background: transparent; color: white; font: inherit; cursor: pointer; }
        .exit:focus-visible, a:focus-visible { outline: 3px solid #69cdd3; outline-offset: 4px; }
        main { width: min(1050px, 100% - 40px); margin: 44px auto; }
        .eyebrow { color: #117a84; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 12px; }
        h1 { margin: 10px 0 13px; font-size: clamp(26px, 4vw, 34px); letter-spacing: -.035em; }
        .muted { color: #60788a; line-height: 1.6; }
        .schools { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-top: 28px; }
        .school { display: block; padding: 24px; background: white; border: 1px solid #e0e9ee; border-radius: 16px; box-shadow: 0 10px 20px rgba(26, 72, 99, .04); color: #173c53; text-decoration: none; }
        .school:hover { border-color: #5baeb3; }
        .school strong { display: block; margin-top: 12px; font-size: 19px; }
        .pill { display: inline-block; font-size: 12px; border-radius: 20px; padding: 5px 10px; color: #155f65; background: #def0ec; font-weight: 700; }
        .back { color: #117a84; font-weight: 700; }
        .note { margin-top: 32px; color: #71889a; font-size: 12px; }
    </style>
</head>
<body>
<header>
    <div><strong>Astuti Gestão Escolar</strong><small>{{ $municipality->legal_name }}</small></div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="exit" type="submit">Sair</button>
    </form>
</header>
<main>@yield('content')</main>
</body>
</html>
