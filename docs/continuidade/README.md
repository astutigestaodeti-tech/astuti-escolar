# Astuti Gestao Escolar - Ponto de retomada

**Ultima revisao:** 21/11/2026  
**Responsavel:** Jefferson Astuti  
**Repositorio oficial:** `astutigestaodeti-tech/astuti-escolar` (privado)  
**Registro cronologico:** [HISTORICO.md](HISTORICO.md)

> Este documento serve para continuar o desenvolvimento em qualquer novo chat. O estado descrito e datado e deve ser confrontado com o GitHub e o notebook antes de qualquer mudanca.

## 1. Mensagem pronta para novo chat

> Quero continuar o projeto **Astuti Gestao Escolar**. Acesse o repositorio privado `astutigestaodeti-tech/astuti-escolar`, leia `AGENTS.md`, `docs/continuidade/README.md` e `docs/continuidade/HISTORICO.md`, alem dos planos relacionados a etapa atual. Confira branches, PRs, commits, testes e o estado local do meu notebook antes de propor mudancas. Nao repita etapas comprovadamente concluidas, nao exponha credenciais, nao use dados reais de alunos e nao execute operacoes destrutivas. Depois de cada marco, atualize esses documentos no GitHub com evidencias e o proximo passo. Responda em portugues brasileiro e nunca utilize travessao longo.

## 2. Objetivo do produto

O Astuti Gestao Escolar e um software comercial para redes municipais publicas de ensino, com interface simples e acessivel. Seu principal diferencial e reduzir redigitacao na implantacao: importar planilhas e documentos, preservar originais, identificar inconsistencias e duplicatas e exigir **conferencia e aprovacao humana autorizada** antes de gravar dados oficiais.

Ordem funcional: alunos atuais, responsaveis, escolas, turmas e matriculas; depois documentos atuais; em seguida acervo historico, vida escolar, Educacenso e modulos complementares. OCR de PDF/imagens e reconstrucao de historicos sao etapas posteriores. Nunca inventar dados escolares ou gerar segunda via oficial sem validacao institucional.

**Homologacao ficticia:** municipio Monte Sereno v2, com 3 escolas, 115 alunos sinteticos e ano letivo 2027, alem de municipio ficticio de contraste para testes de isolamento. Nenhum dado real de aluno pode entrar no ambiente demonstrativo.

## 3. Arquitetura aprovada em 20/11/2026

- Uma unica base de codigo em PHP 8.4.1+ e Laravel 13, com Blade, Tailwind/Vite, PHPUnit e GitHub Actions.
- **PostgreSQL** como banco do produto em desenvolvimento e nas novas instalacoes. O MariaDB anterior e apenas fonte de migracao/retorno, nao dependencia de producao.
- **Um VPS independente por Prefeitura contratante**, cada um com sua aplicacao, dominio, arquivos, backups, contas, banco `platform` e banco `tenant` PostgreSQL. Nao ha compartilhamento de dados ou sessoes entre Prefeituras.
- Desenvolvimento inicial no notebook Windows 11 Pro com Debian 13 no WSL2. **Nenhum VPS contratado ate a confirmacao do usuario em 21/11/2026.** Infraestrutura de producao sera decidida futuramente.
- Desenvolvimento no GitHub privado empresarial. Segredos e arquivos reais ficam fora do Git.
- Protecao de dados de menores, autorizacao por municipio/escola, 2FA para escritas sensiveis, trilhas de auditoria, arquivos privados, backups externos e testes de restauracao antes de qualquer piloto real.

## 4. Estado comprovado no GitHub em 21/11/2026

