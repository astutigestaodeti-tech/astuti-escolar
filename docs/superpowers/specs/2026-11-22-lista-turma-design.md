# Lista da turma - desenho técnico (22/11/2026)

## Objetivo
Permitir a profissionais autorizados consultar uma turma, sua ocupação e seus alunos, identificando possíveis divergências de idade na data de corte sem bloquear nem alterar matrículas.

## Alternativas consideradas
1. Computar idades e ordenação inteiramente em SQL com `f_unaccent`: reduz processamento PHP, mas incorpora regras pedagógicas a SQL e complica testes.
2. Buscar as matrículas ativas da turma com relações pré-carregadas e calcular a apresentação em PHP: reutiliza `Student::ageOn()`, reduz o risco de consultas repetidas, permite testar regras isoladas e é adequado ao tamanho de uma turma. **Escolhida**.

## Consulta e permissão
- A lista `school.classes.index` vincula o nome de cada turma ativa à rota `school.classes.show`.
- O mesmo `Gate::authorize('view', [$school, $municipality])` protege a ficha; turmas devem pertencer à escola requisitada.
- Somente matrículas `active` com `school_id`, `school_year_id` e `school_class_id` correspondentes entram na contagem e na lista.
- `student.primaryGuardianLink.guardian` é carregado com eager loading. Exibir nome e telefone clicável do contato principal, caso exista.
- Ordenar alunos por nome transliterado sem acentos e em caixa baixa, preservando desempate por identificador.

## Datas, vagas e regra pedagógica
- Data de corte: 31/03 do ano letivo da turma, não a data de acesso. Usar `Student::ageOn()`. Sem data de nascimento, apresentar ausência de informação e não sinalizar discrepância.
- Pré-escola: turmas claramente denominadas Pré I, PI-A ou 4 anos admitem exatamente 4 anos; Pré II, PII-A ou 5 anos admitem exatamente 5 anos. A turma pré-escola sem subdivisão admite 4 ou 5 anos (faixa da etapa, não tolerância extra).
- Ensino Fundamental: nomes identificáveis de 1º a 9º ano (inclusive código 1A, 2A etc.) esperam `série + 5` anos, ou seja, 1º ano 6, ... 9º ano 14.
- Etapa EJA, turmas multisseriadas ou nomes genéricos/ambíguos fora da pré-escola não são classificados. Tolerância 0 para séries reconhecidas.
- Exibir `Conferir idade` apenas quando a data existe e a idade exata diverge do esperado. É orientação de conferência, não decisão automática.
- Vagas: mostrar `ocupadas / capacidade` e barra somente quando há capacidade positiva; limitar a barra visual a 100%, mas mostrar porcentagem real e alerta exato acima do limite. Sem vagas cadastradas, informar a ausência de capacidade, sem inventar percentual.

## Limites
Somente leitura das entidades acadêmicas, sem migrações. Sem alterar importadores. Nenhum seletor genérico `.cartao`: usar classes próprias para a ficha de turma. Testes exclusivamente com dados fictícios e isolamento por escola.
