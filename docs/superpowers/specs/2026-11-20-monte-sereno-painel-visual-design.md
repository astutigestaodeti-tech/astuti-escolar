# Identidade visual municipal da demonstração Laravel

## Objetivo

Mostrar a Jefferson no navegador do notebook um painel **servido pelo próprio Laravel**, com acabamento profissional e o brasão fictício de Monte Sereno que ele forneceu, preservando o estado do projeto e sua separação de dados.

## Abordagem adotada

Conservar o Blade renderizado no servidor e as sete telas de demonstração em `/demonstracao`, atualizando apenas o tema CSS e o cabeçalho institucional. Reutilizar o desenho já aprovado como prévia mobile. O brasão será uma derivação WebP otimizada do arquivo PNG fornecido, sem reinterpretar o desenho. O arquivo é servido pelo próprio aplicativo.

Alternativas consideradas: (a) incorporar um HTML isolado à aplicação, que duplicaria rotas e lógica; (b) reescrever o painel com uma biblioteca JavaScript, criando mais dependências antes de precisar delas. A escolha por Blade e CSS mantém a navegação e permite a migração gradual para dados reais autenticados.

## Escopo

- Mostrar brasão na identidade da prefeitura, no seletor municipal e no painel de boas-vindas.
- Usar fundo claro, barra lateral azul profunda, cartões claros, detalhes verde e dourado.
- Manter navegação e busca de alunos fictícios como já funcionam, sem novas operações de gravação.
- Preservar sinalização explícita de demonstração e dados sintéticos.
- Funcionamento responsivo em telefone de 320 px ou mais, com respeito a redução de movimento e foco visível.
- Não alterar autenticação, autorização, isolamento por município ou qualquer banco.
- Não converter a área pública de demonstração em sistema escolar de produção.

## Aceite

O HTML Blade usa `asset('images/brasao-monte-sereno.webp')` no brasão principal e cabeçalho, indica município fictício, e mantém links de `route('demo')`; o CSS é responsivo. Testes automatizados verificam estes contratos e o WebP é válido e transparente. A suíte Laravel/CI deve continuar verde antes de orientar a sincronização ao notebook.
