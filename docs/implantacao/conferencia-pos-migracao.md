# Conferência pós-migração

Protocolo de aceite por unidade. Referência da interface: main 728edf40, 24/11/2026. Este modelo usa somente Monte Sereno, rede fictícia. O êxito técnico da importação não substitui a conferência humana.

## Preparação

1. A Secretaria fixa escola, ano letivo, data de corte e universo comparável do legado; conserva a fonte original em local privado autorizado.
2. A escola indica conferentes com perfil de gestão escolar e segundo fator. O Astuti acompanha as divergências e a TI valida infraestrutura.
3. A Secretaria reúne recibos e totais agregados, sem exportar fichas ou replicar documentos em planilhas de controle. Qualquer evidência nominal indispensável permanece no ambiente restrito da rede.
4. Conferir todas as unidades e todos os conflitos conhecidos. A verificação amostral complementa, não substitui, a conciliação dos totais e a revisão de dados de identidade importados com inconsistências.

Os nomes de telas abaixo correspondem às rotinas presentes na versão de referência. Os nomes de rotas entre crases servem para localização técnica, não exigem acessar endereço manualmente.

## Matriz de conferência

| Item | Tela e caminho | O que verificar e critério |
|---|---|---|
| Total por escola | Painel da rede; Escola > Alunos; Escola > Matrículas (school.enrollments.index) | Comparar alunos distintos e matrículas separadamente; selecionar o ano na lista de matrículas e a situação apropriada. A lista de alunos mostra pessoas com vínculo ativo na escola, não necessariamente o mesmo recorte anual. Não somar pessoas de escolas diferentes como se fossem alunos únicos da rede. |
| Matrículas por turma | Escola > Turmas > Turma (school.classes.show); Matrículas | Conferir escola, ano, etapa, turno, ocupação e lista de alunos ativos. Comparar com o gabarito; investigar divergências com o legado, inclusive transferências e vínculos múltiplos legítimos. |
| Alunos sem turma | Escola > Pendências do cadastro (school.pendencies.index), tipo Sem turma; Ficha do aluno > Histórico de vínculos | Identificar matrícula sem turma e verificar vínculo correto no ano. Usar Editar turma desta matrícula quando cabível; não atribuir arbitrariamente. |
| Identidade e nascimento | Pendências do cadastro, tipos Possível cadastro repetido e Sem data de nascimento; Ficha do aluno > Identificação | Verificar nome, RA e nascimento com a fonte autorizada. Homônimo não significa duplicidade. Resolver conflitos antes de declarar identificação conciliada. |
| CPF e certidão de nascimento | Ficha do aluno > Identificação; Editar cadastro (school.students.edit); Dados do Censo do aluno | Comparar documentos com fonte confiável. A prévia do importador rejeita ou sinaliza documentos inválidos/conflitantes; conferir os campos efetivamente gravados. Não exigir documentos inexistentes como condição para acesso à educação nem criar números artificiais. Pendências do cadastro não possui filtro específico de CPF ou certidão. |
| Responsáveis | Pendências do cadastro, tipo Sem responsável; Ficha do aluno > Responsáveis | Conferir parentesco, contato principal, telefone e vínculo com a pessoa correta. Não presumir condição de responsável legal pelo título de coluna. Tratar ausência de telefone com DDD e CPF conflitante; corrigir por fluxo autorizado. |
| Endereço e zona | Pendências do cadastro, tipo Endereço incompleto; Ficha do aluno > Endereço e Editar cadastro | Conferir logradouro, número, bairro, CEP, cidade, UF e zona urbana/rural conforme fonte. O filtro de endereço incompleto considera rua e CEP; a zona exige revisão da ficha, não se presume a partir do filtro. Não completar por suposição. |
| Transporte | Escola > Alunos, filtros de transporte; Ficha do aluno > Histórico de vínculos; Rotas de transporte (school.transport.routes.index) | Comparar utiliza, não solicitado, cancelado, sem informação e adaptação; conferir rota e ponto quando informados. Conferir ano e matrícula. Ausência de informação é diferente de não solicitado. |
| Pendências da importação | Importar alunos (school.imports.students.index) e Importar lista de transporte (school.imports.transport.index) | Revisar recibos, prévias e totais de inclusões, linhas ignoradas e complementos; não repetir importação para forçar atualização de valor existente. Refazer prévia se a origem mudar. |
| Censo Escolar | Conferência do Censo (census.readiness.index) > Educacenso da escola; Escola no Censo, Infraestrutura, Turmas no Censo, Alunos no Censo e Servidores no Censo | Verificar códigos, dados cadastrais e questionários; conferir recusas e avisos por registro, usar links de correção. A prontidão é lógica e local; não é protocolo oficial. |
| Identificadores Inep | Conferência do Censo > Retorno do Educacenso | Quando houver arquivo oficial compatível, conferir correspondências, conflitos e não localizados. Só confirmar os vínculos reconhecidos após revisão. Não substituir dados cadastrais pela importação de identificadores. |

