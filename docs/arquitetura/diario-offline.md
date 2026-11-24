# Diário do professor instalável e offline

Bloco 3 da ordem decidida em 24/11/2026 com o Jefferson, depois da análise do GEP da Ergon e da revisão cruzada com o ChatGPT. Este documento é o desenho. Cada fase abaixo vira um PR próprio, e nada é liberado com dados reais antes do relatório de impacto (seção 7).

## 1. Problema

O professor faz a chamada todo dia. Em escola rural sem sinal, um sistema só web obriga a anotar no papel e digitar depois, e a chamada do dia anterior hoje só entra como "correção", com motivo. O GEP resolve isso com aplicativo nativo que funciona sem internet. O Astuti vai resolver sem loja de aplicativos: o próprio site instalável no celular (PWA), com a chamada guardada no aparelho até voltar a conexão.

## 2. Escopo por fase

| Fase | Entrega |
|---|---|
| 1 | Aparelho habilitado com segundo fator, manifesto e *service worker* só com a casca do diário. Chamada **online** pela nova tela, já com o identificador da operação (idempotência). |
| 2 | Chamada **offline**: lista da turma guardada no aparelho, fila de operações, sincronização, conflito com conferência humana. |
| 3 | Conteúdo da aula (registro novo, primeiro online, depois offline). |
| 4 | Painel de aparelhos para a direção e a Secretaria: habilitados, última sincronização, pendências e revogação. |

Fora do escopo: notas, BNCC, parecer, foto, qualquer dado de saúde ou de educação especial.

## 3. O que fica no aparelho (minimização)

**Pode ficar:**
- identificador interno e nome do aluno;
- turma e escola autorizadas ao professor;
- dias de funcionamento da turma;
- as próprias marcações da chamada (presente, falta, justificada) e, na fase 3, o conteúdo da aula.

**Nunca fica:**
- CPF, RG, certidão, endereço, telefone e responsáveis;
- foto;
- deficiência, laudo, saúde, educação especial, transtornos;
- Bolsa Família, Censo e cor/raça;
- observação da chamada (fica só no servidor; offline não se escreve observação).

A lista de uma turma é entregue pelo servidor numa resposta própria, só com esses campos, e só para turma em que o professor tem atribuição vigente.

## 4. Habilitação do aparelho e tempo offline

1. O primeiro acesso é sempre online, com senha e segundo fator. O professor dá um nome ao aparelho ("Celular pessoal").
2. O servidor cria o registro do aparelho (`teacher_devices`: usuário, nome, criado em, última sincronização, revogado em) e devolve uma **chave de autorização offline** com validade.
3. A validade é configurada pela Prefeitura. O padrão é **72 horas**, com máximo de 7 dias. Vencida, o aparelho só volta a funcionar online, com novo login.
4. O desbloqueio local é um PIN de 6 dígitos do próprio aparelho, com bloqueio após inatividade. Ele **não** equivale a segundo fator: só protege os dados guardados.
5. A direção ou a Secretaria revogam o aparelho. Também há revogação automática quando o professor perde a atribuição ou o usuário é desativado. Na próxima conexão, o aparelho apaga os dados locais.

Limite que precisa ser dito ao cliente: aparelho desconectado **não recebe** a revogação. Por isso valem a criptografia, o prazo e a minimização.

## 5. Armazenamento local

- **Banco local:** IndexedDB, com os registros cifrados por AES-GCM (Web Crypto).
- **Chave:** derivada do PIN com PBKDF2 e um sal do aparelho; a chave nunca é gravada.
- **Persistência:** pedir armazenamento persistente (`navigator.storage.persist()`) e avisar na tela quando o navegador não garantir.
- **Cache do service worker:** guarda só a casca (HTML, CSS, JS, ícones). Nunca guarda página autenticada com dados nem respostas da API.
- **Pendências na tela:** o professor vê sempre quantas operações faltam sincronizar. Sair do sistema com pendência pede confirmação e não apaga a fila.
- **Mesmo endurecimento das demais telas:** CSP `script-src 'self'` e nenhum script de terceiros.

## 6. Sincronização

Cada chamada gera uma **operação**:

| Dado | Origem |
|---|---|
| UUID da operação | gerado no aparelho |
| aparelho | registro de habilitação |
| turma e data da aula | tela da chamada |
| marcações | professor |
| horário declarado pelo aparelho | relógio do aparelho |
| versão da chamada que o aparelho conhecia | última sincronização |

**O que o servidor faz ao receber:**
1. Confere a sessão, o aparelho não revogado, a autorização offline válida no momento da captura e a atribuição vigente na data da aula.
2. Confere a data: não pode ser futura, precisa estar dentro do ano letivo, não pode ser anterior à habilitação do aparelho e não pode ser mais antiga que a validade offline.
3. Aplica a operação com **idempotência**: o mesmo UUID duas vezes não grava de novo e devolve o mesmo resultado.
4. **Sem conflito** (nenhuma chamada no servidor naquela data, ou a versão que o aparelho conhecia é a atual): grava pelo mesmo caminho da chamada online e com a mesma auditoria. Acrescenta o aparelho, o UUID, o horário declarado e o horário de recebimento.
5. **Com conflito** (alguém registrou ou corrigiu a chamada depois): **não sobrescreve**. A operação fica "em conferência" para a direção decidir na tela de correção, que já exige motivo.

**Regra nova:** chamada capturada offline num dia anterior não é "correção". Ela entra como chamada do dia, marcada como "registrada sem internet", desde que respeite o prazo da autorização. Fora do prazo, só a direção registra, pela correção com motivo.

**Quando a operação é dada por sincronizada:** só depois da confirmação do servidor. Até lá ela fica na fila do aparelho.

## 7. LGPD

Antes de qualquer uso com dados reais:

- **Relatório de impacto (RIPD) específico** do diário offline. Base: arts. 6º, 14, 23, 37, 38, 46 e 48 da LGPD e Enunciado CD/ANPD nº 1/2023 (melhor interesse da criança e do adolescente).
- **Política municipal de uso de aparelhos**, com:
  - preferência por aparelho institucional;
  - regras para o celular pessoal (bloqueio de tela, PIN, sistema atualizado);
  - procedimento em caso de perda ou furto.
- **Auditoria no servidor**, com operador, aparelho, UUID, horários, resultado da sincronização e campos alterados. Nunca guarda as observações.
- **Incidente:** aparelho perdido é revogado no mesmo dia. A janela de exposição é a validade offline, e a Secretaria avalia a comunicação à ANPD conforme o art. 48.

## 8. Testes exigidos

- Idempotência: o mesmo UUID enviado duas vezes.
- Conflito: chamada registrada no servidor depois da captura não é sobrescrita.
- Aparelho revogado, autorização vencida e professor sem atribuição na data: tudo recusado.
- Data futura, fora do ano letivo ou anterior à habilitação: recusada.
- A resposta da lista da turma não contém nenhum campo da seção 3 "Nunca fica" (teste de contrato).
- O service worker não guarda respostas autenticadas (teste das regras de cache).
- Fluxo completo no navegador (fase 2): capturar offline, reabrir, sincronizar e conferir a auditoria.
