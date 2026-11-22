# Implementação: autorização de retirada

**Base:** `origin/main` atualizado. PR independente contra `main`.

1. Criar migração tenant 000020, modelo com validade inclusiva e testes de armazenamento/vencimento.
2. Criar controlador com escopo de aluno/escola, validações em português, transação, segundo fator e auditoria redigida.
3. Carregar autorizações junto aos vínculos no `SchoolStudentController::show`; incluir seção da ficha depois de Responsáveis, com cartões e links de consulta.
4. Criar formulário de inclusão/edição e confirmação de remoção com motivo preservado pelo desafio do segundo fator.
5. Ajustar rotas e retorno Voltar, estilos restritos à seção, e testes de papéis, municípios, validade, auditoria e segunda confirmação.
6. Executar Pint e testes no CI; enviar PR pequeno, sem mesclar. Não alterar arquivos de importação nem a listagem de alunos.
