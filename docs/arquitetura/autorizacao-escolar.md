# Acesso central e autorização escolar

Data da decisão: 20/11/2026

## O que esta etapa disponibiliza

- Usuários são autenticados pelo Laravel usando a tabela platform.users no banco MariaDB central.
- A tabela memberships associa um usuário a um município e a uma função. Cada vínculo deve estar ativo.
- A tabela school_scopes lista as escolas autorizadas para cada vínculo. Os UUIDs apontam para registros da base do município correspondente.
- O perfil municipal_manager tem leitura de todas as escolas de seu município. Os perfis school_director, school_secretary e teacher precisam de escola explicitamente autorizada.
- Conta inativa, vínculo inativo, papel desconhecido ou vínculo sem escola autorizada resultam em acesso negado.
- A rota municipal fica atrás do login. O middleware valida o vínculo **antes** de abrir a conexão municipal. A conexão é encerrada mesmo em caso de exceção.
- O detalhe da escola consulta apenas a base do município autorizado e passa pela política de acesso por escola.
- Não existe cadastro público, listagem pública de usuários ou atalho de suporte Astuti para abrir dados municipais.

## Superfície publicada

- GET /login e POST /login: formulário e autenticação de sessão com limite por e-mail e IP.
- POST /logout: encerra e invalida a sessão.
- GET /m/{municipio}/escolas: lista apenas nomes e IDs das escolas autorizadas.
- GET /m/{municipio}/escolas/{escola}: exibe apenas nome da escola autorizada.
- GET /saude: estado mínimo da aplicação.

Não existem rotas de escrita escolar, fichas de estudantes, documentos, matrículas, consultas de CPF ou arquivos privados nesta etapa.

## Obrigações ainda não concluídas

1. Implantar **segundo fator de autenticação real (2FA)**, preferencialmente o recurso oficial Laravel Fortify com TOTP, antes de habilitar operações privilegiadas. A política de criação escolar permanece negando acesso.
2. Criar convites e gestão de contas e vínculos por administrador autorizado. As contas e os vínculos atuais foram criados apenas em testes sintéticos.
3. Configurar HTTPS, cookies seguros, parâmetros de sessão e auditoria operacional para o ambiente real.
4. Validar permissões detalhadas por turma, docente e prontuário com uma rede piloto.
5. Executar homologação com infraestrutura privada e revisão independente de segurança antes de qualquer dado real.

**Limite:** a autenticação básica e os testes de acesso não significam serviço pronto para escolas reais. O segundo fator e as rotinas auditadas de escrita continuam pendentes.

## Verificação

Os testes TenantAccessTest, SchoolScopeTest e LoginSessionTest exercitam login, bloqueio por vínculo, autorização por escola e sequência A, B, A, usando três bancos MariaDB descartáveis terminados em _test.

A instalação Debian do usuário não foi alterada. O código permanece em pull request de desenvolvimento, sem merge para main.
