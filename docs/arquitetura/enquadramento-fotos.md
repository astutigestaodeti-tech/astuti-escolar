# Enquadramento das fotos de identificação

Decisão de produto #206, 24/11/2026. Preservar o enquadramento integral da fotografia recebida após correção de orientação EXIF. O serviço redimensiona proporcionalmente para encaixar em JPEG vertical 3:4 de 360 × 480 pixels, com preenchimento branco quando necessário. Não recortar, esticar ou usar reconhecimento facial. Manter remoção de metadados, armazenamento privado, autorização e auditoria.

Nas fichas, consultas e documentos impressos usar encaixe integral (object-fit: contain); não aplicar máscara circular a fotografias de identificação. As molduras da tela mantêm proporção 3:4 (33×44, 54×72 e 90×120 pixels); não equivalem a centímetros físicos no monitor. A ficha impressa usa moldura de 30 × 40 mm, compatível com o arquivo armazenado e com o padrão A4. Não adicionar recortes para preencher uma moldura.

Fotos antigas processadas pelo recorte central não recuperam pixels descartados por alteração CSS. Para recuperá-las, usar somente arquivos originais autorizados, confirmar correspondência de cada pessoa, fazer backup privado e validar a substituição. Não reimportar automaticamente nem publicar fotos, capturas ou PDFs de alunos. Não conservar arquivos originais adicionais no sistema sem necessidade.

Os testes usam padrões geométricos de cores, não fotografias de pessoas. A homologação visual na VPS, inclusive impressão, continua necessária. Nenhum arquivo existente foi substituído neste PR.

## Molduras e zoom do navegador (#211)

A área útil da foto mantém a proporção 3:4, sem borda interna: utilizar contorno externo (outline), que não participa do cálculo de largura e altura. Preservar object-fit: contain, sem recortar ou deformar. O mesmo vale para a moldura de impressão de 30 × 40 mm; manter margens A4. Conferir visualmente em zoom de 80%, 90%, 100%, 110% e 125%, e PDF a 100% de escala. O teste estático verifica o contrato CSS, não substitui homologação visual no navegador. Nenhuma fotografia existente deve ser reprocessada para corrigir uma faixa causada pela moldura.
