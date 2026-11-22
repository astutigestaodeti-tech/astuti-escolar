# Identidade visual do Astuti Gestão Escolar

## Conceito

A interface usa a linguagem do caderno e do livro de registro da secretaria escolar:

- **Pauta azul** nas linhas das tabelas e listas, como no livro de matrícula.
- **Margem vermelha** à esquerda do título de cada página, como no caderno escolar. É o único elemento decorativo recorrente.
- **Títulos em serifa** (Source Serif 4), com tom de documento oficial.
- **Texto em Atkinson Hyperlegible**, fonte criada pelo Braille Institute para máxima legibilidade, inclusive para pessoas com baixa visão. É coerente com o foco do produto em educação inclusiva.

O município aparece em primeiro plano (brasão e nome no topo do menu). A marca Astuti fica discreta, no rodapé do menu e na tela de acesso.

## Arquivos

| Arquivo | Função |
|---|---|
| `public/css/astuti.css` | Folha de estilos única: tokens, estrutura, componentes e regras responsivas. Substitui `public/css/demo.css` e os estilos embutidos das views. |
| `public/fonts/` | Fontes hospedadas no próprio sistema (sem CDN externa), com as licenças OFL. |
| `resources/views/components/icon.blade.php` | Ícones em SVG próprios: `<x-icon name="escola" />`. Substituem os símbolos Unicode, que mudavam de aparência conforme o computador. |
| `resources/views/components/astuti-marca.blade.php` | Marca Astuti: `<x-astuti-marca />`. |
| `resources/views/components/iniciais.blade.php` | Sigla da escola a partir do nome: `<x-iniciais :texto="$school->name" />`. |

## Cores

| Token | Valor | Uso |
|---|---|---|
| `--tinta` | `#172a43` | Texto principal |
| `--tinta-2` / `--tinta-3` | `#3f4f66` / `#5a687c` | Texto secundário e de apoio |
| `--caneta` | `#1f4fa8` | Ações, links e botões |
| `--noite` | `#13243b` | Menu lateral e tela de acesso |
| `--pauta` | `#d3deec` | Linhas de tabela e divisões |
| `--margem` | `#c9574b` | Margem do título (somente decorativa) |
| `--verde` | `#2c6a3c` | Situação regular |
| `--ouro` / `--ouro-vivo` | `#9a6f17` / `#d7a743` | Avisos, pendências e foco do teclado sobre fundos escuros |

A paleta foi pensada para atingir os contrastes da WCAG 2.1 AA, e os componentes foram ajustados para legibilidade. A conferência automatizada de cores e uma auditoria visual completa ainda serão necessárias antes do piloto real. O menor tamanho de texto previsto na folha de estilos é 12,5 px.

## Brasão por município

O layout municipal procura `public/images/brasao-{slug}.webp`. Se o arquivo não existir, mostra um monograma com as iniciais do município. Assim, cada prefeitura cliente pode ter o próprio brasão sem alteração de código.

## Acessibilidade

- Link "Pular para o conteúdo" em todas as telas com menu.
- Foco do teclado visível (contorno azul nas áreas claras e dourado nos fundos escuros).
- `aria-current` no item de menu e na trilha de navegação.
- Movimento reduzido respeitado (`prefers-reduced-motion`).
- Responsivo a partir de 320 px; no celular o menu vira uma faixa horizontal.
- Estilos de impressão sem menu e sem barra superior.
