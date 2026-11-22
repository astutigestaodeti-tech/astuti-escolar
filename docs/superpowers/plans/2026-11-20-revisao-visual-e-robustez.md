# Revisão visual e robustez - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrar com segurança a interface do Claude e corrigir problemas confirmados de compatibilidade, testes e navegação autenticada.

**Architecture:** Preservar o núcleo de autorização e tenancy; adotar views e assets versionados, controlador específico para a home, preparar testes que isolam os efeitos persistentes e conferir cada alteração na branch rascunho com CI efêmero.

**Tech Stack:** PHP 8.4.1+, Laravel 13, MariaDB 11, Blade, CSS próprio, Node 24, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-20-revisao-visual-e-robustez-design.md`

## Global Constraints

- Não usar dados reais de crianças e adolescentes, nem expor a demonstração em produção.
- Preservar os limites da tenancy e as policies existentes; nenhuma rota de escrita nova.
- Adotar arquivos do pacote fornecido como base estética, com teste de regressão, contrastes e atribuição de licença.
- Não incorporar `main` ou criar contas reais.
- Todo teste de integração deve usar exclusivamente bancos descartáveis com sufixo `_test`.

## Review Focus

- Usuário sem vínculo ou com vínculo inativo não recebe link de rede.
- Um membro com acesso só à rede A não vê B na home, nem ativa a conexão B.
- Testes repetidos não preservam IDs de sessão nem registros de provisão incompatíveis.
- CSS de foco mantém contraste ao navegar por fundos claros ou pelo menu escuro.
- Demonstração local não transforma ações de importação em upload real.

---

### Task 1: Compatibilidade do ambiente e repetição segura dos testes

**Files:** `composer.json`, `composer.lock`, `tests/Support/TwoMunicipalityTestCase.php`, `tests/Feature/Auth/PlatformSessionStorageTest.php`, `.github/workflows/testes.yml`

**Interfaces:** PHP `^8.4.1`; `TwoMunicipalityTestCase::setUp(): void` prepara bancos temporários e deixa tabelas de efeitos efêmeros limpas.

- [ ] Criar teste de regressão para limpeza das sessões e das tentativas de provisão, e verificação da versão mínima declarada.
- [ ] Rodar o teste antes da correção (RED pelo CI MariaDB).
- [ ] Corrigir o fixture compartilhado e requisito do PHP, atualizar hash do lock de acordo com Composer.
- [ ] Rodar testes duas vezes com o mesmo serviço MariaDB, validar Pint e instalação (GREEN).
- [ ] Registrar commit da alteração autocontida.

### Task 2: Identidade visual, contraste e arquivos locais

**Files:** `public/css/demo.css` (remover), `public/css/astuti.css`, `public/fonts/*`, `resources/views/components/*`, sete views e `docs/design/identidade-visual.md`; testes `tests/Feature/Demo/DemonstrationBrandingTest.php` e `tests/Visual/visual-contract.test.mjs`.

**Interfaces:** Views usam `asset('css/astuti.css')` com versionamento, `<x-icon>` e `<x-astuti-marca>`; CSS mantém foco de contraste adequado.

- [ ] Testar existência do novo CSS, marca e navegação por teclado antes da integração (RED).
- [ ] Importar apenas as alterações visuais do pacote, manter textos de demonstração e o brasão atual; corrigir legenda de função futura.
- [ ] Corrigir cor de foco em fundo claro, textos enganosos e cache-busting do CSS; registrar licenças.
- [ ] Rodar teste de contraste, feature tests de demonstração, build Vite e Pint (GREEN).
- [ ] Registrar commit.

### Task 3: Página inicial autorizada, português e controles de acesso

**Files:** `app/Http/Controllers/HomeController.php`, `routes/web.php`, `resources/views/welcome.blade.php`, `tests/Feature/Auth/HomeMunicipalityTest.php`, `.env.example`, mensagens em `app/Http/Controllers/Auth/LoginController.php`.

**Interfaces:** `HomeController::index(Request $request): View` entrega `$redes` a `welcome`, consultando exclusivamente a base central e `SchoolAccess::hasMunicipalAccess`.

- [ ] Escrever testes de acesso de visitante, usuário autorizado somente à rede A, usuário sem vínculos e usuário suspenso (RED).
- [ ] Implementar controller e view sem consultas à base dentro de Blade; registrar rota `/` e textos exibidos em português.
- [ ] Atualizar exemplos de locale sem remover fallback para inglês antes das traduções completas.
- [ ] Rodar a suíte PHP duas vezes com os mesmos bancos e build/Pint (GREEN).
- [ ] Registrar commit e reavaliar a alteração completa; deixar PR rascunho.
