# Ficha individual do aluno em consulta segura

## Intenção

Permitir que a secretaria navegue da lista de alunos para uma ficha individual no PostgreSQL fictício, usando o modelo `students` e seus vínculos escolares já existentes. A tela é somente leitura e não introduz campos sensíveis.

## Abordagens consideradas

- **Apenas expandir a tabela da lista:** mais rápido, mas dificulta acrescentar histórico acadêmico com controle de permissão por URL.
- **Página própria e escopada por escola (escolhida tecnicamente):** permite link direto autorizado e tratamento claro de registro fora do escopo, sem novas migrações.
- **Cadastro com edição:** prematuro antes de fluxo auditado de gravação e implantação segura; fica fora deste incremento.

## Comportamento e segurança

- Rota GET `/m/{municipio}/escolas/{escola}/alunos/{aluno}`, autenticada, com middleware de município e parâmetros UUID.
- A escola deve estar ativa e autorizada por `SchoolPolicy`. Aluno só é encontrado quando há vínculo ativo **naquela mesma escola**; aluno de outra escola ou vínculo encerrado retorna 404. Escola de acesso negado retorna 403. Usuário sem sessão vai ao login.
- Tela mostra somente nome, ano de nascimento (ou `Não informado`), escola atual e anos letivos vinculados ativamente à escola. Não expor CPF, RG, dados médicos, identificadores nos textos ou vínculos de outras escolas.
- Lista de alunos ganha link por nome. Os nomes são escapados pelo Blade e o retorno para a lista é simples.
- Sem POST, PUT, PATCH ou DELETE, sem banco original ou dados de menores reais.

## Verificações

Testes Laravel de acesso permitido, acesso negado, vínculo encerrado, HTML escapado, link da lista, visitante e isolamento de tenants. CI PostgreSQL 17 com testes PHP duas vezes, Pint, UI e integração MariaDB → PostgreSQL.
