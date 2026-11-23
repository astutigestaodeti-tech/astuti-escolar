# Lista de espera para educação infantil

Implementação da issue #93. Escopo: inscrição, fila e alterações **manuais** de situação. Não cria matrícula, não convoca automaticamente e não publica nomes ou posições na internet.

## Funcionamento e ordenação

- Cada pedido recebe protocolo aleatório de dez caracteres sem ambiguidades, registro no servidor em `requested_at` e a data de corte utilizada. O banco bloqueia mudanças ao protocolo, à data, à etapa, ao ano e aos critérios originais.
- A data de corte é configurada por Prefeitura no `ASTUTI_LISTA_ESPERA_CORTE` em `AAAA-MM-DD` (padrão: 31 de março do ano de ingresso corrente). A mudança da configuração não altera os pedidos já gravados.
- A etapa é calculada pela idade **completa na data de corte**: 0 ou 1 ano, Berçário; 2, Maternal I; 3, Maternal II; 4, Pré I; 5, Pré II. O atendente vê o cálculo e o confirma. Nascimentos fora dessas faixas são recusados para esta etapa.
- A lista só inclui, por padrão, pedidos com situação `aguardando` ou `convocado`. A posição é específica de **escola, etapa e ano** e considera primeiro a **quantidade de critérios de prioridade justificados** (maior primeiro), em seguida o horário original do pedido (mais antigo primeiro) e o UUID para desempate determinístico. É calculada antes da pesquisa para não mudar a numeração quando a lista for filtrada. Pedidos atendidos ou desistentes não ocupam posição.
- Cada pedido pode escolher até três escolas diferentes, em ordem de preferência. Não são criados pedidos adicionais por escola: a mesma inscrição aparece na fila de cada preferência, com posição independente.
- Cadastros com nome e nascimento iguais ou CPF coincidente com inscrição aberta exigem confirmação humana explícita. Nenhum cadastro existente é sobrescrito ou fundido.
- Mudança de situação exige motivo, verificação recente do segundo fator e histórico imutável. Arquivamento é lógico e não apaga a inscrição nem o histórico.

## Permissões e LGPD

- Gestor municipal: consulta e registro em todas as unidades de educação infantil.
- Secretaria escolar: registra inscrições com **primeira preferência em unidade autorizada** e consulta/muda pedidos em que sua unidade consta nas preferências.
- Direção escolar: consulta os pedidos de sua unidade, inclusive critérios e justificativas, mas não registra nem modifica.
- Professor e pessoa não autorizada: recebem 403. Uma escola não vê inscrições exclusivas de outra.
- A página não é pública e usa `Cache-Control: private, no-store`. As justificativas de prioridade podem revelar deficiência, situação protetiva ou vulnerabilidade e aparecem apenas na consulta interna autorizada, nunca no índice geral, no protocolo nem na auditoria.
- O `AuditTrail` grava autoria, operação, situação e identificador do pedido. CPF, telefone, motivos e justificativas são redigidos como `[REDACTED]`; nomes de crianças e responsáveis nem entram no esquema de auditoria deste recurso.
- O CEP usa o serviço e o cache municipais já existentes. A pesquisa de CEP não transmite nome de criança ou dados de responsável.

## Validação

A migration `2026_11_23_000033_create_waitlist_requests.php` cria as três tabelas no banco municipal; nenhuma estrutura da plataforma é alterada. As telas novas estão em `AllSchoolPagesOpenTest` para teste de abertura e inspeção em 1440/390 px com `checar-margens.py`. Não há dados reais nos testes.

Antes do merge, executar Pint, a suíte completa PHP/PostgreSQL e o verificador de margens. 
