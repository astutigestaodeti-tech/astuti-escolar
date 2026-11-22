# Importação controlada da lista de transporte escolar

## Objetivo

Receber CSV de transporte de uma escola, identificar matrículas ativas por RA e conferir as mudanças antes de gravar. A etapa deve funcionar com dados fictícios e permanecer segura quando os formatos reais chegarem; não promete compatibilidade com o CSV original ausente do repositório.

## Escopo e limites

- Uma Prefeitura por VPS e banco municipal. Uma escola e um ano letivo por envio; apenas gestor municipal, diretor ou secretário autorizado com 2FA.
- CSV até 2 MB, UTF-8 ou Windows-1252, separador `;` ou `,`, até 500 linhas; cabeçalhos de RA, situação do transporte e nome opcional. Valores: SIM/ATIVO, NÃO e CANCELADO.
- Identificação **somente pelo RA**, que deve corresponder a um aluno com matrícula ativa nessa escola e ano. Nome, se presente, deve conferir. RA desconhecido, linhas duplicadas, estado desconhecido ou conflito: nenhum registro é alterado.
- Situação pertence à matrícula daquele ano, não ao cadastro municipal do aluno. `active`, `not_requested` e `cancelled` são os estados; o cancelamento nunca é reativado pelo importador.
- A prévia apresenta nome, RA e situação atual/proposta. O arquivo não é salvo. Payload mínimo cifrado por uma hora, vinculado à conta/escola/ano. A confirmação valida novamente escola, matrícula, RA e versão PostgreSQL (`xmin`); concorrência invalida tudo.
- Uma transação grava mudanças, auditoria antes/depois e recibo sem PII para impedir replay. Se qualquer passo falhar, nenhum efeito parcial. Sem cadastro de alunos, sem exclusão, sem endereço/saúde/CPF/telefone.
- As telas têm links de retorno; documentos e testes usam apenas dados fictícios.

## Abordagens consideradas

1. Coluna `transport_status` na matrícula (escolhida): mantém o contexto de escola/ano, usa a auditoria existente e evita uma tabela de domínio prematura.
2. Nova entidade de transporte por aluno: permitiria rotas/benefícios futuros, mas introduziria vínculos independentes sem informação suficiente no CSV.
3. Atualizar a ficha geral do aluno: incorreto quando o transporte muda por escola ou ano letivo.

## Verificação

Parser cobre Windows-1252, acentos, cabeçalhos, status e linhas repetidas. Integração PostgreSQL verifica prévia sem escrita, gravação auditada, permissão e 2FA, escopo escolar, cancelamento irreversível via importação, replay, concorrência e rejeição integral de conflitos.
