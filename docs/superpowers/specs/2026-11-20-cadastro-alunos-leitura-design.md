# Cadastro escolar: primeira consulta real de alunos

## Contexto e intenção

Jefferson quer acelerar a transformação do Astuti Gestão Escolar, hoje com demonstração visual e consulta real de escolas/turmas, em um produto comercial utilizável pela secretaria escolar. O produto precisa receber o acervo atual sem obrigar a redigitar dados e, depois, reconstruir registros antigos com validação humana.

Nesta entrega, criar o **primeiro caminho real e seguro de leitura** para alunos cadastrados no banco de cada município. Permanece proibido usar informações reais de menores durante a fase experimental.

## Alternativas consideradas

1. **CRUD completo agora:** forneceria digitação imediatamente, mas abriria gravações de dados infantis antes de 2FA, auditoria e política de retificação. Não escolhido.
2. **Modelo persistente + consulta restrita agora:** permite testar escolas, identidade do aluno, seus vínculos e a interface, sem liberar inclusão, alteração ou exclusão; prepara a importação por etapas. **Escolhido por decisão técnica**, porque Jefferson delegou a ordem e pediu rapidez com segurança.
3. **Continuar só com dados estáticos na demonstração:** menor risco imediato, mas não desenvolve o núcleo do produto. Não escolhido.

## Comportamento

- Um aluno é uma identidade única **dentro de um único banco municipal**. A tabela `students` tem UUID, nome completo, data de nascimento opcional (acervos antigos podem estar incompletos), datas de registro. Não guarda CPF, laudo, informação sensível, filiação ou documentos neste incremento.
- A relação com a escola é um vínculo acadêmico em `student_enrollments`, independente da identidade: UUID, UUID do aluno, escola, ano letivo, situação e datas. Permite evoluir para transferências e histórico sem reescrever o cadastro.
- `GET /m/{municipio}/escolas/{escola}/alunos` exige login, município ativo, conexão tenant autorizada e permissão de visualização daquela escola. Exibe somente alunos com vínculo **ativo** naquela escola. Não soma alunos de turmas ou de escolas diferentes.
- Busca opcional por nome parcial, com caracteres `%` e `_` tratados literalmente; tamanho máximo 120; ordenação por nome e paginação de 25 registros, mantendo o parâmetro de busca.
- Nome e data são escapados na renderização. Na tabela, mostrar nome e ano de nascimento quando disponível; sem expor identificadores oficiais ou campos adicionais.
- Tela responsiva com identidade visual Astuti e link a partir da ficha da escola. Se não houver alunos, mostrar estado vazio claro e indicação de que importações e escrita chegarão em próximas versões.
- Não criar nenhuma rota HTTP de gravação nem um seeder com pessoas reais. Fixtures de testes com nomes inventados.
- O contexto municipal deve ser limpo após cada requisição, inclusive em erro, pelo middleware existente.

## Restrições e segurança

- A conexão `tenant` permanece bloqueada sem contexto.
- Isolamento físico entre municípios, permissões por escola e 403/404 atuais não podem regredir.
- Apenas leitura em desenvolvimento; 2FA, auditoria de alterações e processo de revisão documental antecedem operações de escrita e piloto real.
- Migrações novas usam nomes `student_enrollments` para evitar confusão com inscrições gerais; `school_year_id` e `school_id` têm FKs no mesmo banco tenant; exclusão em cascata é proibida.
- Não migrar nem alterar banco real do notebook remotamente. A instalação local ocorrerá por comando explícito de Jefferson depois dos testes.

## Critérios de aceite

- Testes de integração com bancos temporários para A, B e escopos de escolas. Usuário da A1 não vê matrícula de A2 nem acessa B.
- Busca e HTML escapado verificados em testes; vínculos encerrados não aparecem.
- Suíte PHP passa duas vezes no mesmo MariaDB descartável, Pint e build continuam válidos, testes visuais continuam verdes.
- Demo pública permanece fictícia e indisponível em produção.

## Fora deste incremento

Formulários de cadastro, permissões de escrita, dados reais, matrícula/transferência operacional, upload OCR, CPF, dados de saúde/AEE, histórico escolar e transmissão de informações oficiais. O modelo deve permitir essas etapas futuras sem prometer que já funcionam.
