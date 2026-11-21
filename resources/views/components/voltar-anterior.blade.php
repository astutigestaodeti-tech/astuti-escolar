@props(['fallback', 'municipio' => null])

@php
    $destino = $fallback;
    $origem = request()->header('referer');

    if (is_string($origem) && strlen($origem) <= 2048) {
        $url = parse_url($origem);

        if (is_array($url)) {
            $scheme = strtolower((string) ($url['scheme'] ?? ''));
            $host = strtolower((string) ($url['host'] ?? ''));
            $port = (int) ($url['port'] ?? ($scheme === 'https' ? 443 : 80));
            $path = (string) ($url['path'] ?? '/');
            $baseMunicipal = is_string($municipio) ? '/m/'.$municipio : null;

            $mesmaOrigem = in_array($scheme, ['http', 'https'], true)
                && $scheme === request()->getScheme()
                && $host === strtolower(request()->getHost())
                && $port === request()->getPort()
                && ! isset($url['user'])
                && ! isset($url['pass']);
            $mesmaPrefeitura = $baseMunicipal === null
                || $path === $baseMunicipal
                || str_starts_with($path, $baseMunicipal.'/');
            $outraPagina = rtrim($path, '/') !== rtrim(request()->getPathInfo(), '/');

            // Referer é controlado pelo cliente: nunca usá-lo para sair do domínio
            // ou retornar a outra Prefeitura. Se faltar, manter a rota autorizada.
            if ($mesmaOrigem && $mesmaPrefeitura && $outraPagina) {
                $destino = $origem;
            }
        }
    }
@endphp

<a class="voltar" href="{{ $destino }}"><x-icon name="voltar" :size="18" /> Voltar à tela anterior</a>
