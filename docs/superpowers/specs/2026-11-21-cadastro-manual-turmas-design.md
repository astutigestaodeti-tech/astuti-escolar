# Cadastro manual de turmas por escola - desenho

**Data:** 21/11/2026  
**Base:** PR #24, feature/voltar-sem-rota-post, commit 44c8c6a.

## Objetivo
Permitir à direção, secretaria e gestão municipal cadastrar e editar turmas
com os campos já existentes em school_classes: nome ou código, etapa,
modalidade, turno, vagas opcionais e ano letivo. Hoje somente a demonstração
cria esses registros.

## Regras e limites
- Usar o escopo e a autorização manageStudents da escola; professores só consultam.
- Exigir RequireConfirmedTwoFactor nas quatro rotas de formulário e gravação.
- Criar turmas com status active, exibidas imediatamente na lista.
- Ano letivo deve estar active ou planned para criar; em edição, o ano original
  encerrado pode permanecer, mas não se permite transferir uma turma para outro
  ano quando já possui matrículas, inclusive arquivadas.
- Preservar o vínculo da escola definido pela URL; não aceitar school_id no POST.
- Nome obrigatório, sem espaços repetidos; duplicata ativa na mesma escola/ano
  é recusada independentemente da caixa de letras. Outras escolas/anos
  podem ter nomes iguais; turmas inativas ou arquivadas não ocupam o nome.
- Capacidade opcional, inteira e positiva até 32767, limite seguro em PostgreSQL.
- Auditar antes e depois pelos eventos de HasSchoolHistory, no mesmo commit
  transacional; edição usa xmin para prevenir sobrescrita concorrente.
- Não alterar arquivos do segundo fator, importadores, views de importação
  nem bases existentes. Não mesclar nem implantar.

## Alternativas
1. Validação somente no controlador: menor alteração, mas duas gravações
   simultâneas podem inserir o mesmo nome.
2. Validação no controlador e índice único parcial no banco: mensagem clara
   para o usuário e integridade garantida contra concorrência. **Escolhida.**
3. Tabela auxiliar de códigos: redundante e fora do objetivo.

## Interface
O botão Nova turma fica ao lado da contagem da lista; Editar aparece em
cada linha somente para perfis de gestão. O formulário reaproveita os estilos
acessíveis do cadastro escolar. O retorno Voltar nunca aponta para POST/PUT.

## Verificação
Testes funcionais com dados fictícios: criar e editar com auditoria;
duplicidade no formulário e no banco; diretoria, secretaria, gestor e professor;
segundo fator; escopo de escola e ano; validação em português, capacidade
opcional e versão de edição. Rodar Pint e CI do PR antes de conclusão.
