# Sistema Presença (Bolsa Família)

Apoio ao operador escolar no acompanhamento da condicionalidade de educação do Programa Bolsa Família, decidido em 24/11/2026 depois da análise do GEP da Ergon.

## Regras oficiais seguidas

- Portaria Interministerial MEC/MDS nº 12/2025 (publicada em 18/12/2025):
  - acompanhamento em cinco períodos bimestrais: fevereiro e março; abril e maio; junho e julho; agosto e setembro; outubro e novembro;
  - frequência **mensal** mínima de 60% para estudantes de 4 a 6 anos incompletos e de 75% para estudantes de 6 a 18 anos incompletos que não concluíram a educação básica.
- O registro oficial é feito no Sistema Presença do MEC. Não há integração automática documentada; o Astuti **não promete envio**. Ele prepara o que o operador digita.

## Decisão de LGPD: o Astuti não guarda o benefício nem o motivo

- A lista de beneficiários vem do MDS para o próprio Sistema Presença. Guardar no Astuti quem recebe o Bolsa Família seria um dado socioeconômico de criança sem necessidade.
- A lista oficial de motivos de baixa frequência inclui situações extremamente sensíveis (por exemplo saúde mental, violência intrafamiliar, abuso e exploração sexual, atos infracionais). O motivo é registrado **só no Sistema Presença**, onde a lei manda registrar. No Astuti, o encaminhamento do caso segue pela busca ativa e pela equipe multiprofissional, com o controle de acesso desses módulos.
- O Astuti apura a frequência de **todos** os alunos de 4 a 17 anos (idade no primeiro dia do período), mês a mês. O operador procura aqui cada aluno da lista do Presença.

## O que a tela faz

- **Rede** (`/sistema-presenca`, gestão municipal): por escola, alunos na faixa etária, abaixo do mínimo, mês sem frequência e quantos já foram lançados.
- **Escola** (`/escolas/{escola}/sistema-presenca`, direção e secretaria):
  - lista nominal por turma, com o percentual de cada mês, faltas e faltas justificadas;
  - filtros por período, situação e nome;
  - botão "Marcar lançado", só para o controle do trabalho do operador.
- **Falta justificada conta como falta.** A justificativa é o que vira motivo no Presença.
- Matrícula encerrada no período aparece com aviso, pelas chamadas enquanto o aluno estava na turma.
- Consultas fixas por tela, independentemente do número de alunos (agregação no banco).

## Dados gravados

- Tabela `presence_registrations` (migration tenant 000060): escola, ano letivo, aluno, período, quem marcou e quando. Só isso.
- Auditoria `presence_registration.marked` e `.unmarked` com os identificadores, sem nome nem frequência.
