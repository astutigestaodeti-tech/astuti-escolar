# Histórico escolar do Ensino Fundamental (issue #106)

## Finalidade e origem dos dados

O histórico escolar é montado a partir das matrículas existentes no banco municipal, sem redigitar anos cursados na rede. Só entram automaticamente no quadro de anos escolares as turmas do Ensino Fundamental com **ano letivo da turma fechado** e **resultado final confirmado**. Uma turma ainda aberta aparece apenas no aviso "Ano em curso", sem resultado ou média atribuídos.

Os dados de cada ano fechado são lidos de matrícula, escola, turma, componentes curriculares, notas, chamada anual e resultado final da issue #102. As médias seguem o boletim: apenas as notas efetivamente lançadas, sem transformar períodos vazios em nota zero.

A secretaria/direção pode digitar anos anteriores, com ano, etapa, escola, município, UF, resultado, frequência opcional e até 20 componentes com nota ou conceito de texto. Existe um registro ativo por aluno e ano, com índice único parcial no PostgreSQL. A edição conserva o identificador, não duplica o cadastro e exige segundo fator recente. Exclusão é lógica, com data, autor e motivo obrigatórios gravados juntos por `forceFill(...)->save()`, compatível com o CHECK da migration.

Ao detectar sobreposição com ano encerrado pela rede, a interface exibe aviso. No documento, o ano oficial tem prioridade e aparece **uma única linha por ano**. Anos em curso são informados em separado; sobreposições com eles também geram aviso. Se existirem dois vínculos encerrados da rede no mesmo ano, a tela informa o conflito para a secretaria revisar.

## Emissão, snapshot e autenticidade

A migration municipal `2026_11_23_000037_create_student_transcripts.php` cria `student_prior_school_years` e amplia o CHECK de `issued_documents.kind` para aceitar `school_transcript`, sem modificar documentos antigos. A emissão usa o mesmo gerador de códigos aleatórios de dez caracteres e QR Code local da declaração de matrícula.

O snapshot JSONB contém identificação escolar, nascimento e naturalidade, RA, escola emissora, anos, notas/conceitos, frequência, resultados, anos em curso e observações. O SHA-256 canônico representa esse conteúdo no instante da emissão. Trigger PostgreSQL proíbe alteração do JSONB, hash, aluno, escola, tipo ou instante de emissão, inclusive por SQL; a revogação permanece possível. Uma correção futura não altera o histórico emitido: emite-se outra via e, se necessário, revoga-se a antiga.

A visualização e impressão A4 paisagem da via emitida usam **somente o snapshot gravado**, não recalculam dados. O documento traz identificação, anos, observações, espaços de assinatura da direção e secretaria, código, QR e SHA-256. O QR conduz ao endpoint público já existente, com throttle e `noindex`.

**Para o kind `school_transcript`, a página pública mostra apenas tipo (Histórico escolar), escola emissora, emissão e válida/revogada. Não mostra nome nem nome abreviado, RA, INEP, nascimento, notas, frequência, ano, observações, hash ou snapshot.** A declaração de matrícula preserva o comportamento anterior. A consulta externa indica a validade do código, enquanto a cópia completa permanece restrita à instituição.

O cadastro atual possui RA, naturalidade e INEP **da escola**. Ainda não há campo próprio de INEP do aluno. Esse valor é incluído somente se existir, sem inferir o código da escola como código do estudante. A UF da rede pode ser configurada com `ASTUTI_MUNICIPALITY_UF`; sem configuração não se presume a UF com base no endereço residencial do aluno.

## Permissões e LGPD

- **Direção e secretaria**, com membership ativo e escopo da escola: visualizam e emitem, registram/editam e arquivam anos anteriores e revogam documentos. Alteração e arquivo exigem segundo fator recente; emissão exige segundo fator confirmado, como a declaração.
- **Gestão municipal**: consulta históricos e vias emitidas, mas não altera anos nem emite/revoga documentos.
- **Professor ou outra escola**: sem acesso aos históricos individuais.
- Todas as telas internas têm `Cache-Control: private, no-store`. O snapshot fica exclusivamente no PostgreSQL municipal, não em pasta pública, CDN, QR ou auditoria.
- A auditoria registra autoria, evento e identificador do aluno/ano; não copia JSON de notas/conceitos, textos livres, RA, nome, conteúdo do snapshot nem justificativa de exclusão. A whitelist é específica de `StudentPriorSchoolYear`. Não foi alterado o mascaramento global do AuditTrail, evitando afetar alunos e servidores.
- A exclusão lógica não apaga vias documentais emitidas. A emissão é feita apenas para aluno com vínculo na escola emissora.

## Testes e layout

`SchoolTranscriptTest` usa alunos, escolas, notas e históricos integralmente fictícios. Cobre ano fechado versus aberto, importação automática, anos anteriores, sobreposição, ordenação, conceito, exclusão justificada, auditoria, preservação de snapshot, hash, bloqueio em SQL, verificação pública sem nome, perfis e segundo fator.

`AllSchoolPagesOpenTest` inclui prévia, impressão e verificação pública do histórico, com HTML exportável para checagem em 1440 e 390 px. As tabelas usam `table-layout:auto`, largura mínima e rolagem no celular, `overflow-wrap:normal` e `word-break:normal`; o CSS inclui `font:inherit` em `textarea`.

**Limitação do ambiente de autoria:** o teste local PHP/PostgreSQL não está disponível. Pint, suíte completa e `checar-margens.py` ficam condicionados à execução no CI e no ambiente do Claude antes do merge. Sem alteração de dados reais ou deploy.
