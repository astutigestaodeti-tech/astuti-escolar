<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificar documento | Astuti Gestão Escolar</title>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}?v={{ filemtime(public_path('css/astuti.css')) }}">
</head>
<body class="verificacao-publica">
    <main class="folha verificacao-cartao" aria-labelledby="titulo-verificacao">
        <div class="folha-cabecalho">
            <div><span class="contexto">Consulta pública de autenticidade</span>
                <h1 id="titulo-verificacao">Verificação de documento escolar</h1>
                <p>A consulta apresenta somente os dados necessários para conferir a declaração.</p>
            </div>
        </div>
        <div class="folha-corpo">
            @if ($info === null)
                <p class="verificacao-situacao verificacao-invalida" role="status">Documento não encontrado</p>
                <p>Confira o código informado no documento e tente novamente.</p>
            @else
                <p class="verificacao-situacao {{ $info['status'] === 'Documento válido' ? 'verificacao-valida' : 'verificacao-invalida' }}" role="status">{{ $info['status'] }}</p>
                <dl class="verificacao-dados">
                    <div><dt>Tipo</dt><dd>Declaração de matrícula</dd></div>
                    <div><dt>Aluno</dt><dd>{{ $info['student'] }}</dd></div>
                    <div><dt>Escola</dt><dd>{{ $info['school'] }}</dd></div>
                    <div><dt>Ano letivo</dt><dd>{{ $info['year'] }}</dd></div>
                    @if ($info['stage'])<div><dt>Etapa</dt><dd>{{ $info['stage'] }}</dd></div>@endif
                    <div><dt>Emissão</dt><dd>{{ $info['issued'] }}</dd></div>
                </dl>
                @if ($info['status'] === 'Documento revogado')
                    <p>Este documento foi revogado pela unidade emissora e não deve ser aceito como válido.</p>
                @endif
            @endif
        </div>
    </main>
</body>
</html>
