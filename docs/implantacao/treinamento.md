# Trilhas de treinamento por perfil

Capacitação presencial e prática para o Astuti Gestão Escolar. Referência: main 728edf40, 24/11/2026. Todos os exercícios usam exclusivamente a rede fictícia Monte Sereno, em instalação de treinamento separada. Não utilizar cadastros, documentos, imagens, contatos, arquivos ou contas de pessoas reais.

## Preparação e método

- O Astuti prepara a aplicação funcional com escolas, ano, períodos, turmas, alunos, componentes e servidores sintéticos. Verificar o que já foi criado automaticamente, como períodos e componentes iniciais; não duplicar.
- Usar o kit fictício Monte Sereno e suas fontes sintéticas autorizadas, quando disponíveis, conferindo sua versão e gabarito. O ensaio descrito na [importação assistida](../arquitetura/importacao-assistida-alunos.md) usa três escolas e dez turmas; a base sintética criada pelo comando de demonstração pode ser diferente. Não exigir que ambas tenham a mesma composição.
- O protótipo visual em /demonstracao não efetua gravação e não substitui a instalação funcional de treinamento. A rota está limitada aos ambientes de desenvolvimento/teste previstos pelo controlador.
- Preparar contas fictícias de perfil e escola distintos, com segundo fator, sem publicar credenciais. Atribuir o professor à turma e conferir correspondência de e-mail institucional fictício.
- Disponibilizar navegador atualizado, teclado, recursos de acessibilidade e guia de exercícios. Avaliar dificuldades e assegurar alternativa de aprendizagem acessível.
- O formador demonstra, o participante executa e o avaliador confere resultado, escopo e privacidade. Restaurar ou repor somente a base fictícia de treino sob procedimento autorizado.

### Módulo comum (45 minutos)

Login e segundo fator, escolha do município e escola conforme permissão, navegação, mensagens de validação, proteção de dados, saída segura e suporte. Não compartilhar contas; não fotografar fichas para encaminhar chamados. Simular tentativa de acesso fora do escopo e confirmar a recusa onde o perfil não tem autorização. Se houver acesso inesperado, registrar defeito e não improvisar concessão de privilégio.

## 1. Gestão municipal (4 horas, além do módulo comum)

**Objetivo:** organizar a rede, acompanhar indicadores e conferir obrigações administrativas sem acessar conteúdo reservado de atendimento.

| Oficina | Exercício fictício | Evidência de aprendizagem |
|---|---|---|
| Organização e implantação | Conferir escolas, turmas, anos, períodos e componentes existentes; comparar quantitativos do piloto. | Identifica recortes, aluno versus matrícula e inconsistências. |
| Conferência do Censo | Abrir Conferência do Censo e Educacenso por escola, localizar pendência e orientar correção; usar arquivo de retorno sintético compatível se disponível. | Distingue conferência local de envio; revisa correspondências antes de confirmar. |
| Gestão pedagógica | Consultar conselhos concluídos por escola/período, distorção e aulas previstas/dadas. Abrir a lista nominal de distorção da escola, quando autorizado. | Interpreta sugestão de etapa e chamada faltante; não tenta editar parecer. |
| Sistema Presença | Consultar resumo por escola e período; explicar 60%/75% e mês sem frequência. | Distingue apoio à digitação de integração oficial; não cadastra benefício ou motivo. |
| Políticas educacionais | Consultar indicadores de alfabetização, busca ativa, equidade/VAAR, tempo integral, alimentação, equipe e relatório mensal, conforme escopo contratado. | Interpreta indicadores e limitações, respeita grupos pequenos. |
| Governança | Revisar evidência agregada, acesso, canal de incidentes, plano de backup e procedimento de aceite com TI. | Identifica situação que impede homologação. |

A gestão municipal não edita conselho de classe e não consulta conteúdo individual da equipe multiprofissional.

## 2. Direção escolar (4 horas)

**Objetivo:** organizar e acompanhar a unidade, conferir cadastros e conduzir ações pedagógicas.

