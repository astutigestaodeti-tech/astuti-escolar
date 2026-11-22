# Plano: conferência e gravação assistida de alunos

Base: PR #12 `feature/importador-base-ra-turma`. Um PR independente para esta etapa.

1. **Testes de fluxo:** criar cenários de envio sem gravação, confirmação humana de repetidos, importação E02 auditada, gabarito das três escolas, replay, duplicidade existente, matriz 403 e token adulterado. Evidência final no CI PostgreSQL.
2. **Recibo mínimo:** migration municipal `student_import_receipts` com ID único, escola, ano, autoria, contagem e data; limpeza das fixtures descartáveis do teste. Registrar evento no allowlist de auditoria, sem arquivo, nome ou dados de responsável.
3. **Controlador:** upload limitado a `.xlsx`, montar preview do motor existente, cifrar payload com prazo/conta/escola, revalidar no POST, serializar transação, checar duplicidade, vincular turma, criar alunos e matrículas auditados. Falha desfaz o lote inteiro.
4. **Interface:** formulário de envio com ano e arquivo; revisão por linha e confirmações; link na escola só para quem pode gerenciar; botão de retorno.
5. **Documentação e validação:** atualizar arquitetura e uma entrada consolidada no histórico; rodar CI completo e Pint, corrigir falhas, revisar o PR e parar. Não tocar nas branches #6, #7, #8, #9, #10, #11 e #12 nem mesclar.
