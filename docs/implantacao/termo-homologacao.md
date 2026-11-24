# Modelo de termo de homologação da implantação

**Astuti Gestão Escolar - Prefeitura de Monte Sereno (fictícia)**

Modelo sem dados pessoais, exclusivamente para preparação de documento de aceite. Não é contrato, termo de licenciamento, parecer jurídico, certificação de segurança, aprovação de órgão de controle ou declaração de conformidade com a LGPD. Adaptar e aprovar em processo administrativo próprio, de acordo com o instrumento contratual e o edital. O documento preenchido e assinado não deve ser publicado no repositório.

## 1. Identificação do aceite

| Campo | Preenchimento |
|---|---|
| Referência do processo/instrumento | ____ |
| Objeto | Implantação da licença de uso do Astuti Gestão Escolar, conforme escopo contratado |
| Rede exemplificativa | Monte Sereno (fictícia) |
| Versão/commit entregue | ____ |
| Período de implantação | ____ |
| Número de escolas abrangidas | ____ |
| Referência das evidências restritas | ____ |
| Data da decisão | ____ |

A homologação abrange somente os módulos, ambientes e unidades expressamente identificados nas evidências. O aceite não equivale a homologação de dados pelo Inep ou MEC, nem dispensa o cumprimento de obrigações legais.

## 2. Escopo efetivamente conferido

Marcar uma situação por item: aprovado, não aprovado, aprovado com ressalva ou não aplicável (fora do escopo justificado). Anexos devem ser agregados e não conter dados pessoais.

| Verificação | Situação | Referência da evidência |
|---|---|---|
| Diagnóstico e plano aprovado | ____ | ____ |
| Instalação exclusiva, ambientes segregados e HTTPS | ____ | ____ |
| Contas, escopos e segundo fator | ____ | ____ |
| Backup cifrado, cópia externa e restauração ensaiada | ____ | ____ |
| Importações compatíveis e recibos | ____ | ____ |
| Totais por escola e turma, alunos e matrículas conciliados | ____ | ____ |
| CPF, certidão, responsáveis, endereço, zona e transporte conferidos conforme fontes | ____ | ____ |
| Calendário, períodos, frequência, notas e documentos do escopo | ____ | ____ |
| Gestão pedagógica e conselho de classe, conforme perfis existentes | ____ | ____ |
| Conferência lógica do Censo e retorno de identificadores, quando aplicável | ____ | ____ |
| Sistema Presença como apoio ao lançamento manual no sistema oficial | ____ | ____ |
| Demais módulos efetivamente contratados e testados | ____ | ____ |
| Treinamento por perfil e aptidão de multiplicadores | ____ | ____ |
| Operação assistida, suporte e plano de continuidade | ____ | ____ |
| Avaliação de segurança, privacidade e controles de acesso | ____ | ____ |
| Procedimento de devolução de dados pactuado e testado | ____ | ____ |

Não marcar como aprovado recurso ainda planejado. Na versão de referência de 24/11/2026 (728edf40), o diário instalável offline não foi entregue, o gerador do Educacenso permanece bloqueado, não há integração automática com MEC/Inep e o perfil autônomo de coordenação escolar não está provisionável. Atualizar essa lista mediante verificação da versão realmente instalada.

## 3. Licença e dados

O modelo de negócio é a concessão de licença de uso do software nos limites do contrato. A propriedade intelectual do sistema, seu código-fonte e demais elementos próprios do Astuti permanecem com o Astuti; a implantação não implica cessão desses direitos.

Os dados escolares mantidos no sistema são da Prefeitura, para fins de gestão, custódia, acesso e devolução, respeitados os direitos dos titulares de dados pessoais e as obrigações legais. O fornecedor não adquire direito de comercializar, reutilizar ou reter os dados da rede para finalidade própria por prestar o serviço. A atuação como operador de dados, quando sob instruções do controlador municipal, deve ser formalizada no instrumento apropriado. Não há transferência de propriedade intelectual do software pela devolução dos dados.

## 4. Cláusula-modelo de devolução de dados e continuidade

Para validação pela Prefeitura e inclusão no instrumento contratual pertinente:

