{{-- Saúde na ficha: dados já carregados no controlador. Professor recebe só alertas graves. --}}
<section class="folha ficha-saude" id="saude" aria-labelledby="titulo-saude">
    <div class="folha-cabecalho">
        <div>
            <h2 id="titulo-saude">Saúde e cuidados</h2>
            <p>{{ $canManageHealth ? 'Informações dadas pela família. Dado sensível: não copie para outros documentos.' : 'Alertas graves informados pela família, com o que fazer.' }}</p>
        </div>
        @if ($canManageHealth)
            <a class="botao secundario" href="{{ route('school.students.health.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">Editar ficha de saúde</a>
        @endif
    </div>

    @if ($healthLocked ?? false)
        <p class="nota-segundo-fator"><x-icon name="escudo" :size="16" /><span>A ficha completa de saúde e a educação especial são dados sensíveis e só aparecem com o segundo fator ativo. <a href="{{ route('two-factor.settings') }}">Ativar o segundo fator</a></span></p>
    @endif
    @if ($healthAlerts->isEmpty())
        <p class="texto-suave">{{ $canManageHealth ? 'Nenhum alerta de saúde informado.' : 'Nenhum alerta grave informado.' }}</p>
    @else
        <ul class="saude-alertas">
            @foreach ($healthAlerts as $alerta)
                <li @class(['saude-alerta', 'saude-alerta-grave' => $alerta->isCritical()])>
                    <div>
                        <span class="etiqueta {{ $alerta->isCritical() ? 'conferir' : 'neutra' }}">{{ $alerta->isCritical() ? 'Grave' : 'Atenção' }}</span>
                        <span class="saude-alerta-tipo">{{ $alerta->kindLabel() }}</span>
                        <p class="saude-alerta-texto">{{ $alerta->alert_text }}</p>
                        @if ($alerta->care_instructions)
                            <p class="saude-alerta-acao"><strong>O que fazer:</strong> {{ $alerta->care_instructions }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canManageHealth)
        <p class="saude-emergencia"><strong>Tipo sanguíneo:</strong> {{ $healthProfile?->bloodTypeLabel() ?? 'Não informado' }}@if ($healthProfile?->informed_by) <span class="texto-suave">(informado por {{ $healthProfile->informed_by }}{{ $healthProfile->informed_on ? ' em '.$healthProfile->informed_on->format('d/m/Y') : '' }})</span>@endif</p>
    @endif
</section>
