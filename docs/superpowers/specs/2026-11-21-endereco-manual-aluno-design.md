# Cadastro manual de endereço do aluno - desenho técnico

Data: 2026-11-21. Base: PR #18 (`feature/cadastro-aluno-feedback-ra-sed`).

## Objetivo
Registrar e editar endereço opcional do aluno municipalmente, exibindo-o na ficha apenas para quem já possui acesso autorizado ao aluno. A inclusão a partir de planilhas e OCR fica fora desta entrega.

## Alternativas consideradas
- Guardar o endereço diretamente em `students` (escolhida): uma migração aditiva e auditoria do próprio aluno, sem nova entidade ou permissões.
- Criar tabela `student_addresses`: histórico endereçável próprio, mas aumenta joins, auditoria e migrações sem necessidade atual.
- Somente formulário sem persistência: não atende à necessidade de cadastro e consulta.

## Estrutura e contratos
- Migration municipal nova e reversível: `address_street VARCHAR(180)`, `address_number VARCHAR(20)`, `address_district VARCHAR(120)`, `postal_code VARCHAR(9)`, `residence_zone VARCHAR(5)`; todos `NULL` por padrão. Constraint `CHECK (residence_zone IN ('urban','rural'))`; `NULL` continua válido.
- `Student` autoriza atribuição em massa somente dessas novas colunas. `AuditTrail` permite os mesmos atributos no JSON filtrado. Eventos de criação e alteração do modelo registram valores anteriores e posteriores, na transação existente.
- `StudentRecordController::store` e `::update` recebem cinco campos opcionais. Strings vazias viram `NULL`; logradouro, número e bairro são aparados. CEP de oito dígitos recebe hífen na posição cinco; CEP já em `00000-000` é aceito; letras, caracteres extras e comprimento inválido causam erro em português. Zona restrita a `urban` e `rural`.
- Na edição, um campo de endereço não enviado deve conservar seu valor atual. Campo enviado vazio deve poder limpá-lo. Essa distinção mantém compatibilidade com formulários e chamadas existentes.
- O formulário usa grupo semântico `fieldset` com legenda **Endereço**, os cinco rótulos pedidos, exemplo de CEP e seleção **Urbana/Rural**. A ficha mostra o endereço com marcador **Não informado** em campos vazios, usando as mesmas autorizações de consulta que já existem.

## Segurança, não objetivos e revisão
- Nenhuma alteração em `ConfirmedStudentImportController`, views de importação, importadores XLSX/CSV/OCR, permissões, middlewares ou segundo fator.
- Não importar endereço de `alunos.csv` nesta etapa; nenhuma inferência de CEP ou zona residencial.
- Dados dos testes são inteiramente fictícios e executados em bancos descartáveis PostgreSQL 17. Não tocar no notebook nem no banco real.
- Conferir validação, normalização, `CHECK` no banco, auditoria antes/depois, preservação na edição parcial e isolamento da escola.