| Oficina | Exercício fictício | Evidência de aprendizagem |
|---|---|---|
| Cadastro e organização | Conferir turmas, matrículas e responsáveis; cadastrar servidor fictício e atribuir professor à turma. | Corrige uma pendência de cadastro com fonte e escopo corretos. |
| Calendário e frequência | Consultar calendário, dias não letivos, reposição e relatório mensal de frequência. | Identifica um dia sem chamada e fluxo de correção permitido. |
| Conselho de classe | Selecionar período, ler notas/faltas/sondagem, registrar reunião, parecer e encaminhamento; concluir e reabrir com motivo e 2FA recente. | Reconhece bloqueio de edição e não registra relato clínico. |
| Encaminhamentos | Usar link para ficha de busca ativa ou para solicitação de equipe; confirmar que o conselho não cria casos automaticamente. | Segue fluxo autorizado, sem duplicar pedido ou copiar relato reservado. |
| Indicadores e Presença | Abrir painel pedagógico da escola e distorção nominal; consultar frequência mensal e marcar lançamento fictício. | Distingue sessão de chamada de hora-aula e marcação interna de protocolo MEC. |
| Obrigações e apoio | Revisar dados do Censo, rotina de transporte e alimentação conforme permissão. | Encaminha pendência ao responsável sem expor dados. |

Coordenação escolar não é perfil autônomo disponível na versão de referência. Não treinar sob conta emprestada de direção; oferecer formação conceitual até formalização do perfil próprio.

## 3. Secretaria escolar (5 horas)

**Objetivo:** executar cadastro e atendimento administrativo da unidade com rastreabilidade.

| Oficina | Exercício fictício | Evidência de aprendizagem |
|---|---|---|
| Importação de alunos | Subir XLSX sintético, revisar prévia, confirmar homônimo distinto somente com evidência fictícia e tratar duplicidade; confirmar com 2FA. | Explica por que prévia não grava e confere recibo. |
| Complementação | Abrir Pendências do cadastro; conferir CPF/certidão na ficha, responsáveis, endereço e zona; completar lacunas válidas. | Não inventa CPF, nascimento, telefone ou condição de responsável legal. |
| Matrícula | Conferir turma e ano, simular transferência/encerramento autorizado e consultar histórico de vínculos. | Distingue correção cadastral de movimentação escolar. |
| Transporte | Importar CSV fictício, revisar linhas de fora e conferir adaptação, rota e ponto na ficha. | Reconhece que o vazio não apaga valor e que cancelamento não pode ser revertido por importação. |
| Documentos | Emitir declaração de matrícula fictícia e verificar código/QR; consultar histórico conforme etapas suportadas. | Confere conteúdo e validade sem divulgar ficha pessoal. |
| Presença e rotinas | Consultar frequência mensal no período e marcar lançamento fictício após exercício simulado; revisar dados do Censo, alimentação e saída conforme atribuição. | Não grava lista de beneficiários ou motivo; distingue registro local do lançamento oficial. |

Secretaria escolar não edita conselho de classe nem lê devolutivas multiprofissionais. A ficha de saúde usa permissão própria definida pelo sistema; não inserir diagnóstico ou laudo, nem reproduzir informações de saúde em planilhas e chamados.

## 4. Professor (3 horas)

**Objetivo:** executar rotina pedagógica da turma atribuída, com o mínimo de dados necessário.

| Oficina | Exercício fictício | Evidência de aprendizagem |
|---|---|---|
| Turma e chamada | Abrir turma atribuída e registrar chamada do dia, distinguindo presente, falta e falta justificada. Consultar relatório mensal. | Confirma turma/data e não grava data futura; pede correção histórica à gestão quando necessário. |
| Notas e alfabetização | Lançar notas por componente e período no ensino fundamental conforme atribuição; registrar sondagem quando aplicável. | Distingue sem nota de nota zero e respeita fechamento. |
| Conselho | Consultar o conselho do período e registrar parecer pedagógico curto; tentar editar conselho concluído no ensaio. | Não altera dados de reunião ou encaminhamentos reservados à direção; edição concluída bloqueada. |
| Apoio escolar | Consultar alerta da turma e abrir solicitação à equipe multiprofissional no fluxo autorizado, sem diagnóstico ou narrativa familiar. | Reconhece limites de informação e autorização. |
| Conectividade | Simular perda de internet sem dados reais; identificar canal de contingência. | Entende que o diário offline ainda não está homologado nesta versão. |