> Ao término, rescisão ou substituição da contratação, o Astuti disponibilizará à Prefeitura, mediante procedimento seguro, os dados municipais sob sua custódia e necessários à continuidade do serviço, em formato aberto, documentado e legível por ferramentas independentes, sem exigir licença do Astuti para a leitura. A entrega compreenderá o escopo de dados previsto no contrato, incluindo registros estruturados, relacionamentos, metadados necessários à interpretação e documentos/anexos sob custódia, observadas as restrições legais de acesso, sigilo e retenção. A Prefeitura conferirá a integridade e a completude da entrega antes do encerramento operacional. O tratamento e o descarte das cópias remanescentes, inclusive backups, obedecerão ao prazo pactuado, às instruções legítimas do controlador e às hipóteses de conservação obrigatória. A devolução dos dados não importa cessão do código-fonte ou da propriedade intelectual do Astuti.

**Procedimento a pactuar e testar antes da homologação:**

1. Definir responsáveis, data de corte, janela de continuidade, prazo e escopo da entrega no contrato.
2. Produzir, por procedimento técnico autorizado, tabelas estruturadas em CSV UTF-8 ou JSON, com dicionário de campos, identificadores e relacionamentos; preservar datas, valores nulos e codificações. Entregar arquivos em seus formatos originais ou em formatos abertos adequados (por exemplo PDF/A quando viável), com índice de anexos e vínculos.
3. Incluir os registros, histórico e evidências administrativas/auditoria pertinentes ao escopo, respeitando minimização, permissões e exigências legais; excluir credenciais, segredos, tokens, chaves de aplicação e componentes que não constituam dados municipais transferíveis.
4. Gerar manifesto de arquivos, contagens e somas SHA-256; proteger a transferência e combinar a entrega das chaves por canal separado autorizado.
5. Conferir legibilidade com ferramentas independentes, contagens, integridade referencial e amostras em ambiente isolado. A Prefeitura registra aceite ou solicita correção.
6. Após aceite e autorização de encerramento, revogar acessos, observar retenção legal e contratual e documentar descarte seguro ou conservação excepcional. Não eliminar backup necessário sem autorização nem prometer exclusão instantânea de toda cópia.

O backup PostgreSQL e os arquivos privados são recursos operacionais de recuperação já documentados; não equivalem, por si só, a exportação interoperável. **A versão de referência não possui exportador geral em formato aberto de todo o acervo.** Este procedimento é requisito de implantação e saída, não declaração de botão pronto ou entrega já testada. A disponibilidade de extração segura precisa ser demonstrada em ensaio fictício e prevista no contrato antes do aceite.

## 5. Ressalvas e plano de correção

| Código | Ressalva sem dados pessoais | Impacto | Responsável por função | Prazo | Critério de resolução |
|---|---|---|---|---|---|
| H-01 | ____ | ____ | ____ | ____ | ____ |

Não aprovar com ressalva impeditiva: perda de dados, acesso indevido, divergência crítica de identidade ou matrícula, impossibilidade de recuperação, ausência de requisito essencial contratado ou falha crítica de segurança. Ressalvas não impeditivas exigem decisão fundamentada e plano de correção.

## 6. Decisão

- [ ] Homologação aprovada, dentro do escopo efetivamente conferido.
- [ ] Homologação aprovada com ressalvas não impeditivas, descritas acima.
- [ ] Homologação não aprovada, com necessidade de correção e novo teste.

A Prefeitura registra a decisão conforme competência e rito administrativo. O aceite não renuncia a direitos, garantias, obrigações de suporte ou correção previstos em contrato, nem autoriza tratamento fora das finalidades pactuadas.

**Manifestação por função (sem identificação pessoal neste modelo):**

- Representação competente da Prefeitura: ____________________
- Secretaria Municipal de Educação: ____________________
- TI da Prefeitura: ____________________
- Representação do Astuti: ____________________

Data: ____ / ____ / ____

As assinaturas da versão oficial devem ser colhidas no processo administrativo apropriado. Este modelo não contém assinaturas reais nem nome de agente público.

Referências: [roteiro de implantação](roteiro.md), [conferência pós-migração](conferencia-pos-migracao.md), [backup e restauração](../operacao/vps-backup.md).
