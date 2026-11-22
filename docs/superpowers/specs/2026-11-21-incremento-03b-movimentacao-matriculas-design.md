# Incremento 03 parte B: movimentação de matrículas

## Objetivo

Permitir que gestores autorizados transfiram uma matrícula ativa para outra escola da mesma rede, encerrem um vínculo por motivo fixo ou arquivem um cadastro criado por engano. Preservar o histórico escolar, a auditoria, o segundo fator e o escopo de cada escola.

## Regras

- Ações só partem da matrícula ativa vinculada à escola de origem. O identificador da matrícula faz parte da URL para não confundir vínculos de anos diferentes.
- Transferência e encerramento: gestor municipal, diretor ou secretário com permissão de gestão na origem. Destino da transferência: outra escola ativa da mesma base municipal. Encerramento: saída da rede (`left_network`, destino textual opcional), abandono (`abandoned`) ou falecimento (`deceased`).
- Transferência encerra a matrícula de origem (`transferred`, `ended_at`) e cria, na mesma transação, matrícula `active` na escola de destino, no mesmo ano letivo e na mesma data. Recusar destino igual à origem ou se o aluno já estiver ativo no destino.
- Data obrigatória no intervalo inclusivo do ano letivo e não anterior ao início da matrícula; situação ativa e versão de linha PostgreSQL `xmin` devem ser conferidas novamente sob bloqueio pessimista no banco, inclusive se duas alterações ocorrerem no mesmo segundo.
- Exclusão por erro de cadastro: apenas gestor municipal ou diretor autorizado na origem, com justificativa de pelo menos 15 caracteres. Arquivar matrícula via `deleteWithReason`; arquivar também o aluno se não restar outra matrícula não excluída. Não usar exclusão física.
- Todas as operações exigem segundo fator confirmado no POST/DELETE. GETs de confirmação redirecionam para o desafio se ainda não confirmado. Botões só aparecem para perfis autorizados.
- Toda alteração usa as rotinas auditadas dos modelos, dentro de uma transação municipal. O destino textual opcional de saída fica em tabela auxiliar municipal vinculada à matrícula, com evento auditado no mesmo commit. Isso mantém o formato da tabela histórica de matrículas compatível com a cópia de bancos MariaDB e faz com que somente status e ended_at mudem no vínculo. Não incluir dados sensíveis, documentos nem informações médicas.

## Abordagem

Usar um controlador de movimentação com métodos de confirmação e um serviço de domínio transacional, apoiados na Policy da escola. Consideradas alternativas de atualização direta por query builder (rejeitada: contornaria a auditoria) e uma matrícula sem identificador na rota (rejeitada: ambiguidade de vínculos de anos distintos). A abordagem escolhida mantém Eloquent auditado e bloqueio de linhas.

## Não objetivos

- Importação, alterações do limitador de segundo fator, PRs em andamento, criação de matrícula avulsa ou transferência entre Prefeituras.
- Exclusão física, modificação do banco original de notebook ou uso de estudantes reais.

## Verificação

Testes de integração PostgreSQL com dados fictícios para fluxo, auditoria antes/depois, papéis, segundo fator, 404, versões concorrentes, datas e listagem das duas escolas; Pint e CI em pull request.
