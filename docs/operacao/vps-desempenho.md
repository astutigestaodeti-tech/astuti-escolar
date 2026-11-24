# Desempenho da VPS: ajuste e teste de carga

Dois scripts, usados nesta ordem: medir, ajustar e medir de novo.

- `scripts/operacao/vps/teste-de-carga.py` simula várias pessoas abrindo telas ao mesmo tempo. Só lê (GET), não abre fichas de alunos (para não encher a auditoria) e recusa rodar em `APP_ENV=production`.
- `scripts/operacao/vps/ajustar-desempenho.sh` ajusta o PHP-FPM, o OPcache e o PostgreSQL à memória e aos processadores da máquina. Sem a confirmação, só mostra o que faria.

## 1. Medir como está

Na VPS, dentro do tmux, na pasta do sistema:

```
cd /srv/astuti-escolar
python3 scripts/operacao/vps/teste-de-carga.py --usuario admin --pessoas 20 --duracao 120 --local
```

O script pede a senha e, se a conta tiver segundo fator, o código do aplicativo. `--local` mede só a VPS, sem a Cloudflare no meio. Anote a mediana, o "95% até" e os erros.

## 2. Ver o ajuste proposto (não muda nada)

```
sudo bash scripts/operacao/vps/ajustar-desempenho.sh
```

Mostra os valores atuais e os recomendados. Em 4 GB e 2 processadores: até 16 processos do PHP (o padrão do Debian é 5), PostgreSQL com 1 GB de cache próprio, JIT desligado.

## 3. Aplicar

```
sudo bash scripts/operacao/vps/ajustar-desempenho.sh --confirmar=AJUSTAR-DESEMPENHO
```

O PHP é recarregado sem queda. O PostgreSQL é reiniciado e fica cerca de 5 segundos fora do ar. Se o PHP ou o banco recusarem os valores, o script desfaz sozinho.

Arquivos próprios criados: `/etc/php/8.4/fpm/pool.d/zz-astuti-desempenho.conf`, `/etc/php/8.4/fpm/conf.d/98-astuti-desempenho.ini` e `/etc/postgresql/17/main/conf.d/astuti-desempenho.conf`. Nenhum arquivo do sistema é editado.

## 4. Medir de novo, subindo o número de pessoas

```
python3 scripts/operacao/vps/teste-de-carga.py --usuario admin --pessoas 20 --duracao 120 --local
python3 scripts/operacao/vps/teste-de-carga.py --usuario admin --pessoas 50 --duracao 120 --local
python3 scripts/operacao/vps/teste-de-carga.py --usuario admin --pessoas 100 --duracao 180 --local
```

Como ler o veredito:

| Veredito | 95% das telas abrem em | Erros |
|---|---|---|
| Confortável | até 1 segundo | menos de 1% |
| Aceitável | até 2,5 segundos | menos de 2% |
| Pesado | mais que isso | ou mais erros |

O número de pessoas do último "Confortável" é a capacidade para uso intenso. Cada pessoa simulada abre uma tela a cada 3 segundos em média, o que corresponde a alguém trabalhando sem parar; no dia a dia, o número de pessoas conectadas pode ser 2 a 3 vezes maior.

## Voltar ao padrão

```
sudo bash scripts/operacao/vps/ajustar-desempenho.sh --desfazer=AJUSTAR-DESEMPENHO
```

## Cuidados

- Rode o teste fora do horário em que alguém estiver usando a VPS.
- Todas as pessoas simuladas usam a mesma conta; o resultado é uma boa aproximação, não uma medida exata de contas diferentes.
- Na Prefeitura (produção), não rode o teste de carga; use a medida feita aqui para dimensionar a máquina.
