# Checklist de diagnóstico da rede

Preencher internamente em reunião com a Secretaria, diretores e TI. Este modelo público é vazio; o exemplo é Monte Sereno (fictício). Não incluir nomes ou documentos de alunos, servidores, responsáveis, dados de saúde, credenciais, endereços de servidores ou valores contratuais. A versão preenchida para uma rede real deve ficar em repositório privado da implantação.

**Identificação de trabalho:** Monte Sereno (fictício) | Data: ____ | Versão avaliada: ____ | Código interno do diagnóstico: ____

Para cada item marcar: atendido, pendente, não se aplica ou fora do escopo. Registrar apenas referência da evidência, responsável por função e prazo em controle privado.

## 1. Rede e escopo

- [ ] Quantidade de escolas ativas, paralisadas ou extintas; urbanas/rurais, etapas e modalidades.
- [ ] Quantidade de turmas por escola, turno, ano/etapa e jornada; classes especiais, AEE e multisseriadas.
- [ ] Alunos distintos e matrículas ativas, encerradas e transferidas por escola e ano, com data de corte.
- [ ] Lista de espera, vagas, demanda de creche e critérios aprovados pela rede.
- [ ] Códigos Inep das escolas e situação das informações cadastrais.
- [ ] Requisitos do edital e do contrato, separando obrigatório, desejável, fora do escopo e recurso ainda planejado.
- [ ] Escola piloto, ordem das ondas, calendário de implantação e responsáveis por validação.

## 2. Fontes e migração

- [ ] Sistema anterior e responsável pela extração; autorização formal e data de corte.
- [ ] Formatos disponíveis: XLSX, CSV, PDF, imagens, outros formatos (listar apenas tipos); versão do layout e documentação.
- [ ] Inventário de arquivos, quantidade de linhas, tamanho, codificação, separadores, planilhas e qualidade de digitalização.
- [ ] Mapa de campos: escola, ano, turma, RA, nome, nascimento, CPF, certidão, endereço, zona, responsáveis, telefone, transporte.
- [ ] Identificadores estáveis, duplicados, homônimos, documentos inválidos, informações ausentes e alunos sem turma.
- [ ] Dados históricos, notas, frequência, documentos e anexos que precisam ser preservados; identificar o que não tem importador confirmado e prever solução aprovada.
- [ ] Amostra sintética representativa de cada layout; validar prévia do importador, sem usar dados reais em desenvolvimento.
- [ ] Gabarito agregado do legado por escola, ano, turma e situação; separar contagem de alunos da de matrículas.
- [ ] Transporte em CSV: RA quando houver, identificação nominal, situação, rota, ponto e adaptação quando informados.
- [ ] Retorno oficial do Educacenso disponível e compatível com edição suportada; conferir identificadores e autorização.
- [ ] Plano de reconciliação, recibos, mudanças entre data de corte e operação, acesso ao legado e contingência.

## 3. Organização pedagógica

- [ ] Ano letivo, calendário da rede e das escolas, feriados, recessos, dias não letivos e reposições.
- [ ] Bimestres ou trimestres, datas e regras locais; verificar períodos existentes no sistema antes de criar.
- [ ] Componentes curriculares e critérios de avaliação; verificar componentes iniciais já existentes.
- [ ] Servidores, atribuições docentes vigentes, organização por turma e e-mail institucional para identificar o professor.
- [ ] Chamada, correção de frequência, notas, boletim, fechamento do ano, histórico e renovação.
- [ ] Conselho de classe e forma de encaminhar para equipe multiprofissional e busca ativa; verificar perfis disponíveis.
- [ ] Etapas Inep das turmas para distorção idade-série; horários para dias de aula previstos.
- [ ] Monitoramento de alfabetização e atendimento a necessidades educacionais especiais.
- [ ] Sistema Presença: operador responsável, períodos oficiais e processo de lançamento no MEC; não extrair lista de beneficiários nem motivos para o Astuti.

## 4. Transporte e alimentação

- [ ] Rotas/linhas, pontos de embarque, situações de transporte, demanda de adaptação e forma de atualização.
- [ ] Endereços e zonas urbanas/rurais, critérios da rede para concessão; a importação não decide direito ao transporte.
- [ ] Cardápios, etapas, tipos de refeição, escolas atendidas, registro de refeições e responsabilidade do nutricionista.
- [ ] Procedimento restrito para alergias e alertas de saúde, sem coletar informação clínica no checklist.
- [ ] Requisitos de frota, estoque e compras não cobertos pelo escopo atual, se demandados.

