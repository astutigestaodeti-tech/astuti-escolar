# HISTORICO DE DESENVOLVIMENTO

## 2026-11-21 | Importação assistida: conferência e gravação no banco municipal

- Novo fluxo de envio de .xlsx por escola e ano letivo, com prévia legível, confirmação expressa de linhas repetidas, autorização escolar e segundo fator.
- O arquivo não é armazenado: a prévia mínima fica cifrada por uma hora no próprio formulário e vinculada à conta/escola. Recibo municipal sem PII impede repetição do lote.
- Gravação de alunos e matrículas com RA e turma em uma transação PostgreSQL; datas de matrícula ausentes não são inventadas. Duplicidade existente, turma inválida ou falha de auditoria desfaz o lote inteiro.
- Testes do kit fictício E01/E02/E03, 115 alunos e matrículas, seis nascimentos pendentes, auditoria, replay, alteração de token e matriz de permissões. Sem merge automático, deploy ou bancos reais.

## 2026-11-21 | Base do importador: RA e turma da matrícula

- Revisados os PRs #5 a #11 e a cadeia de dependências. CI do ajuste de integridade histórica do PR #10 concluído com sucesso; o vínculo externo da matrícula usa RESTRICT, sem exclusão em cascata.
- Em PR próprio sobre o #9, acrescentados RA opcional com índice único sobre forma canônica no município e turma opcional com FK composta escola/ano; cadastro e edição com segundo fator, matrícula explícita, versão segura em gravações no mesmo segundo e auditoria; ficha mostra os campos.
- A migração de dados fictícios compara colunas legadas sem preencher artificialmente as novas; o importador permanece somente em prévia até a futura conferência humana e gravação segura.
- Testes de regressão cobrem duplicidade, isolamento de turmas, 403, 2FA e trilha auditada. O CI e Pint devem estar verdes antes do pedido de revisão; nada foi mesclado nem implantado no notebook.

## 2026-11-21 | PR #5 | Bloco 2: ICU pt-BR e busca sem acento

- Bancos PostgreSQL novos do guia Debian, CI e ensaios isolados criados com ICU `pt-BR` e `template0`, mantendo intocados os bancos originais.
- Testes exigem metadados reais da collation, ordem Á/É correta e busca `joao` encontrando `João` somente na escola autorizada.
- Migration municipal instala `unaccent`, `pg_trgm`, a função imutável `f_unaccent` e índice GIN; a busca mantém escape de curingas e escopo de permissão.
- CI volta a usar somente `vendor/bin/pint --test`, sem rotina de depuração; fluxos de cópia e restauração possuem verificações das opções ICU.
- PR #5 permanece rascunho e sem merge; Bloco 3 de permissões de produção não iniciado. Confirmação dos testes e Pint depende do resultado do CI do commit deste bloco.

## 2026-11-21 | PR #5 | Normalizacao segura de emails no PostgreSQL

- Login, cadastro e importacao ficticia passaram a normalizar emails em minusculas na plataforma.
- Uma migracao PostgreSQL detecta previamente conflitos sem divulgar emails, interrompe a operacao e reverte a transacao; sem conflitos, normaliza as contas existentes e cria indice unico sobre `lower(email)`.
- Testes cobrem login com caixa mista, recusa de duplicidade, conversao e falha segura da migracao e transferencia da conta ficticia sem alterar a origem.
- O fluxo de CI deixou de duplicar testes em pushes de branches que ja possuem PR: as validacoes passam a rodar em `pull_request`.
- `AGENTS.md` registra o novo processo de trabalho por risco e a regra de VPS exclusiva por Prefeitura.
- O PR permanece em rascunho, sem merge; nenhum banco original, dado real nem notebook foi alterado.
- Proximos blocos, na ordem solicitada: ordenacao ICU pt-BR e busca sem acento, permissoes de producao em PR proprio, e limite/auditoria do segundo fator.

Registro unico desta sessao. Testes completos e Pint sao condicionantes de entrega; verificar o status do CI deste commit antes do ensaio.

## 2026-11-21 | PR #13 | Reimportacao de alunos sem bloqueio por cadastro existente

- Previa classifica RA ou nome com data ja existentes como `ja_cadastrado`, com descarte automatico e contagem no recibo, sem reutilizar RA.
- Nome coincidente com nascimento ausente exige confirmar `homonimo` como outra pessoa antes da inclusao.
- Confirmacao revalida os cadastros sob advisory lock e grava de forma transacional somente as linhas aceitas.
- Testes cobrem reenvio da E02 (58 ignorados, zero gravados), E02 com uma aluna nova e homonimo sem data; formatacao e CI exigidos antes da integracao.
