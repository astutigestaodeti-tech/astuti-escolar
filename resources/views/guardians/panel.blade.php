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

    @if ($guardianLinks->isNotEmpty() && ! $guardianLinks->contains('is_primary_contact', true))
        <div class="nota responsaveis-sem-principal" role="status">
            <x-icon name="info" :size="18" />
            <p>Este aluno está sem contato principal. Edite um responsável para marcar.</p>
        </div>
    @endif

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
                        <x-foto-identificacao tipo="responsavel" :id="$responsavel->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$responsavel->full_name"
                            :tem-foto="$responsavel->photo_path !== null" :pode-alterar="$canManage" />
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
                        <div><dt>Telefone</dt><dd class="numero">
                            <a href="tel:+55{{ preg_replace('/\D/', '', (string) $responsavel->phone) }}">{{ $responsavel->formatted_phone }}</a>
                            @if ($whatsapp = \App\Models\Tenant\Guardian::whatsappLink($responsavel->phone))
                                <a class="responsavel-whatsapp" href="{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="Abrir conversa no WhatsApp com {{ $responsavel->full_name }}">WhatsApp</a>
                            @endif
                        </dd></div>
                        @if ($responsavel->phone_secondary)
                            <div><dt>Outro telefone</dt><dd class="numero"><a href="tel:+55{{ $responsavel->phone_secondary }}">{{ \App\Models\Tenant\Guardian::formatPhone($responsavel->phone_secondary) }}</a></dd></div>
                        @endif
                        @if ($responsavel->workplace || $responsavel->work_phone)
                            <div><dt>Trabalho</dt><dd>{{ $responsavel->workplace ?? 'Telefone' }}@if ($responsavel->work_phone)<br><a class="numero" href="tel:+55{{ $responsavel->work_phone }}">{{ \App\Models\Tenant\Guardian::formatPhone($responsavel->work_phone) }}</a>@endif</dd></div>
                        @endif
                        <div><dt>E-mail</dt><dd>@if ($responsavel->email)<a href="mailto:{{ $responsavel->email }}">{{ $responsavel->email }}</a>@else Não informado @endif</dd></div>
                    </dl>
                    @if ($canManage)
                        <div class="responsavel-acoes">
                            <a class="responsavel-editar" href="{{ route('school.students.guardians.edit', [...$parametrosResponsaveis, 'vinculo' => $vinculoResponsavel->id]) }}" aria-label="Editar dados de {{ $responsavel->full_name }}">Editar</a>
                            <a class="responsavel-editar responsavel-desvincular" href="{{ route('school.students.guardians.unlink.form', [...$parametrosResponsaveis, 'vinculo' => $vinculoResponsavel->id]) }}" aria-label="Desvincular {{ $responsavel->full_name }} do aluno">Desvincular</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
