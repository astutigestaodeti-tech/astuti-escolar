# Histórico escolar, exclusão lógica e auditoria

O Astuti Gestão Escolar **nunca apaga fisicamente registros escolares por uma ação da interface**. `School`, `SchoolYear`, `SchoolClass`, `Student` e `StudentEnrollment` usam exclusão lógica; vínculos e documentos futuros devem seguir a mesma regra.

## Operação

- Uma exclusão precisa de **ID do responsável** e **motivo registrado**. O serviço de domínio chama `deleteWithReason($motivo, $idDoServidor)` após verificar a autorização da escola. `delete()` diretamente e `forceDelete()` no modelo ou construtor de consultas lançam uma exceção, mesmo se o registro já tiver sido excluído e restaurado antes. Alterações pendentes devem ser salvas separadamente.
- Não se pode arquivar escola ou ano letivo com turma ou matrícula ativa, nem aluno com matrícula ativa. Arquive primeiro os registros filhos. A exclusão em cascata é proibida.
- Registros excluídos permanecem com `deleted_at`, `deleted_by` e `deletion_reason`; as consultas Eloquent normais os ocultam. Pessoas autorizadas podem consultar `withTrashed()` para conferência.
- A restauração usa `restoreWithReason($motivo, $idDoServidor)` em um registro recuperado por `withTrashed()` e grava o evento `restored`, com autoria e justificativa no snapshot da auditoria. `restore()` diretamente é bloqueado. O motivo e a autoria da exclusão original continuam no registro. Turmas e matrículas só podem ser restauradas quando seus pais estiverem ativos. Relações acadêmicas preservam o ano e a escola históricos com `withTrashed()` para consulta.
- Escritas normais no modelo são auditadas. Atualizações, inserções, restaurações e exclusões em lote por Eloquent, assim como `saveQuietly()`, `updateQuietly()`, `deleteQuietly()`, `restoreQuietly()` e `Model::withoutEvents()`, são bloqueadas para registros escolares. A gravação interna dos metadados de exclusão é controlada pela própria rotina transacional, sem permitir gravações silenciosas externas.
- **Mudança de situação escolar**, como transferência, conclusão ou abandono, não é uma exclusão: atualizar o campo `status` e auditar a alteração.
- A interface atual é de leitura e não expõe rota para apagar, recuperar ou excluir fisicamente; novas rotas de edição devem passar por Policy/Gate por município e escola antes de executar a operação.

## Trilha e isolamento

`audit_logs` pertence exclusivamente ao banco do município. `platform_audit_logs` pertence ao banco central e registra autenticação e provisionamento. A classe `App\Audit\AuditTrail` recebe o destino explícito, guarda UUID, instante com microssegundos, ator, papéis ativos, ação, entidade, ID, anterior, posterior, IP, agente e request ID. Uma lista fechada de campos permitidos impede que senha, token ou laudo apareçam nos snapshots, sem mascarar nomes legítimos por palavras contidas nos valores. Os modelos e construtores de consulta impedem alterações em lote; gatilhos MariaDB `BEFORE UPDATE` e `BEFORE DELETE` também bloqueiam SQL direto nas duas tabelas de auditoria.

Criação, atualização, exclusão lógica e restauração são feitas na mesma transação MariaDB de seus registros de auditoria. Uma falha de auditoria interrompe e desfaz a operação de domínio. Uma instância de registro já carregada fica vinculada ao município em que foi criada/consultada e não pode ser reutilizada para gravar em outra base.

**Produção:** o usuário SQL da aplicação deve ter apenas `SELECT` e `INSERT` em `audit_logs` e `platform_audit_logs`, **sem `UPDATE`, `DELETE`, `TRUNCATE`, `DROP` ou privilégios de alteração de gatilhos** nessas tabelas. As novas migrações exigem uma conta de manutenção com privilégio `TRIGGER`, distinta da aplicação. Os gatilhos não protegem contra `TRUNCATE` feito por administrador com privilégios elevados; limite contas privilegiadas e faça backups imutáveis. Mantenha cópias protegidas, monitoramento de integridade e política de retenção aprovada pelo controlador de dados. Proibição no modelo não substitui essa configuração SQL.

As tabelas municipais e centrais devem preservar a divisão física dos bancos. Não copiar registros de auditoria entre municípios, não enviar laudos ou senhas e não usar dados reais na demonstração de Monte Sereno v2.

## Verificação e restauração

Os testes cobrem criação/atualização com antes/depois, tentativa de alteração ou exclusão da trilha, sequência A/B/A, login bem-sucedido e falho, provisionamento, exclusão lógica com autoria e motivo, restauração e eventos de ambos os movimentos. O backup precisa cobrir dados e auditoria com retenção apropriada e recuperação testada em ambiente isolado.
