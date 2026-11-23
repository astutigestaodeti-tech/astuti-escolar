@extends('layouts.municipal')

@section('title', 'Ficha de '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Alunos</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Ficha do aluno</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Consulta individual</span>
            <h1>Ficha do aluno</h1>
            <p>Informações de consulta da unidade {{ $school->name }}.</p>
        </div>
        <div class="acoes-titulo">
            <span class="etiqueta ok">Vínculo ativo</span>
            @if ($canManage)
                <a class="botao secundario" href="{{ route('school.students.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}"><x-icon name="ficha" :size="16" /> Editar dados</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif
    @if ($errors->has('foto'))
        <div class="status-erro" role="alert"><x-icon name="info" :size="18" /> {{ $errors->first('foto') }}</div>
    @endif

    <div class="ficha-escola">
        @php
            $logradouro = trim(implode(', ', array_filter([$student->address_street, $student->address_number])));
            $zona = ['urban' => 'Urbana', 'rural' => 'Rural'][$student->residence_zone ?? ''] ?? null;
            $temEndereco = $logradouro !== '' || $student->address_complement || $student->address_district || $student->address_city || $student->address_state || $student->postal_code || $zona;
            $editarEndereco = route('school.students.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]).'#endereco';
        @endphp
        <section class="folha" aria-labelledby="titulo-dados-aluno">
            <div class="folha-cabecalho ficha-cabecalho-foto">
                <x-foto-identificacao tipo="aluno" :id="$student->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$student->full_name"
                    :tem-foto="$student->photo_path !== null" :pode-alterar="$canManage" tamanho="grande"
                    :aviso="$student->photoIsOld() ? 'Foto de '.$student->photo_updated_at->format('m/Y').': vale atualizar' : null" />
                <div><h2 id="titulo-dados-aluno">Dados do aluno</h2><p>Informações disponíveis no cadastro municipal.</p></div>
            </div>
            <div class="ficha-grupos">
                <div class="ficha-grupo" aria-labelledby="titulo-identificacao">
                    <h3 id="titulo-identificacao">Identificação</h3>
                    <dl>
                        <div><dt>Nome completo</dt><dd>{{ $student->full_name }}</dd></div>
                        <div><dt>RA</dt><dd class="numero">{{ $student->ra ?? 'Não informado' }}</dd></div>
                        @php
                            // Uma idade só: a de hoje. A da data de corte (31/03 do ano letivo),
                            // que define etapa e série, aparece apenas quando é diferente.
                            $idadeHoje = $student->birth_date ? $student->ageOn(now()) : null;
                            $anoLetivoAtual = $enrollments->firstWhere('status', 'active')?->schoolYear?->year;
                            $idadeCorte = $anoLetivoAtual ? $student->ageOn(\Illuminate\Support\Carbon::create((int) $anoLetivoAtual, 3, 31)) : null;
                        @endphp
                        <div><dt>Data de nascimento</dt><dd class="numero">
                            @if ($student->birth_date)
                                {{ $student->birth_date->format('d/m/Y') }}
                                @if ($idadeHoje !== null)
                                    <small class="ficha-idade">{{ $idadeHoje }} {{ $idadeHoje === 1 ? 'ano' : 'anos' }}@if ($idadeCorte !== null && $idadeCorte !== $idadeHoje); <abbr title="Idade em 31/03/{{ $anoLetivoAtual }}, que define a etapa e a série do ano letivo">{{ $idadeCorte }} na data de corte</abbr>@endif</small>
                                @endif
                            @else
                                Não informada
                            @endif
                        </dd></div>
                        @if ($student->social_name)
                            <div><dt>Nome social</dt><dd>{{ $student->social_name }}</dd></div>
                        @endif
                        <div><dt>Sexo</dt><dd>{{ \App\Http\Controllers\StudentRecordController::SEX_LABELS[$student->sex ?? ''] ?? 'Não informado' }}</dd></div>
                        <div><dt>Cor/raça</dt><dd>{{ \App\Http\Controllers\StudentRecordController::RACE_LABELS[$student->race_color ?? ''] ?? 'Não informada' }}</dd></div>
                        <div><dt>Nacionalidade</dt><dd>{{ \App\Http\Controllers\StudentRecordController::NATIONALITY_LABELS[$student->nationality ?? ''] ?? 'Não informada' }}</dd></div>
                        <div><dt>Naturalidade</dt><dd>{{ trim(implode('/', array_filter([$student->birthplace_city, $student->birthplace_state]))) ?: 'Não informada' }}</dd></div>
                        <div><dt>CPF</dt><dd class="numero">{{ $student->cpf ?? 'Não informado' }}</dd></div>
                        @php
                            $rgFormatado = \App\Validation\IdentityDocuments::formatRg($student->rg_number);
                            $rgOrgao = trim(($student->rg_issuer ?? '').'/'.($student->rg_state ?? ''), '/');
                        @endphp
                        <div><dt>RG</dt><dd class="numero">
                            @if ($rgFormatado)
                                {{ $rgFormatado }} {{ $rgOrgao }}
                                @if ($student->rg_issued_at)
                                    <small class="ficha-idade">expedido em {{ $student->rg_issued_at->format('d/m/Y') }}</small>
                                @endif
                            @else
                                Não informado
                            @endif
                        </dd></div>
                        <div><dt>Certidão de nascimento</dt><dd class="numero">{{ \App\Validation\IdentityDocuments::formatBirthCertificate($student->birth_certificate) ?? 'Não informada' }}</dd></div>
                        <div><dt>Escola</dt><dd>{{ $school->name }}</dd></div>
                    </dl>
                </div>
                <div class="ficha-grupo" aria-labelledby="titulo-endereco-aluno">
                    <h3 id="titulo-endereco-aluno">Endereço</h3>
                    @if ($temEndereco)
                        <dl>
                            <div><dt>Logradouro e número</dt><dd>{{ $logradouro !== '' ? $logradouro : 'Não informado' }}</dd></div>
                            <div><dt>Complemento</dt><dd>{{ $student->address_complement ?? 'Não informado' }}</dd></div>
                            <div><dt>Bairro</dt><dd>{{ $student->address_district ?? 'Não informado' }}</dd></div>
                            <div><dt>Cidade</dt><dd>{{ $student->address_city ?? 'Não informado' }}</dd></div>
                            <div><dt>Estado</dt><dd>{{ $student->address_state ?? 'Não informado' }}</dd></div>
                            <div><dt>CEP</dt><dd class="numero">{{ $student->postal_code ?? 'Não informado' }}</dd></div>
                            <div><dt>Zona</dt><dd>{{ $zona ?? 'Não informada' }}</dd></div>
                            <div><dt>Distância até a escola</dt><dd>
                                @if ($distanciaEscola !== null)
                                    {{ \App\Geo\Distance::label($distanciaEscola) }} <small class="ficha-idade">em linha reta</small>
                                @elseif ($student->latitude === null)
                                    Casa não marcada no mapa
                                @else
                                    Escola não marcada no mapa
                                @endif
                                @if ($canManage)
                                    <small class="ficha-idade"><a href="{{ route('school.students.geo.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">{{ $student->latitude === null ? 'Marcar no mapa' : 'Ajustar no mapa' }}</a></small>
                                @endif
                            </dd></div>
                        </dl>
                        @if ($avisoTransporte)
                            <p class="geo-aviso" role="note">{{ $avisoTransporte }}</p>
                        @endif
                    @else
                        <p class="ficha-vazio">Endereço ainda não informado.
                            @if ($canManage)
                                <a href="{{ $editarEndereco }}">Informar endereço</a>
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </section>

        @include('guardians.panel')

        @include('health.panel')
        @include('pickups.panel')

        <section class="folha cartao ficha-historico" aria-labelledby="titulo-vinculos">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-vinculos">Histórico de vínculos nesta escola</h2>
            <p>Anos letivos, situações e datas registradas nesta unidade.</p>
            <ul class="documentos vinculos-aluno">
                @foreach ($enrollments as $enrollment)
                    @php
                        $situacao = match ($enrollment->status) {
                            'active' => 'Ativo',
                            'completed' => 'Concluído',
                            'cancelled' => 'Cancelado',
                            'transferred' => 'Transferido',
                            'left_network' => 'Transferido para fora da rede',
                            'abandoned' => 'Abandono',
                            'deceased' => 'Falecimento',
                            default => 'Outro',
                        };
                    @endphp
                    <li>
                        <span class="etiqueta neutra numero">Ano letivo {{ $enrollment->schoolYear?->year ?? 'Não informado' }}</span>
                        <span class="etiqueta {{ $enrollment->status === 'active' ? 'ok' : 'neutra' }}">{{ $situacao }}</span>
                        <div class="vinculo-datas"><small>Turma: {{ $enrollment->schoolClass?->name ?? 'Não informada' }}</small></div>
                        <div class="vinculo-datas"><small>Transporte escolar:</small> @include('students.partials.transporte', ['situacao' => $enrollment->transport_status])</div>
                        @if ($canManage && $enrollment->status === 'active')
                            <a href="{{ route('school.students.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'matricula' => $enrollment->id]) }}">Editar turma desta matrícula</a>
                        @endif
                        @if ($enrollment->started_at || $enrollment->ended_at)
                            <div class="vinculo-datas">
                                @if ($enrollment->started_at)
                                    <small>Início: {{ $enrollment->started_at->format('d/m/Y') }}</small>
                                @endif
                                @if ($enrollment->ended_at)
                                    <small>Encerramento: {{ $enrollment->ended_at->format('d/m/Y') }}</small>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    @if ($canManage)
        @foreach ($enrollments->where('status', 'active') as $enrollment)
            @php
                $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'matricula' => $enrollment->id];
            @endphp
            <div class="formulario-acoes" aria-label="Movimentações da matrícula do ano letivo {{ $enrollment->schoolYear?->year }}">
                <a class="botao secundario" href="{{ route('school.enrollments.transfer.form', $params) }}">Transferir matrícula</a>
                <a class="botao secundario" href="{{ route('school.enrollments.end.form', $params) }}">Encerrar matrícula</a>
                @if ($canDeleteEnrollment)
                    <a class="botao secundario" href="{{ route('school.enrollments.delete-registration.form', $params) }}">Excluir cadastro</a>
                @endif
            </div>
        @endforeach
    @endif

    @if ($canManage)
        <div class="nota"><x-icon name="escudo" /><div><strong>Alterações exigem o segundo fator</strong><p>Para editar, confirme o código do seu aplicativo autenticador. Toda alteração fica registrada no histórico. Utilize apenas informações fictícias.</p></div></div>
    @else
        <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta</strong><p>Sua conta pode consultar esta ficha, mas não alterar dados escolares. Utilize apenas informações fictícias.</p></div></div>
    @endif
@endsection
