# Tempo integral, meta pactuada e 4% do Fundeb - issue #115

## Escopo e classificação

A jornada diária de uma turma fica em school_classes.daily_hours, decimal de uma casa, entre 0,5 e 16 horas. Somente a gestão municipal a grava pelo detalhe da turma, com segundo fator recente, transação e auditoria existente do modelo SchoolClass. A direção e secretaria continuam podendo administrar os demais campos da turma pelas permissões originais, **mas não alteram a jornada**. Campos sem valor permanecem nulos e são classificados provisoriamente pelo texto do turno.

- Jornada de 7,0 horas ou mais: integral.
- Jornada de menos de 7 horas: parcial, mesmo se o turno disser Integral.
- Sem jornada: tentativa de identificação pelo turno existente. A lista sinaliza o cadastro ainda não conferido e as divergências entre jornada informada e turno.
- A Conferência do Censo usa a jornada quando conhecida e mantém o turno como alternativa; não muda os critérios de etapa.

## Acompanhamento da rede

O serviço FullTimeReadiness usa consultas fixas pelos modelos School, SchoolYear, SchoolClass e StudentEnrollment, com alunos carregados em lote. Não faz consultas por matrícula. Usa somente matrículas ativas e alunos não arquivados, com eliminação de duplicatas por aluno, além de escolas ativas.

- Cada etapa (creche, pré-escola, fundamental iniciais e finais) tem meta pactuada anual. O registro de metas é exclusivo da gestão municipal; no painel da rede, meta versus matrículas integrais atuais é explícito, inclusive diferença não atendida.
- O painel apresenta matrículas integrais por escola, turma e aluno, com situação de classificação pela jornada. Direção e secretaria podem consultar somente suas escolas, sem receita, meta ou comprovantes financeiros; professor não tem acesso ao painel.
- **Criadas no ano:** alunos com matrícula integral ativa no ano de referência que não constavam em turma integral no ano letivo anterior, inclusive alunos novos e conversões de parcial para integral. A comparação considera vínculos anteriores que permanecem registrados como active ou completed, inclusive transferências entre escolas da rede. Sem cadastro de ano anterior, conta como novo apenas para fins de comparação interna.
- **Limitação:** o sistema não preserva uma fotografia institucional formal do último dia do ano anterior e a situação histórica de conversão pode ter mudado. Essa métrica é aproximação operacional, não comprovação de vagas novas ou preenchimento automático de sistemas do MEC. A prestação externa requer documentos e evidências oficiais.

## Receita e aplicação mínima de 4%

A migration tenant 2026_11_23_000040_create_full_time_education.php acrescenta daily_hours a school_classes e cria integral_pact_targets, integral_fundeb_finances, integral_fundeb_expenses; todas no banco exclusivo municipal. Índice único protege a meta por ano/etapa e a receita por ano. CHECK protege limites de jornada, etapas permitidas, gasto positivo e metadados coerentes do comprovante.

A gestão informa a receita anual do Fundeb e registra cada aplicação por descrição, valor e PDF opcional. Os montantes são armazenados em centavos inteiros, sem ponto flutuante. Mínimo exigido: teto em centavos de 4% da receita informada. Saldo faltante: máximo entre zero e mínimo menos aplicações cadastradas. **Esse valor registrado não equivale a uma prestação oficial homologada.**

Comprovantes PDF até 8 MB são armazenados no disco Laravel local privado, fora de public/, com nome aleatório. Download exclusivo municipal autenticado, sem cache e com nosniff. A gravação e a auditoria são transacionais; o upload é limpo se ocorrer erro. Não há exclusão de aplicação nem campo para inserir informação sobre crianças no documento.

## LGPD, isolamento e auditoria

A listagem nominal contém somente nome, escola, turma, etapa e comparação gerencial, com acesso restrito ao gestor da rede ou direção/secretaria da própria escola. Não lista cor/raça, deficiência, saúde ou documentos pessoais. Não há endpoint público. Financeiro e evidências ficam só com a gestão municipal.

A auditoria guarda alterações de jornadas e das metas/receita/despesas por whitelist específica das novas entidades. Para PDFs registra apenas sua existência, sem nome, caminho ou conteúdo; a leitura do painel e dos documentos é auditada. Escrita exige segundo fator recente. Páginas e documentos têm Cache-Control: private, no-store. O código obedece ao teste de arquitetura C2: não faz consultas raw para escolas, turmas ou matrículas, não usa toBase nem alterações silenciosas.

## Interface e testes

Menu "Tempo integral" exclusivo da gestão, painel de escola acessível a direção e secretaria, campo gerencial de jornada no detalhe da turma, aviso de divergência na lista, e CSS responsivo com tabela de rolagem lateral, sem quebra de palavras. Conteúdo em .folha-corpo com recuo inclusive no topo.

FullTimeEducationTest: classificação >= 7h, turno versus jornada e Censo, meta versus realizado, comparação anterior, 4% em centavos, PDF privado, permissões, auditoria, 2FA recente e validação. AllSchoolPagesOpenTest: cinco novas visualizações com dados totalmente fictícios para conferir margens 1440/390.

Não foram executados testes locais, por indisponibilidade de PHP/Composer/PostgreSQL descartável no ambiente de autoria. O CI verificará Pint e a suíte, e o Claude executará a conferência de margens antes de mesclar. Sem deploy.
