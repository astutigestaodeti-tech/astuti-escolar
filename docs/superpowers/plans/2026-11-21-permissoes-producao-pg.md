# Plano de execucao: Bloco 3

1. **Contrato e seguranca.** Criar comando `astuti:permissoes-pg` apenas para producao ou CI descartavel. Validar dois bancos locais distintos, PostgreSQL 17 e administrador real; falhar fechado para destinos ou papeis inesperados.
2. **Provisionamento.** Criar papeis login sem senha inicial, definir titularidade dos objetos da aplicacao, retirar privilegios herdados do PUBLIC, conciliar grants operacionais e de auditoria; configurar ALTER DEFAULT PRIVILEGES sem UPDATE em tabelas futuras.
3. **Teste de integracao.** Em PostgreSQL 17 ICU pt-BR descartar os bancos no fim do job, migrar como papel dono, conectar como web via senha real, testar negacoes SQLSTATE 42501, reexecucao, login e auditoria platform + tenant.
4. **Operacao e governanca.** Documentar comandos e a sequencia de deploy com senhas privadas, sessao/cache sem DELETE nas tabelas; atualizar AGENTS.md com chefia/revisao do Claude; registrar HISTORICO.md uma vez.
5. **Entrega.** Um commit logico de codigo + teste + docs + Pint, PR proprio sobre o PR #5; conferir CI, corrigir falhas, parar e devolver numero de PR/status ao Jefferson. Sem merge nem tocar PRs #6/#7.
