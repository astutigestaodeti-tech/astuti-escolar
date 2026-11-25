@extends('layouts.municipal')

@section('title', 'Ficha cadastral do aluno')
@section('body-class', 'impressao-ficha-aluno')
@section('timbre-proprio', '1')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/navegacao-registros.css') }}?v={{ filemtime(public_path('css/navegacao-registros.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ficha-impressao.css') }}?v={{ filemtime(public_path('css/ficha-impressao.css')) }}">
@endpush

@section('content')
    @php($params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
    <div class="folha-titulo ficha-print-acoes">
        <div><span class="contexto">Prévia A4</span><h1>Ficha cadastral do aluno</h1><p>Confira os dados antes de imprimir ou salvar em PDF.</p></div>
        <div class="ficha-print-botoes">
            <a class="registro-nav-botao registro-nav-neutro" href="{{ route('school.students.print.configure', $params) }}">Alterar seleção</a>
            <a class="registro-nav-botao registro-nav-neutro" href="{{ route('school.students.show', $params) }}">Voltar à ficha</a>
            <button type="button" class="registro-nav-botao registro-nav-roxo" data-action="print-declaration"><x-icon name="imprimir" :size="16" /> Imprimir / PDF</button>
        </div>
    </div>

    <article class="folha ficha-print-papel" aria-labelledby="ficha-print-titulo">
        <x-documento.timbre :municipality="$municipality" :school-name="$school->name" :school-inep="$school->inep_code" />
        <h2 id="ficha-print-titulo" class="ficha-print-titulo">Ficha cadastral do aluno</h2>
        <p class="ficha-print-restrito">Uso administrativo restrito @if (app()->environment('local', 'testing')) | Ambiente de demonstração: dados fictícios @endif</p>
        <div class="ficha-print-identidade">
            @if (in_array('foto', $groups, true))
                <div class="ficha-print-foto">
                    @if ($student->photo_path)
                        <img src="{{ route('school.photos.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'tipo' => 'aluno', 'id' => $student->id]) }}" alt="Foto de identificação do aluno" width="113" height="113">
                    @else
                        <span>Sem foto</span>
                    @endif
                </div>
            @endif
            <div class="ficha-print-identidade-dados">
                <strong>{{ $student->full_name }}</strong>
                <p>RA: {{ $student->ra ?? 'Não informado' }}</p>
                @if (in_array('identificacao', $groups, true))
                    <dl class="ficha-print-dados">
                        <div><dt>Nome social</dt><dd>{{ $student->social_name ?? 'Não informado' }}</dd></div>
                        <div><dt>Nascimento</dt><dd>{{ $student->birth_date?->format('d/m/Y') ?? 'Não informado' }}</dd></div>
                        <div><dt>Sexo</dt><dd>{{ \App\Http\Controllers\StudentRecordController::SEX_LABELS[$student->sex ?? ''] ?? 'Não informado' }}</dd></div>
                        <div><dt>Nacionalidade</dt><dd>{{ \App\Http\Controllers\StudentRecordController::NATIONALITY_LABELS[$student->nationality ?? ''] ?? 'Não informada' }}</dd></div>
                        <div><dt>Naturalidade</dt><dd>{{ implode('/', array_filter([$student->birthplace_city, $student->birthplace_state])) ?: 'Não informada' }}</dd></div>
                    </dl>
                @endif
            </div>
        </div>

        @if (in_array('endereco', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Endereço residencial</h3>
                <dl class="ficha-print-dados">
                    <div><dt>Logradouro e número</dt><dd>{{ implode(', ', array_filter([$student->address_street, $student->address_number])) ?: 'Não informado' }}</dd></div>
                    <div><dt>Complemento</dt><dd>{{ $student->address_complement ?? 'Não informado' }}</dd></div>
                    <div><dt>Bairro</dt><dd>{{ $student->address_district ?? 'Não informado' }}</dd></div>
                    <div><dt>Município / UF</dt><dd>{{ implode('/', array_filter([$student->address_city, $student->address_state])) ?: 'Não informado' }}</dd></div>
                    <div><dt>CEP</dt><dd>{{ $student->postal_code ?? 'Não informado' }}</dd></div>
                    <div><dt>Zona</dt><dd>{{ ['urban' => 'Urbana', 'rural' => 'Rural'][$student->residence_zone ?? ''] ?? 'Não informada' }}</dd></div>
                </dl>
            </section>
        @endif
        @if (in_array('responsaveis', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Pais e responsáveis cadastrados</h3>
                @forelse ($guardians as $link)
                    <div class="ficha-print-item">
                        <strong>{{ $link->guardian->full_name }}</strong>
                        <p>{{ \App\Http\Controllers\GuardianController::RELATIONSHIPS[$link->relationship] ?? 'Outro vínculo' }}@if ($link->is_legal_guardian) | Responsável legal @endif @if ($link->is_primary_contact) | Contato principal @endif</p>
                    </div>
                @empty <p>Nenhum responsável cadastrado.</p> @endforelse
            </section>
        @endif
        @if (in_array('emergencia', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Contatos para emergência</h3>
                <p class="ficha-print-nota">Telefones cadastrados dos responsáveis, não uma lista independente de contatos de emergência.</p>
                @forelse ($guardians as $link)
                    <div class="ficha-print-item">
                        <strong>{{ $link->guardian->full_name }} @if ($link->is_primary_contact) (principal) @endif</strong>
                        <p>Telefone: {{ \App\Models\Tenant\Guardian::formatPhone($link->guardian->phone) ?? 'Não informado' }}</p>
                        @if ($link->guardian->phone_secondary)<p>Alternativo: {{ \App\Models\Tenant\Guardian::formatPhone($link->guardian->phone_secondary) }}</p>@endif
                        @if ($link->guardian->work_phone)<p>Trabalho: {{ \App\Models\Tenant\Guardian::formatPhone($link->guardian->work_phone) }}</p>@endif
                    </div>
                @empty <p>Nenhum contato cadastrado.</p> @endforelse
            </section>
        @endif
        @if (in_array('retirada', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Pessoas autorizadas para retirada (vigentes)</h3>
                @forelse ($pickups as $pickup)
                    <div class="ficha-print-item"><strong>{{ $pickup->full_name }}</strong><p>{{ $pickup->relationship ?? 'Vínculo não informado' }} | Telefone: {{ $pickup->formatted_phone ?: 'Não informado' }} | Validade: {{ $pickup->expires_at?->format('d/m/Y') ?? 'Sem vencimento informado' }}</p></div>
                @empty <p>Nenhuma autorização adicional vigente cadastrada.</p> @endforelse
                <p class="ficha-print-nota">Conferir a autorização no sistema antes de liberar a saída.</p>
            </section>
        @endif
        @if (in_array('matricula', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Matrículas ativas nesta escola</h3>
                @forelse ($enrollments as $entry)
                    <div class="ficha-print-item"><strong>Ano letivo {{ $entry->schoolYear?->year ?? 'Não informado' }}</strong><p>Turma: {{ $entry->schoolClass?->name ?? 'Sem turma' }} | Etapa: {{ $entry->schoolClass?->stage ?? 'Não informada' }} | Turno: {{ $entry->schoolClass?->shift ?? 'Não informado' }}</p><p>Início: {{ $entry->started_at?->format('d/m/Y') ?? 'Não informado' }}</p></div>
                @empty <p>Nenhuma matrícula ativa encontrada.</p> @endforelse
            </section>
        @endif
        @if (in_array('transporte', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Transporte escolar</h3>
                @forelse ($enrollments as $entry)
                    <div class="ficha-print-item">
                        <strong>Ano letivo {{ $entry->schoolYear?->year ?? 'Não informado' }}</strong>
                        <p>Situação: {{ ['active' => 'Usa transporte', 'not_requested' => 'Não solicitado', 'cancelled' => 'Cancelado'][$entry->transport_status ?? ''] ?? 'Sem informação' }}</p>
                        @if ($entry->transport_status === 'active')
                            <p>Linha: {{ $entry->transport_route ?? 'Não informada' }} | Ponto: {{ $entry->transport_stop ?? 'Não informado' }}</p>
                        @endif
                    </div>
                @empty <p>Sem informação de transporte.</p> @endforelse
            </section>
        @endif
        @if ($purpose)
            <p class="ficha-print-restrito">Dados sensíveis | Finalidade: {{ \App\Http\Controllers\StudentRegistrationPrintController::PURPOSES[$purpose] }}</p>
        @endif
        @if (in_array('saude', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Alertas graves de saúde e cuidados essenciais</h3>
                @forelse ($alerts as $alert)
                    <div class="ficha-print-item"><strong>{{ $alert->kindLabel() }}</strong><p>{{ $alert->alert_text }}</p>@if ($alert->care_instructions)<p>Cuidados: {{ $alert->care_instructions }}</p>@endif</div>
                @empty <p>Nenhum alerta grave cadastrado.</p> @endforelse
            </section>
        @endif
        @if (in_array('educacao_especial', $groups, true))
            <section class="ficha-print-grupo">
                <h3>Educação especial</h3>
                @if ($specialEducation)
                    <p>Categorias registradas: {{ implode(', ', $specialEducation->conditionLabels()) ?: 'Não informadas' }}</p>
                    <p>Situação do AEE: {{ $specialEducation->aeeLabel() }}</p>
                @else <p>Sem informações cadastradas.</p> @endif
            </section>
        @endif

        <footer class="ficha-print-rodape">
            <p>Gerada em {{ $generatedAt->format('d/m/Y \à\s H:i') }} | Responsável pela geração: {{ $issuer }}</p>
            <p>Retrato do cadastro na data indicada. Não substitui declaração de matrícula ou histórico escolar. Uso administrativo restrito.</p>
        </footer>
    </article>
    <script type="module" src="{{ asset('js/declaracao-imprimir.js') }}?v={{ filemtime(public_path('js/declaracao-imprimir.js')) }}"></script>
@endsection
