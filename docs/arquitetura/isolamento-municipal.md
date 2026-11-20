# Isolamento de dados municipais - fundação técnica

Data da decisão: 20/11/2026

## Motivação

O Astuti Gestão Escolar será contratado por diferentes municípios. Dados de um município não podem ser consultados, alterados ou confundidos com dados de outro, mesmo quando um processo de aplicação atende operações de redes diferentes em sequência.

## Fronteiras

- **Central `platform`:** metadados dos municípios, seu identificador técnico de banco (`db_key`), situação de provisão e tentativas de preparação. O banco central **não é substituído** quando se entra em um município.
- **Municipal `tenant`:** escola e, nos próximos incrementos, aluno, matrícula, documentos e demais dados escolares. Cada município usa base MariaDB distinta.
- **Escola:** haverá autorização adicional dentro da base municipal, definida junto com os papéis e vínculos de usuários.

## Decisões implementadas

1. `config/tenancy.php` não contém senhas nem nomes de bancos reais. A infraestrutura segura de implantação deverá registrar um mapa privado `db_key -> configuração MariaDB`. Um `db_key` desconhecido ou inválido é recusado.
2. A conexão `tenant` fica inutilizável por padrão. `TenantConnectionManager::activate()` abre apenas uma conexão configurada e `reset()` elimina a conexão anterior. O campo `db_key` tem unicidade no banco central e a ativação rejeita configurações que apontam para o mesmo destino físico declarado (host, porta, socket e banco). Isso é defesa contra erro de configuração, não substitui auditoria das credenciais na infraestrutura.
3. `TenantContext::enter()` exige município persistido e com estado `active`. `runFor()` usa `finally` para liberar a conexão, inclusive após exceção.
4. Modelos escolares herdam `TenantModel`, que recusa consultas sem contexto ativo.
5. O comando `astuti:municipio-provisionar {slug}` migra a base municipal previamente configurada, com estados `provisioning`, `active` e `failed`. Cada execução deixa um registro sem credenciais em `municipality_provision_attempts`.
6. A ferramenta de testes cria três bases MariaDB **descartáveis**. O teste realiza a sequência A, B, A no mesmo processo, consulta escolas de nomes iguais com IDs diferentes e confirma que a conexão central continua independente.

## Decisão importante: rotas web continuam fechadas

O plano inicial da Tarefa 2 previa provar HTTP 200 em `/m/{municipio}/escolas` para dois municípios e HTTP 403 para um usuário sem vínculo, mas também proibia abrir rotas antes de concluir a autenticação central e os vínculos da Tarefa 3.

**Decisão:** nesta etapa são testados os mecanismos de banco diretamente e **nenhuma rota de leitura escolar é publicada**. Os testes HTTP de autenticação, vínculo ativo, impedimento de troca de URL, autorização por escola e sequência A, B, A entre requisições serão implementados com a Tarefa 3. O custo dessa decisão é que o isolamento **end-to-end por usuário ainda não está demonstrado**.

## Requisitos operacionais restantes

- Configuração privada do catálogo de credenciais por município, preferencialmente cofre de segredos na produção.
- Banco de teste central e dois bancos de teste municipais distintos, com nomes terminados em `_test`, usuário de teste sem privilégios em bases reais.
- Migrações centrais aplicadas deliberadamente antes da primeira provisão. Nunca apontar os testes de integração para bases municipais operacionais.
- Autenticação, vínculos e escopos por escola, com negação padrão. Até lá, nenhuma rota publica dados escolares.
- Auditoria operacional complementar, backups por município, restauração ensaiada e mecanismos de acesso excepcional para suporte técnico, todos em incrementos posteriores.

A presença desta infraestrutura não autoriza uso de dados reais nem implantação em produção.
