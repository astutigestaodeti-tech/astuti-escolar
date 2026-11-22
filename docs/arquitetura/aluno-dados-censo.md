# Dados do aluno para o Censo Escolar (Educacenso)

Incluídos em 22/11/2026: nome social, sexo, cor/raça, nacionalidade, naturalidade (cidade e UF) e CPF. Todos são opcionais, porque a escola registra o que a família informa.

- Valores fechados, no padrão do Educacenso, com CHECK no banco: sexo (`female`, `male`); cor/raça (`branca`, `preta`, `parda`, `amarela`, `indigena`, `nao_declarada`); nacionalidade (`brasileira`, `naturalizada`, `estrangeira`).
- CPF: aceito com ou sem pontuação, gravado como `000.000.000-00`, com conferência dos dígitos verificadores. Um índice único parcial impede o mesmo CPF em dois cadastros ativos. A mensagem de CPF repetido não revela de quem é o outro cadastro.
- LGPD: cor/raça é dado sensível. CPF e cor/raça entram na auditoria como `[REDACTED]`: o histórico mostra que o dado foi informado ou mudou, sem guardar o valor. Valores nulos continuam nulos.

## Importação pelas planilhas

- Colunas reconhecidas: "Nome social", "Sexo", "Cor/Raça" (ou "Raça", "Cor"), "Nacionalidade", "Naturalidade" (ou "Município de nascimento"), "UF de nascimento" e "CPF do aluno". Uma coluna só "CPF" continua sendo lida como CPF do responsável, como antes, e não vai para o cadastro do aluno.
- Leitura tolerante: "F", "Feminino" ou "Menina"; "Pardo" ou "Parda"; "Brasileiro naturalizado"; naturalidade "RECIFE/PE", "Recife - PE" ou "Recife (PE)".
- Valor fora das opções do Censo (por exemplo, cor/raça "Azul" ou naturalidade sem UF válida) não é adivinhado: vira pendência com o texto original e não impede a gravação.
- CPF inválido não é gravado. CPF repetido em dois alunos da mesma planilha não vai para nenhum deles. CPF que já pertence a outro aluno da rede não é gravado, e a mensagem não diz de quem é.
- A tela de conferência mostra "CPF válido", sem o número.
- Aluno já cadastrado: endereço e Censo só completam campos vazios, com a marcação individual da pessoa. Um valor já preenchido nunca é substituído pela planilha.
- Na confirmação, os valores vindos da prévia cifrada são conferidos de novo, e o CPF é checado outra vez com o lock da importação.

