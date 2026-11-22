# Endereço do aluno e base local de CEPs

Decisão de Jefferson em 22/11/2026: o endereço começa pelo CEP, e o sistema completa o resto.

- Campos do aluno: CEP, logradouro, número, complemento, bairro, cidade, UF e zona, todos opcionais e auditados com antes e depois.
- Base de CEPs **local**, na base municipal de cada Prefeitura (tabela `postal_codes`). A consulta não envia nada para fora do servidor.
- No formulário, ao completar os 8 números do CEP, `public/js/endereco-cep.js` consulta `GET /m/{municipio}/cep/{cep}` e preenche só os campos vazios ou os que o próprio sistema preencheu. Sem JavaScript, o servidor faz o mesmo ao gravar. O que a pessoa digitou sempre prevalece.
- CEP geral (um CEP para várias ruas) vem sem logradouro e bairro; só cidade, UF e, se houver, zona.
- Carga: `php artisan astuti:ceps-importar <municipio> arquivo.csv --fonte="..."`, com o cabeçalho `cep;logradouro;bairro;cidade;uf[;zona]` (UTF-8 ou Windows-1252). Rodar de novo atualiza sem apagar. Cada carga fica registrada na auditoria central.
- Demonstração: `--ficticio` carrega `database/data/ceps/monte-sereno-ficticio.csv` (97 CEPs gerados a partir do gabarito do kit). O script do notebook carrega essa base sozinho na cópia isolada 55436.

## Pendente de decisão de Jefferson

A fonte dos CEPs reais de cada Prefeitura: o cadastro imobiliário da própria Prefeitura, a base oficial dos Correios (DNE, que é paga) ou uma consulta externa (que envia o CEP para fora do servidor).
