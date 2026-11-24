@if (($teamPanels ?? []) !== [])
    @push('head')
        <link rel="stylesheet" href="{{ asset('css/equipe-multiprofissional.css') }}?v={{ filemtime(public_path('css/equipe-multiprofissional.css')) }}">
    @endpush
    <section class="folha equipe-aluno" id="equipe-multiprofissional">
        <div class="folha-cabecalho"><div><h2>Equipe multiprofissional</h2><p>Pedido de acompanhamento e orientação prática à escola. Sem informações técnicas.</p></div></div>
        <div class="folha-corpo">
            @if (! $teamVerified)
                <p>Confirme seu segundo fator para consultar o acompanhamento ou solicitar atendimento. <a href="{{ route('two-factor.settings') }}">Segurança da conta</a>.</p>
            @else
            @foreach ($teamPanels as $panel)
                <div class="equipe-encaminhamento">
                    <h3>{{ $panel['class'] }} | {{ $panel['year'] }}</h3>
                    @if ($panel['ongoing'])<p><strong>Existe acompanhamento em andamento.</strong></p>
                    @elseif ($panel['guidance'] !== [])<p>Acompanhamento registrado.</p>
                    @else<p>Não há acompanhamento em andamento informado.</p>@endif
                    @foreach ($panel['guidance'] as $guidance)
                        <p><strong>Orientação prática:</strong> {{ $guidance }}</p>
                    @endforeach
                    <form method="POST" action="{{ route('school.team.store', ['municipio' => $municipality->slug, 'escola' => $school->id, 'matricula' => $panel['enrollment']]) }}" class="equipe-form">
                        @csrf
                        <label>Área <select name="area" class="entrada" required>@foreach (\App\Models\Tenant\MultiprofessionalRequest::AREAS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <label>Motivo <select name="motivo" class="entrada" required><option value="">Selecione</option>@foreach (\App\Models\Tenant\MultiprofessionalRequest::REASONS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <button class="botao" type="submit">Solicitar atendimento</button>
                    </form>
                </div>
            @endforeach
            @endif
            <p class="texto-ajuda">Use apenas categorias. Não registre relatos familiares, hipóteses diagnósticas, CID ou laudos.</p>
        </div>
    </section>
@endif
