# Fase 3 / 01 - Educacenso - leiaute e migração

Status: planejamento em rascunho; nenhuma implementação concluída. Execução após a Fase 2 (#256 a #267), conforme decisão do proprietário. Refs #209, #219, #221. Dependência: Fase 2, em especial cadastros, matrículas e ano letivo.

## Escopo

Inventariar conferência do Censo, importadores, exportadores e validações existentes. Conferir documentação oficial do Inep para o ano e a etapa aplicáveis, inclusive a versão efetivamente publicada do leiaute (V6 se confirmada). Preservar questões de formato ainda sem resposta oficial e não alterar regras por suposição.

## Critérios de aceite

Testar arquivos fictícios com casos válidos e inválidos, campos opcionais, caracteres, referências, contagens, rejeições e reprocessamento idempotente. Conferir ano/data de referência, mapeamento de campos e versão do leiaute; homologar com evidência oficial antes de declarar compatibilidade.

## Condições comuns

Inventariar main, módulos, testes, documentação e issues existentes antes de implementar. Validar legislação, manuais e leiautes oficiais vigentes na ocasião; registrar fonte, data e versão, sem presumir regra, obrigação ou integração. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA, acessibilidade, margens A4 e dados fictícios. Não enviar dados reais. CI completo, revisão do SHA e homologação em Monte Sereno antes da entrega; mudanças de banco ou infraestrutura exigem procedimento seguro. Manter draft sem auto-merge e sincronizar somente ao iniciar a unidade após Fase 2. Dividir em PRs menores se necessário.
