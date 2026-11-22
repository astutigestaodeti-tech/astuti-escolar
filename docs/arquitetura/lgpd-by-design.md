# LGPD desde o desenho (regras obrigatórias)

Decisão de Jefferson em 22/11/2026. Estas regras valem para toda tabela, tela, importação, API e portal da família. Quem programa (Claude ou ChatGPT) aplica sem esperar pedido; o PR diz, em uma linha, como a regra foi aplicada.

## 1. Base legal e melhor interesse

- Dados de crianças e adolescentes podem ser tratados pelas hipóteses dos arts. 7º e 11 da LGPD, e não só por consentimento, desde que prevaleça o melhor interesse (art. 14; Enunciado CD/ANPD nº 1, de 22/05/2023).
- Na rede pública, o cadastro escolar decorre de política pública e obrigação legal (matrícula, Censo Escolar, transporte, alimentação). Para isso **não** se pede consentimento.
- Consentimento só para o que não é obrigação: divulgação de imagem (site, redes sociais, eventos), compartilhamento com aplicativos de terceiros e biometria.

## 2. Minimização

- Só se coleta o que serve a uma finalidade escolar, legal ou de segurança. Campo sem finalidade clara não entra.
- Obrigatório só o que a lei ou a rotina exige. O resto é opcional, e opcional nunca bloqueia a gravação nem a importação.
- Exemplo aplicado: no trabalho do responsável guardamos só o nome do local e o telefone, para emergências. CNPJ, endereço e e-mail da empresa ficam de fora.

## 3. Categorias de dados

| Categoria | Exemplos | Tratamento |
|---|---|---|
| Cadastrais | nome, nascimento, filiação, endereço, CPF, certidão, contatos | perfil escolar com permissão de gestão |
| Acadêmicos | frequência, notas, avaliações, relatórios | escola e professores da turma |
| Sensíveis (art. 5º, II) | saúde (alergias, condições, medicamentos, laudos, deficiência), cor/raça, tipo sanguíneo, religião, biometria | camada extra: permissão própria, auditoria sem valor, nunca em listas gerais |
| Imagem | foto de identificação | uso interno, servida só a quem tem permissão, nunca por endereço público |

## 4. Regras de campo

- **Cor/raça:** opcional, categorias do IBGE/Censo com "Não declarada". (Já aplicado.)
- **Tipo sanguíneo:** opcional, na ficha de saúde, com "Não informado / Não sabe" e aviso de que serve só para atendimento de emergência.
- **Religião:** desligada por padrão. Só aparece se a rede ligar com finalidade registrada (escola confessional, ensino religioso facultativo, restrição alimentar por crença). Mesmo ligada, nunca obrigatória, com "Prefiro não informar".
- **Saúde:** professor vê apenas alertas críticos (ex.: alergia grave e o que fazer); laudo e diagnóstico só para quem tem permissão de saúde.
- **Foto:** ver seção 6.

## 5. Consentimento e auditoria

- Consentimento é uma caixa própria, destacada e desmarcada por padrão, ligada ao cadastro de um responsável legal.
- Registro: quem consentiu, data, hora, IP e versão do termo. A revogação precisa ser tão fácil quanto o aceite (portal da família, quando existir).
- Toda alteração de dado pessoal entra na auditoria. Dado sensível ou identificador (CPF, documento, telefones, cor/raça, saúde) aparece como `[REDACTED]`: registra-se que mudou, nunca o valor.

## 6. Foto de identificação

- Finalidade: segurança na entrada e na saída (conferir quem busca o aluno). É uso interno, diferente de divulgação de imagem, que exige consentimento à parte.
- Sem reconhecimento facial: a foto é só olhada por uma pessoa. Reconhecimento automático transformaria a foto em dado biométrico (sensível) e não é necessário.
- O arquivo é reprocessado ao ser enviado (recorte quadrado, tamanho pequeno), o que apaga dados escondidos como a localização GPS. Fica fora da pasta pública e só é entregue a usuário com permissão de consulta da escola, sem cache.
- Foto de aluno com mais de 12 meses ganha aviso para atualizar.
- Removida a pessoa autorizada ou o vínculo, a foto vai junto.

## 7. Controle de acesso (RBAC)

- Secretaria: cadastro básico, responsáveis, transporte, quem pode buscar.
- Coordenação ou saúde: dados de saúde completos (perfil próprio, quando a ficha de saúde existir).
- Professor e portaria: consultam o necessário para a rotina (lista da turma, quem pode buscar, alertas críticos), sem editar.
- Cada nova tela diz no PR quais perfis veem e quais alteram, com teste de isolamento entre escolas e perfis.
