# Monte Sereno, ambiente de teste local - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar cenário sintético testável por login e banco municipal com um único comando de desenvolvimento.

**Architecture:** Conexão `monte_sereno` configurada somente no ambiente local reconhecido; comando Artisan de instalação protegido por validações antes de escrita, aproveitando as migrations e o contexto municipal. Registros estáveis e idempotentes, senha forte gerada.

**Tech Stack:** PHP 8.4, Laravel 13, MariaDB 11, PHPUnit de integração com bases `_test`, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-20-monte-sereno-ambiente-local-design.md`

## Global Constraints

- Nunca gravar em produção, banco remoto ou banco diferente dos destinos de demonstração permitidos; nunca criar ou excluir bases.
- Não gerar senha fixa, não registrar segredos em Git/logs, nem criar rota de escrita ou login bypass.
- Somente dados fictícios, 3 escolas, 115 alunos, ano 2027, 3 turmas.
- Nenhuma exclusão ou atualização forçada de registros existentes, salvo `--renovar-senha` explicitamente invocado.

## Review Focus

1. Configuração com ambiente `production` ou host remoto: recusar antes de qualquer migração/gravação.
2. Banco central e municipal idênticos: recusar antes de qualquer gravação.
3. Segunda execução: não duplicar 115 alunos nem resetar a senha.
4. Usuário consegue autenticar e entrar na tela real protegida por Gate.
5. O comando deve falhar seguramente se não existir base municipal, sem criá-la silenciosamente.

---

### Task 1: Conexão local limitada a Monte Sereno

**Files:**
- Modify: `config/tenancy.php`
- Test: `tests/Feature/Demo/LocalDemoConfigurationTest.php`

**Interfaces:** Configuração `tenancy.connections.monte_sereno` herda host, porta, socket e credenciais privados da conexão `platform` apenas em `local` com banco central `astuti_platform_dev`; base de destino `astuti_monte_sereno_dev`.

- [ ] Testar que a conexão só existe para configuração local reconhecida, sem URL remota.
- [ ] Confirmar RED da configuração ausente no CI.
- [ ] Implementar configuração condicional mínima.
- [ ] Confirmar GREEN e ausência de alterações em ambientes não permitidos.

### Task 2: Comando seguro e idempotente de cenário fictício

**Files:**
- Create: `app/Console/Commands/PrepareMonteSerenoDemo.php`
- Test: `tests/Feature/Demo/PrepareMonteSerenoDemoTest.php`
- Modify: `README.md`

**Interfaces:** `astuti:demo-preparar {--renovar-senha}` prepara schema e dados sintéticos para município `monte-sereno`, imprime o e-mail de login e uma senha aleatória apenas quando criada/renovada.

- [ ] Criar testes para destino local protegido, cenário de 3 escolas/115 alunos e login real, segunda execução, produção proibida, host remoto, banco compartilhado e senha regenerada somente por opção.
- [ ] Confirmar RED por comando inexistente na CI.
- [ ] Implementar fluxo com validações antes de escrever e migrações de platform/tenant usando o comando de provisão existente.
- [ ] Confirmar GREEN duas vezes no CI, Pint, build e visual.
- [ ] Atualizar README com um comando e caminho de navegação, sem instruções destrutivas.

## Verificação final

- [ ] Revisar diffs/segredos/rotas, rodar verificações e conferir commit exato na CI.
- [ ] Revisão independente final; manter draft PR sem merge para `main`.
- [ ] Dar ao Jefferson comandos de atualização e `php artisan astuti:demo-preparar` somente após verificar verde.
