# Revisão visual e robustez do incremento 01

## Intenção

Adotar a nova identidade visual entregue por Jefferson no arquivo `Astuti_Gestao_Escolar_visual_2026-11-20_Claude.zip`, mantendo o isolamento municipal e as rotas já aprovadas, e corrigir as falhas demonstradas na revisão externa antes do próximo incremento.

## Objetivos

1. Substituir a apresentação por folha de estilos única, fontes locais licenciadas e componentes Blade, preservando o brasão fictício de Monte Sereno.
2. Melhorar a legibilidade e a navegação por teclado, incluindo um indicador de foco visível em fundos claros e escuros.
3. Declarar a versão mínima real de PHP para as dependências atuais; garantir correspondência entre `composer.json` e `composer.lock`.
4. Tornar a execução repetida dos testes de integração segura, sem resíduos de sessões ou tentativas de provisão.
5. Dar um destino útil ao login: página com redes ativas autorizadas, com consulta no controller e negação implícita de redes inativas/sem escopo.
6. Evitar afirmações de funcionalidades ainda inexistentes e melhorar o português dos textos exibidos.

## Não objetivos

- Não criar importação funcional, matrículas reais, AEE, PEI ou emissão de histórico.
- Não integrar a dados oficiais, mudar dados de Monte Sereno sem o kit v2 verificável, habilitar usuários reais, ligar acessos públicos ou implantar em produção.
- Não alterar regras de isolamento, política de acesso por escola, migrations ou a configuração privada do notebook.
- Não incorporar à branch `main` enquanto 2FA e auditoria de escrita estiverem pendentes.

## Decisão de integração

Usar a base oficial `feature/incremento-01-saude` (commit `9289bd1`) e incorporar seletivamente as views, CSS, fontes e componentes do ZIP de Claude. Não substituir o repositório inteiro pelo ZIP nem aceitar automaticamente qualquer alteração em arquivos de aplicação. Esta alternativa mantém o histórico rastreável; sobrescrever a instalação do notebook eliminaria a possibilidade de revisão e retorno seguro.

## Comportamento esperado

- Um visitante vê a página de entrada e o link de demonstração apenas no ambiente local.
- Um usuário autenticado vê somente municípios ativos com vínculo ativo e permissão efetiva; caso contrário, lê uma mensagem de ausência de redes.
- As páginas mantêm texto escapado e ações sujeitas às rotas e policies já existentes.
- CSS e fontes funcionam localmente sem depender de CDN, e a versão do CSS muda com o arquivo no deploy.
- Testes de integração funcionam duas vezes no mesmo banco descartável.
- A demonstração permanece só em `local` e `testing`, com dados explicitamente fictícios.

## Verificação

- Testes PHP, incluindo novo teste de acesso às redes e duas passagens da suíte sobre os mesmos bancos efêmeros no CI.
- Teste estático executável de contraste da cor do foco, conferência dos assets, build Vite e Pint.
- Verificação de que somente arquivos autorizados foram alterados; proibição de segredos, `.env`, bancos e arquivos de usuário no Git.

## Pendências conscientemente posteriores

O kit Monte Sereno v2 (3 escolas/115 alunos/ano 2027) não acompanha o ZIP entregue neste turno; validar sua origem antes de substituir o gabarito fictício atual. 2FA, trilha de auditoria, cabeçalhos de segurança e estratégias de importação permanecem bloqueadores para piloto real.
