{{-- Dados carregados pelo SchoolStudentController para evitar consultas no Blade. --}}
@php
    $parametrosResponsaveis = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
@endphp

<section class="folha" id="responsaveis" aria-labelledby="titulo-responsaveis">
    <div class="folha-cabecalho">
        <div><h2 id="titulo-responsaveis">Responsáveis</h2><p>Contatos e vínculo familiar registrados para este aluno.</p></div>
        @if ($canManage)
            <a class="botao secundario" href="{{ route('school.students.guardians.create', $parametrosResponsaveis) }}">Adicionar responsável</a>
        @endif
    </div>

    @forelse ($guardianLinks as $vinculoResponsavel)
        <div class="ficha-grupo">
            <h3>{{ $vinculoResponsavel->guardian->full_name }}</h3>
            <dl>
                <div><dt>Parentesco</dt><dd>{{ \App\Http\Controllers\GuardianController::RELATIONSHIPS[$vinculoResponsavel->relationship] ?? 'Outro' }}</dd></div>
                <div><dt>Telefone</dt><dd>{{ $vinculoResponsavel->guardian->formatted_phone }}</dd></div>
                <div><dt>E-mail</dt><dd>{{ $vinculoResponsavel->guardian->email ?? 'Não informado' }}</dd></div>
                <div><dt>Responsável legal</dt><dd>{{ $vinculoResponsavel->is_legal_guardian ? 'Sim' : 'Não' }}</dd></div>
                <div><dt>Contato principal</dt><dd>{{ $vinculoResponsavel->is_primary_contact ? 'Sim' : 'Não' }}</dd></div>
            </dl>
            @if ($canManage)
                <a class="botao secundario" href="{{ route('school.students.guardians.edit', [...$parametrosResponsaveis, 'vinculo' => $vinculoResponsavel->id]) }}">Editar</a>
            @endif
        </div>
    @empty
        <p class="texto-ajuda">Nenhum responsável cadastrado para este aluno.</p>
    @endforelse
</section>
