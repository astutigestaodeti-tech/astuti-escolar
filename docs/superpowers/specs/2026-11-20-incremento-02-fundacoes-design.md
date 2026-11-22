# Incremento 02: fundações antes do cadastro de alunos

## Escopo

Aplicar a issue #2 à branch criada de `feature/incremento-01-saude`, sem alterar a interface visual além de traduções e cabeçalhos. O armazenamento continua separado entre `platform` e `tenant`. Nenhum dado real será incluído.

## Decisões

1. Auditoria municipal (`audit_logs`) e central (`platform_audit_logs`) usam UUID, instante, ator, papel, ação, entidade, identificador, valores anteriores e posteriores em JSON, IP, agente e identificador da requisição. O serviço `App\Audit\AuditTrail` escolhe explicitamente a conexão. Cada modelo municipal de domínio registra automaticamente criação, atualização, exclusão lógica e restauração; eventos de login, logout e provisionamento ficam na plataforma. A auditoria armazena exclusivamente campos permitidos para o histórico escolar: jamais senha, token, laudo ou conteúdo médico. Modelos de auditoria são imutáveis após a criação. Os registros usam a mesma transação/conexão dos dados alterados.
2. `School`, `SchoolYear`, `SchoolClass`, `Student` e `StudentEnrollment` recebem `deleted_at`, `deleted_by` e `deletion_reason` por migração adicional. Exclusão lógica exige motivo e ator responsável explícitos; a API `deleteWithReason` registra ambos. A exclusão física (`forceDelete`) de modelos municipais fica bloqueada. `restore()` mantém o motivo anterior para rastreabilidade e gera evento de auditoria. Os escopos padrão não retornam registros excluídos.
3. O idioma principal é português brasileiro, com tradução dos campos de formulário e mensagens padrão de autenticação, validação, paginação e senha. A locale de teste pode ser fixada independentemente do processo de CI.
4. Um middleware de cabeçalhos atende respostas web de sucesso e erro. A política CSP inicial conserva `style-src 'unsafe-inline'` para não quebrar estilos embutidos legados, mas proíbe scripts externos, `object-src` e enquadramento. HSTS somente em produção *e* HTTPS. Sessões de produção devem ativar `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`; desenvolvimento local HTTP não é alterado.

## Qualidade e limites

- Transformar cada critério verificável da issue em teste PHPUnit; manter os testes existentes (mais de 54 no ponto de partida) e Pint.
- Verificar isolamento municipal A/B/A e a ausência de segredos tanto no JSON de auditoria quanto em mensagens de erro.
- Evitar alteração no CSS e nos componentes do padrão `docs/design/identidade-visual.md`.
- Provisionamento não depende de serviços externos e continua sem expor dados privados.
- Revisão independente de Claude será solicitada pelo proprietário após o PR em rascunho.
