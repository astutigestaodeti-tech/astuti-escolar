# Monte Sereno: ambiente de teste local em um comando

## Intenção

Permitir que Jefferson experimente as **telas reais autenticadas** no Debian/WSL2, sem digitar dados nem inserir registros de crianças reais. O comando `php artisan astuti:demo-preparar` usará as bases locais previamente criadas `astuti_platform_dev` e `astuti_monte_sereno_dev` e entregará credenciais fictícias de acesso.

## Abordagens consideradas

1. **Instalador exclusivo para desenvolvimento (escolhida):** ativar a conexão fictícia somente em `APP_ENV=local`, aproveitar o isolamento municipal existente, provisionar o esquema e cadastrar exemplos idempotentes com uma única execução. Torna a consulta real exercitável, sem expor um instalador HTTP.
2. Seeder genérico sem guardas: mais curto, mas pode gravar no banco errado ou em produção, incompatível com os dados escolares sensíveis.
3. Apenas outra tela estática: simples, porém não permite testar login, autorização nem a consulta verdadeira que acabamos de desenvolver.

O usuário já autorizou a escolha técnica autônoma e pediu explicitamente para prosseguir sem novas perguntas. A execução nativa é a opção deste incremento.

## Contrato e salvaguardas

- O instalador é **comando Artisan**, não rota HTTP. Em produção, retorna erro **antes de qualquer escrita**.
- Ambiente `local`: exigir banco central exatamente `astuti_platform_dev`, banco municipal exatamente `astuti_monte_sereno_dev`, servidor de banco apenas loopback/socket local, nenhum `url` remoto. **Não criar bancos** automaticamente. Usar as credenciais privadas já configuradas para a base central, sem armazenar senhas no repositório.
- Ambiente `testing`: somente se `TENANCY_INTEGRATION=1` e bancos distintos terminados em `_test`. Permitir conexão municipal injetada pelas fixtures de integração. Nenhuma exceção para produção.
- `config/tenancy.php` oferece a conexão `monte_sereno` automaticamente somente para uma configuração local explicitamente reconhecida. Fora dela a configuração segue vazia, como hoje.
- Provisionar migrations centrais e municipais pelo fluxo existente, deixando os registros separados e rastreáveis. Não apagar nem substituir registros preexistentes.
- Criar município `monte-sereno` com brasão já distribuído, três escolas fictícias, ano letivo **2027**, três turmas e **115 alunos sintéticos** distribuídos entre as escolas. Esses dados **não são apresentados como o kit v2 oficial**, que ainda não está disponível no repositório.
- Criar `demo.monte-sereno@astuti.invalid` como usuário de desenvolvimento, com senha aleatória forte mostrada somente na primeira execução (ou com `--renovar-senha` explícito). Não incluir senha padrão no código, no Git ou nos logs do CI.
- Repetir o comando não duplica escolas, alunos, matrículas, vínculos de usuário ou turmas; não apaga dados adicionados depois.
- O usuário entra em `/login`, escolhe Monte Sereno em `/` e navega para escolas, turmas e alunos. A demonstração pública `/demonstracao` permanece separada.
- Operações de escrita por HTTP continuam indisponíveis; não habilitar importação de documentos reais, CPF, laudos, 2FA fictício ou envio a órgãos públicos.

## Critérios de aceite

1. Rodar o comando no ambiente de teste cria os dados fictícios em uma base municipal separada, sem contaminar o banco central com estudantes.
2. Rodar duas vezes preserva exatamente três escolas, 115 estudantes, 115 vínculos e três turmas, com usuário e senha preservados.
3. Login com a senha gerada abre o município fictício e uma tela real mostra seus alunos (com paginação).
4. Executar em `production`, com banco fora da lista permitida, ou com conexão remota é recusado antes de gravar.
5. A CI confirma testes PHP duas vezes, Pint, build Vite e testes visuais, sem tocar em bancos de produção.
