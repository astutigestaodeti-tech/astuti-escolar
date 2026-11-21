# Projeto Astuti Gestão Escolar - PostgreSQL e instalações independentes

**Data:** 20/11/2026  
**Estado:** proposta de arquitetura, aguardando aprovação do Jefferson  
**Base técnica:** `feature/incremento-03-cadastro-importacao` (`c431c980`), dependente dos PRs #3 e #4 ainda abertos.

## 1. Objetivo e decisões

O Astuti Gestão Escolar terá um único código-fonte, mantido pela Astuti, e uma **instalação de produção totalmente independente por Prefeitura contratante**. O PostgreSQL será o único SGBD suportado nas novas instalações. O desenvolvimento e os testes permanecerão no notebook Windows 11 com Debian/WSL, sem contratar infraestrutura de produção antes de existir cliente.

Cada Prefeitura receberá um VPS exclusivo, preferencialmente em datacenter profissional, com instância própria de Laravel/PHP, PostgreSQL, identidade de configuração, contas, arquivos, domínio, certificado TLS, monitoramento e backups externos. VPS exclusivo significa máquina virtual e **não** garantia de hardware físico dedicado. Exigências contratuais específicas poderão justificar servidor físico dedicado.

A Astuti distribuirá a mesma versão do aplicativo por um processo de implantação reproduzível; não haverá banco de dados de alunos, autenticação ou sessão compartilhado entre Prefeituras. Mudanças em uma instalação não poderão atingir outra.

## 2. Escolha de implementação e alternativas

**Escolhida: instalações isoladas por VPS, dois bancos PostgreSQL locais.** Em cada implantação, um banco `platform` guarda usuários, permissões, cadastro da própria Prefeitura, sessões e auditoria central, e um banco municipal `tenant` guarda escolas, anos, turmas, alunos, matrículas e auditoria escolar. Os dois bancos permanecem separados para aproveitar os limites de segurança já implementados; ambos pertencem exclusivamente àquele VPS/cliente. Em produção haverá somente um município configurado para aquela implantação. Continuaremos testando a sequência A/B/A em bancos descartáveis para prevenir regressão de isolamento durante a transição.

**Alternativa A: um VPS com um único banco por Prefeitura.** Reduz o número de bancos, mas obrigaria refatoração simultânea das conexões, modelos, migrações e testes já existentes; será reavaliada somente se houver vantagem demonstrada.

**Alternativa B: plataforma compartilhada com vários municípios e bancos separados.** Menor custo por cliente, porém contradiz a decisão comercial de instância independente e aumenta o impacto potencial de um incidente; rejeitada.

**Alternativa C: servidor físico dedicado para cada Prefeitura.** Oferece separação física de equipamento, mas impõe custo e operação maiores. Fica disponível somente quando necessário ou exigido por edital.

## 3. Escopo da migração

1. Criar uma branch nova baseada no trabalho mais recente do Incremento 03, sem alterar nem mesclar os PRs #3 e #4 sem autorização.
2. Alterar a conexão `platform` e o gerenciador de `tenant` para PostgreSQL, com configuração explícita de host, porta, nome e credenciais. Sem URL de conexão não auditada, banco desconhecido ou acesso municipal fora de contexto.
3. Adaptar migrations, constraints, índices, transações, locks, tipos de dados, consultas específicas do SGBD e provisionamento de Monte Sereno.
4. Substituir as duas migrações de gatilhos de auditoria em sintaxe MariaDB por funções/gatilhos PostgreSQL; impedir UPDATE, DELETE e TRUNCATE de auditorias em operação normal. Separar privilégios de aplicação e de manutenção, sem conceder superusuário ao processo web. As rotinas de limpeza dos **bancos descartáveis de teste** terão caminho explícito e nunca poderão atingir produção.
5. Preparar uma migração **não destrutiva** para copiar e conferir os dados fictícios das bases MariaDB existentes no notebook para novas bases PostgreSQL, incluindo cadastros, relacionamentos, históricos e hashes de senha quando íntegros. Não recriar ou renovar senhas silenciosamente. Para cada base verificar pré-requisitos, contagem por tabela, identidades, chaves e referências; interromper e reportar qualquer divergência antes do corte.
6. Preservar bases MariaDB originais e backup verificável até completar testes, validação funcional e possibilidade de retorno. Não copiar segredos, dumps nem registros pessoais para o repositório GitHub.
7. Atualizar o workflow GitHub Actions para três bases PostgreSQL efêmeras, PHP com `pdo_pgsql` e suíte repetida duas vezes, com Pint, build da interface e testes visuais.
8. Documentar comandos de instalação e configuração no Debian/WSL, preparação do ambiente Monte Sereno v2, restauração de backup e implantação de uma Prefeitura por VPS.

