<?php

namespace App\Developer;

/**
 * Agrupa apenas eventos sanitizados e contíguos; não inventa tentativas
 * nem associa um PR isolado a uma publicação cumulativa desconhecida.
 */
final class DeploymentHistory
{
    // Catálogo inicial conferido pelos pares base/merge dos PRs integrados.
    // Não é um coletor de metadados para versões futuras.
    private const RELEASES = [
        '6fc1b7d42f5336df386ef89e88d0c510f0ad8c15' => [
            'previous' => '5f416be50f29c6afc9aa8097b31e60c1bc1c0315',
            'pr' => 241,
            'title' => 'Tabela do Sistema Presença',
            'description' => 'Corrige a apresentação da tabela em telas menores.',
        ],
        'ad5c34baabbec9a584f2b4bf0f93949c513940e2' => [
            'previous' => '6fc1b7d42f5336df386ef89e88d0c510f0ad8c15',
            'pr' => 238,
            'title' => 'Margens institucionais de impressão',
            'description' => 'Padroniza as margens dos documentos A4.',
        ],
        'c6fc3cbfb45c2d2097050b913c039824f7fb6a40' => [
            'previous' => 'ad5c34baabbec9a584f2b4bf0f93949c513940e2',
            'pr' => 243,
            'title' => 'Botões da ficha do aluno',
            'description' => 'Organiza as ações de transferência, encerramento e exclusão.',
        ],
        '7aee1e7a38d51a408a4de6a1c4fb4868a90d966a' => [
            'previous' => 'c6fc3cbfb45c2d2097050b913c039824f7fb6a40',
            'pr' => 242,
            'title' => 'Cardápio escolar em A4',
            'description' => 'Corrige o botão de impressão do mural do cardápio.',
        ],
        '1a7c23089e896d254ea2283fb5985c09e2a70c70' => [
            'previous' => '7aee1e7a38d51a408a4de6a1c4fb4868a90d966a',
            'pr' => 245,
            'title' => 'Histórico de implantações',
            'description' => 'Prepara a leitura validada do histórico e o componente visual da aba Agente.',
        ],
        'ed8859b888e80e467c0f396721b0bab87c6018f0' => [
            'previous' => '1a7c23089e896d254ea2283fb5985c09e2a70c70',
            'pr' => 246,
            'title' => 'Identificação da versão no rodapé',
            'description' => 'Exibe a versão publicada pelo agente e retira informações internas dos bancos.',
        ],
        'a7856a1c68f01a9623cdc7e295da41b7dd642942' => [
            'previous' => 'ed8859b888e80e467c0f396721b0bab87c6018f0',
            'pr' => 247,
            'title' => 'Segundo fator e validade do monitoramento',
            'description' => 'Exige verificação real do segundo fator no console e ajusta a validade da coleta.',
        ],
        '4de8ea4feb982e549cd263586d6e3413264caa43' => [
            'previous' => 'a7856a1c68f01a9623cdc7e295da41b7dd642942',
            'pr' => 248,
            'title' => 'Aba Agente disponível no menu',
            'description' => 'Inclui acesso privativo, histórico, filtros e atualização automática.',
        ],
    ];

    public function releases(?string $previous, ?string $target): array
    {
        if ($previous === null || $target === null || $previous === $target) {
            return [];
        }

        $items = [];
        while (isset(self::RELEASES[$target]) && count($items) < count(self::RELEASES)) {
            $item = self::RELEASES[$target];
            $items[] = ['pr' => $item['pr'], 'title' => $item['title'], 'description' => $item['description']];
            if ($item['previous'] === $previous) {
                return array_reverse($items);
            }
            $target = $item['previous'];
        }

        // Nunca apresenta cadeia parcial como se representasse toda a atualização.
        return [];
    }

    /** Recebe os eventos em ordem decrescente, conforme DeploymentSnapshot. */
    public function group(array $events, string $stage = ''): array
    {
        $groups = [];
        foreach (array_reverse($events) as $event) {
            $index = count($groups) - 1;
            $last = $groups[$index]['latest'] ?? null;
            $new = $last === null
                || $event['previous'] === null || $event['target'] === null
                || $last['previous'] !== $event['previous']
                || $last['target'] !== $event['target']
                || $event['stage'] === 'versao_detectada'
                || in_array($last['stage'], ['implantado', 'rollback_concluido'], true)
                || ($event['stage'] === 'implantando' && in_array('implantando', array_column($groups[$index]['events'] ?? [], 'stage'), true));

            if ($new) {
                $groups[] = [
                    'latest' => $event,
                    'events' => [$event],
                    'releases' => $this->releases($event['previous'], $event['target']),
                    'elapsed' => null,
                ];

                continue;
            }

            $groups[$index]['events'][] = $event;
            $groups[$index]['latest'] = $event;
            $first = $groups[$index]['events'][0];
            if ($first['stage'] === 'versao_detectada') {
                $groups[$index]['elapsed'] = (int) floor(max(0, $event['epoch'] - $first['epoch']));
            }
        }

        $groups = array_reverse($groups);
        if ($stage !== '') {
            $groups = array_values(array_filter($groups, static fn (array $group): bool => in_array($stage, array_column($group['events'], 'stage'), true)));
        }

        return $groups;
    }
}
