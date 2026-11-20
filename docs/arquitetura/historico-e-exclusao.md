# Histórico escolar, exclusão lógica e auditoria

O Astuti Gestão Escolar **nunca apaga fisicamente registros escolares por uma ação da interface**. `School`, `SchoolYear`, `SchoolClass`, `Student` e `StudentEnrollment` usam exclusão lógica; vínculos e documentos futuros devem seguir a mesma regra.

## Operação

- Uma exclusão precisa de **ID do responsável** e **motivo registrado**. O serviço de domínio chama `deleteWithReason($motivo, $idDoServidor)` após verificar a autorização da escola. `delete()` sem esses metadados e `forceDelete()` no modelo lançam uma exceção.
- Registros excluídos permanecem com `deleted_at`, `deleted_by` e `deletion_reason`; as consultas Eloquent normais os ocultam. Pessoas autorizadas podem consultar `withTrashed()` para conferência.
- A restauração usa `restore()` em um registro recuperado por `withTrashed()` e grava o evento `restored`. O motivo e a autoria da exclusão original continuam no registro para histórico. O usuário autenticado que restaurou consta na auditoria.
- **Mudança de situação escolar**, como transferência, conclusão ou abandono, não é uma exclusão: atualizar o campo `status` e auditar a alteração.
- A interface atual é de leitura e não expõe rota para apagar, recuperar ou excluir fisicamente; novas rotas de edição devem passar por Policy/Gate por município e escola antes de executar a operação.

## Trilha e isolamento

`audit_logs` pertence exclusivamente ao banco do município. `platform_audit_logs` pertence ao banco central e registra autenticação e provisionamento. A classe `App\Audit\AuditTrail` recebe o destino explícito, guarda UUID, instante, ator, papel, ação, entidade, ID, anterior, posterior, IP, agente e request ID, além de mascarar campos como senha, token e laudo. Ambos os modelos impedem atualização e exclusão na camada Eloquent.

**Produção:** o usuário SQL da aplicação deve ter apenas `SELECT` e `INSERT` em `audit_logs` e `platform_audit_logs`, **sem `UPDATE`, `DELETE`, `TRUNCATE` ou `DROP`** nessas tabelas. As migrações exigem usuário de manutenção distinto; não execute migrações com as credenciais restritas de produção. Mantenha cópias protegidas, monitoramento de integridade e política de retenção aprovada pelo controlador de dados. Proibição no modelo não substitui essa configuração SQL.

As tabelas municipais e centrais devem preservar a divisão física dos bancos. Não copiar registros de auditoria entre municípios, não enviar laudos ou senhas e não usar dados reais na demonstração de Monte Sereno v2.

## Verificação e restauração

Os testes cobrem criação/atualização com antes/depois, tentativa de alteração ou exclusão da trilha, sequência A/B/A, login bem-sucedido e falho, provisionamento, exclusão lógica com autoria e motivo, restauração e eventos de ambos os movimentos. O backup precisa cobrir dados e auditoria com retenção apropriada e recuperação testada em ambiente isolado.