Diário instalável, sincronização offline e registros de conteúdo offline são planejados nesta referência. Não instalar ou testar com dados reais nem prometer sincronização automática. Avaliar treinamento complementar após homologação própria da funcionalidade.

## 5. Equipe multiprofissional (3 horas)

**Objetivo:** usar fila de pedidos, agenda e devolutiva administrativa sem transformar o Astuti em prontuário técnico.

Perfis separados: psicologia, serviço social e pedagogia da equipe. Não combinar contas de atendimento com perfis escolares ou de gestão. Usar segundo fator recente para operações que o exigem.

| Oficina | Exercício fictício | Evidência de aprendizagem |
|---|---|---|
| Fila e área | Abrir pedido da área autorizada ou a definir e assumir pedido fictício. | Não consulta pedido fora do escopo nem assume pedido atribuído a outro profissional. |
| Agenda e atendimento | Agendar e confirmar atendimento administrativo de data fictícia. | Identifica estado do pedido e não duplica atendimento do dia. |
| Devolutiva | Registrar orientação prática fictícia à escola, sem laudo, hipótese diagnóstica ou informações de terceiros. | Produz texto mínimo e adequado à finalidade escolar. |
| Encaminhamento | Registrar destino/data, imprimir via administrativa fictícia se aplicável e registrar contrarreferência. | Distingue documento administrativo de laudo técnico. |
| Encerramento e incidentes | Encerrar caso fictício e simular solicitação de suporte/afastamento de profissional. | Aciona procedimento administrativo sem copiar prontuário ou prometer reatribuição automática. |

A gestão municipal recebe apenas indicadores agregados da equipe. O módulo não guarda prontuário, relato técnico, laudo, anexos técnicos ou registro clínico.

## 6. Oficina complementar de TI (2 horas)

Não é um novo perfil escolar. Orientar o responsável técnico autorizado em instalação exclusiva, atualização controlada, backup cifrado, cópia externa, ensaio de restauração, monitoramento, permissões, resposta a incidentes e suporte. Exercícios em ambiente isolado com dados fictícios, sem testar restauração sobre bancos em uso. Ver [guia de segurança](../operacao/vps-checklist-seguranca.md) e [backup](../operacao/vps-backup.md).

## Avaliação e multiplicação

Critérios mínimos propostos para cada participante: executar as tarefas críticas do perfil, identificar limites de acesso, demonstrar segundo fator, reconhecer situação de risco e explicar quando pedir ajuda. Repetir exercício se houver falha. Não exigir velocidade que prejudique acessibilidade ou qualidade de conferência.

| Perfil | Turmas de formação | Participantes previstos | Aptos | Reforço | Evidência agregada |
|---|---:|---:|---:|---:|---|
| Gestão municipal | ____ | ____ | ____ | ____ | ____ |
| Direção escolar | ____ | ____ | ____ | ____ | ____ |
| Secretaria escolar | ____ | ____ | ____ | ____ | ____ |
| Professor | ____ | ____ | ____ | ____ | ____ |
| Equipe multiprofissional | ____ | ____ | ____ | ____ | ____ |

Tempos são referências pedagógicas, não carga horária certificada pelo sistema. Repetir turmas em paralelo ou em ondas conforme tamanho da rede. Registros nominativos de presença de servidores, quando necessários, ficam no ambiente administrativo autorizado da Prefeitura, não neste repositório. Não há promessa de módulo de certificação ou de formação continuada.

## Leituras de apoio

- [Manual do Astuti](../manual/README.md) e [primeiro acesso](../manual/00-primeiro-acesso.md).
- [Importação assistida](../arquitetura/importacao-assistida-alunos.md) e [transporte](../arquitetura/importacao-transporte.md).
- [Conferência do Censo](../manual/03-conferencia-do-censo.md) e [Educacenso](../arquitetura/educacenso-exportacao.md).
- [Gestão pedagógica](../arquitetura/gestao-pedagogica.md), [Sistema Presença](../arquitetura/sistema-presenca.md) e [equipe multiprofissional](../arquitetura/equipe-multiprofissional-etapa-1.md).
- [LGPD desde o desenho](../arquitetura/lgpd-by-design.md) e [roteiro de implantação](roteiro.md).

Aptidão em ambiente fictício é pré-requisito de entrada em operação assistida, não autorização isolada para tratar dados reais.
