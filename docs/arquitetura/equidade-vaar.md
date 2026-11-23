# Painel de equidade e VAAR - issue #108

## Dados e metodologia

Painel interno para comparar frequência, médias de notas e resultados finais confirmados, com cortes por cor/raça, sexo e zona de residência, em nível de rede, escola ou turma e com seleção do ano. Reaproveita o padrão da Conferência do Censo: leitura em lote das matrículas, chamada, notas e decisões finais. Não faz uma consulta por escola, turma ou aluno.

- A frequência média é calculada com base no percentual de presença de cada estudante nas chamadas do ano e da turma, com justificadas tratadas como ausências, tal como no boletim.
- A média escolar de cada aluno é a média das médias de notas efetivamente lançadas em seus componentes. Períodos vazios não são nota zero. A média do grupo é a média dos alunos com notas.
- O resultado retido vem exclusivamente da confirmação humana em student_final_results, não da sugestão da issue #102.
- A lista de acompanhamento Saeb considera faltas das turmas de 2º, 5º e 9º ano e contém apenas aluno, escola, turma e faltas/frequência. Ela não contém cor/raça, sexo, deficiência nem zona de residência.
- Após o PR #112 (Educação Especial e AEE), deficiência e TEA são agrupados exclusivamente a partir de student_special_education.conditions. Altas habilidades isoladamente não contam como deficiência. A categoria sem registro não comprova ausência; nenhum valor individual é transmitido à view ou à auditoria.

**Atenção:** frequência escolar não equivale à participação no Saeb. O próprio Inep afere as condicionalidades II e III do VAAR. As médias e diferenças dos grupos no painel são sinais internos, não o cálculo oficial da elegibilidade.

## Condicionalidades I a V

A migration municipal 2026_11_23_000038_create_vaar_conditions.php cria a tabela vaar_conditions no banco exclusivo tenant. Um registro por ano/inciso, protegido com CHECK e índice único. O gestor municipal informa status (cumprida, a comprovar ou pendente), responsável e, opcionalmente, anexa PDF institucional. O cadastro de situação cumprida é declaração interna, não homologação oficial.

I: seleção de gestores escolares por critérios técnicos de mérito e desempenho.
II: participação de pelo menos 80% dos estudantes avaliados no Saeb.
III: redução das desigualdades socioeconômicas e raciais aferidas pelo Saeb.
IV: regime de colaboração estadual e legislação do ICMS Educacional.
V: referencial curricular alinhado à BNCC e aprovado pelo respectivo sistema.

Fontes públicas consultadas:
- Lei 14.113/2020, art. 14: https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2020/lei/l14113.htm
- Cartilha oficial FNDE: https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/manuais-a-cartilhas-1/Cartilha_v_final_01_08_2025.pdf
- Indicadores Inep: https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/fundeb/bases-de-dados/indicadores-da-complementacao-vaar

O PDF de evidência é restrito a documento institucional sem dados pessoais de crianças. O arquivo fica no disco local privado do Laravel, com nome aleatório, fora de public/. Downloads exigem perfil de gestor municipal, sem cache e com Content-Type-Options nosniff. Atualização e auditoria em transação, limpeza de novo upload se falhar e retirada do anexo anterior somente após gravar com sucesso.

## LGPD e proteção contra reidentificação

- O serviço de agregação mantém os campos sensíveis apenas transitoriamente na memória; nunca os repassa à view, aos logs ou à auditoria. As tabelas de cor/raça e demais grupos contêm apenas agregados.
- Grupo de 1 a 4 alunos aparece literalmente como **grupo pequeno**, sem números, médias, frequência ou retenções. Quando há grupo pequeno, outro grupo é suprimido para que a subtração do total não revele o valor escondido. A soma preta+parda+indígena também é suprimida se houver grupo racial pequeno.
- Médias escolares só são exibidas quando ao menos cinco alunos possuem registros. Retenções positivas de 1 a 4 alunos também ficam ocultas. Se os resultados finais confirmados forem de menos de cinco alunos, o indicador não é mostrado.
- Não há filtros nominativos ou cruzamentos por atributos sensíveis. O acompanhamento nominal de faltas é uma lista operacional separada, sem qualquer coluna sensível. Persistem riscos residuais de diferenças entre recortes; os usuários e o acesso são restritos.
- Somente municipal_manager consulta os indicadores de toda a rede, marca condicionalidades e lê PDFs. school_director vinculado à unidade pode consultar apenas a própria escola, sem acesso ao checklist da rede; teacher e school_secretary não acessam.
- Páginas e downloads com Cache-Control: private, no-store. Auditoria registra somente eventos e metadados administrativos (inciso, status, responsável, presença de evidência, autor), nunca cor/raça, dados individuais ou caminho/conteúdo do PDF. Whitelist criada apenas para VaarCondition, sem alterar o mascaramento global.

## Interface e validação

Rotas: /m/{municipio}/equidade-vaar (gestão) e /m/{municipio}/escolas/{escola}/equidade-vaar (direção da unidade ou gestão). Menu municipal para gestão e cartão na página da própria escola para direção.

O conteúdo dentro dos cartões usa folha-corpo, inclusive recuo superior. Tabelas possuem table-layout:auto, min-width, overflow-wrap:normal, rolagem lateral e sem separação de palavras. Placeholders curtos, como Ex.: Coordenação.

EquityVaarTest cobre perfis, escola/turma, células pequenas e supressão complementar, médias e retenções confirmadas, lista Saeb sem classificação sensível, PDF privado e auditoria, 2FA recente e CHECKs. AllSchoolPagesOpenTest inclui quatro páginas novas para conferência em 1440 e 390 px, com fixtures fictícias.

O ambiente de autoria não roda a suíte Laravel completa com bancos PostgreSQL descartáveis. O Claude revisará o PR e os pixels; o CI determinará a aprovação dos testes. Sem uso de dados reais, merge ou deploy.
