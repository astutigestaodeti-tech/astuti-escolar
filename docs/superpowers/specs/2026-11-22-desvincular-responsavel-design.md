# Desvincular responsável de aluno (22/11/2026)

## Objetivo
Permitir que a equipe escolar remova um vínculo lançado indevidamente sem apagar o cadastro municipal de responsável, que pode ser usado por irmãos.

## Comportamento
- O cartão de cada responsável exibe **Desvincular** somente para quem tem permissão `manageStudents`.
- A confirmação mostra aluno, responsável, parentesco e pede um motivo entre 10 e 500 caracteres.
- A gravação POST exige segundo fator **recente**, preservando o texto do motivo após o desafio.
- Dentro de transação tenant, a aplicação bloqueia o aluno e o vínculo, verifica que pertencem à escola/aluno autorizado, remove somente `student_guardians` e audita a exclusão com snapshot anterior e motivo.
- O CPF e o telefone do responsável não compõem o snapshot. O cadastro do responsável continua no município.
- Não se promove automaticamente outro vínculo como contato principal. A ficha avisa sempre que há responsáveis e nenhum contato principal.
- O sucesso informa quando o cadastro ainda está ligado a outro aluno.

## Segurança e limites
Sem alteração de esquema ou de arquivos do importador. Os testes usam exclusivamente dados fictícios. A auditoria mantém autoria e escopo municipal. A permissão é verificada para a tela e para o POST.
