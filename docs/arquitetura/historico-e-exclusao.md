# Histórico escolar, exclusão lógica e auditoria

O Astuti Gestão Escolar **nunca apaga fisicamente registros escolares por uma ação da interface**. `School`, `SchoolYear`, `SchoolClass`, `Student` e `StudentEnrollment` usam exclusão lógica; vínculos e documentos futuros devem seguir a mesma regra.

## Operação

- Uma exclusão precisa de **ID do responsável** e **motivo registrado**. O serviço de domínio chama `deleteWithReason($motivo, $idDoServidor)` após verificar a autorização da escola. `delete()` diretamente e `forceDelete()` no modelo ou construtor de consultas lançam uma exceção, mesmo se o registro já tiver sido excluído e restaurado antes. Alterações pendentes devem ser salvas separadamente.
- Registros excluídos permanecem com `deleted_at`, `deleted_by` e `deletion_reason`; as consultas Eloquent normais os ocultam. Pessoas autorizadas podem consultar `withTrashed()` para conferência.
- A restauração usa `restore()` em um registro recuperado por `withTrashed()` e grava o evento `restored`. O motivo e a autoria da exclusão original continuam no registro para histórico, mas não autorizam uma segunda exclusão. O usuário autenticado que restaurou consta na auditoria. Relações acadêmicas preservam o ano e a escola históricos com `withTrashed()` quando necessário.
- **Mudança de situação escolar**, como transferência, conclusão ou abandono, não é uma exclusão: atualizar o campo `status` e auditar a alteração.
- A interface atual é de leitura e não expõe rota para apagar, recuperar ou excluir fisicamente; novas rotas de edição devem passar por Policy/Gate por município e escola antes de executar a operação.

## Trilha e isolamento

`audit_logs` pertence exclusivamente ao banco do município. `platform_audit_logs` pertence ao banco central e registra autenticação e provisionamento. A classe `App\Audit\AuditTrail` recebe o destino explícito, guarda UUID, instante com microssegundos, ator, papéis ativos, ação, entidade, ID, anterior, posterior, IP, agente e request ID. Uma lista fechada de campos permitidos impede que senha, token ou laudo apareçam nos snapshots. Ambos os modelos e seus construtores de consultas impedem atualização e exclusão na camada Eloquent.

Criação, atualização, exclusão lógica e restauração são feitas na mesma transação MariaDB de seus registros de auditoria. Uma falha de auditoria interrompe e desfaz a operação de domínio. Uma instância de registro já carregada fica vinculada ao município em que foi criada/consultada e não pode ser reutilizada para gravar em outra base.

**Produção:** o usuário SQL da aplicação deve ter apenas `SELECT` e `INSERT` em `audit_logs` e `platform_audit_logs`, **sem `UPDATE`, `DELETE`, `TRUNCATE` ou `DROP`** nessas tabelas. As migrações exigem usuário de manutenção distinto; não execute migrações com as credenciais restritas de produção. Mantenha cópias protegidas, monitoramento de integridade e política de retenção aprovada pelo controlador de dados. Proibição no modelo não substitui essa configuração SQL.

As tabelas municipais e centrais devem preservar a divisão física dos bancos. Não copiar registros de auditoria entre municípios, não enviar laudos ou senhas e não usar dados reais na demonstração de Monte Sereno v2.

## Verificação e restauração

Os testes cobrem criação/atualização com antes/depois, tentativa de alteração ou exclusão da trilha, sequência A/B/A, login bem-sucedido e falho, provisionamento, exclusão lógica com autoria e motivo, restauração e eventos de ambos os movimentos. O backup precisa cobrir dados e auditoria com retenção apropriada e recuperação testada em ambiente isolado.