## 5. Equipe multiprofissional e assistência escolar

- [ ] Existência de psicologia, serviço social e pedagogia da equipe, atribuições e fluxo de solicitação.
- [ ] Separação de contas da equipe e contas de gestão/escola; política de acesso a casos e devolutivas.
- [ ] Fluxo de busca ativa, responsáveis pelos contatos, retorno e encaminhamentos.
- [ ] Procedimento para afastamento/desligamento de profissional e continuidade de casos, sem pressupor reatribuição automática.
- [ ] Se houver prontuário ou acervo técnico, manter em solução autorizada e avaliar separadamente; o módulo atual não é prontuário.

## 6. TI, acessibilidade e conectividade

- [ ] Requisitos de VPS exclusiva, ambiente separado para treinamento e homologação técnica.
- [ ] Requisitos de desempenho, disponibilidade e capacidade, sem copiar endereços de infraestrutura neste modelo.
- [ ] Conectividade por escola: disponibilidade, velocidade, estabilidade e horários de interrupção, especialmente nas rurais.
- [ ] Equipamentos institucionais, navegadores, dispositivos pessoais, acessibilidade, apoio a usuários e política de uso.
- [ ] Plano de contingência para chamada em escola sem conexão; diário offline ainda em desenvolvimento nesta versão, sem promessa de disponibilidade.
- [ ] HTTPS, firewall, atualização, contas de administração segregadas, menor privilégio, monitoramento e revisão de acesso.
- [ ] Backup diário cifrado dos dois bancos e arquivos privados, cópia externa, custódia separada das chaves, ensaio de recuperação e metas pactuadas de perda/tempo de recuperação.
- [ ] Canal de suporte, plantão ou horários acordados, registro de incidentes e procedimento de reversão.
- [ ] Fase A de segurança e teste de permissões aprovados antes de receber dados reais.

## 7. Proteção de dados e responsabilidades

- [ ] Prefeitura define controlador, encarregado/canal de proteção de dados e funções responsáveis; definir em instrumento próprio o papel do Astuti como operador quando agir sob instruções da Prefeitura.
- [ ] Finalidades, bases legais, melhor interesse de crianças e adolescentes, minimização, retenção e descarte.
- [ ] Inventário de tratamento, avaliação de riscos e necessidade de relatório de impacto; revisão específica antes de qualquer diário offline com dados reais.
- [ ] Perfis nominativos por escola: gestão municipal, direção, secretaria escolar, professor e equipe multiprofissional; segundo fator e processo de revogação.
- [ ] Coordenação escolar: validar a necessidade de perfil próprio, ainda não provisionável na versão de referência; não emprestar conta de outro perfil.
- [ ] Rotina de atendimento de titulares, correção, incidentes e comunicação conforme avaliação da Prefeitura.
- [ ] Acesso do fornecedor apenas mediante finalidade, autorização e canal apropriado, sem coleta de dados em repositório público ou mensageria pessoal.
- [ ] Forma de transferência segura dos arquivos, restrição de acesso, prazo de retenção de cópias e descarte.
- [ ] Plano de devolução dos dados em formato aberto, com teste de legibilidade, documentação e tratamento das cópias de backup ao fim do contrato.
- [ ] Separar licença de uso, titularidade/gestão dos dados e propriedade intelectual do software.

## 8. Capacitação e aceite

- [ ] Multiplicadores por unidade e perfil, datas de formação e recursos de acessibilidade.
- [ ] Ambiente funcional fictício Monte Sereno; exercícios sem dados de crianças reais.
- [ ] Critérios para migração, treinamento, operação assistida e aceite; evidências agregadas sem dados pessoais.
- [ ] Responsável por aprovar cada etapa, tratar ressalvas e autorizar entrada em uso.
- [ ] Estimativa reavaliada após piloto, dependências externas e lista de requisitos planejados.

## Registro de pendências

| Código | Tema | Situação | Responsável por função | Prazo | Referência da evidência |
|---|---|---|---|---|---|
| D-01 | ____ | ____ | ____ | ____ | ____ |

**Saída do diagnóstico:** plano de implantação, inventário e mapa de campos, plano de segurança/privacidade, matriz de acessos, cronograma e critérios aprovados. Ver [roteiro](roteiro.md) e [regras de LGPD do projeto](../arquitetura/lgpd-by-design.md).
