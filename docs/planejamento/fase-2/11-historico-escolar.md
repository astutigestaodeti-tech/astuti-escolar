# Fase 2 / 11 - Histórico escolar

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #198, #204, #219. Dependência: F2-02 a F2-06 e F2-09.

## Escopo da auditoria e implementação

Inventariar histórico existente, origem dos registros anuais, resultados, frequência, carga horária, transferências, validação, emissão e retificação. Conferir preservação das informações originais, documentos A4, autenticação/verificação e integração com acervo.

## Critérios de aceite

Testar aluno com múltiplos anos/escolas, transferência, dados incompletos, retificação auditada, emissão e reemissão, fidelidade à fonte e controle de acesso. Verificação pública deve evitar dados pessoais; preservar documentos já emitidos e conferir margens institucionais.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
