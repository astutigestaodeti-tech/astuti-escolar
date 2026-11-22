{{-- Dados carregados pelo SchoolStudentController para evitar consultas no Blade. --}}
@php
    $parametrosResponsaveis = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
@endphp

<section class="folha ficha-responsaveis" id="responsaveis" aria-labelledby="titulo-responsaveis">
    <div class="folha-cabecalho">
        <div><h2 id="titulo-responsaveis">Responsáveis</h2><p>Quem responde pelo aluno e como falar com a família.</p></div>
        @if ($canManage && $guardianLinks->isNotEmpty())
            <a class="botao secundario" href="{{ route('school.students.guardians.create', $parametrosResponsaveis) }}">Adicionar responsável</a>
        @endif
    </div>

    @if ($guardianLinks->isEmpty())
        <div class="responsaveis-vazio">
            <p class="ficha-vazio">Nenhum responsável cadastrado.
                @if ($canManage)
                    <a href="{{ route('school.students.guardians.create', $parametrosResponsaveis) }}">Adicionar responsável</a>
                @endif
            </p>
        </div>
    @else
        <ul class="responsaveis-lista">
            @foreach ($guardianLinks as $vinculoResponsavel)
                @php($responsavel = $vinculoResponsavel->guardian)
                <li class="responsavel">
                    <div class="responsavel-topo">
                        <h3>{{ $responsavel->full_name }}</h3>
                        <span class="responsavel-parentesco">{{ \App\Http\Controllers\GuardianController::RELATIONSHIPS[$vinculoResponsavel->relationship] ?? 'Outro' }}</span>
                    </div>
                    @if ($vinculoResponsavel->is_primary_contact || $vinculoResponsavel->is_legal_guardian)
                        <div class="responsavel-etiquetas">
                            @if ($vinculoResponsavel->is_primary_contact)
                                <span class="etiqueta ok">Contato principal</span>
                            @endif
                            @if ($vinculoResponsavel->is_legal_guardian)
                                <span class="etiqueta neutra">Responsável legal</span>
                            @endif
                        </div>
                    @endif
                    <dl>
                        <div><dt>Telefone</dt><dd class="numero"><a href="tel:+55{{ preg_replace('/\D/', '', (string) $responsavel->phone) }}">{{ $responsavel->formatted_phone }}</a></dd></div>
                        <div><dt>E-mail</dt><dd>@if ($responsavel->email)<a href="mailto:{{ $responsavel->email }}">{{ $responsavel->email }}</a>@else Não informado @endif</dd></div>
                    </dl>
                    @if ($canManage)
                        <a class="responsavel-editar" href="{{ route('school.students.guardians.edit', [...$parametrosResponsaveis, 'vinculo' => $vinculoResponsavel->id]) }}" aria-label="Editar dados de {{ $responsavel->full_name }}">Editar</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