## 4. Segurança, operação e implantação

- Segredos separados por Prefeitura e fora do Git; PostgreSQL não exposto à internet pública.
- Cada VPS terá usuário de aplicação com permissões mínimas, firewall, TLS/HTTPS, atualizações de segurança, trilhas de auditoria e registro de incidentes.
- Backups automáticos criptografados, guardados fora do próprio VPS, com retenção definida no contrato e ensaio periódico de restauração. Backup local isoladamente não é suficiente.
- Cada atualização da Astuti passará por testes e será aplicada por cliente, com janela controlada, backup prévio e mecanismo de retorno. Versões serão rastreáveis no GitHub.
- A infraestrutura e o domínio de cada contrato serão definidos depois. Este incremento **não** contrata servidores, não usa dados reais de estudantes e não publica a aplicação na internet.

## 5. Critérios de aceite

1. PostgreSQL é o único banco de produção usado pela instalação `platform` e pela conexão `tenant`; nenhuma conexão MariaDB necessária para executar o sistema migrado.
2. Cada implantação aceita exclusivamente sua Prefeitura configurada. Uma instalação não possui credenciais, conexão, sessão ou arquivos de outra Prefeitura.
3. A conexão municipal começa bloqueada e é sempre liberada no encerramento da operação, inclusive diante de exceções. Testes automáticos preservam o isolamento A/B/A.
4. O ambiente fictício Monte Sereno v2 exibe exatamente **3 escolas, 115 alunos e ano letivo 2027**, com login e consulta funcional após migração.
5. Auditoria de plataforma e municipal continua protegida contra UPDATE, DELETE e TRUNCATE, inclusive SQL bruto, preservando autor e motivo dos eventos de histórico escolar.
6. Dados MariaDB existentes são copiados com validação documentada, sem alteração das bases originais e sem perda silenciosa de dados ou de senha; inconsistências bloqueiam o corte.
7. A suíte atual e os testes novos passam **duas vezes consecutivas** em três bancos PostgreSQL descartáveis; Pint, build e verificações de interface ficam verdes.
8. Há documentação reproduzível para notebook Debian e para uma implantação isolada em VPS, incluindo backups e restauração testada.

## 6. Fora de escopo e dependências

- Não criar funcionalidades escolares novas antes de concluir a migração.
- Não contratar VPS nem migrar dados reais nesta etapa.
- Não mesclar PR #3 ou PR #4 automaticamente. O trabalho será apresentado em um novo PR dependente da base atual, com revisão antes de qualquer merge.
- Ajustes de performance, dimensionamento inicial de VPS, provedor, domínio, faturamento, licenciamento e eventual painel de administração multicliente ficam para etapa própria.
- A migração de dados do **notebook do Jefferson** exige execução local controlada, pois o banco está em seu Debian/WSL e não deve ser acessado externamente.

## 7. Decisões deixadas ao implementador

- Estrutura interna de scripts de migração e relatórios, desde que sejam reexecutáveis, verifiquem pré-condições e nunca apaguem o MariaDB.
- Organização de testes e de comandos CLI de desenvolvimento, desde que cubram todos os critérios acima.
- Ferramenta de implantação automatizada do MVP, dando preferência a soluções simples e auditáveis, sem fornecedor obrigatório.
