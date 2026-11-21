# Histórico de vínculos na ficha do aluno - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development and superpowers:verification-before-completion.

**Goal:** Mostrar vínculos atuais e históricos de uma única escola autorizada, com status e datas, sem mudar a autorização.

**Spec:** docs/superpowers/specs/2026-11-21-historico-vinculos-ficha-aluno-design.md

**Arquivos**
- `tests/Feature/Students/SchoolStudentShowTest.php`: regressão multiano e multiescola.
- `app/Http/Controllers/SchoolStudentController.php`: remover filtro por status apenas na coleção exibida; manter filtro ativo para autorizar a ficha.
- `resources/views/students/show.blade.php`: nomear histórico e apresentar status/datas.

## Task 1: histórico por escola
1. Acrescentar teste: secretária autorizada na escola A vê vínculo atual de 2027, vínculo concluído de 2026 com datas e não vê matrícula 2025 de escola B. **Expected:** 200 para aluno ativo na A, nenhum dado da B.
2. Executar CI com o teste novo antes da implementação. **Expected:** falha por ausência do vínculo concluído.
3. Ajustar query da coleção de vínculos para incluir status históricos mantendo `school_id`.
4. Adaptar view para listar ano, situação e datas opcionais, preservando `{{ }}` do Blade. **Expected:** saída legível e escapada.
5. Executar CI completo (PHP/Pint/build/UX/migração) e conferir logs. **Expected:** todos verdes; sem migração ou mudança de dados.
6. Revisar diff/segurança, registrar resultado nos arquivos de continuidade do main, manter PR draft.

**Restrição:** não tocar clusters PostgreSQL 5432, MariaDB, backups, arquivos .env ou dados reais.