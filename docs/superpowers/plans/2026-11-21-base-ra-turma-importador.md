# Plano de implementação: RA e turma da matrícula

Data: 21/11/2026. Base: PR #9 (preview da planilha); novo PR independente.

1. Escrever testes de integração para cadastro, edição, permissões, 2FA, auditoria, concorrência e restrições SQL.
2. Migrar o esquema municipal com campos opcionais e índices/FK composta, com rollback ordenado.
3. Atualizar os modelos, a lista permitida de atributos auditados, o cadastro e a ficha.
4. Adaptar a cópia fictícia MariaDB para PostgreSQL a campos novos ausentes na origem.
5. Documentar por que o importador permanece só em prévia até a conferência humana. Validar via Pint e suíte do CI em PR, sem alterar dados locais nem mesclar.
