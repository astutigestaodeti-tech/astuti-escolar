# Proxies de confianca para HTTPS e IP do visitante

## Intencao
Preparar a instalacao independente de cada Prefeitura para receber HTTPS de um proxy reverso sem confiar em cabecalhos enviados diretamente por visitantes. O aplicativo deve identificar IP e esquema HTTPS reais somente quando a conexao vier de IP/CIDR de proxy explicitamente autorizado.

## Estado atual
O Laravel 13 utiliza `TrustProxies`, mas `bootstrap/app.php` nao configura rede confiavel nem filtra os cabecalhos. O middleware `SecurityHeaders` envia HSTS apenas quando `APP_ENV=production` e `Request::isSecure()`. O guia de VPS exige TLS e cookie Secure, mas nao documenta a fronteira de confianca do proxy.

## Abordagens
1. **Middleware nativo do Laravel com IPs declarados por instalacao (escolhida):** ler `TRUSTED_PROXIES` da configuracao cacheavel, validar IPv4/IPv6/CIDR, passar os IPs ao `trustProxies` e aceitar somente `X-Forwarded-For`, `X-Forwarded-Proto` e `X-Forwarded-Port`.
2. Confiar em todos os proxies (`*`): descartada, porque conexoes diretas ou cabecalhos falsificados poderiam forjar IP ou esquema seguro.
3. Construir middleware proprio de interpretacao dos cabecalhos: descartada, por duplicar a implementacao testada de Laravel/Symfony.

## Comportamento
- Sem `TRUSTED_PROXIES`, lista confiavel vazia. O aplicativo ignora dados `X-Forwarded-*` de clientes comuns.
- Com lista valida de IPs ou CIDRs separados por virgulas, somente proxies coincidentes podem informar IP de origem, esquema e porta.
- `X-Forwarded-Host`, `Forwarded` e `X-Forwarded-Prefix` nao sao confiados nesta arquitetura, impedindo trocar o hostname usado em URLs por meio desses campos.
- Valor `*`, endereco ou CIDR invalido interrompe configuracao em vez de ampliar a confianca.
- HSTS permanece exclusivo de producao sobre conexao reconhecida como HTTPS; cookie de sessao Secure continua exigido em producao.
- O proxy deve sobrescrever cabecalhos de origem fornecidos pelo cliente, oferecer o aplicativo somente por conexao interna e redirecionar HTTP publico para HTTPS.

## Limites
Nenhuma alteracao em bancos, migracoes, usuarios, dados escolares, `.env` real, VPS ou PRs existentes. A branch parte do PR #5 e o novo PR permanece separado e sem merge. Testes executados em CI com ambiente ficticio e sem autenticacao real.
