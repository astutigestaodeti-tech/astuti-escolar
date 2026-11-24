# Roteiro de implantação do Astuti Gestão Escolar

**Referência:** main em 24/11/2026, commit 728edf40 (integração #184). Modelo de trabalho para a equipe Astuti e a Prefeitura, exemplificado exclusivamente pela rede fictícia Monte Sereno.

Este roteiro é uma proposta operacional para a licença de uso, implantação presencial com migração assistida, treinamento e suporte. Não constitui proposta de preço, garantia de prazo, contrato ou homologação já realizada. As funcionalidades descritas correspondem à versão de referência; todo novo município deve testar a versão que receberá. A contratação, a configuração e o aceite dependem de autorização própria.

## Premissas e estimativa de prazo

- Uma VPS exclusiva por Prefeitura, com aplicação, bancos PostgreSQL platform e tenant, arquivos privados e backups próprios. Desenvolvimento e treinamento separados da instalação de produção.
- Antes de dados reais, cumprir os controles de segurança e privacidade, concluir o que faltar da Fase A e validar o ensaio de recuperação. A Secretaria define finalidade, acesso e retenção; a TI da Prefeitura e o Astuti executam os controles que lhes couberem.
- Implantação por ondas, com escola piloto e validação por unidade. Sugestão de equipe: dois profissionais Astuti (implantação e técnico), um responsável da Secretaria com poder de decisão, um representante de TI e um multiplicador por escola. A rede maior precisa de multiplicadores e conferência em paralelo.
- Prazos abaixo em dias úteis de trabalho, estimativas de planejamento sem compromisso contratual, presumindo fontes exportáveis, equipe disponível, infraestrutura pronta para instalação, turmas mapeáveis e ausência de grandes adaptações. Não incluem espera por licitação, acesso ao sistema anterior, respostas de terceiros ou correções de software. Não há pressuposto fixo de alunos por escola.
- Etapas podem se sobrepor entre ondas, mas nenhuma escola passa à operação real sem os critérios anteriores atendidos. A soma sequencial é uma referência conservadora, não uma promessa de entrega.

| Etapa | 10 escolas | 30 escolas | 80 escolas |
|---|---:|---:|---:|
| 1. Diagnóstico | 3 | 5 | 10 |
| 2. Migração | 5 | 10 | 20 |
| 3. Conferência humana | 5 | 10 | 20 |
| 4. Treinamento | 3 | 6 | 12 |
| 5. Operação assistida | 10 | 15 | 20 |
| 6. Homologação | 2 | 3 | 5 |
| Total sequencial de referência | 28 | 49 | 87 |

Reestimar no fim do diagnóstico, depois do piloto. Considerar volume e qualidade de registros, turmas sem código, fontes em papel, necessidade de OCR, deslocamentos rurais, acessibilidade, disponibilidade de pessoal e complexidade do acervo. Registrar a nova estimativa e dependências no plano de implantação reservado à rede.

## 1. Diagnóstico

**Objetivo:** compreender o cenário e definir escopo, responsabilidades, fontes, riscos, critérios de aceite e cronograma.

| Responsável | Atribuição |
|---|---|
| Astuti | Realizar reunião de abertura, inventariar formatos e testar compatibilidade com amostras sintéticas; propor ondas e limites. |
| Secretaria | Designar responsável pelo projeto, encarregado de dados e responsáveis pelos módulos; validar escopo, inventário e bases de comparação. |
| Escolas | Mapear rotinas, cadastro, calendário, turnos, necessidades de acessibilidade, internet e capacidade de treinamento. |
| TI da Prefeitura | Levantar conectividade, equipamentos, segurança, requisitos de hospedagem e acesso autorizado ao legado. |

**Entradas:** checklist de diagnóstico, quantitativos agregados, dicionário dos dados, layouts sem registros reais, requisitos do edital e contrato quando celebrado.

**Saídas:** plano por escola, matriz de responsabilidades e acesso, mapa de origem-destino por campo, lista de dados não migráveis automaticamente, plano de continuidade, riscos com responsáveis, cronograma revisado e critérios de aceite.

**Prazo:** 3 / 5 / 10 dias úteis, respectivamente para 10 / 30 / 80 escolas.

**Riscos:** fontes inacessíveis, contagens divergentes, incompatibilidade de exportação, ausência de responsável, infraestrutura insuficiente ou requisitos não atendidos pelo produto. Validar o layout antes de aceitar o escopo.

**Pronto quando:** Secretaria aprova escopo, fontes, plano de proteção de dados, cronograma e critérios; pendências impeditivas têm tratamento antes da etapa que afetarem. Usar [checklist de diagnóstico](checklist-diagnostico.md).

## 2. Migração

**Objetivo:** preparar uma instalação exclusiva e transferir dados compatíveis, com controle de origem, versão, escola, ano e conferência de cada lote.

| Responsável | Atribuição |
|---|---|
| Astuti | Preparar instalação e executar piloto e importações assistidas; documentar erros, recibos e campos não migrados. |
| Secretaria | Autorizar as fontes, definir data de corte, validar mapeamento de etapas/turmas e indicar conferentes. |
| Escolas | Conferir identificação e turma, revisar homônimos e inconsistências, validar responsáveis e transporte. |
| TI da Prefeitura | Viabilizar exportação do legado, transferência cifrada, segregação de ambientes, backup e recuperação testada. |

**Entradas:** plano aprovado, cópia autorizada do legado, arquivos segregados por escola/ano, gabarito agregado, mapa de campos e instalação revisada. Iniciar com dados sintéticos; nenhum dado real entra antes do aceite dos controles de segurança.

**Procedimento:**

1. Preparar VPS conforme [arquitetura de produção](../operacao/vps-prefeitura.md), [instalação](../operacao/vps-instalacao-passo-a-passo.md), [checklist de segurança](../operacao/vps-checklist-seguranca.md) e [backup](../operacao/vps-backup.md). Validar HTTPS, isolamento, papéis de banco, monitoramento, contas nominativas e segundo fator. Backup cifrado dos dois bancos e arquivos privados, cópia externa e ensaio de restauração em ambiente isolado. Adotar ensaio mensal e após mudanças de versão do banco, conforme guia de backup.
2. Preparar escolas, ano letivo, períodos, turmas e configurações autorizadas. Verificar componentes curriculares e períodos que já existam antes de criar outros. Conferir correspondências com o legado sem inventar datas, etapas ou vínculos.
3. Executar piloto com o kit fictício Monte Sereno no ambiente de treinamento. Distinguir o protótipo visual da aplicação funcional: a rota demonstrativa não grava registros e não é disponibilizada em produção.
4. Importar alunos pela tela Importar alunos da escola (XLSX, PDF ou imagem JPG/JPEG/PNG, até 10 MB e 500 alunos por prévia). Conferir leitura, identidade, RA, turma e conflitos. Confirmar com segundo fator; a prévia não grava. Repetições e homônimos exigem o tratamento mostrado pela tela. Dados válidos de identificação, endereço e responsável podem ser aproveitados quando reconhecidos; opção de completar somente lacunas em cadastros existentes, sem sobrescrever valores preenchidos. Corrigir fonte e refazer prévia se necessário. Registrar recibo e contagens do lote, não reproduzir dados pessoais em planilha de acompanhamento.
5. Só depois de validar matrículas, importar transporte pela tela Importar lista de transporte: CSV, até 2 MB e 500 linhas de dados por lote, RA ou identificação nominal nos casos previstos pelo leitor, situação e campos operacionais reconhecidos. Nome sem RA exige conferência explícita. Conferir linhas excluídas e conflitos; não reativar transporte cancelado por importação.
6. Se a rede tiver exportação oficial do Educacenso compatível com a edição suportada, usar Retorno do Educacenso (TXT ou ZIP, até 20 MB) sob gestão municipal e segundo fator recente. Conferir prévia, correspondências e conflitos; confirmação grava identificadores reconhecidos, sem importar o histórico completo. Não confundir retorno com geração ou envio de arquivo.
7. Reconciliar quantidades do legado, alunos e matrículas antes/depois, linhas ignoradas, complementos e pendências. Executar ondas seguintes somente após aceite do piloto. Manter legado disponível em modo de consulta conforme autorização e planejar alterações ocorridas após a data de corte.

**Saídas:** instalação validada, lotes confirmados com recibos, cadastro inicial, planilha restrita de reconciliação agregada e fila de lacunas a corrigir. Não prometer migração integral de notas antigas, histórico, frequência, laudos, acervos, arquivos ou tabelas arbitrárias: avaliar campo a campo e prever entrada manual, consulta ao legado ou escopo adicional.

**Prazo:** 5 / 10 / 20 dias úteis.

**Riscos:** OCR imperfeito, homônimos, duplicidades, dados ausentes, falta de correspondência com turmas, exportações incompatíveis e alterações simultâneas no legado. Usar prévia, lotes pequenos, conferência humana e backups. Uma falha de gravação do lote não justifica excluir ou restaurar toda a base sem autorização; recuperação segue procedimento próprio.

**Pronto quando:** piloto aprovado, lotes autorizados confirmados, divergências contabilizadas, backups e ensaio registrados, sem erro crítico não tratado.

Referências: [importação assistida de alunos](../arquitetura/importacao-assistida-alunos.md), [importação de transporte](../arquitetura/importacao-transporte.md), [Educacenso](../arquitetura/educacenso-exportacao.md). O controlador vigente de importação de alunos é a referência para campos novos ainda não descritos no documento histórico.

## 3. Conferência humana

**Objetivo:** validar os dados migrados e o funcionamento de rotinas, independentemente de sucesso técnico da importação.

| Responsável | Atribuição |
|---|---|
| Astuti | Apoiar a reconciliação, diagnosticar falhas e acompanhar correções aprovadas. |
| Secretaria | Comparar totais e recortes com a origem; aprovar divergências justificadas e rejeitar inconsistências críticas. |
| Escolas | Conferir fichas, turmas, responsáveis, documentos, endereços, transporte e pendências escolares. |
| TI da Prefeitura | Validar acesso, logs, backup, restauração e condições de uso na rede. |

**Entradas:** gabarito agregado, recibos, cadastro migrado, acesso autorizado às fontes e [checklist de conferência](conferencia-pos-migracao.md).

**Saídas:** checklist assinado internamente por unidade, quantidades conciliadas, pendências com prioridade, responsável e prazo; relatórios de teste de autorização e recuperação sem dados pessoais.

**Prazo:** 5 / 10 / 20 dias úteis.

**Riscos:** confundir alunos com matrículas, aprovar por amostra sem conciliar totais, confirmar identificação sem documento ou ocultar pendências. Separar ausência de informação de erro e impedir aprovação com divergência crítica.

**Pronto quando:** todas as escolas conciliadas, sem associação de aluno ou responsável incorreta, sem conflito de identificação pendente que comprometa a operação, com pendências residuais formalmente aceitas e plano de correção.

## 4. Treinamento

**Objetivo:** capacitar cada perfil para operar apenas as rotinas de sua competência, em ambiente fictício.

| Responsável | Atribuição |
|---|---|
| Astuti | Ministrar oficinas presenciais, disponibilizar material, exercícios e avaliação prática. |
| Secretaria | Organizar calendário de formação e multiplicadores; garantir participação e acessibilidade. |
| Escolas | Liberar equipe para as oficinas, praticar cenários e identificar dúvidas. |
| TI da Prefeitura | Preparar dispositivos, navegador, acesso ao ambiente de treino e apoio ao segundo fator. |

**Entradas:** [trilhas por perfil](treinamento.md), kit Monte Sereno, contas fictícias com escopos distintos, estações e material acessível.

**Saídas:** registro agregado de participação e aptidão por perfil, dúvidas e plano de reforço; material de referência e canal de suporte definidos.

**Prazo:** 3 / 6 / 12 dias úteis, com turmas de formação em paralelo quando necessário.

**Riscos:** treinamento em produção, compartilhamento de contas, ausência de internet e confusão entre demonstração visual e sistema funcional. Simular sem dados pessoais reais e conferir permissões no início da oficina.

**Pronto quando:** representantes e multiplicadores aprovam os exercícios críticos do seu perfil e há plano de reposição para ausentes.

## 5. Operação assistida

**Objetivo:** acompanhar a entrada em uso das unidades já conferidas, com suporte próximo e controle de ocorrências.

| Responsável | Atribuição |
|---|---|
| Astuti | Acompanhar operação, orientar correções e tratar incidentes e defeitos com versão revisada. |
| Secretaria | Autorizar início de cada onda, centralizar demandas, avaliar impacto e aprovar correções. |
| Escolas | Executar rotinas autorizadas, registrar chamados sem expor alunos e comunicar divergências. |
| TI da Prefeitura | Monitorar disponibilidade, acesso, rede, backup, segurança e acionamento de incidentes. |

**Entradas:** cadastro conferido, treinamento concluído, autorização de entrada em uso, controles de segurança aprovados, canais de suporte e plano de retorno.

**Saídas:** registro de chamados por categoria e gravidade, métricas acordadas de atendimento, lista de correções e evidências agregadas de funcionamento.

**Prazo:** 10 / 15 / 20 dias úteis.

**Riscos:** falha de conectividade, interrupção das rotinas, erros em permissões, atraso de chamadas ou falha de recuperação. Enquanto diário offline não estiver homologado, prever contingência administrativa autorizada pela Prefeitura, sem armazenamento de dados de alunos em aplicativos pessoais. Defeito crítico ou incidente implica suspender o fluxo afetado, preservar evidências restritas e acionar o responsável; seguir plano de incidentes.

**Pronto quando:** rotinas críticas exercitadas em todas as ondas, suporte e continuidade funcionais, nenhum incidente crítico em aberto e pendências residuais com prazo e responsável. Não substituir decisão da Prefeitura sobre continuidade do sistema anterior.

## 6. Homologação pela Prefeitura

**Objetivo:** registrar o aceite formal da implantação e o escopo efetivamente verificado, sem confundir homologação municipal com aprovação de órgão federal.

| Responsável | Atribuição |
|---|---|
| Astuti | Entregar evidências, versão, manuais, orientações de suporte e limitações conhecidas. |
| Secretaria | Consolidar resultados, decidir aceite, ressalvas ou rejeição e encaminhar ao representante competente. |
| Escolas | Confirmar funcionamento das rotinas e concluir a conferência de sua unidade. |
| TI da Prefeitura | Atestar controles operacionais, recuperação e requisitos de infraestrutura acordados. |

**Entradas:** evidências agregadas das etapas, registro de testes, lista de pendências, plano de continuidade, minuta de termo.

**Saídas:** [termo de homologação](termo-homologacao.md) aprovado ou rejeitado, plano de correções, transição para suporte regular e procedimento de devolução de dados pactuado.

**Prazo:** 2 / 3 / 5 dias úteis.

**Riscos:** aceite genérico, promessa de recurso inexistente, confusão entre licença e cessão de código, inexistência de estratégia de saída. Registrar versão, escopo, critérios e limitações.

**Pronto quando:** a autoridade competente da Prefeitura decide formalmente após verificar critérios; sem bloqueio crítico. Havendo ressalvas, indicar prazo e responsável, sem dispensar controles indispensáveis.

## Limites da versão de referência

- Educacenso: conferência lógica e retorno de identificadores disponíveis; geração do arquivo ainda bloqueada, aguardando confirmação do formato; não há envio automático ao Inep.
- Sistema Presença: apoio à digitação oficial, frequência mensal e marcação de lançamento. Não guarda benefício nem motivo de baixa frequência.
- Diário instalável offline: planejado, não incluso como capacidade entregue nesta referência. Treinar chamada on-line.
- Conselho de classe: gestão pela direção e parecer do professor atribuído; perfil autônomo de coordenação escolar ainda não provisionável.
- Devolução em formato aberto: compromisso a especificar no contrato e demonstrar no teste de saída; não afirmar existência de exportador geral de todo o banco e acervo.
- Segurança: verificar a conclusão da Fase A e condições operacionais antes de uso real. A documentação não certifica conformidade legal ou segurança por si só.

## Entrega documental

Este roteiro, os checklists, o termo e as trilhas formam um conjunto. Exemplos e anexos publicados usam somente Monte Sereno fictício. Evidências de implantação real, configurações, credenciais, relatórios nominais e arquivos do legado ficam em ambiente privado autorizado, nunca no repositório público.
