# Pessoas autorizadas para retirada do aluno

**Data:** 2026-11-22

## Regra funcional
A seção "Quem pode buscar" aparece imediatamente após "Responsáveis" na ficha. Os vínculos de student_guardians são exibidos no primeiro grupo, "Responsáveis (autorizados)", sem replicar os cadastros. Pessoas adicionais são guardadas apenas em student_pickup_authorizations, vinculadas ao estudante e ao município atual.

A autorização adicional possui nome completo e telefone com DDD obrigatórios. Relação, documento (RG ou CPF para conferência no portão), observação e data "Autorizado até" são opcionais. A validade é inclusiva: uma data anterior ao dia de consulta significa autorização vencida; ela aparece riscada e não entra na contagem dos autorizados. Não há exclusão automática no banco, para manter histórico e permitir correção.

## Segurança e auditoria
- Só contas autorizadas a consultar a escola enxergam a seção, inclusive professores com acesso à escola.
- Operações GET de formulário e POST/PUT de escrita exigem permissão `manageStudents` e vínculo ativo do aluno à escola. POST e PUT exigem segundo fator confirmado.
- Remoção é POST com motivo de 10 a 500 caracteres, segundo fator **recente**, transação e auditoria `deleted` com snapshot antes e motivo depois.
- Em cada escrita a matrícula é rechecada sob lock. Uma autorização só pode ser editada ou removida no mesmo aluno.
- AuditTrail libera apenas campos conhecidos e mascara `phone` e `document` como `[REDACTED]`.
- A migração tenant 000020 cria a tabela e valida o formato de telefone por CHECK PostgreSQL.

## Fora do escopo
Nenhuma alteração nas telas de importação, na listagem de alunos ou nos dados dos responsáveis. Não há sincronização com catraca/portaria. O documento é dado de conferência, não comprovação automática de identidade.
