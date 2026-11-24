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
                <p>A consulta apresenta somente os dados necessários para conferir a autenticidade.</p>
            </div>
        </div>
        <div class="folha-corpo">
            @if ($info === null && ! ($askCode ?? false))
                <p class="verificacao-situacao verificacao-invalida" role="status">Documento não encontrado</p>
                <p>Confira o código informado no documento e tente novamente.</p>
            @elseif ($info !== null)
                <p class="verificacao-situacao {{ $info['status'] === 'Documento válido' ? 'verificacao-valida' : 'verificacao-invalida' }}" role="status">{{ $info['status'] }}</p>
                <dl class="verificacao-dados">
                    <div><dt>Tipo</dt><dd>{{ match ($info['kind']) { 'school_transcript' => 'Histórico escolar', 'active_search_referral' => 'Documento escolar de acesso restrito', default => \App\Models\Tenant\IssuedDocument::DECLARATIONS[$info['kind']] ?? 'Documento escolar' } }}</dd></div>
                    @if (isset($info['student']))
                        <div><dt>Aluno</dt><dd>{{ $info['student'] }}</dd></div>
                    @endif
                    <div><dt>Escola</dt><dd>{{ $info['school'] }}</dd></div>
                    @if (isset($info['student']))
                        <div><dt>Ano letivo</dt><dd>{{ $info['year'] }}</dd></div>
                        @if ($info['stage'])<div><dt>Etapa</dt><dd>{{ $info['stage'] }}</dd></div>@endif
                    @endif
                    <div><dt>Emissão</dt><dd>{{ $info['issued'] }}</dd></div>
                </dl>
                @if ($info['status'] === 'Documento revogado')
                    <p>Este documento foi revogado pela unidade emissora e não deve ser aceito como válido.</p>
                @endif
            @endif
            <form class="verificacao-form" method="GET" action="{{ route('documents.verify.form') }}">
                <label class="rotulo" for="codigo">{{ $info === null ? 'Código de verificação' : 'Conferir outro código' }}</label>
                <div class="verificacao-form-linha">
                    <input class="entrada" id="codigo" name="codigo" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Ex.: ABCD 2345 EF" required>
                    <button class="botao" type="submit">Verificar</button>
                </div>
                <p class="texto-ajuda">O código está no rodapé do documento, ao lado do QR Code.</p>
            </form>
        </div>
    </main>
</body>
</html>
