{{-- Componente privado. Somente montar depois da autorização técnica e 2FA. --}}
<section class="folha" aria-labelledby="agente-resumo">
    <div class="folha-cabecalho">
        <div>
            <h2 id="agente-resumo">Agente</h2>
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
            <p>Eventos mais recentes primeiro. Cada linha representa uma etapa registrada, não uma implantação independente.</p>
        </div>
    </div>
    <div class="folha-corpo">
        @forelse (array_slice($snapshot['events'], 0, 30) as $event)
            <article class="agente-evento agente-etapa-{{ $event['stage'] }}">
                <div>
                    <strong>{{ $event['label'] }}</strong>
                    <p>{{ $event['time'] }}</p>
                </div>
                <div class="agente-evento-links">
                    @if ($event['target'])
                        <a href="https://github.com/astutigestaodeti-tech/astuti-escolar/commit/{{ $event['target'] }}" rel="noreferrer">Versão {{ substr($event['target'], 0, 7) }}</a>
                    @endif
                    @if ($event['ci'])
                        <a href="https://github.com/astutigestaodeti-tech/astuti-escolar/actions/runs/{{ $event['ci'] }}" rel="noreferrer">Testes CI</a>
                    @endif
                </div>
            </article>
        @empty
            <p>Nenhum evento registrado no histórico disponível.</p>
        @endforelse
        @if (count($snapshot['events']) > 30)
            <p>Exibindo os 30 eventos mais recentes do histórico disponível.</p>
        @endif
    </div>
</section>
