# Auditoria inicial de alunos e responsáveis - F2/01 (#256)

Base examinada: deee0c3b970332690cf0fb0043f06aa851ca21a2, em 26/11/2026. Revisão estática inicial, não homologação de telas nem prova de execução concorrente.

| Grupo | Evidência na base | Situação |
| --- | --- | --- |
| Nome, nascimento e RA | StudentRecordController, Student, students/form e StudentRecordWriteTest | Nome obrigatório; nascimento e RA opcionais; normalização e validação de RA; verificar regras de nome e casos de recém-nascidos posteriormente. |
| Endereço | StudentRecordController e students/form | Rua, número, complemento, bairro, cidade, UF, CEP e zona opcionais; testes de CEP, UF, edição e isolamento. |
| Identificação e documentos | censusRules e StudentRecordWriteTest | Nome social, sexo, raça/cor, nacionalidade, naturalidade, CPF, RG/órgão/UF/data e certidão opcionais. CPF único ativo por índice students_cpf_active_unique. |
| Matrícula | StudentRecordController | Ano obrigatório no ingresso; turma, etapa e início opcionais; vínculo ativo e escopo escolar; revisão anual aprofundada na F2/02–03. |
| Responsáveis | GuardianController, Guardian, StudentGuardian, guardians/form, migration 000018, GuardianWriteTest | Nome e parentesco obrigatórios; CPF opcional único; telefone obrigatório para novo responsável, reutilização permite omitir contato; RG/UF, e-mail, telefones adicionais e trabalho opcionais. Vínculo único, contato principal único, responsável legal e desvínculo justificado. |
| Duplicidades | DuplicateStudents, StudentRecordController e migration 000017 | Alerta por nome/nascimento e trava de criação; unicidade de RA e CPF. Não fundir automaticamente. |
| Segurança | StudentRecordWriteTest e GuardianWriteTest | Testes existentes de 2FA, escopo, auditoria mascarada, concorrência de edição e desvínculo. Revisão de todas as rotas e políticas ainda pendente. |

## Primeira correção

O controlador verificava disponibilidade de CPF antes da transação e o banco possuía índice único ativo, mas o tratamento de SQLSTATE 23505 após tentativa de gravação só reconhecia o índice de RA. Uma disputa concorrente pelo CPF podia resultar em erro não tratado ao invés da mensagem de validação. O tratamento compartilhado agora reconhece ambos os índices por SQLSTATE e detalhe do driver, sem expor conteúdo da consulta ou dados de alunos. Os demais erros continuam sendo lançados. Testes unitários simulam a exceção de unicidade; não equivalem a um ensaio de duas conexões concorrentes. Aguardar CI e posterior homologação.

## Pendências

Auditar todos os campos e políticas restantes, pessoas autorizadas para retirada, importadores, fotos, ficha A4, pendências cadastrais, acessibilidade e testes de concorrência real. Avaliar política municipal para telefone obrigatório e nome mínimo sem alterar exigências por hipótese. Verificar dados de guarda e autorização sem ampliar coleta de documentos sensíveis. Validar interface e impressão 1440/390 em Monte Sereno. O PR permanece rascunho até completar o escopo ou dividir entregas funcionais revisáveis.
