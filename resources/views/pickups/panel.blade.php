{{-- Vínculos e autorizações já foram carregados no controlador; não consultar o banco no Blade. --}}
@php
    $parametrosRetirada = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $autorizacoesVigentes = $pickupAuthorizations->filter(fn ($pessoa) => ! $pessoa->isExpired())->count();
@endphp

<section class="folha ficha-retiradas" id="quem-pode-buscar" aria-labelledby="titulo-quem-pode-buscar">
    <div class="folha-cabecalho">
        <div>
            <h2 id="titulo-quem-pode-buscar">Quem pode buscar</h2>
            <p>Conferência na saída: {{ $guardianLinks->count() + $autorizacoesVigentes }} {{ $guardianLinks->count() + $autorizacoesVigentes === 1 ? 'pessoa autorizada' : 'pessoas autorizadas' }}. Verifique a identidade no portão.</p>
        </div>
        @if ($canManage)
            <a class="botao secundario" href="{{ route('school.students.pickups.create', $parametrosRetirada) }}">Adicionar pessoa autorizada</a>
        @endif
    </div>

    @isset($lastExit)
        <p class="ficha-ultima-saida">
            <strong>Última saída:</strong>
            {{ $lastExit->exited_at->isToday() ? 'hoje' : ($lastExit->exited_at->isYesterday() ? 'ontem' : $lastExit->exited_at->format('d/m/Y')) }}
            às {{ $lastExit->exited_at->format('H:i') }}, entregue a {{ $lastExit->person_name }}@if ($lastExit->person_relationship) ({{ $lastExit->person_relationship }})@endif.
        </p>
    @endisset

    @if ($guardianLinks->isNotEmpty())
        <div class="retirada-grupo">
            <h3 class="retirada-subtitulo">Responsáveis (autorizados)</h3>
            <p class="retirada-ajuda">Os responsáveis vinculados ao aluno já estão autorizados, sem precisar de outro cadastro.</p>
            <ul class="retiradas-lista">
                @foreach ($guardianLinks as $vinculo)
                    @php($responsavelAutorizado = $vinculo->guardian)
                    <li class="retirada-pessoa">
                        <div class="retirada-identificacao">
                            <x-foto-identificacao tipo="responsavel" :id="$responsavelAutorizado->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$responsavelAutorizado->full_name"
                                :tem-foto="$responsavelAutorizado->photo_path !== null" tamanho="media" />
                            <strong>{{ $responsavelAutorizado->full_name }}</strong>
                            <span class="etiqueta ok">Responsável autorizado</span>
                        </div>
                        <p>{{ \App\Http\Controllers\GuardianController::RELATIONSHIPS[$vinculo->relationship] ?? 'Outro' }}</p>
                        <p>Telefone: <a class="numero" href="tel:+55{{ $responsavelAutorizado->phone }}">{{ $responsavelAutorizado->formatted_phone }}</a></p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="retirada-grupo">
        <h3 class="retirada-subtitulo">Outras pessoas autorizadas</h3>
        @if ($pickupAuthorizations->isEmpty())
            <p class="ficha-vazio">Nenhuma outra pessoa cadastrada para buscar este aluno.</p>
        @else
            <ul class="retiradas-lista">
                @foreach ($pickupAuthorizations as $pessoaAutorizada)
                    @php($vencida = $pessoaAutorizada->isExpired())
                    <li class="retirada-pessoa {{ $vencida ? 'retirada-vencida' : '' }}">
                        <div class="retirada-identificacao">
                            <x-foto-identificacao tipo="autorizado" :id="$pessoaAutorizada->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$pessoaAutorizada->full_name"
                                :tem-foto="$pessoaAutorizada->photo_path !== null" :pode-alterar="$canManage && ! $vencida" tamanho="media" />
                            <strong>{{ $pessoaAutorizada->full_name }}</strong>
                            @if ($vencida)
                                <span class="etiqueta neutra">Vencida em {{ $pessoaAutorizada->expires_at->format('d/m/Y') }}</span>
                            @else
                                <span class="etiqueta ok">Autorizada</span>
                            @endif
                        </div>
                        <div class="retirada-dados">
                            @if ($pessoaAutorizada->relationship)
                                <p>Relação: {{ $pessoaAutorizada->relationship }}</p>
                            @endif
                            <p>Telefone: <a class="numero" href="tel:+55{{ $pessoaAutorizada->phone }}">{{ $pessoaAutorizada->formatted_phone }}</a></p>
                            @if ($pessoaAutorizada->document)
                                <p>Documento para conferência: {{ $pessoaAutorizada->document }}</p>
                            @endif
                            @if ($pessoaAutorizada->expires_at)
                                <p>Autorizado até: {{ $pessoaAutorizada->expires_at->format('d/m/Y') }}</p>
                            @endif
                            @if ($pessoaAutorizada->notes)
                                <p>Observação: {{ $pessoaAutorizada->notes }}</p>
                            @endif
                        </div>
                        @if ($canManage)
                            <div class="responsavel-acoes">
                                <a class="responsavel-editar" href="{{ route('school.students.pickups.edit', [...$parametrosRetirada, 'autorizacao' => $pessoaAutorizada->id]) }}" aria-label="Editar autorização de {{ $pessoaAutorizada->full_name }}">Editar</a>
                                <a class="responsavel-editar responsavel-desvincular" href="{{ route('school.students.pickups.remove.form', [...$parametrosRetirada, 'autorizacao' => $pessoaAutorizada->id]) }}" aria-label="Remover autorização de {{ $pessoaAutorizada->full_name }}">Remover</a>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
