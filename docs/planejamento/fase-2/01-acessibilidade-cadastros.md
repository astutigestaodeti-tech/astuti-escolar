# Auditoria de acessibilidade cadastral - formulário de responsáveis

Base examinada: `2e57e67f24dfe3a08d42cc3c97d2218d975e7ad8`, 27/11/2026. Referência: #256, verificação 16.1. Revisão estática do Blade e teste automatizado de estrutura HTML; não substitui navegação por teclado, leitor de tela ou homologação visual.

| Elemento | Evidência estática | Próxima verificação |
|---|---|---|
| Nome, CPF, RG/UF, contatos e parentesco | Rótulos com for/id; UF tem rótulo sr-only | Confirmar anúncio pelo leitor e foco visível |
| Responsável legal e contato principal | Checkbox dentro de label | Conferir seleção com Espaço e anúncio do estado |
| CPF | aria-describedby aponta para cpf-ajuda | Conferir leitura da ajuda |
| Erros | Resumo role=alert e mensagens individuais | Associar erros por aria-describedby e aria-invalid; conferir retorno do foco após erro |
| Ações | Botões submit e link Cancelar | Conferir ordem Tab/Shift+Tab e ativação por teclado |
| Telefone | type=tel, inputmode=tel, máscara JS | Conferir digitação, colagem e correção por teclado |

Teste de regressão proposto em GuardianWriteTest: garante identificadores únicos, rótulos associados, ajuda de CPF, controles obrigatórios e ausência de tabindex positivo no formulário de criação. Não modifica tela. O resultado depende do CI no SHA exato. A associação dos erros individuais ainda é uma lacuna; a correção visual/semântica precisa de ensaio com página renderizada e margem em 1440 e 390 px conforme AGENTS.md. Não marcar 16.1 integralmente antes da execução do roteiro real, incluindo edição e desvínculo.

LGPD: somente dados fictícios; não adiciona campos, permissões nem coleta.
