# Astuti Maintenance - contrato offline (#249)

Primeira unidade, sem ativação operacional. Nenhuma rota, serviço, sudo, comando de sistema, credencial, arquivo de configuração ou banco foi alterado.

## Inventário inicial e fronteiras

Monte Sereno já possui o auto-deploy restrito, monitoramento, publicadores de snapshot e versão, e um serviço separado de recuperação. O console técnico lê snapshots sanitizados; não escreve no agente. O deploy vigente valida CI de main, paths, backups de código e smoke tests. A telemetria confirmou o commit 4de8ea4 e a árvore limpa em 26/11/2026. O inventário de permissões efetivas, locks, backups de banco e recuperação ainda exige conferência local; não presumir capacidade nem conceder privilégios nesta unidade.

## Formato v1

O pedido é JSON compacto, UTF-8, sem whitespace adicional, campos na ordem abaixo, no máximo 4096 bytes. Todos os campos são obrigatórios; nenhum campo adicional, parâmetro ou comando livre é permitido. Inteiros são inteiros JSON, não strings, decimais ou exponenciais. Campos de texto admitidos são ASCII. O validador exige igualdade com a reserialização canônica e rejeita chaves duplicadas. Exemplo fictício:

```json
{"schema":1,"installation":"monte-sereno","sequence":8,"nonce":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","issued_at":1795719990,"expires_at":1795720060,"operation":"diagnostico.saude"}
```

Assinatura Ed25519 destacada sobre os bytes exatos do prefixo ASCII astuti-maintenance:v1 seguido de LF e do JSON. Assinatura de 64 bytes e chave pública de 32 bytes codificadas em hexadecimal minúsculo. A chave pública, instalação e última sequência são recebidas exclusivamente de uma futura configuração/estado local confiável, não do envelope. A chave privada permanece fora do GitHub e da VPS; não existe serviço de assinatura neste bloco. Sodium ausente implica rejeição; a dependência deve ser verificada na futura instalação.

Validade máxima de 300 segundos, emissão não futura, expiração estritamente posterior ao instante de validação. Relógio confiável. Sequência estritamente maior que a última reservada. Nonce de 128 bits em hexadecimal; a validação de formato não garante unicidade. Catálogo inicial: diagnostico.saude e diagnostico.implantacao, ambos sem parâmetros. Validação não significa autorização de execução.

## Próximas unidades, não implementadas

Consumidor separado, com lock, estado durável e reserva atômica da sequência e do nonce antes de qualquer efeito; rejeição de replay mesmo após reinício, registro do resultado para idempotência e recuperação de falhas. Não usar a sessão PHP ou a fila escrita pelo web server. Implementar somente diagnóstico/simulação inicialmente. Definir rotação/revogação da chave, política local de autorização, limite de frequência/tempo de execução, auditoria sanitizada, isolamento e procedimentos de instalação. A autenticação criptográfica não substitui CI nem aprovação de novas capacidades. Não conectar o validador à rede ou executar tarefas privilegiadas antes desses critérios.

Testes unitários cobrem assinatura, adulteração, chave incorreta, escopo, expiração, sequência, operação não prevista e formato canônico. Não demonstram consumo durável, execução, recuperação ou segurança operacional completa.
