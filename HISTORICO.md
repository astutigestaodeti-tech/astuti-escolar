# HISTORICO DE DESENVOLVIMENTO

## 2026-11-21 | PR #5 | Normalizacao segura de emails no PostgreSQL

- Login, cadastro e importacao ficticia passaram a normalizar emails em minusculas na plataforma.
- Uma migracao PostgreSQL detecta previamente conflitos sem divulgar emails, interrompe a operacao e reverte a transacao; sem conflitos, normaliza as contas existentes e cria indice unico sobre `lower(email)`.
- Testes cobrem login com caixa mista, recusa de duplicidade, conversao e falha segura da migracao e transferencia da conta ficticia sem alterar a origem.
- O fluxo de CI deixou de duplicar testes em pushes de branches que ja possuem PR: as validacoes passam a rodar em `pull_request`.
- `AGENTS.md` registra o novo processo de trabalho por risco e a regra de VPS exclusiva por Prefeitura.
- O PR permanece em rascunho, sem merge; nenhum banco original, dado real nem notebook foi alterado.
- Proximos blocos, na ordem solicitada: ordenacao ICU pt-BR e busca sem acento, permissoes de producao em PR proprio, e limite/auditoria do segundo fator.

Registro unico desta sessao. Testes completos e Pint sao condicionantes de entrega; verificar o status do CI deste commit antes do ensaio.
