@php
    $status = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
    $textos = [
        401 => ['Entre novamente', 'Sua sessão terminou. Entre de novo com seu e-mail e senha.'],
        402 => ['Não foi possível abrir a página', 'Volte e tente de novo.'],
        403 => ['Acesso não permitido', 'Sua conta não tem permissão para abrir esta página ou fazer esta ação.'],
        404 => ['Página não encontrada', 'O endereço não existe ou o registro não está disponível para a sua conta.'],
        405 => ['Esta página não abre pelo endereço', 'Este endereço só funciona ao enviar um formulário, como a conferência de uma importação. Volte à tela de envio e repita o envio, se precisar.'],
        419 => ['A página expirou', 'A página ficou aberta por muito tempo sem uso. Recarregue a página e faça o envio de novo.'],
        429 => ['Muitas tentativas seguidas', 'Aguarde um minuto e tente de novo.'],
        500 => ['Algo deu errado', 'O sistema não conseguiu concluir o pedido. Nada foi gravado pela metade. Tente de novo em instantes.'],
        503 => ['Sistema em manutenção', 'O sistema volta em alguns minutos.'],
    ];
    [$titulo, $explicacao] = $textos[$status] ?? ($status >= 500 ? $textos[500] : ['Não foi possível abrir a página', 'Volte e tente de novo.']);

    // Mensagens próprias do sistema em 403 (por exemplo, segundo fator) ajudam mais que o texto geral.
    $detalhe = $status === 403 && isset($exception) ? trim((string) $exception->getMessage()) : '';
    if ($detalhe === 'This action is unauthorized.' || $detalhe === 'Forbidden') {
        $detalhe = '';
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13243b">
    <title>{{ $titulo }} | Astuti Gestão Escolar</title>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}">
</head>
<body>
<main class="inicio" id="conteudo">
    <div class="inicio-caixa seguranca-pagina">
        <x-astuti-marca />
        <section class="folha seguranca-folha" aria-labelledby="titulo-erro">
            <span class="seguranca-etiqueta">Código {{ $status }}</span>
            <h1 id="titulo-erro">{{ $titulo }}</h1>
            <p>{{ $explicacao }}</p>
            @if ($detalhe !== '')
                <p class="seguranca-passo">{{ $detalhe }}</p>
            @endif
            <div class="seguranca-form">
                <a class="botao" href="{{ url('/') }}">Ir para o início</a>
            </div>
        </section>
    </div>
</main>
</body>
</html>
