# Filtro de ano letivo na consulta de matrículas - design

## Objetivo
Na lista somente leitura de matrículas da escola autorizada, permitir filtrar por ano letivo e combinar o filtro com a busca por aluno. Facilitar a conferência de vínculos sem ampliar permissões nem alterar registros.

## Abordagens consideradas
1. Campo livre para digitar qualquer ano: simples, mas expõe o usuário a valores inválidos e pouco orienta.
2. Escolher ano com opções municipais globais: simples, mas pode mencionar anos sem vínculos na escola autorizada.
3. **Adotada:** seletor "Todos os anos" com anos distintos encontrados somente nas matrículas ativas, não arquivadas, da escola autorizada. Mais claro e sem mostrar anos de outras escolas.

## Comportamento
- `GET /m/{municipio}/escolas/{escola}/matriculas` aceita opcionalmente `ano_letivo` como ano inteiro listado no seletor, além de `busca` (máximo 120 caracteres).
- Opções do seletor provêm exclusivamente dos vínculos ativos da escola após autorização, excluindo matrículas ou alunos arquivados; independem da busca textual.
- Ano selecionado filtra `school_years.year` na consulta existente; sem seleção, comportamento anterior permanece.
- Ano malformado ou indisponível nesta escola resulta em erro de validação, sem consulta ampliada.
- Busca por nome e ano selecionado são combináveis; paginação preserva ambos os parâmetros.
- Rótulos em português, seletor acessível e responsivo; estado selecionado visível após pesquisa.
- Continuam valendo a autorização por escola, autenticação e TOTP; nenhuma operação de escrita é adicionada.

## Instalações por Prefeitura
**Produção é uma instalação independente por Prefeitura, cada qual com VPS exclusivo, aplicação própria e dois bancos PostgreSQL próprios (`platform` e `tenant`).** Não haverá duas Prefeituras como clientes dentro da mesma instância em produção. O cenário de múltiplos municípios existe apenas em testes locais descartáveis para provar isolamento de acesso. Este filtro não muda essa arquitetura.

## Restrições
Nenhuma migração, novo endpoint de escrita, alteração do PostgreSQL original 5432, MariaDB, backups, `.env`, dados reais ou merge. Trabalhar apenas na branch isolada do PR #5, mantido draft.
