# Transporte escolar: linha, ponto e veículo adaptado

**Origem:** issue #49. **Data:** 22/11/2026.

## Objetivo

A escola deve consultar os dados operacionais que a lista fictícia da SME já traz: linha/rota, ponto de embarque e indicação de veículo adaptado. A importação continua sem criar matrícula nem estudante.

## Decisão e abordagens

1. **Ampliar a matrícula existente (escolhida):** três colunas opcionais no `student_enrollments`, reaproveitando o CSV, a conferência cifrada, o lock, o recibo e a auditoria existentes. Mantém o contexto por escola/ano e evita cadastro paralelo.
2. Criar uma tabela própria de transporte: dá mais flexibilidade futura, mas acrescenta relações e migração sem necessidade para estes três campos.
3. Guardar os dados como JSON: exigiria validação e filtro menos explícitos, sendo pior para consulta e auditoria.

## Comportamento e limites

- Migração municipal `000022` com `transport_route` até 60, `transport_stop` até 160 e `transport_adapted_vehicle` booleano; todos anuláveis.
- Cabeçalhos: Linha/Rota, Ponto de embarque/Ponto, Adaptado/Veículo adaptado, com ou sem acentos. Adaptado: Sim/S/X = sim; Não/N = não; vazio = não informado. Valor desconhecido é erro legível.
- A prévia mostra somente os dados da matrícula autorizada. Campo opcional ausente/vazio jamais apaga um valor anterior; não informado não equivale a Não.
- Na confirmação, o token cifrado é revalidado e a versão da matrícula é rechecada sob lock. A alteração dos quatro campos é uma única operação auditada dentro da transação de lote.
- A lista apresenta linha sob a etiqueta, além de filtro por veículo adaptado com contagem e escopo da escola. O partial da ficha apresenta linha, ponto e indicação de adaptação, sem editar `students/show.blade.php`.
- Somente a gestão da escola, com segundo fator confirmado, importa. Consulta respeita o gate `view`, inclusive para professor autorizado. Dados fictícios apenas, sem alterar fotografias, saída de alunos, outras migrações ou bancos do notebook.

## LGPD desde o desenho

Finalidade operacional: organizar atendimento seguro de transporte escolar, sem importar laudos, diagnósticos ou endereço domiciliar. Ponto de embarque e indicador de veículo adaptado são registrados na auditoria como `[REDACTED]`. O arquivo CSV não fica armazenado; a prévia é cifrada e expira em uma hora. O recibo não inclui dados pessoais. Nenhum consentimento adicional é pedido para esse tratamento administrativo.
