# Fechamento do ano letivo - Ensino Fundamental (issue #102)

## Funcionalidade

O sistema sugere, mas **nunca confirma**, aprovação ou retenção. Somente direção ou secretaria escolar vinculadas à unidade confirma decisões individualmente ou em lote. A confirmação em lote respeita decisões previamente registradas.

Sugestão:
- Aprovado quando todos os componentes obrigatórios ativos têm notas, suas médias atingem ASTUTI_MEDIA_MINIMA (padrão 6,0) e a frequência atinge ASTUTI_FREQUENCIA_MINIMA (padrão 75%).
- Retido quando existe componente com média abaixo do limite ou frequência inferior ao mínimo.
- Cursando quando faltam notas de componentes obrigatórios ou frequência e nenhum indicador já determina retenção.
- Sem nota não significa zero. Médias são aritméticas apenas das notas existentes, como no boletim.
- Transferência, saída da rede, abandono, conclusão e falecimento preservam a situação original da matrícula, sem novo cálculo.
- Decisão divergente da sugestão exige motivo, auditado com autoria e valores anteriores e novos.

## Migration municipal 000036

A migration 2026_11_23_000036_create_class_year_results.php acrescenta ao banco tenant apenas:
- class_year_closures, uma linha por turma existente e futura, com autoria, fechamento e reabertura. Trigger garante linha para turma nova.
- student_final_results, resultado confirmado ligado à matrícula e turma, sugestão, decisão, motivo, autor e data, sem repetir nome, CPF, endereço, saúde ou documentos.
- Triggers PostgreSQL bloqueiam alterações de notas e resultados em turmas fechadas. Os locks nas linhas de fechamento sincronizam gravações e encerramento.

O fechamento exige quatro bimestres ou três trimestres fechados e a confirmação humana de todas as matrículas ativas. Segundo fator recente e motivo obrigatório para fechamento e para reabertura excepcional pela gestão municipal.

## Ata de resultados finais

Página interna para impressão A4 paisagem: rede, escola, turma, etapa, ano, médias por componente, frequência anual, resultado, totais e espaços de assinatura. Antes do fechamento, a ata aparece como PRÉVIA NÃO OFICIAL. Os componentes usam abreviações numeradas com legenda.

O endpoint público existente de documentos não foi reutilizado. Ele exige documento individual vinculado ao aluno e exibe nome abreviado na validação, incompatível com documento coletivo sem alteração adicional do modelo. A ata **não tem endpoint público** e não transfere dados a terceiros.

## Permissões e LGPD

- Direção e secretaria com vínculo e escopo da escola: confirmar em lote ou individualmente, fechar turma e consultar/emitir ata.
- Gestão municipal: consultar, emitir ata e reabrir ano fechado; não confirma decisão pedagógica pela escola.
- Professor: somente consulta da ata da sua própria turma, mediante membership teacher da escola, vínculo vigente no cadastro de servidor e e-mail institucional correspondente.
- Educação Infantil recebe 404. Professor de outra turma ou escola não pode consultar a ata.
- Cache-Control: private, no-store em todas as páginas. Auditoria utiliza whitelists específicas das novas entidades; não altera o mascaramento global.

## Validação

Testes fictícios incluem sugestão por médias e frequência, nota ausente, divergência fundamentada, lote, situações de transferência, fechamento com períodos abertos, proteção PostgreSQL, segundo fator, reabertura, perfis, isolamento e auditoria. As duas páginas foram incluídas no AllSchoolPagesOpenTest para conferência das margens 1440/390 px.

O ambiente de autoria não executa a suíte Laravel/PostgreSQL; o Claude precisa executar Pint, suíte e margens no ambiente dele antes do merge. Sem dados reais nem deploy.
