# Busca inteligente de CEP no cadastro manual de alunos

Data: 21/11/2026. Base: PR #25, feature/cadastro-turmas-escola.
Aprovado por Jefferson no diálogo, incluindo cache local e campos Complemento, Cidade e Estado.

## Objetivo
A digitação começa pelo CEP. Ao fornecer oito algarismos, a interface busca
logradouro, bairro, cidade e UF, preenche as sugestões e permite editar todos
os campos. Número, complemento e zona continuam preenchidos pela pessoa.

## Decisões e alternativas
1. Base DNE oficial integral no VPS: mais controle offline, mas exige obtenção,
   licenciamento e atualização de base comercial. Não será distribuída.
2. API direta no navegador: simples, mas expõe dependência e cria consultas
   repetidas. Não será usada.
3. ViaCEP consultado pelo backend, com cache municipal PostgreSQL de 30 dias,
   tolerância a indisponibilidade com cache antigo e edição manual. Escolhida
   e aprovada. Somente o CEP vai ao provedor, nunca nome ou dados do estudante.

## Escopo técnico
- Migração tenant nova para students.address_complement (180), address_city
  (120), address_state (2), todos anuláveis, e tabela postal_code_cache
  com CEP único, endereço público e fetched_at.
- StudentRecordController aceita campos opcionais nas duas gravações, normaliza
  UF para maiúsculas e valida a lista real de 27 UFs. Model e AuditTrail
  incluem os novos dados, preservando antes/depois.
- Endpoint GET sob município e escola, com manageStudents, login e segundo fator
  confirmado, throttle, validação estrita de CEP e JSON mínimo.
- ViaCEP é consultado com timeout curto; 404/503 deixam edição manual;
  cache recente atende sem chamada externa e cache antigo responde como
  desatualizado quando a API falha.
- Foco na experiência: CEP primeiro, botão Buscar e consulta automática,
  status acessível aria-live, sugestões não sobrescrevem mudanças feitas
  enquanto a resposta chega. Número, complemento e zona jamais são preenchidos
  pelo serviço. CEP geral pode retornar somente cidade/UF.
- Nova ficha mostra os três campos; nenhum fluxo de importação, OCR, 2FA ou
  ramificação de outro PR será modificado.

## Testes e risco
Feature tests falsificam chamadas HTTP; verificam cache, falhas, CEP sem
logradouro, permissões, 2FA, validação, criação/edição e auditoria.
Node:test verifica formatação do CEP e proteção contra sobrescrever dados.
O CI testa Pint, PHP e integração MariaDB/PostgreSQL. Sem dados reais,
merge ou deploy.