| Item | Estado verificado |
| --- | --- |
| Repositorio | `astutigestaodeti-tech/astuti-escolar`, privado |
| `main` antes deste registro | `edf52e89` - base antiga do projeto, sem receber ainda os incrementos em PR |
| Branch de trabalho mais recente | `feature/migracao-postgresql-instalacoes-isoladas` |
| Cabeca da branch remota verificada nesta etapa | `5024059` (Tasks 1-4 concluidas e revisadas: migracao sintetica MariaDB->PG no CI, recuperacao, repetibilidade e runbooks; PR #5 ainda draft) |
| PR #1 | `feature/incremento-01-saude` para `main`, rascunho/aberto |
| PR #3 | Incremento 02 para Incremento 01, rascunho/aberto |
| PR #4 | Incremento 03 para Incremento 02, rascunho/aberto |
| PR #5 | PostgreSQL e VPS por Prefeitura para Incremento 03, rascunho/aberto |
| CI na branch PostgreSQL | PR run final `44870385911` em `5024059`: integracao MariaDB+PG real sintetica 2 x 22 testes/228 assercoes; suite PHP 2 x 165/606; Pint 128 arquivos, Vite e 4 testes UI verdes. Revisao global PASS |

O sucesso do GitHub Actions comprova as verificacoes automatizadas executadas por aquele workflow, **nao** a transferencia concluida dos bancos do notebook nem a homologacao do sistema para Prefeituras.

Documentos tecnicos atuais, localizados na branch do PR #5:

- `docs/superpowers/specs/2026-11-20-postgresql-instalacoes-isoladas-design.md`
- `docs/superpowers/plans/2026-11-20-postgresql-instalacoes-isoladas.md`
- `docs/operacao/instalacao-postgresql-debian.md`
- `docs/operacao/migracao-mariadb-postgresql.md`
- `docs/operacao/vps-prefeitura.md`

**Atencao:** titulos ou campos antigos desses documentos podem dizer `proposta` ou `aguardando aprovacao`; Jefferson aprovou posteriormente a migracao PostgreSQL e a arquitetura por Prefeitura. Reavaliar futuras mudancas estruturais antes de implementa-las.

## 5. Estado comprovado no notebook em 21/11/2026

Evidencia: capturas do terminal Debian enviadas pelo usuario em conversa de continuidade.

| Verificacao | Resultado visto |
| --- | --- |
| Diretorio do projeto | `/home/aflora/astuti-escolar` |
| Sistema | Debian GNU/Linux 13 (trixie) em WSL2 no Windows 11 Pro |
| PHP e extensoes | 8.4.28; `pdo_pgsql`, `pgsql` e `pdo_mysql` presentes |
| Composer | 2.8.8 |
| PostgreSQL | 17.12; `127.0.0.1:5432` aceitando conexoes; servico ativo |
| Nginx | Ausente, o que nao impede desenvolvimento local com `artisan serve` |
| Branch local no ultimo print | `feature/migracao-postgresql-instalacoes-isoladas`, HEAD `5024059` apos fast-forward na captura 73; arvore limpa antes do pull |
| PostgreSQL Laravel | Conexao `platform`, banco `astuti_platform_dev`, host `127.0.0.1`, porta `5432`, usuario `astuti_app` |
| Banco central | 11 tabelas exibidas por `php artisan db:show` |
| Migracoes centrais | Captura 72: 11 migrations `Ran`, sendo `jobs` executada em batch 2; tabelas `jobs`, `job_batches`, `failed_jobs` presentes |
| Banco municipal PostgreSQL | `astuti_monte_sereno_dev` confirmado, com 7 tabelas de estrutura escolar, auditoria e migrations |
| Contagens PostgreSQL verificadas | Central: `municipalities`, `users`, `memberships` com 0; municipal: escolas, anos, turmas, alunos e matriculas com 0 |
| MariaDB de origem | 3 bases `astuti_*_dev` confirmadas; 12 tabelas de origem comparadas as restauracoes, com contagens iguais (captura 70) |
| Bancos restaurados MariaDB | Capturas 69 e 70: `astuti_restore_platform_20261120_235929_30385` e `astuti_restore_monte_20261120_235929_30385`; 12 de 12 tabelas com contagens iguais as origens |
| Dry-run atual PostgreSQL | Captura 75: `astuti:migrar-dados-postgresql` sem `--executar` aprovado no HEAD `5024059`, 12 tabelas conferidas; nenhuma gravacao |
| Backups MariaDB | Captura 68: dois dumps `.sql.gz` localizados em `~/astuti-backups/mariadb-20261120-234757/`, tamanhos 4,7 KB e 17 KB, ambos aprovados em `gzip -t`; sem alteracao |
| Backups PostgreSQL | Captura 72: dumps `-Fc` dos bancos central e municipal criados fora do Git em `~/astuti-backups/pgsql-antes-jobs-*/`; arquivos legiveis via `pg_restore -l` e com SHA-256; restauracao completa ainda nao ensaiada |

**Sincronizacao concluida em 21/11/2026:** o notebook estava no commit `ec78138`, quatro commits atras do remoto. Jefferson executou `git pull --ff-only` e comprovou HEAD `a27be7b1`, branch correta e arvore limpa. O historico detalhado esta em `HISTORICO.md`.

**Atualizacao posterior comprovada nas capturas 73-75:** fast-forward de `a27be7b1` para `5024059`; o diagnostico encontrou configuracoes PostgreSQL corretas e apenas variaveis temporarias de usuario/senha da origem MariaDB ausentes da primeira invocacao. Ao informa-las apenas para o comando, a simulacao em `5024059` foi **aprovada**, conferindo as 12 tabelas e 115 alunos ficticios, sem qualquer gravacao. O proximo passo e conferir backups PG e MariaDB e ensaiar restauracao em bancos isolados, nunca nos `_dev`.

As capturas confirmam a estrutura das sete tabelas municipais PostgreSQL, os dois dumps GZIP, a existencia das restauracoes MariaDB e a igualdade de contagens em 12 tabelas. **A simulacao de migracao sem `--executar` no commit `a27be7b1` foi aprovada em 21/11/2026, sem gravacoes.** Nao foi comprovada a copia efetiva para o PostgreSQL, a validacao linha a linha, o login ou os testes locais completos. **Nao executar importacao efetiva, `migrate:fresh`, `db:wipe` ou seed de demonstracao antes da revisao tecnica e autorizacao.**

## 6. Proximo passo seguro

1. **Concluido:** branch local `feature/migracao-postgresql-instalacoes-isoladas` atualizada por fast-forward ate `5024059` na captura 73; bancos PostgreSQL central e municipal preservados e sem transferencia de dados.
2. **Concluido em capturas 68-70:** dois dumps MariaDB GZIP integros, restauracoes `astuti_restore_platform_20261120_235929_30385` e `astuti_restore_monte_20261120_235929_30385` preservadas; contagens iguais nas 12 tabelas comparadas. Nunca recriar, apagar ou sobrescrever origens e backups sem motivo e autorizacao.
3. **Concluido em captura 70:** simulacao `php artisan astuti:migrar-dados-postgresql` sem `--executar` aprovada no codigo `a27be7b1`, com 1 municipio, 1 usuario, 3 escolas, 115 alunos, 3 turmas, 115 matriculas e 237 auditorias municipais. Nenhum dado foi gravado.
4. **Revisao tecnica pre-corte em 21/11/2026:** comando restringe ambiente, confere conteudo e hashes, mas commits nos dois bancos PG nao sao atomicamente conjuntos. CI do PR #5 passou, porem nao executa transferencia real MariaDB->PG. Nao assumir copia concluida nem prontidao para producao.
5. **Concluido em captura 71:** `migrate:status` confirmou apenas a migration central `0001_01_01_000002_create_jobs_table` como `Pending` e outras dez como `Ran`. Consulta `to_regclass` ao PostgreSQL central confirmou `jobs`, `job_batches` e `failed_jobs` todas `AUSENTE`; nenhuma tabela foi modificada.
6. **Concluido em captura 72:** backups PostgreSQL central e municipal criados e conferidos pelo indice do arquivo; aplicacao da migration `jobs` em PostgreSQL central com `DONE`, status `Ran` e tres tabelas fisicamente presentes. **Proximo passo:** preparar e executar testes de integracao de copia MariaDB->PG em destinos descartaveis, ensaiar rollback e falha entre commits e validar relacionamentos, hashes, login e auditoria. Nunca usar os destinos principais como primeiro teste de transferencia.
7. **Tasks 1-4 implementadas e revisadas em 21/11:** o CI usa MariaDB 11.8 e PostgreSQL 17 descartaveis com bancos `_test`, realiza copia real de fixture Monte Sereno, compara 12 tabelas, UUIDs, hashes e relacoes, demonstra rollback, falha entre commits, recusa retry e retomada num novo par. O run final `44870385911` aprovou 2 x 22 testes/228 assercoes na integracao e 2 x 165/606 na suite geral, Pint, Vite e UI. Revisao global: PASS, com runbook corrigido para manter dumps MariaDB anteriores e exigir dois dumps PG antes do corte. **Proximo passo: conferencias no notebook e ensaio de restauracao em bancos isolados**, somente apos sincronizar branch com seguranca. O notebook esta no ultimo HEAD comprovado `5024059` e teve novo dry-run aprovado (captura 75), sem copia para PG real. Nao executar `--executar` sem nova verificacao local e autorizacao especifica.
8. **Nao executar `--executar` nos destinos principais nem mesclar PR #5 nesta etapa.** Fazer nova revisao e obter autorizacao especifica depois dos testes. Manter PRs anteriores em rascunho ate os criterios de aceitacao.

## 7. Como manter este registro util

A cada marco ou fim de sessao relevante:

1. Acrescentar ao `HISTORICO.md` uma entrada com data, objetivo, decisoes, evidencias, commits/PRs, testes, resultado, pendencias e proximo comando seguro.
2. Atualizar neste arquivo apenas a fotografia atual: codigo, banco, ambiente e proximo passo, distinguindo fatos observados de tarefas previstas.
3. Fazer commit com mensagem clara, preferencialmente `docs(continuidade): atualizar estado em AAAA-MM-DD`.
4. Revisar se nenhum segredo, dado escolar real, dump ou informacao pessoal de outros assuntos foi incluido.

**Limite importante:** o ChatGPT nao arquiva automaticamente todas as mensagens de todos os chats no GitHub. Este registro tecnicamente relevante deve ser atualizado pelo assistente quando houver acesso autorizado ao repositorio ou por um processo de sincronizacao que venha a ser implementado e autorizado. Ele nao substitui o historico integral de conversas.


## 8. Estado mais recente em 21/11/2026 (prevalece sobre os marcos historicos acima)

1. **No notebook, comprovado pela captura 81:** o codigo local estava no HEAD `574401b1` da branch `feature/migracao-postgresql-instalacoes-isoladas`. Os dois backups PostgreSQL atuais foram criados e validados em `~/astuti-backups/pgsql-pre-copia-hNtsqqkz/` e restaurados em bancos de teste independentes. Central: 11 migrations, 0 municipios e 0 usuarios. Municipal: 8 migrations, 0 escolas e 0 alunos.
2. **No GitHub, preparado posteriormente:** novo roteiro `scripts/operacao/ensaiar-copia-isolada-postgresql.sh` e respectivos testes. A branch avancou ate o commit documental `6a7b012`; o codigo da rotina e os testes passaram no workflow `44908611752` (ambos os jobs `success`). Instrucoes em `docs/operacao/migracao-mariadb-postgresql.md` e plano em `docs/superpowers/plans/2026-11-21-ensaio-copia-cluster-isolado.md`.
3. **Nao concluido:** a transferencia real dos 115 estudantes ficticios MariaDB para PostgreSQL ainda nao ocorreu na maquina. Nao ha evidencia de validacao de login no banco de ensaio e nem autorizacao de corte para os PostgreSQL `*_dev` originais. PR #5 permanece em rascunho. Nenhum VPS contratado.
4. **Proxima acao:** no Debian do notebook, executar um unico comando e enviar a captura do resultado:

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/ensaiar-copia-isolada-postgresql.sh
```

Este script prepara um **cluster PostgreSQL distinto**, confere sua identidade e conexoes Laravel efetivas e so entao faz copia ficticia nele. Preserva todos os backups anteriores, nao modifica a origem MariaDB nem os bancos PostgreSQL originais da porta 5432. Consultar `HISTORICO.md` para evidencias, limites e cuidados antes de qualquer proxima etapa.


## 9. Captura 82: correção do cluster de ensaio (21/11/2026)

- A primeira execução do novo roteiro chegou a criar o cluster de ensaio `astuti_ens_20261121_103015_6876` na porta 55432, porém **não iniciou o PostgreSQL** devido a configuração inválida `listen_addresses = 127.0.0.1` sem aspas.
- Como o erro ocorreu antes da restauração, **nenhuma cópia de alunos foi realizada**, e os bancos PostgreSQL originais e os backups foram preservados. O cluster mal configurado ficou parado, sem exclusão automática.
- TDD e revisão corrigiram o roteiro para `listen_addresses = localhost` (loopback), com teste de regressão. Commits do patch na branch de trabalho: `0056f65`, `cf7a046`; documentação `f908b61`. **CI após o patch aprovado:** run `44909385490`, ambos os jobs `success`.
- **Próxima ação depois do CI:** executar novamente o comando único documentado na seção 8, que criará um cluster diferente numa nova porta livre. Enviar a saída completa. Não usar `--executar` manualmente nos bancos originais.

Para detalhes técnicos, consulte a última entrada de `HISTORICO.md`.


## 10. Captura 83: copia isolada falhou depois da simulacao (21/11/2026)

- Segundo cluster isolado `17/astuti_ens_20261121_103538_6170` na porta **55433** criado e iniciado com sucesso. Dumps restaurados no cluster isolado. Simulacao aprovada, com **115 alunos ficticios** na origem MariaDB.
- Na tentativa de copia, a CLI exibiu somente erro generico de precondicao/integridade, **sem revelar a excecao causadora**. Nao considerar os dados copiados. O cluster 55433 foi parado e preservado; os bancos PostgreSQL originais e os backups foram mantidos, segundo o script.
- **Proximo passo substitui temporariamente o comando da secao 8:** realizar apenas **diagnostico de log do cluster parado**, sem novo ensaio. Se nao houver log de erro SQL, corrigir instrumentacao segura do Laravel com TDD antes de nova tentativa. Nao rodar `--executar` manualmente nem eliminar clusters de ensaio. O PR #5 nao deve ser mesclado ainda.


## 11. Captura 84 e diagnostico de excecao seguro (21/11/2026)

- Leitura do log do cluster isolado 55433 exibiu `NENHUM ERRO SQL REGISTRADO`. Isso nao identifica a causa da falha da copia da captura 83 nem prova que nao houve erro SQL; a CLI anterior ocultou o detalhe.
- Roteiro e Laravel atualizados na branch de trabalho ate o commit **`c762abb3`** para relatar a **ultima etapa/tabela iniciada** e uma causa **sanitizada** do erro somente no cluster isolado autorizado. Nenhuma funcionalidade escolar foi marcada como concluida e a migracao principal nao ocorreu.
- **CI aprovado no commit `c762abb3`**: run [44911118712](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44911118712), ambos os jobs `success`, incluindo PHP, integracao MariaDB/PostgreSQL, Pint e interface. Houve revisao independente sem achado critico comprovado; ela apontou lacunas de cobertura da CLI e testes de borda do gate como trabalho pendente.
- **Proxima acao segura:** sincronizar a branch no Debian e executar novamente o roteiro completo de ensaio isolado, com criacao de outro cluster em porta livre; conferir exclusivamente o resultado e diagnostico. Nao executar `--executar` fora do roteiro, nao alterar bancos originais e nao mesclar PR #5. Preservar os clusters 55432 e 55433 para auditoria.


## 12. Captura 85: divergencia identificada na auditoria central (21/11/2026)

- Terceiro cluster de ensaio `17/astuti_ens_20261121_105834_239` na porta **55434**. Restauracao de dumps e simulacao MariaDB foram aprovadas.
- A copia foi interrompida ao **conferir `platform_audit_logs`**, depois de conferir as primeiras cinco tabelas centrais. A divergencia de conteudo dos 7 eventos de auditoria ainda precisa ser identificada por coluna; nao alterar dados ou afrouxar a validacao.
- Nova instrumentacao tipada e sanitizada foi implementada na branch do PR #5 ate `296db3a`, com teste vermelho no CI `44911879568`. Informa apenas nomes de campos divergentes, quando rodando o roteiro no cluster isolado autorizado. A causa raiz e a copia completa **continuam pendentes**.
- **CI aprovado:** [44912032171](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44912032171), dois jobs aprovados, 174 testes PHP, duas rodadas de integracao, Pint e interface. A seguir, atualizar a branch no Debian, rodar somente `scripts/operacao/ensaiar-copia-isolada-postgresql.sh` e compartilhar o aviso `Campos divergentes`. Manter clusters 55432, 55433 e 55434, backups e PG originais intactos. Sem corte ou merge.


## 13. Revisao final da captura 85 e ponto de retomada

- **Branch do PR #5 atualizada ate `235846c`.** Revisao independente sem achado critico nos arquivos novos; dois ajustes de fidelidade do diagnostico foram reproduzidos com testes vermelhos e corrigidos: linha ausente e JSON numerico equivalente.
- **CI final verde:** [44912663585](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44912663585), dois jobs aprovados, 176 testes PHP, duas integracoes, shell, Pint e interface.
- **Nao concluido:** copiar os dados ficticios de Monte Sereno; descobrir a coluna de `platform_audit_logs` que divergiu na captura 85; conferir o resultado de uma transferencia completa; cortar para os PG originais; mesclar PR #5.
- **Proximo comando seguro no Debian:** estando na branch correta com a arvore Git limpa, fazer `git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas` e executar **apenas** `bash scripts/operacao/ensaiar-copia-isolada-postgresql.sh`. O roteiro validara e usara cluster temporario novo, fora da porta 5432. Compartilhar a linha `Diagnostico do cluster isolado: ... Campos divergentes: ...`, se aparecer. A lista de colunas e pista para reproduzir em testes, nao prova automatica de perda ou mudanca de dados.
- **Proibido nesta etapa:** executar a copia diretamente nos bancos `*_dev` da porta 5432, eliminar os tres clusters de ensaio anteriores sem auditoria ou fazer merge/corte. O gate de exibicao do diagnostico nao substitui a selecao fisica do cluster pelo roteiro.


## 14. Captura 86: causa temporal reproduzida e patch pronto para ensaio (21/11/2026)

- Cluster de ensaio numero quatro `17/astuti_ens_20261121_111614_25474`, porta **55435**. Restauracao e simulacao aprovadas; tentativa de copia interrompida ao conferir **`platform_audit_logs.occurred_at`**. Nao houve corte nem transferencia completa dos 115 alunos nos PG originais.
- **Causa reproduzida no CI real MariaDB/PostgreSQL:** frações de microssegundos equivalentes como `.123400` (MariaDB) e `.1234` (PostgreSQL) eram tratadas como diferentes. O teste com sete auditorias falhou antes do patch no workflow `44913274817`.
- **Correção aplicada:** commit `f0735cb` da branch `feature/migracao-postgresql-instalacoes-isoladas`. O comparador remove apenas zeros finais da representação textual, preservando todos os microssegundos significativos. Revisão independente favorável ao ensaio isolado, sem bloqueadores críticos/importantes. Ficou apontada melhoria menor futura: restringir a normalização a colunas temporais conhecidas.
- **CI final verde:** [44913413409](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44913413409), dois jobs success, 177 testes PHP, 24 testes integrados em cada uma de duas rodadas, shell, Pint e interface.
- **Próximo comando no notebook Debian:** `cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/ensaiar-copia-isolada-postgresql.sh`. O roteiro criará outro cluster isolado fora da porta 5432. Se aprovado, conferir contagens de auditorias, alunos e logins no cluster isolado antes de autorizar qualquer etapa posterior; se falhar, registrar ultima etapa/diagnostico e interromper. Não chamar `--executar` manualmente nos bancos originais, nao eliminar clusters 55432-55435, nao mesclar PR #5.


## 15. Captura 87: transferencia ficticia concluida em PostgreSQL isolado (21/11/2026)

- **Sucesso observado no notebook:** o cluster de ensaio `17/astuti_ens_20261121_112332_22176`, porta **55436**, recebeu a transferencia MariaDB -> PostgreSQL no roteiro de isolamento. A saida conferiu ambas as bases, inclusive as duas tabelas de auditoria; resultado final **1 municipio, 3 escolas, 115 alunos ficticios**, com 115 matriculas, 7 auditorias centrais e 237 municipais na origem testada.
- O script preserva o cluster de ensaio e o **para ao finalizar**. Os bancos PostgreSQL originais da porta **5432**, o MariaDB e os backups nao foram alterados pelo ensaio, segundo a saida. **Nao ocorreu migracao definitiva.**
- **Proximo teste funcional, ainda pendente:** servir o Laravel localmente e visitar `http://127.0.0.1:8001/saude` e `http://127.0.0.1:8001/demonstracao`. A demonstracao usa memoria/sinteticos; para login real e listagens com 115 alunos, e necessario preparar uma sessao isolada com conexao ao cluster 55436, verificando identidade e sem reutilizar a porta 5432 ou reescrever `.env`.
- **Nao fazer:** nova transferencia sem necessidade, `astuti:demo-preparar` ou migracao destrutiva nas bases originais, excluir os clusters anteriores, promover o PR #5 a producao antes dos testes completos. Detalhes em `HISTORICO.md`.

**Abrir agora a demonstracao no notebook, sem conectar a sessao ao banco central:** em uma janela Debian, executar:

```bash
cd ~/astuti-escolar && ASTUTI_DEMO_CACHE_DIR="$(mktemp -d)" && APP_CONFIG_CACHE="$ASTUTI_DEMO_CACHE_DIR/config.php" APP_ENV=local SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync php artisan serve --host=127.0.0.1 --port=8001
```

No navegador do notebook, acessar `http://127.0.0.1:8001/saude` e depois `http://127.0.0.1:8001/demonstracao`. Deixar o terminal aberto enquanto navega e encerrar o servidor com `Ctrl+C`. A configuracao temporaria nao altera `.env` e usa arquivos locais para sessao/cache; as telas demonstrativas usam dados sinteticos em memoria, **nao os 115 alunos copiados no cluster 55436**. Se faltar manifest do Vite, verificar/buildar recursos da interface antes de repetir. Testar login com os dados copiados exigira outro roteiro de isolamento.

## 16. Capturas 91-92: pronto o roteiro seguro para testar login real no PostgreSQL isolado (21/11/2026)

- **Confirmado no notebook:** cluster PostgreSQL 17 `astuti_ens_20261121_112332_22176` iniciado na porta **55436**. Consultas `SELECT` retornaram **1 usuario central**, **3 escolas** e **115 alunos ficticios**. Esta e uma copia de ensaio; o PostgreSQL principal continua separado na porta 5432.
- **Pronto no GitHub, branch `feature/migracao-postgresql-instalacoes-isoladas`:** `scripts/operacao/testar-login-isolado-postgresql.sh` e o teste automatizado `tests/Shell/TestarLoginIsoladoPostgresqlTest.sh`. O roteiro recusa cluster errado ou contagens divergentes, valida a configuracao Laravel, troca credenciais somente na copia ficticia, separa o cookie de sessao e inicia a aplicacao na **porta 8002**. **Nao** altera `.env` nem executa migracoes. Auditoria e registros de login desse teste ficam exclusivamente na copia isolada.
- **Testes verificados:** os 5 cenarios simulados do novo roteiro e os 23 testes Bash anteriores passaram localmente. GitHub Actions [44915694895](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44915694895) concluiu com `success` para o commit que integrou o roteiro ao CI. Isso **nao substitui** a execucao no notebook.
- **Com o cluster 55436 online**, em outro terminal Debian (mantenha a demonstracao 8001 como esta):

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/testar-login-isolado-postgresql.sh
```

Depois, acessar `http://127.0.0.1:8002/login`; usar e-mail e senha temporaria mostrados no **terminal local**. Nao enviar a senha nem captura com credencial. Encerre o servidor de teste com `Ctrl+C`. Se o script disser que o cluster esta parado, conferir `pg_lsclusters` e iniciar exclusivamente `17/astuti_ens_20261121_112332_22176` por `sudo pg_ctlcluster --skip-systemctl-redirect 17 astuti_ens_20261121_112332_22176 start`.

**Ainda pendente:** Jefferson executar o comando no notebook e confirmar se o login, as listagens das 3 escolas e dos 115 alunos reais copiados estao corretos. Os 1.284 alunos e 4 escolas do painel em `/demonstracao` **sao apenas indicadores ilustrativos**, nao dados do banco. Manter PR #5 em rascunho; nao fazer corte PostgreSQL, merge ou limpeza dos clusters antigos. Consulte `HISTORICO.md` capturas 91-92 para detalhes e a documentacao operacional na branch.

## 17. Captura 93: corrigida opcao `psql` do roteiro de login (21/11/2026)

- A primeira tentativa real no notebook falhou em **`psql: unrecognized option '--database=postgres'`**, antes de qualquer atualizacao de usuario/senha. O acesso `http://127.0.0.1:8002/login` recusou conexao porque o servidor Laravel nao iniciou; isto **nao e problema do navegador**.
- Os tres usos invalidos de `--database=` foram substituidos por **`-d`** no roteiro da branch `feature/migracao-postgresql-instalacoes-isoladas`, commit **`ab82dc5`**, e adicionada regressao que falhou antes do patch, commit **`cf733b6`**. Reexecutados localmente: **5 + 9 + 8 + 6** cenarios Bash aprovados. Teste no Debian com PostgreSQL real **ainda pendente**.
- Basta repetir, **sem qualquer edicao manual**, com o cluster 55436 ainda online:

```bash
cd ~/astuti-escolar && git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas && bash scripts/operacao/testar-login-isolado-postgresql.sh
```

Depois da inicializacao comprovada, abrir `http://127.0.0.1:8002/login` e usar as credenciais exibidas no terminal. Nao enviar a senha. Registrar proxima captura no `HISTORICO.md`. Sem corte, merge ou alteracao dos bancos originais.

## 18. Capturas 94-96: login real, tres escolas e senha de ensaio opcional (21/11/2026)

- **Concluido e observado nas capturas do notebook:** login autenticado como `Secretaria Escolar Ficticia`, selecao da rede Prefeitura Municipal de Monte Sereno e pagina `Escolas autorizadas` exibindo tres escolas ativas: EMEF Caminhos do Saber, EMEF Rio Claro e EMEI Jardim das Flores. Sao telas da aplicacao real de desenvolvimento, nao o painel sintetico de 8001.
- **Ainda nao concluido:** abrir as telas de uma escola e consultar a listagem de alunos e matriculas para confrontar os **115 alunos ficticios** existentes no PostgreSQL de ensaio 55436; executar testes negativos de permissao por URL/acesso nao autorizado. A tela informa verificacao de permissao, mas isso nao substitui testes de seguranca.
- **Atualizado e testado na branch de desenvolvimento:** `scripts/operacao/testar-login-isolado-postgresql.sh` aceita `ASTUTI_DEMO_PASSWORD` de 6 a 64 caracteres alfanumericos para a **conta de demonstracao copiada exclusivamente no cluster 55436**. Sem variavel usa senha temporaria aleatoria. Senha do role PostgreSQL permanece aleatoria, e a variavel nao chega ao servidor HTTP. **7 + 9 + 8 + 6** cenarios de teste Shell simulados passaram; ainda nao foi aplicada a nova senha no notebook.
- **Para trocar a senha local e testar novamente:** interromper com `Ctrl+C` somente o terminal que serve **8002**, atualizar a branch com `git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas` e executar `ASTUTI_DEMO_PASSWORD=<senha-ficticia-escolhida> bash scripts/operacao/testar-login-isolado-postgresql.sh` (substituir o marcador por valor de teste, nao por credencial real). Usar **http://127.0.0.1:8002/login**, depois abrir uma escola e conferir alunos. Senhas fracas nunca em producao.
- **Protecao obrigatoria:** PostgreSQL original 5432, MariaDB, `.env`, backups e clusters historicos intocados. PR #5 ainda rascunho, sem migracao definitiva ou merge. Detalhes em `HISTORICO.md`, capturas 94-96.

## 19. Capturas 97-100: alunos e turmas reais da copia ficticia visualizados no notebook (21/11/2026)

- **Confirmado na interface autenticada em 8002:** abertura de `EMEF Caminhos do Saber`, detalhes da unidade ativa, lista de **45 alunos ficticios com vinculo ativo** (pagina **1 de 2**; nomes ate `Estudante ficticio 025` mostrados), alem de **1 turma ativa** `1º Ano A` (Ensino Fundamental, regular, manha, **45 vagas**, ano letivo **2027**). O total de vagas da turma nao deve ser interpretado automaticamente como matriculas.
- **Lacuna de dados exibida:** codigo INEP da escola consta `Nao informado`, no conjunto ficticio. Conferir futuramente exigencia e validacoes de cadastro; nao presumir que cadastro de unidade esta completo.
- **Nao verificado:** pagina 2 apos clicar `Proxima`, busca por nome, listagens das outras duas escolas e somatoria visual dos **115 alunos** ja conferidos por SQL no cluster 55436, testes negativos de autorizacao e edicao de dados.
- **Funcionalidade ainda planejada:** inclusao e alteracao de alunos, matriculas, anos letivos e turmas. O proprio sistema informa `Somente consulta, por enquanto`, pendente de autenticacao reforcada e trilha de auditoria.
- **Proximo teste no notebook, sem terminal nem escrita:** clique em `Proxima` para conferir os demais alunos da EMEF Caminhos do Saber; depois visite Alunos nas escolas Rio Claro e Jardim das Flores, anotando as contagens; verifique se a soma resulta em **115**. Pode testar a busca por um `Estudante ficticio`. Sem corte de banco, alteracao dos originais, limpeza de clusters ou merge do PR #5.

## 20. Validacao visual dos 115 alunos nas tres escolas e de todas as segundas paginas (21/11/2026)

- **Novo marco observado nas capturas 100-111 do notebook:** a aplicacao autenticada exibiu **45 alunos** na EMEF Caminhos do Saber, **40** na EMEI Jardim das Flores e **30** na EMEF Rio Claro, totalizando **115 alunos ficticios**, exatamente a contagem anterior obtida do banco de ensaio PostgreSQL **55436**. As tres telas de segunda pagina (`Pagina 2 de 2`) foram demonstradas, com sequencias de alunos **001 a 115** distribuidas entre as escolas.
- **Turmas de consulta vistas na interface:** Caminhos do Saber `1º Ano A`, fundamental, regular, manha, 45 vagas; Jardim das Flores `Infantil IV A`, infantil, regular, tarde, 40 vagas; Rio Claro `2º Ano A`, fundamental, regular, integral, 30 vagas. Todas apresentam ano letivo **2027**. As unidades ficticias mostram `Codigo INEP: Nao informado`; adequar dados quando houver cadastro completo.
- **Confirmado funcionalmente por evidencia visual:** login -> municipio -> escolas autorizadas -> detalhes da escola -> consulta de turmas e alunos -> paginacao em todas as tres unidades; as contagens de alunos coincidem com as dos dados isolados. Isso **nao equivale a testes completos de seguranca, inclusao ou importacao**.
- **Proximos testes:** busca por nome em cada escola (existente, inexistente e texto parcial), bloqueio de URL direta sem permissao e isolamento por unidade, alem dos testes automatizados para estas operacoes. Escritas, importacoes e emissao de documentos continuam planejadas, nao entregues. Manter PostgreSQL original 5432, MariaDB, backups e `.env` intactos; PR #5 segue rascunho.

## 21. Busca por nome sem distinguir maiúsculas no PostgreSQL (21/11/2026)

- **Entregue na branch isolada:** teste regressivo `6a1f483` e correção `b89fe64`. Pesquisar `mari` agora encontra `Maria` e `MARINA` sob PostgreSQL, sem mudar permissões de município/escola, vínculos ativos, curingas literais ou a paginação.
- **Prova automatizada:** [execução RED 44919446199](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44919446199) falhou no teste novo; [execução GREEN 44919611025](https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/44919611025) passou com **178 testes PHP duas vezes (633 asserções cada), Pint, build, quatro testes UI e integração MariaDB → PostgreSQL**. Vinte e quatro testes condicionais ficaram ignorados, não falharam.
- **Teste no notebook ainda pendente:** atualizar com `git pull --ff-only origin feature/migracao-postgresql-instalacoes-isoladas` somente após conferir que a branch local é a mesma e que `git status --porcelain` está vazio. Usar o ambiente fictício em `http://127.0.0.1:8002/login` e pesquisar nomes em minúsculas. Não executar migração, alterações nos bancos originais, deleção de clusters nem merge do PR #5.
- **Próxima entrega:** ficha de consulta individual de aluno com vínculo ativo na escola autorizada, sem liberar qualquer escrita.