A tela Pendências do cadastro mostra até 300 itens na lista, com indicação de excedentes. Usar resumo e filtros para organizar a conferência e voltar após correções. A ausência de pendência nessa tela não comprova validade de todos os documentos ou ausência de problema no Censo.

## 1. Reconciliação dos lotes

Adotar uma linha por escola, ano, tipo de lote e rodada. Contabilizar separadamente cadastros novos, matrículas novas, linhas ignoradas, complementos e casos recusados. Um complemento não equivale a novo aluno. Repetições internas e linhas rejeitadas podem impedir equivalência direta entre linhas e pessoas; documentar a classificação da fonte.

| Unidade fictícia | Ano | Origem: pessoas | Origem: matrículas ativas | Astuti: pessoas no recorte | Astuti: matrículas ativas | Diferença justificada | Situação |
|---|---|---:|---:|---:|---:|---|---|
| Escola piloto de Monte Sereno | ____ | ____ | ____ | ____ | ____ | ____ | ____ |

Para cada rodada registrar código do lote, escola, ano, data, quantidade de linhas, quantidade elegível, novas inclusões, ignoradas, complementadas, excluídas/recusadas, recibo e situação. Conferir se o total pós-importação corresponde ao total anterior mais as inclusões, descontando mudanças autorizadas e documentadas no intervalo. Não usar um total fixo do kit para todas as instalações.

## 2. Roteiro de verificação funcional após migração

- [ ] Autenticar com contas distintas e segundo fator; confirmar escola e escopo antes de trabalhar.
- [ ] Testar consulta e gravação permitidas e recusas em perfil ou escola não autorizados.
- [ ] Conferir ano, períodos acadêmicos, componentes existentes, calendário e atribuições docentes.
- [ ] Simular matrícula, movimentação, frequência, notas e documento verificável em ambiente fictício, sem alterar registros reais para testar.
- [ ] Verificar conselho de classe, gestão pedagógica e distorção com etapas confirmadas ou marcadas a confirmar.
- [ ] Conferir Sistema Presença: recorte de período e percentuais mensais; comparação com sistema oficial feita pelo operador autorizado. Não cadastrar benefício ou motivo de baixa frequência no Astuti.
- [ ] Validar rotinas contratadas de alimentação, transporte, busca ativa, alfabetização, educação especial, equipe multiprofissional, equidade/VAAR e tempo integral, dentro dos perfis existentes.
- [ ] Conferir backups cifrados, cópia externa, ensaio de restauração, separação dos ambientes, acesso à versão e suporte.
- [ ] Conferir dados do Censo; anotar que a geração está bloqueada nesta referência e não existe envio automático. Recusas do Censo não devem ser escondidas nem confundidas com erro de matrícula.

## 3. Registro das divergências

| Código | Tipo de divergência | Quantidade | Gravidade | Responsável por função | Prazo | Estado |
|---|---|---:|---|---|---|---|
| C-01 | ____ | ____ | ____ | ____ | ____ | ____ |

- **Crítica:** pessoa ou matrícula incorreta, acesso indevido, exposição de dados, perda de informação, backup/restauração inviável, bloqueio da operação essencial ou total não conciliado. Não liberar a unidade até solucionar e retestar.
- **Alta:** lacuna relevante que compromete obrigação ou rotina; resolver antes do fluxo afetado ou adotar contingência expressamente aprovada.
- **Residual:** ausência legítima de dado, ajuste cadastral não impeditivo ou melhoria planejada, com responsável e prazo. Não transformar campo opcional em obrigação sem fundamento.

Não inserir nomes, CPF, certidões, endereços ou motivos de atendimento nesta tabela. Usar código de evidência controlado em ambiente privado quando necessário.

## 4. Aceite da conferência

- [ ] Todas as escolas e recortes definidos reconciliados.
- [ ] Identificações conflitantes e vínculos incorretos resolvidos.
- [ ] Campos ausentes classificados e encaminhados sem preenchimento artificial.
- [ ] Pendências do Censo identificadas e atribuídas, sem prometer validação federal.
- [ ] Controles de segurança e recuperação aprovados.
- [ ] Pendências remanescentes formalmente aceitas, sem bloqueio crítico.

Registro interno: data ____ | escola/onda fictícia ____ | referência do relatório ____ | funções responsáveis ____ | resultado: aprovado / não aprovado / aprovado com ressalvas não impeditivas.

Prosseguir conforme [roteiro](roteiro.md) e [termo de homologação](termo-homologacao.md).
