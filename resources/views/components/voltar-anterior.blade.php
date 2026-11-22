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
            // Aceitar somente caminhos literais da aplicação. Rejeitar pontos,
            // escapes percentuais e barras codificadas antes da normalização no navegador.
            $caminhoSeguro = preg_match('/\\A\\/[a-zA-Z0-9_\\/-]*\\z/D', $path) === 1;
            $outraPagina = rtrim($path, '/') !== rtrim(request()->getPathInfo(), '/')
                || (string) ($url['query'] ?? '') !== (string) (request()->getQueryString() ?? '');

            // A página anterior pode ter sido resposta a um envio (POST), como a
            // conferência da importação em .../previa. Abrir esse endereço com GET
            // dá erro 405; só voltar para endereços que existem como página.
            $abrePorGet = false;
            if ($mesmaOrigem && $mesmaPrefeitura && $caminhoSeguro && $outraPagina) {
                try {
                    $abrePorGet = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path, 'GET')) !== null;
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                    $abrePorGet = false;
                }
            }

            // Referer é controlado pelo cliente: nunca usá-lo para sair do domínio
            // ou retornar a outra Prefeitura. Se faltar, manter a rota autorizada.
            if ($mesmaOrigem && $mesmaPrefeitura && $caminhoSeguro && $outraPagina && $abrePorGet) {
                $destino = $origem;
            }
        }
    }
@endphp

<a class="voltar" href="{{ $destino }}"><x-icon name="voltar" :size="18" /> Voltar à tela anterior</a>
