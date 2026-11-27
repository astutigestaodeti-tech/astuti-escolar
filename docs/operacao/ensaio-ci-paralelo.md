# Ensaio de CI paralelo (issue #196)

A rotina obrigatória não foi alterada. Este workflow experimental roda somente em PRs que modificam seus arquivos ou por acionamento manual após integração. Não é um check obrigatório e não substitui a suíte integral.

Dois runners Ubuntu independentes, cada um com seu próprio serviço PostgreSQL 17 e três bases descartáveis locais. Grupo OCR inclui OcrDocumentImportTest e OcrStudentImportScreenTest; grupo geral é o complemento, abrangendo novos testes automaticamente. Cada grupo roda duas vezes consecutivas em seus próprios bancos. Os testes usam Tesseract e Poppler reais. Não modificar parâmetros de produção, fixtures ou regras de segurança. Pint, shell, build e testes UI permanecem na rotina original e são repetidos no ensaio para comparação.

O script confere inventário integral, ausência de sobreposição e igualdade entre união dos grupos e suíte completa; verifica quantidade executada no relatório JUnit, falhas e contagens de skips e asserções por rodada. Não publica relatórios brutos. A comparação entre a suíte integral e a soma dos grupos deve ser revisada pelos mantenedores, inclusive skips, asserções, dependências entre classes e resíduos entre grupos.

Critérios de aceite para proposta futura: comparar no mesmo commit e ambiente equivalente, em pelo menos três execuções; medir caminho crítico incluindo inicialização, fila e tempo total de runners; contagens e resultados equivalentes; investigar diferenças, sem aceitar falhas mascaradas. Preservar teste de permissões PostgreSQL obrigatório. Não assumir que duas rodadas por grupo equivalem automaticamente às duas rodadas integrais: interações entre grupos exigem avaliação específica. Se aprovado, planejar PR separado com check agregador obrigatório de nome existente, que falhe se qualquer grupo falhar, for cancelado ou não rodar. Manter a suíte integral como controle até validar alternativa. Nenhuma alteração de proteção de branches.

Baseline run 45528855374: 888 aprovados, 26 skips, 8923 asserções por rodada, 252,74 e 251,88 s; job completo 600 s. Estimativa inicial de 6 a 7 minutos, não garantia; execução paralela pode consumir mais minutos totais de runners.
