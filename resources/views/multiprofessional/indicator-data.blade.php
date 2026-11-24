<div class="folha-corpo">
    <p>Pedidos abertos, atendimentos realizados, encaminhamentos e encerramentos com data no mês. Tempo médio de espera em dias corridos até o primeiro atendimento realizado no mês.</p>
    <dl class="equipe-dados">
        <div><dt>Pedidos abertos</dt><dd>{{ $teamIndicators['opened'] ?? 'Protegido' }}</dd></div>
        <div><dt>Atendimentos</dt><dd>{{ $teamIndicators['attended'] ?? 'Protegido' }}</dd></div>
        <div><dt>Encaminhamentos</dt><dd>{{ $teamIndicators['referred'] ?? 'Protegido' }}</dd></div>
        <div><dt>Casos encerrados</dt><dd>{{ $teamIndicators['closed'] ?? 'Protegido' }}</dd></div>
        <div><dt>Espera média</dt><dd>{{ $teamIndicators['wait_days'] === null ? 'Protegido ou sem dados' : number_format($teamIndicators['wait_days'], 1, ',', '.').' dias' }}</dd></div>
    </dl>
    <p class="texto-ajuda">Grupos com menos de cinco alunos distintos e supressão complementar não são divulgados. Protegido não é zero. A área é a classificação disponível na emissão; indicadores não comprovam, isoladamente, o cumprimento da lei.</p>
</div>
@foreach (['schools' => 'Pedidos por escola', 'areas' => 'Pedidos por área', 'destinations' => 'Encaminhamentos por destino'] as $key => $heading)
    <div class="folha-corpo equipe-apos"><h3>{{ $heading }}</h3></div>
    <div class="rolagem equipe-rolagem"><table class="tabela equipe-tabela equipe-tabela-agregada">
        <thead><tr><th scope="col">Grupo</th><th scope="col">Quantidade</th></tr></thead>
        <tbody>@forelse ($teamIndicators[$key] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] ?? 'Protegido' }}</td></tr>@empty<tr><td colspan="2">Sem dados.</td></tr>@endforelse</tbody>
    </table></div>
@endforeach
