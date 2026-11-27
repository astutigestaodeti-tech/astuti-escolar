# Fase 3 / 09 - Alimentação escolar

Status: planejamento em rascunho; nenhuma implementação concluída. Execução após a Fase 2 (#256 a #267), conforme decisão do proprietário. Refs #209; inventariar módulo de alimentação e correção de impressão #242.

## Escopo

Revisar cardápios, planejamento, registros de execução, restrições alimentares, relatórios e fluxos com nutricionista. Verificar normas vigentes do PNAE e requisitos municipais antes de alterar cálculos ou documentos; separar dados sensíveis de relatórios gerais.

## Critérios de aceite

Testar cardápios por escola/período, substituições, dietas especiais com acesso restrito, consistência das quantidades e impressão A4. Não automatizar decisões clínicas ou nutricionais; preservar responsabilidade profissional.

## Condições comuns

Inventariar main, módulos, testes, documentação e issues existentes antes de implementar. Validar legislação, manuais e leiautes oficiais vigentes na ocasião; registrar fonte, data e versão, sem presumir regra, obrigação ou integração. Preservar LGPD, isolamento por escola/ano/perfil, auditoria, 2FA, acessibilidade, margens A4 e dados fictícios. Não enviar dados reais. CI completo, revisão do SHA e homologação em Monte Sereno antes da entrega; mudanças de banco ou infraestrutura exigem procedimento seguro. Manter draft sem auto-merge e sincronizar somente ao iniciar a unidade após Fase 2. Dividir em PRs menores se necessário.
