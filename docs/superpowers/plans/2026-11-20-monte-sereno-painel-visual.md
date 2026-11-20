# Painel visual Monte Sereno Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrar a identidade municipal e o brasão de Monte Sereno à demonstração já servida pelo Laravel, com navegação responsiva.

**Architecture:** Uma view Blade (`resources/views/demo/painel.blade.php`), um tema CSS sem dependências (`public/css/demo.css`) e um asset WebP em `public/images/`. Nenhuma modificação ao banco ou middleware.

**Tech Stack:** Laravel 13, Blade, CSS, WebP e GitHub Actions com PHP 8.4 para testes de integração.

**Spec:** `docs/superpowers/specs/2026-11-20-monte-sereno-painel-visual-design.md`.

## Global Constraints

- Dados inteiramente fictícios, e a demonstração nunca realiza importações reais.
- O brasão deve ser uma cópia visual do arquivo enviado por Jefferson, não uma recriação por IA.
- O URL continua `/demonstracao`; menus consultivos continuam em `route('demo')`.
- Preservar acesso protegido às rotas reais por município e não tocar nos modelos e bancos.
- Não mover alterações para `main` antes da revisão e dos controles pendentes do PR nº 1.

## Review Focus

1. 320 px: legenda e brasão municipal não invadem o título, nem criam rolagem horizontal.
2. Arquivo PNG com transparência: WebP mantém alpha e é servido pelo Laravel sem rede externa.
3. Dados reais: nenhum contador fictício é promovido a número oficial.
4. Acessibilidade: brasão recebe alternativa textual, links continuam tecláveis e foco visível.
5. Regressões: todos os slugs da demonstração e a busca por alunos permanecem intactos.

## Task 1: Contratos do painel e imagem

**Files:** `tests/Feature/Demo/DemonstrationBrandingTest.php`, `public/images/brasao-monte-sereno.webp`, `resources/views/demo/painel.blade.php`.

**Interfaces:** Blade recebe `$screens`, `$screen` e `$students` como antes; brasão localizado por `asset('images/brasao-monte-sereno.webp')`.

- [x] Escrever teste de integração Laravel para a identidade municipal, brasão e navegação.
- [x] Conferir RED na CI: GitHub Actions 44808469321, 2 falhas esperadas por ausência do texto da prefeitura; 43 testes existentes passaram.
- [ ] Gerar versão WebP do PNG original com transparência; manter o PNG original intacto.
- [ ] Modificar a view apenas no seletor municipal, cabeçalho institucional, favicon/theme color e texto do painel.
- [ ] Rodar suite na CI e conferir sucesso.

## Task 2: Tema elegante e responsivo

**Files:** `public/css/demo.css`, `tests/Feature/Demo/DemonstrationBrandingTest.php`.

**Interfaces:** Classes `municipal-identity`, `municipal-lockup`, `crest-mini`, `crest-header` e `.welcome-block` do Blade.

- [ ] Conferir manualmente CSS de 320 px e foco de teclado. Teste de renderização por navegador quando disponível.
- [ ] Adicionar tema CSS acessível com desenho do brasão na welcome card e reforçar apresentação mobile.
- [ ] Rodar suite Laravel na CI e conferir sucesso.
- [ ] Fazer revisão estática e registrar que execução PHP precisa da CI.

## Task 3: Integração segura e validação

**Files:** mesmos acima, documentação e testes da mudança.

**Interfaces:** Atualizar somente o ramo remoto de desenvolvimento `feature/incremento-01-saude`, sem merge.

- [ ] Publicar view, CSS, imagem e teste na branch existente por transação GitHub com controle do SHA esperado.
- [ ] Ler os arquivos publicados e verificar SHA da imagem, conteúdo e URLs.
- [ ] Conferir o resultado da CI do commit; se não for possível, informar claramente a limitação.
- [ ] Entregar comando seguro para Jefferson atualizar o notebook e acessar `http://localhost:8001/demonstracao`.
