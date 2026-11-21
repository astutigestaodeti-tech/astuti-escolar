# Bloco 2: ordenação pt-BR e consulta de alunos sem acento

**Contexto.** Com a migração PostgreSQL, a collation padrão C ordena nomes acentuados depois de Z. A busca por `ILIKE` não equipara `joao` a `João`. O CI precisa provar a configuração real dos bancos.

## Decisão

- Criar os bancos `platform` e municipais com `TEMPLATE template0 LOCALE_PROVIDER icu ICU_LOCALE 'pt-BR'`. Inclui bases efêmeras do CI, do ensaio de transferência e de restauração; não alterar banco pré-existente do notebook.
- Preservar `ORDER BY full_name, id`, apoiado na collation correta do banco.
- Em cada banco municipal novo/migrado, criar extensões `unaccent` e `pg_trgm`. Usar `public.f_unaccent(text)` imutável apoiada no dicionário padrão, com GIN `gin_trgm_ops` sobre `public.f_unaccent(lower(full_name))`.
- Normalizar os dois lados da comparação e escapar `%`, `_` e barras invertidas. Manter os filtros por escola, vínculo ativo e perfil existentes.
- Testar metadados dos dois bancos, ordenação que falha em collation C, busca por `joao`/João e isolamento escolar. Os testes de shell garantem flags de ICU nos scripts de ensaio.
- A função imutável presume um dicionário `unaccent` fixo. Alterações posteriores das regras exigem reconstrução do índice GIN.

## Segurança e implantação

- Não mexer na porta 5432 existente, MariaDB, `.env`, backups ou dados reais; não recriar banco existente automaticamente.
- Migration cria apenas as estruturas de consulta; remoção reversa da migration deixa extensões compartilhadas intactas.
- Restaurar Pint sem depuração na mesma entrega. PR #5 segue em rascunho, sem merge.
