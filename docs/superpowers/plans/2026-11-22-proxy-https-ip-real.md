# HTTPS e IP real atras de proxy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Confiar somente no proxy reverso explicitamente autorizado, preservando o IP do visitante e a identificacao HTTPS sem aceitar spoofing de hostname.

**Architecture:** `config/trustedproxy.php` carrega a lista validada por `App\Support\TrustedProxyAddresses::parse()`; `bootstrap/app.php` configura o `TrustProxies` nativo com lista restrita de cabecalhos. O middleware HSTS existente reconhece a conexao TLS terminada no proxy atraves de `Request::isSecure()`.

**Tech Stack:** PHP 8.4, Laravel 13, Symfony HttpFoundation, testes PHPUnit e GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-22-proxy-https-ip-real-design.md`

## Global Constraints
- Branch `feature/infra-proxy-https-ip-real` iniciada em `feature/migracao-postgresql-instalacoes-isoladas`; novo PR contra essa branch, sem merge.
- Nenhum banco de producao, dado de crianca, token, senha, VPS, mudanca em `.env` privado ou PR existente.
- Confianca vazia por padrao; proibir wildcard e valores IP/CIDR invalidos.
- Sem `X-Forwarded-Host`, `Forwarded` nem `X-Forwarded-Prefix`.
- Alteracoes em portugues, sem travessao longo.

## Review Focus
- Cliente direto falsifica `X-Forwarded-Proto`: deve continuar HTTP e sem HSTS.
- Cliente direto falsifica `X-Forwarded-For`: deve manter o IP da conexao.
- Proxy autorizado com `X-Forwarded-Host` malicioso: deve preservar Host original.
- Proxy autorizado reconhece HTTPS e IP externo: HSTS apenas em producao.
- Entradas `*`, CIDR fora do limite, valores textuais e lista vazia: nao confiar amplamente.

---

### Task 1: Validacao e configuracao explicita dos proxies

**Files:**
- Create: `tests/Unit/TrustedProxyAddressesTest.php`
- Create: `app/Support/TrustedProxyAddresses.php`
- Create: `config/trustedproxy.php`
- Modify: `.env.example`

**Interfaces:**
- Produces: `TrustedProxyAddresses::parse(?string $raw): array` e `config('trustedproxy.proxies')`.

- [ ] **Step 1:** Escrever teste para vazio, lista de IP/CIDR e rejeicao de wildcard/invalidos.
- [ ] **Step 2:** Rodar CI com testes novos e observar falha antes da implementacao.
- [ ] **Step 3:** Criar parser e configuracao minima, documentar variavel vazia.
- [ ] **Step 4:** Reexecutar CI e conferir casos, sem encerrar o PR ainda.

### Task 2: Confianca seletiva dos cabecalhos e HSTS

**Files:**
- Create: `tests/Feature/Security/TrustedProxyTest.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Consumes: `config('trustedproxy.proxies')`.
- Produces: `$middleware->trustProxies(at: ..., headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT)`.

- [ ] **Step 1:** Criar testes reais de requisicao para proxy confiavel/nao confiavel, spoofing e HSTS.
- [ ] **Step 2:** Conferir falha do caso `X-Forwarded-Host` antes da correção.
- [ ] **Step 3:** Configurar middleware nativo e repetir testes.
- [ ] **Step 4:** Executar toda a suite PHP duas vezes, Pint, build, UI e integracao PG/MariaDB no CI.

### Task 3: Guia operacional e encerramento

**Files:**
- Modify: `docs/operacao/vps-prefeitura.md`

- [ ] **Step 1:** Registrar `TRUSTED_PROXIES`, limites de rede e headers a sobrescrever, com exemplo apenas ficticio.
- [ ] **Step 2:** Revisar PR, conferir diff e CI efetivamente verde.
- [ ] **Step 3:** Registrar commit/PR, testes, pendencias e instrucoes de implantacao na continuidade do projeto quando integrada a base documental.
- [ ] **Step 4:** Entregar PR a Jefferson para revisao, sem merge nem deploy.
