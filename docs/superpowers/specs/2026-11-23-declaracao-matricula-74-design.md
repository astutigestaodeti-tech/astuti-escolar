# Declaração de matrícula verificável - desenho da issue #74

## Propósito

Permitir que a direção ou a secretaria de uma escola emita e, quando necessário, revogue uma declaração de matrícula A4. Quem receber o documento poderá confirmar sua autenticidade por código ou QR Code sem receber dados pessoais excessivos de um menor.

## Escopo e limites

- Somente a gestão (`manageStudents`) vê lista, formulário, impressão e revogação. Emissão e revogação exigem segundo fator confirmado; a revogação exige confirmação recente e motivo.
- Só matrículas ativas na escola autorizada podem gerar novas declarações. Os dados de escola, aluno, turma, etapa, turno e ano são congelados no ato da emissão; edições posteriores não os alteram.
- A página pública `/verificar/{codigo}` não exige login, tem limite de 20 consultas por minuto por IP e `noindex`, mostra somente situação, tipo, nome abreviado, escola, ano/etapa e data de emissão. Código inválido ou inexistente recebe mensagem mínima.
- Cada VPS de produção acessa somente o banco municipal configurado. A verificação pública recusa serviço sem `tenancy.deployed_db_key` válido e disponível no conjunto de conexões da instalação. Em testes, o identificador municipal é definido explicitamente.
- Nenhum dado real, deploy, merge ou modificação de `LICENSE`/`HISTORICO.md`.

## Abordagens consideradas

1. **Tabela municipal de documentos com retrato imutável** (escolhida): permite auditoria, revogação e verificação fiel à emissão; usa o mesmo PostgreSQL do município.
2. Documento gerado sob demanda a partir da matrícula atual: menor esquema, porém uma mudança de turma ou nome alteraria silenciosamente o documento já entregue.
3. Serviço externo de QR/PDF: introduziria compartilhamento de dados de menores e dependência fora da VPS, contrariando segurança e LGPD.

## Desenho

- Migração tenant `000032` cria `issued_documents`, chaves estrangeiras restritivas, código único de 10 caracteres em alfabeto sem `0/O/1/I`, campos de emissão/revogação, finalidade opcional de 120 caracteres e snapshots em colunas próprias.
- Modelo `IssuedDocument` não terá gravação implícita: serviço/controlador registra a emissão e a revogação junto com auditoria dentro de transação. O filtro de auditoria mascara `verification_code` também quando o chamador passa o valor integral inadvertidamente.
- Controlador autenticado reutiliza políticas de escola, aluno e matrícula; um controlador público separado resolve o município pela configuração da VPS, sem consultar outro município.
- A interface interna usa os cartões existentes com `.folha-corpo`; a folha de impressão e o SVG do QR são gerados localmente. O botão de impressão usa arquivo JS próprio; CSS de impressão A4 em `@media print`.
- Testes de integração com somente dados fictícios: permissão por papel/escola, segundo fator, unicidade, snapshot, revogação e auditoria; teste de verificação pública, throttle e minimização; teste de layout inclui emissão/impressão/verificação e script de margens.

## Fora do escopo

Assinatura eletrônica qualificada, PDF armazenado, envio por e-mail, geolocalização, documentos de transferência e pesquisa pública por nome/RA/CPF.
