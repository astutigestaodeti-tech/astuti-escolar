@extends('layouts.municipal')

@section('title', 'Pedido '.$record->protocol)
@section('body-class', 'pagina-lista-espera')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/lista-espera.css') }}?v={{ filemtime(public_path('css/lista-espera.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('waitlist.index', ['municipio' => $municipality->slug, 'ano' => $record->intake_year]) }}">Lista de espera</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $record->protocol }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Pedido de vaga de educação infantil</span>
            <h1>Protocolo {{ $record->protocol }}</h1>
            <p>Registrado em {{ $record->requested_at->format('d/m/Y \à\s H:i') }}. A data original e o protocolo são permanentes.</p>
        </div>
        <span class="etiqueta neutra">{{ $statuses[$record->status] }}</span>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif
    @error('matricula')
        <div class="status-erro" role="alert"><x-icon name="info" :size="18" /> {{ $message }}</div>
    @enderror
    <section class="folha" aria-labelledby="titulo-inscricao">
        <div class="folha-cabecalho"><div><h2 id="titulo-inscricao">Dados da inscrição</h2><p>Consulta restrita à Secretaria Municipal de Educação e às unidades indicadas.</p></div></div>
        <div class="folha-corpo">
            <dl class="lista-espera-dados">
                <div><dt>Criança</dt><dd>{{ $record->child_name }}</dd></div>
                <div><dt>Nascimento</dt><dd>{{ $record->birth_date->format('d/m/Y') }}</dd></div>
                <div><dt>CPF</dt><dd>{{ $record->cpf ?: 'Não informado' }}</dd></div>
                <div><dt>Responsável</dt><dd>{{ $record->guardian_name }}</dd></div>
                <div><dt>Contato</dt><dd>{{ \App\Models\Tenant\Guardian::formatPhone($record->guardian_phone) }}</dd></div>
                <div><dt>Endereço</dt><dd>{{ $record->address_street }}, {{ $record->address_number }}{{ $record->address_complement ? ', '.$record->address_complement : '' }}, {{ $record->address_district }}, {{ $record->address_city }}/{{ $record->address_state }}, CEP {{ $record->postal_code }}</dd></div>
                <div><dt>Etapa</dt><dd>{{ $stages[$record->stage] }} (ingresso {{ $record->intake_year }}, corte {{ $record->cutoff_date->format('d/m/Y') }})</dd></div>
                <div><dt>Turno desejado</dt><dd>{{ $shifts[$record->desired_shift] ?? $record->desired_shift }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="folha lista-espera-secao" aria-labelledby="titulo-preferencias">
        <div class="folha-cabecalho"><div><h2 id="titulo-preferencias">Escolas de preferência</h2></div></div>
        <div class="folha-corpo">
            <ol class="lista-espera-itens">
                @foreach ($record->preferences as $preference)
                    <li>{{ $preference->school?->name ?? 'Escola indisponível' }}</li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="folha lista-espera-secao" aria-labelledby="titulo-prioridades">
        <div class="folha-cabecalho"><div><h2 id="titulo-prioridades">Prioridades justificadas</h2><p>Dados sensíveis. Acesso apenas por perfis autorizados.</p></div></div>
        <div class="folha-corpo">
            @if ($record->priority_count === 0)
                <p>Nenhuma prioridade marcada.</p>
            @else
                <dl class="lista-espera-dados">
                    @foreach ($record->priority_criteria as $code => $justification)
                        <div><dt>{{ $priorities[$code] ?? 'Critério informado' }}</dt><dd>{{ $justification }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </div>
    </section>

    <section class="folha lista-espera-secao" aria-labelledby="titulo-historico">
        <div class="folha-cabecalho"><div><h2 id="titulo-historico">Histórico de situação</h2><p>As mudanças são manuais, justificadas e auditadas.</p></div></div>
        <div class="folha-corpo">
            @if ($history->isEmpty())
                <p>Nenhuma mudança de situação registrada.</p>
            @else
                <ol class="lista-espera-itens">
                    @foreach ($history as $change)
                        <li>
                            {{ $statuses[$change->previous_status] ?? $change->previous_status }}
                            para {{ $statuses[$change->new_status] ?? $change->new_status }}
                            em {{ \Illuminate\Support\Carbon::parse($change->changed_at)->format('d/m/Y H:i') }}.
                            Motivo: {{ $change->reason }}
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>

    @if ($record->student_id)
        <section class="folha lista-espera-secao" aria-labelledby="titulo-matricula-feita">
            <div class="folha-cabecalho"><div><h2 id="titulo-matricula-feita">Matrícula feita pela lista</h2><p>A criança já é aluna da rede; o pedido está encerrado como atendido.</p></div></div>
        </section>
    @elseif ($canChange && in_array($record->status, ['aguardando', 'convocado'], true))
        <section class="folha lista-espera-secao" aria-labelledby="titulo-matricular">
            <div class="folha-cabecalho"><div><h2 id="titulo-matricular">Matricular a criança</h2><p>Abriu a vaga? A criança vira aluna da turma escolhida, o responsável fica vinculado e o pedido é encerrado como atendido, sem digitar os dados de novo.</p></div></div>
            <div class="folha-corpo">
                <a class="botao" href="{{ route('waitlist.enroll.form', ['municipio' => $municipality->slug, 'pedido' => $record->id]) }}">Matricular pela lista de espera</a>
            </div>
        </section>
    @endif

    @if ($canChange)
        <section class="folha lista-espera-secao" aria-labelledby="titulo-mudar-situacao">
            <div class="folha-cabecalho"><div><h2 id="titulo-mudar-situacao">Alterar situação</h2><p>Exige segundo fator confirmado recentemente e motivo obrigatório.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('waitlist.status', ['municipio' => $municipality->slug, 'pedido' => $record->id]) }}" class="lista-espera-mudanca">
                    @csrf
                    <label for="new_status">Nova situação
                        <select id="new_status" name="status" required>
                            <option value="">Selecione</option>
                            @foreach ($statuses as $key => $label)
                                @if ($key !== $record->status)
                                    <option value="{{ $key }}" @selected(old('status') === $key)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <label for="status_reason">Motivo da mudança (10 a 500 caracteres)
                        <textarea id="status_reason" name="reason" class="entrada" rows="3" maxlength="500" required>{{ old('reason') }}</textarea>
                    </label>
                    @if ($errors->any() && ! $errors->has('matricula'))
                        <div class="campo-erro" role="alert">{{ $errors->first() }}</div>
                    @endif
                    <button class="botao" type="submit">Registrar mudança</button>
                </form>
            </div>
        </section>
        <section class="folha lista-espera-secao" aria-labelledby="titulo-arquivar-pedido">
            <div class="folha-cabecalho"><div><h2 id="titulo-arquivar-pedido">Arquivar pedido</h2><p>Exclusão lógica. O registro e a auditoria serão preservados.</p></div></div>
            <div class="folha-corpo">
                <form method="POST" action="{{ route('waitlist.archive', ['municipio' => $municipality->slug, 'pedido' => $record->id]) }}" class="lista-espera-mudanca">
                    @csrf
                    <label for="archive_reason">Motivo do arquivamento
                        <textarea id="archive_reason" name="reason" class="entrada" rows="2" minlength="10" maxlength="500" required></textarea>
                    </label>
                    <button class="botao secundario" type="submit">Arquivar com justificativa</button>
                </form>
            </div>
        </section>
    @endif
@endsection
