{{-- Componente privado. Somente montar depois da autorização técnica e 2FA. --}}
<section class="folha" aria-labelledby="agente-resumo">
    <div class="folha-cabecalho">
        <div>
            <h2 id="agente-resumo">Resumo da implantação</h2>
            <p>Acompanhamento das implantações do Astuti Gestão Escolar.</p>
        </div>
    </div>
    <div class="folha-corpo">
        @if (!$snapshot['available'])
            <p role="status">Monitoramento indisponível. Nenhuma coleta válida recebida.</p>
        @else
            <p role="status">
                Última coleta: {{ $snapshot['collected_at'] }} (Brasília).
                @if ($snapshot['stale'])
                    <strong>Dados desatualizados. Não representam o estado atual.</strong>
                @endif
            </p>
            <dl class="agente-resumo">
                <div>
                    <dt>Versão instalada na coleta</dt>
                    <dd>{{ $snapshot['installed'] ? substr($snapshot['installed'], 0, 7) : 'Indisponível' }}</dd>
                </div>
                <div>
                    <dt>Última versão detectada</dt>
                    <dd>{{ $snapshot['target'] ? substr($snapshot['target'], 0, 7) : 'Indisponível' }}</dd>
                </div>
                <div>
                    <dt>Versão bloqueada</dt>
                    <dd>{{ $snapshot['blocked'] ? substr($snapshot['blocked'], 0, 7) : 'Não registrada na coleta' }}</dd>
                </div>
            </dl>
        @endif
    </div>
</section>

<section class="folha" aria-labelledby="agente-historico">
    <div class="folha-cabecalho">
        <div>
            <h2 id="agente-historico">Histórico de implantações</h2>
            <p>Tentativas e registros mais recentes primeiro. Abra os detalhes para consultar as etapas. O filtro mantém todas as etapas do registro encontrado.</p>
        </div>
    </div>
    <div class="folha-corpo">
        @forelse ($groups as $group)
            <article class="agente-grupo agente-etapa-{{ $group['latest']['stage'] }}">
                <div class="agente-grupo-cabecalho">
                    <div>
                        <h3>{{ count($group['releases']) === 1 ? $group['releases'][0]['title'] : (count($group['releases']) > 1 ? 'Atualização cumulativa' : 'Descrição não disponível') }}</h3>
                        <p><strong>{{ $group['latest']['label'] }}</strong> · {{ $group['latest']['time'] }}</p>
                        @if ($group['latest']['target'])
                            <p><a href="https://github.com/astutigestaodeti-tech/astuti-escolar/commit/{{ $group['latest']['target'] }}" rel="noreferrer">Versão {{ substr($group['latest']['target'], 0, 7) }}</a></p>
                        @endif
                        @if ($group['elapsed'] !== null)
                            <p>Intervalo entre detecção e último evento: {{ intdiv($group['elapsed'], 60) }} min {{ $group['elapsed'] % 60 }} s.</p>
                        @endif
                    </div>
                </div>
                @foreach ($group['releases'] as $release)
                    <p>@if (count($group['releases']) > 1)<strong>{{ $release['title'] }}:</strong> @endif{{ $release['description'] }} <a href="https://github.com/astutigestaodeti-tech/astuti-escolar/pull/{{ $release['pr'] }}" rel="noreferrer">PR #{{ $release['pr'] }}</a></p>
                @endforeach
                <details class="agente-detalhes">
                    <summary>Consultar etapas ({{ count($group['events']) }})</summary>
                    @foreach ($group['events'] as $event)
                        <div class="agente-evento agente-etapa-{{ $event['stage'] }}">
                            <div><strong>{{ $event['label'] }}</strong><p>{{ $event['time'] }}</p></div>
                            @if ($event['ci'])
                                <a href="https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/{{ $event['ci'] }}" rel="noreferrer">Testes CI</a>
                            @endif
                        </div>
                    @endforeach
                </details>
            </article>
        @empty
            <p>Nenhum evento registrado no histórico disponível.</p>
        @endforelse
        <p>{{ $total }} registros agrupados no filtro. Página {{ $page }} de {{ $pages }}.</p>
        <div class="agente-evento-links">
            @if ($page > 1)
                <a href="{{ route('developer.agent.index', ['municipio' => $municipality->slug, 'stage' => $stage, 'page' => $page - 1]) }}">Página anterior</a>
            @endif
            @if ($page < $pages)
                <a href="{{ route('developer.agent.index', ['municipio' => $municipality->slug, 'stage' => $stage, 'page' => $page + 1]) }}">Próxima página</a>
            @endif
        </div>
    </div>
</section>
