# Dados do aluno para o Censo Escolar (Educacenso)

Incluídos em 22/11/2026: nome social, sexo, cor/raça, nacionalidade, naturalidade (cidade e UF) e CPF. Todos são opcionais, porque a escola registra o que a família informa.

- Valores fechados, no padrão do Educacenso, com CHECK no banco: sexo (`female`, `male`); cor/raça (`branca`, `preta`, `parda`, `amarela`, `indigena`, `nao_declarada`); nacionalidade (`brasileira`, `naturalizada`, `estrangeira`).
- CPF: aceito com ou sem pontuação, gravado como `000.000.000-00`, com conferência dos dígitos verificadores. Um índice único parcial impede o mesmo CPF em dois cadastros ativos. A mensagem de CPF repetido não revela de quem é o outro cadastro.
- LGPD: cor/raça é dado sensível. CPF e cor/raça entram na auditoria como `[REDACTED]`: o histórico mostra que o dado foi informado ou mudou, sem guardar o valor. Valores nulos continuam nulos.
- Importação desses campos das planilhas: próximo PR. Os CPFs do kit fictício não têm dígitos válidos e serão marcados como pendentes, sem travar a importação.
