# Fase 2 / 12 - Acervo histórico e digitalização

Status: planejamento em rascunho; nenhuma implementação ou auditoria concluída por este documento. Prioridade aprovada em 26/11/2026. Refs #209, #198, #204. Dependência: F2-01, F2-03 e F2-11.

## Escopo da auditoria e implementação

Inventariar documentos, arquivos e OCR existentes; mapear classificação, metadados, indexação, vínculo com aluno/ano, trilha de acesso, integridade, versões, retenção e descarte autorizado. Validar requisitos arquivísticos e municipais antes de definir prazos, formatos ou digitalização substitutiva.

## Critérios de aceite

Testar upload com formato/tamanho controlado, acesso e download autorizados, hash de integridade, reindexação, documentos duplicados, falha de OCR, recuperação e auditoria. OCR não substitui conferência humana; não excluir originais nem dados sem autorização específica. Avaliar backup e restauração de arquivos.

## Condições comuns

Inventariar main e PRs antes de programar; registrar caminhos e evidências existentes, evitar duplicidade. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA em ações críticas, acessibilidade e padrão A4. Usar apenas dados fictícios de Monte Sereno. Testes de regressão, CI completo, revisão do SHA e homologação antes de promover o PR de rascunho. Se houver migration, dependência ou mudança de infraestrutura, exigir procedimento seguro específico; não alterar bancos reais. Não habilitar auto-merge enquanto for rascunho. Sincronizar com main apenas quando começar a unidade, respeitar dependências funcionais e dividir implementação em PRs menores se necessário.
