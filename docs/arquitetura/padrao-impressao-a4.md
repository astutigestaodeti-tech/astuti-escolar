# Padrão institucional de impressão A4

Decisão de produto de 24/11/2026 (#204), atualizada em 25/11/2026 (#238). Aplicável à ficha cadastral e a todos os documentos e relatórios impressos ou salvos em PDF pelo Astuti Gestão Escolar, inclusive novas funcionalidades.

- Papel A4: 210 × 297 mm em retrato; 297 × 210 mm em paisagem.
- Margens: superior 20 mm, direita 20 mm, inferior 20 mm, esquerda 30 mm. A margem esquerda maior reserva espaço para perfuração e arquivo em pasta AZ.
- CSS: `margin: 20mm 20mm 20mm 30mm` (ordem superior, direita, inferior, esquerda). Retrato como padrão, paisagem onde a largura de tabelas exige.
- Área útil em retrato 160 × 257 mm; em paisagem 247 × 170 mm.
- O timbre institucional, textos, tabelas, fotos autorizadas e demais conteúdo devem respeitar a área útil. Preservar paginação própria quando prevista; não sobrepor conteúdo nem reduzir legibilidade para forçar página única.
- No navegador, imprimir em A4, escala 100%, respeitar margens CSS e desmarcar cabeçalhos e rodapés automáticos do navegador (não os do documento). O usuário pode alterar as opções no diálogo; o sistema não controla todas as impressoras.
- A regra geral fica em public/css/astuti.css e os @page específicos mantêm orientação e margens padronizadas. O teste tests/Visual/print-paper-standard.test.mjs verifica as regras de página. Se houver novos geradores de PDF, devem obedecer às mesmas dimensões e ser testados.
- Homologação: conferir PDFs em Chromium/Edge e, quando possível, impressão física, retrato e paisagem, documentos curtos e longos, colunas, quebras, timbre e paginação. Conferir margens reais, não apenas CSS. Não forçar uma página única; permitir paginação sem cortar dados. Não publicar fotos de alunos ou documentos com dados pessoais.

A alteração inicial uniformiza CSS; a validação visual de cada tipo de documento e de páginas múltiplas permanece necessária. O teste estático não substitui essa validação. Não altera permissões, conteúdo nem auditoria.
