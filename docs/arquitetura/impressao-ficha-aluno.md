# Impressão da ficha cadastral do aluno

Issue #198; acompanhamento #193. Primeira entrega administrativa, sem migrations e sem emissão de documento público.

## Fluxo

Ação roxa na ficha do aluno (padrão visual #195), configuração de grupos e POST de geração de prévia, sem dados pessoais na URL. Nome e RA sempre identificam o documento. Prévia usa timbre institucional e papel A4 retrato, margens de 15 mm e contador de páginas nos navegadores que suportam caixas de margem CSS. Imprimir / PDF usa o diálogo nativo do navegador; não gera arquivo no servidor nem garante impressão física ou salvamento. Recomenda-se Chrome/Edge atualizado e desativar os cabeçalhos/rodapés automáticos do navegador na prévia, se necessário. O rodapé identifica data/hora e responsável pela geração; a via não substitui declaração ou histórico.

## Proteção

As duas operações verificam município, escola ativa, autorização manageStudents e vínculo ativo antes de permitir acesso. Exigem 2FA recente (15 minutos), inclusive para a ficha administrativa. Se expirar durante o formulário, a seleção deve ser refeita depois da confirmação; não guardar o POST de impressão na sessão. A prévia é privada e no-store, sem rota pública. A política manageHealth é também exigida para grupos sensíveis. A seleção e finalidade são listas fechadas validadas no servidor. A auditoria registra student.registration_print.generated, ID do aluno, autor e chaves de grupos/finalidade, não conteúdo do documento nem a confirmação de impressão física.

Por padrão, selecionam-se os oito grupos cadastrais. Foto privada somente se escolhida. Os dados de emergência são os telefones dos responsáveis já cadastrados, e não um cadastro autônomo de contatos. Retirada inclui autorizações vigentes, que ainda precisam ser conferidas no sistema na saída. Matrículas e transporte são exclusivamente os vínculos ativos da escola em questão. Para reduzir exposição, não se imprimem CPF, RG, certidão, raça/cor, coordenadas, documentos de terceiros, indicador de veículo adaptado, informações clínicas extensas ou registros técnicos da equipe multiprofissional nesta primeira versão.

Saúde (somente alertas graves e cuidados essenciais) e educação especial (categorias e situação do AEE) ficam desmarcadas e exigem finalidade explícita. A ficha de emergência independente permanece pendente. Não incluir laudos, diagnósticos adicionais ou observações livres.

## Validação

Testes de grupos selecionados, dados ausentes, autorizações vencidas, escopo, negação de professor, 2FA e auditoria; configuração e prévia adicionadas à rotina AllSchoolPagesOpenTest para conferência de margens em 1440 e 390. Conferir na VPS de desenvolvimento: título e cores, foto, escolha parcial de grupos, impressão de uma e múltiplas páginas, A4, numeração no navegador utilizado, e acesso negado. Nenhuma captura de cadastro ou foto deve ser publicada no GitHub. A homologação de uso real e os procedimentos de guarda/descarte de impressos são responsabilidade da Prefeitura.
