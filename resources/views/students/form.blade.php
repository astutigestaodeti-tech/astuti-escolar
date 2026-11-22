@extends('layouts.municipal')

@php
    $editando = $student !== null;
    $titulo = $editando ? 'Editar dados do aluno' : 'Novo aluno';
@endphp

@section('title', $titulo.' - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Alunos</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $titulo }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Secretaria escolar</span>
            <h1>{{ $titulo }}</h1>
            <p>{{ $editando ? 'Corrija os dados de '.$student->full_name.'.' : 'Cadastro e matrícula na unidade '.$school->name.'.' }} Toda alteração fica registrada no histórico.</p>
        </div>
    </div>

    <section class="folha formulario-aluno" aria-labelledby="titulo-formulario">
        <div class="folha-cabecalho"><div><h2 id="titulo-formulario">Dados do aluno</h2><p>Os campos marcados com * são obrigatórios.</p></div></div>

        @if ($errors->any())
            <div class="erro" role="alert" id="resumo-erros">
                <x-icon name="alerta" :size="18" />
                <div>
                    <strong>Revise o formulário</strong>
                    <ul>
                        @foreach ($errors->all() as $mensagem)
                            <li>{{ $mensagem }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" novalidate
              action="{{ $editando
                  ? route('school.students.update', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
                  : route('school.students.store', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">
            @csrf
            @if ($editando)
                @method('PUT')
                <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                <input type="hidden" name="versao" value="{{ old('versao', $studentVersion) }}">
                <input type="hidden" name="versao_matricula" value="{{ old('versao_matricula', $enrollmentVersion) }}">
            @endif

            @if ($editando)
                <p class="texto-ajuda">Você está editando a matrícula do ano letivo {{ $enrollment->schoolYear?->year ?? 'não informado' }}.
                    <a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">Veja os outros vínculos na ficha.</a>
                </p>
            @endif

            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="full_name">Nome completo *</label>
                    <input class="entrada" id="full_name" name="full_name" type="text" maxlength="180" autocomplete="off" required
                           value="{{ old('full_name', $student?->full_name) }}"
                           @error('full_name') aria-invalid="true" aria-describedby="full_name-erro" @enderror>
                    @error('full_name')<p class="campo-erro" id="full_name-erro">{{ $message }}</p>@enderror
                </div>

                <div class="formulario-campo">
                    <label class="rotulo" for="ra">RA do aluno</label>
                    <input class="entrada" id="ra" name="ra" type="text" maxlength="32" autocomplete="off"
                           placeholder="000123456789-0/SP" value="{{ old('ra', $student?->ra) }}"
                           aria-describedby="{{ $errors->has('ra') ? 'ra-ajuda ra-erro' : 'ra-ajuda' }}"
                           @error('ra') aria-invalid="true" @enderror>
                    <p class="texto-ajuda" id="ra-ajuda">Formato da SED: números, hífen, dígito e UF. Ex.: 000123456789-0/SP. Pode ficar em branco.</p>
                    @error('ra')<p class="campo-erro" id="ra-erro">{{ $message }}</p>@enderror
                </div>

                <div class="formulario-campo">
                    <label class="rotulo" for="birth_date">Data de nascimento</label>
                    <input class="entrada" id="birth_date" name="birth_date" type="date" min="1900-01-01" max="{{ now()->subDay()->format('Y-m-d') }}"
                           value="{{ old('birth_date', $student?->birth_date?->format('Y-m-d')) }}"
                           @error('birth_date') aria-invalid="true" aria-describedby="birth_date-erro" @enderror>
                    @error('birth_date')<p class="campo-erro" id="birth_date-erro">{{ $message }}</p>@enderror
                </div>

                @unless ($editando)
                    <div class="formulario-campo">
                        <label class="rotulo" for="school_year_id">Ano letivo da matrícula *</label>
                        <select class="entrada" id="school_year_id" name="school_year_id" required
                                @error('school_year_id') aria-invalid="true" aria-describedby="school_year_id-erro" @enderror>
                            <option value="">Escolha o ano letivo</option>
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected(old('school_year_id') === $year->id)>{{ $year->year }}</option>
                            @endforeach
                        </select>
                        @error('school_year_id')<p class="campo-erro" id="school_year_id-erro">{{ $message }}</p>@enderror
                    </div>

                    <div class="formulario-campo">
                        <label class="rotulo" for="started_at">Data de início na escola</label>
                        <input class="entrada" id="started_at" name="started_at" type="date" value="{{ old('started_at') }}"
                               @error('started_at') aria-invalid="true" aria-describedby="started_at-erro" @enderror>
                        @error('started_at')<p class="campo-erro" id="started_at-erro">{{ $message }}</p>@enderror
                    </div>
                @endunless

                <div class="formulario-campo largo">
                    <label class="rotulo" for="school_class_id">Turma da matrícula</label>
                    <select class="entrada" id="school_class_id" name="school_class_id"
                            @error('school_class_id') aria-invalid="true" aria-describedby="school_class_id-erro" @enderror>
                        <option value="">Sem turma definida</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('school_class_id', $enrollment?->school_class_id) === $class->id)>{{ $class->name }} ({{ $class->schoolYear?->year ?? 'Ano não informado' }})</option>
                        @endforeach
                    </select>
                    @error('school_class_id')<p class="campo-erro" id="school_class_id-erro">{{ $message }}</p>@enderror
                    <p class="texto-ajuda">A turma deve pertencer a esta escola e ao mesmo ano letivo da matrícula. Se ainda não foi definida, deixe em branco.</p>
                </div>

                <fieldset class="grupo-endereco grupo-censo" id="censo">
                    <legend>Dados do Censo Escolar</legend>
                    <p class="texto-ajuda">Pedidos pelo Educacenso. Todos são opcionais: preencha o que a família informou na matrícula.</p>
                    <div class="formulario-grade">
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="social_name">Nome social</label>
                            <input class="entrada" id="social_name" name="social_name" type="text" maxlength="180" autocomplete="off"
                                   value="{{ old('social_name', $student?->social_name) }}" aria-describedby="social_name-ajuda{{ $errors->has('social_name') ? ' social_name-erro' : '' }}"
                                   @error('social_name') aria-invalid="true" @enderror>
                            <p class="texto-ajuda" id="social_name-ajuda">Só quando o aluno ou a família pedir o uso do nome social.</p>
                            @error('social_name')<p class="campo-erro" id="social_name-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="sex">Sexo</label>
                            <select class="entrada" id="sex" name="sex"
                                    @error('sex') aria-invalid="true" aria-describedby="sex-erro" @enderror>
                                <option value="">Não informado</option>
                                @foreach (\App\Http\Controllers\StudentRecordController::SEX_LABELS as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(old('sex', $student?->sex) === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('sex')<p class="campo-erro" id="sex-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="race_color">Cor/raça</label>
                            <select class="entrada" id="race_color" name="race_color"
                                    @error('race_color') aria-invalid="true" aria-describedby="race_color-erro" @enderror>
                                <option value="">Não informada</option>
                                @foreach (\App\Http\Controllers\StudentRecordController::RACE_LABELS as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(old('race_color', $student?->race_color) === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('race_color')<p class="campo-erro" id="race_color-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="nationality">Nacionalidade</label>
                            <select class="entrada" id="nationality" name="nationality"
                                    @error('nationality') aria-invalid="true" aria-describedby="nationality-erro" @enderror>
                                <option value="">Não informada</option>
                                @foreach (\App\Http\Controllers\StudentRecordController::NATIONALITY_LABELS as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(old('nationality', $student?->nationality) === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('nationality')<p class="campo-erro" id="nationality-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="cpf">CPF</label>
                            <input class="entrada" id="cpf" name="cpf" type="text" maxlength="14" inputmode="numeric" autocomplete="off"
                                   placeholder="000.000.000-00" value="{{ old('cpf', $student?->cpf) }}"
                                   @error('cpf') aria-invalid="true" aria-describedby="cpf-erro" @enderror>
                            @error('cpf')<p class="campo-erro" id="cpf-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="birthplace_city">Cidade de nascimento</label>
                            <input class="entrada" id="birthplace_city" name="birthplace_city" type="text" maxlength="120" autocomplete="off"
                                   value="{{ old('birthplace_city', $student?->birthplace_city) }}"
                                   @error('birthplace_city') aria-invalid="true" aria-describedby="birthplace_city-erro" @enderror>
                            @error('birthplace_city')<p class="campo-erro" id="birthplace_city-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="birthplace_state">UF de nascimento</label>
                            <select class="entrada" id="birthplace_state" name="birthplace_state"
                                    @error('birthplace_state') aria-invalid="true" aria-describedby="birthplace_state-erro" @enderror>
                                <option value="">Não informada</option>
                                @foreach (\App\Address\BrazilianStates::NAMES as $uf => $estado)
                                    <option value="{{ $uf }}" @selected(old('birthplace_state', $student?->birthplace_state) === $uf)>{{ $uf }} - {{ $estado }}</option>
                                @endforeach
                            </select>
                            @error('birthplace_state')<p class="campo-erro" id="birthplace_state-erro">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="grupo-endereco grupo-documentos" id="documentos">
                    <legend>Documentos</legend>
                    <p class="texto-ajuda">Opcionais. Copie da certidão e do RG apresentados na matrícula; o sistema aceita com ou sem pontuação.</p>
                    <div class="formulario-grade">
                        <div class="formulario-campo">
                            <label class="rotulo" for="rg_number">RG</label>
                            <input class="entrada" id="rg_number" name="rg_number" maxlength="20" inputmode="text" autocomplete="off" placeholder="12.345.678-9"
                                   value="{{ old('rg_number', \App\Validation\IdentityDocuments::formatRg($student?->rg_number)) }}"
                                   @error('rg_number') aria-invalid="true" aria-describedby="rg_number-erro" @enderror>
                            @error('rg_number')<p class="campo-erro" id="rg_number-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="rg_issuer">Órgão emissor</label>
                            <input class="entrada" id="rg_issuer" name="rg_issuer" maxlength="20" placeholder="SSP" value="{{ old('rg_issuer', $student?->rg_issuer) }}"
                                   @error('rg_issuer') aria-invalid="true" aria-describedby="rg_issuer-erro" @enderror>
                            @error('rg_issuer')<p class="campo-erro" id="rg_issuer-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="rg_state">UF do RG</label>
                            <select class="entrada" id="rg_state" name="rg_state" @error('rg_state') aria-invalid="true" aria-describedby="rg_state-erro" @enderror>
                                <option value="">Não informada</option>
                                @foreach (\App\Address\BrazilianStates::NAMES as $uf => $estado)
                                    <option value="{{ $uf }}" @selected(old('rg_state', $student?->rg_state) === $uf)>{{ $uf }} - {{ $estado }}</option>
                                @endforeach
                            </select>
                            @error('rg_state')<p class="campo-erro" id="rg_state-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="rg_issued_at">Data de expedição do RG</label>
                            <input class="entrada" id="rg_issued_at" name="rg_issued_at" type="date" value="{{ old('rg_issued_at', $student?->rg_issued_at?->format('Y-m-d')) }}"
                                   @error('rg_issued_at') aria-invalid="true" aria-describedby="rg_issued_at-erro" @enderror>
                            @error('rg_issued_at')<p class="campo-erro" id="rg_issued_at-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo formulario-campo-largo">
                            <label class="rotulo" for="birth_certificate">Certidão de nascimento</label>
                            <input class="entrada" id="birth_certificate" name="birth_certificate" maxlength="80" autocomplete="off"
                                   placeholder="Matrícula de 32 números ou livro, folha e termo"
                                   value="{{ old('birth_certificate', \App\Validation\IdentityDocuments::formatBirthCertificate($student?->birth_certificate)) }}"
                                   @error('birth_certificate') aria-invalid="true" aria-describedby="birth_certificate-erro" @enderror>
                            <p class="texto-ajuda">Certidão nova: a matrícula de 32 números. Certidão antiga: livro, folha e termo.</p>
                            @error('birth_certificate')<p class="campo-erro" id="birth_certificate-erro">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="grupo-endereco" id="endereco"
                          data-postal-lookup-url="{{ route('school.students.postal.lookup', ['municipio' => $municipality->slug, 'escola' => $school->id, 'cep' => '__CEP__']) }}">
                    <legend>Endereço</legend>
                    <p class="texto-ajuda">Comece pelo CEP. O sistema sugere rua, bairro, cidade e estado quando disponíveis. Confira e corrija o que precisar. Todos os campos são opcionais.</p>
                    <div class="formulario-grade grade-endereco">
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="postal_code">CEP</label>
                            <div class="cep-busca">
                                <input class="entrada" id="postal_code" name="postal_code" type="text" maxlength="9" inputmode="numeric" autocomplete="postal-code"
                                       placeholder="00000-000" value="{{ old('postal_code', $student?->postal_code) }}"
                                       aria-describedby="{{ $errors->has('postal_code') ? 'postal_code-ajuda postal-lookup-status postal_code-erro' : 'postal_code-ajuda postal-lookup-status' }}"
                                       @error('postal_code') aria-invalid="true" @enderror>
                                <button type="button" class="botao secundario" data-postal-search>Buscar CEP</button>
                            </div>
                            <p class="texto-ajuda" id="postal_code-ajuda">Digite oito números ou use 00000-000. Se a consulta falhar, preencha o endereço normalmente.</p>
                            <p class="texto-ajuda" id="postal-lookup-status" data-postal-status role="status" aria-live="polite"></p>
                            @error('postal_code')<p class="campo-erro" id="postal_code-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo end-logradouro">
                            <label class="rotulo" for="address_street">Logradouro</label>
                            <input class="entrada" id="address_street" name="address_street" type="text" maxlength="180" autocomplete="address-line1"
                                   value="{{ old('address_street', $student?->address_street) }}"
                                   @error('address_street') aria-invalid="true" aria-describedby="address_street-erro" @enderror>
                            @error('address_street')<p class="campo-erro" id="address_street-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_number">Número</label>
                            <input class="entrada" id="address_number" name="address_number" type="text" maxlength="20"
                                   value="{{ old('address_number', $student?->address_number) }}"
                                   @error('address_number') aria-invalid="true" aria-describedby="address_number-erro" @enderror>
                            @error('address_number')<p class="campo-erro" id="address_number-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo end-complemento">
                            <label class="rotulo" for="address_complement">Complemento</label>
                            <input class="entrada" id="address_complement" name="address_complement" type="text" maxlength="180" autocomplete="address-line2"
                                   value="{{ old('address_complement', $student?->address_complement) }}"
                                   @error('address_complement') aria-invalid="true" aria-describedby="address_complement-erro" @enderror>
                            @error('address_complement')<p class="campo-erro" id="address_complement-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo end-bairro">
                            <label class="rotulo" for="address_district">Bairro</label>
                            <input class="entrada" id="address_district" name="address_district" type="text" maxlength="120"
                                   value="{{ old('address_district', $student?->address_district) }}"
                                   @error('address_district') aria-invalid="true" aria-describedby="address_district-erro" @enderror>
                            @error('address_district')<p class="campo-erro" id="address_district-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo end-cidade">
                            <label class="rotulo" for="address_city">Cidade</label>
                            <input class="entrada" id="address_city" name="address_city" type="text" maxlength="120" autocomplete="address-level2"
                                   value="{{ old('address_city', $student?->address_city) }}"
                                   @error('address_city') aria-invalid="true" aria-describedby="address_city-erro" @enderror>
                            @error('address_city')<p class="campo-erro" id="address_city-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_state">Estado (UF)</label>
                            <select class="entrada" id="address_state" name="address_state" autocomplete="address-level1"
                                    @error('address_state') aria-invalid="true" aria-describedby="address_state-erro" @enderror>
                                <option value="">Não informado</option>
                                @foreach (\App\Address\BrazilianStates::NAMES as $uf => $estado)
                                    <option value="{{ $uf }}" @selected(old('address_state', $student?->address_state) === $uf)>{{ $uf }} - {{ $estado }}</option>
                                @endforeach
                            </select>
                            @error('address_state')<p class="campo-erro" id="address_state-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="residence_zone">Zona de residência</label>
                            <select class="entrada" id="residence_zone" name="residence_zone"
                                    @error('residence_zone') aria-invalid="true" aria-describedby="residence_zone-erro" @enderror>
                                <option value="">Não informada</option>
                                <option value="urban" @selected(old('residence_zone', $student?->residence_zone) === 'urban')>Urbana</option>
                                <option value="rural" @selected(old('residence_zone', $student?->residence_zone) === 'rural')>Rural</option>
                            </select>
                            @error('residence_zone')<p class="campo-erro" id="residence_zone-erro">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>
            </div>

            @if (session('possivel_duplicidade'))
                <label class="confirmacao">
                    <input type="checkbox" name="confirmar_duplicidade" value="1">
                    <span>Conferi: é outra pessoa, com o mesmo nome. Quero cadastrar mesmo assim.</span>
                </label>
            @endif

            <div class="formulario-acoes">
                <button class="botao" type="submit">{{ $editando ? 'Salvar alterações' : 'Cadastrar e matricular' }}</button>
                <a class="botao secundario" href="{{ $editando
                    ? route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
                    : route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Cancelar</a>
            </div>
        </form>
    </section>

    <script type="module" src="{{ asset('js/student-postal-code.js') }}?v={{ filemtime(public_path('js/student-postal-code.js')) }}"></script>

    <div class="nota"><x-icon name="escudo" /><div><strong>Use apenas dados fictícios neste ambiente</strong><p>Documentos, laudos e dados de saúde não são cadastrados aqui.</p></div></div>
@endsection
