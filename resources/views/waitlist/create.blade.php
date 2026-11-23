@extends('layouts.municipal')

@section('title', 'Novo pedido de vaga')
@section('body-class', 'pagina-lista-espera')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/lista-espera.css') }}?v={{ filemtime(public_path('css/lista-espera.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('waitlist.index', ['municipio' => $municipality->slug]) }}">Lista de espera</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Novo pedido</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Educação infantil</span>
            <h1>Novo pedido de vaga</h1>
            <p>Preencha os dados informados pela família. O protocolo e a data do pedido serão gravados pelo sistema.</p>
        </div>
    </div>

    <section class="folha" aria-labelledby="titulo-pedido">
        <div class="folha-cabecalho"><div>
            <h2 id="titulo-pedido">Dados do pedido</h2>
            <p>Os campos com * são obrigatórios. CPF e critérios de prioridade são opcionais.</p>
        </div></div>
        <div class="folha-corpo">
            @if ($errors->any())
                <div class="erro" role="alert"><x-icon name="alerta" :size="18" />
                    <div><strong>Confira os dados</strong><ul>
                        @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul></div>
                </div>
            @endif

            <form method="POST" action="{{ route('waitlist.store', ['municipio' => $municipality->slug]) }}" novalidate>
                @csrf

                <fieldset class="lista-espera-grupo">
                    <legend>Criança e responsável</legend>
                    <div class="formulario-grade">
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="child_name">Nome completo da criança *</label>
                            <input class="entrada" id="child_name" name="child_name" required maxlength="180" value="{{ old('child_name') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="birth_date">Data de nascimento *</label>
                            <input class="entrada" type="date" id="birth_date" name="birth_date" required value="{{ old('birth_date') }}"
                                   data-cutoff="{{ $cutoff->format('Y-m-d') }}" data-stage-input>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="cpf">CPF (opcional)</label>
                            <input class="entrada" id="cpf" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="{{ old('cpf') }}">
                        </div>
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="guardian_name">Nome do responsável *</label>
                            <input class="entrada" id="guardian_name" name="guardian_name" required maxlength="180" value="{{ old('guardian_name') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="guardian_phone">Telefone com DDD *</label>
                            <input class="entrada" id="guardian_phone" name="guardian_phone" inputmode="tel" required maxlength="20" placeholder="(11) 98765-4321" value="{{ old('guardian_phone') }}">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="lista-espera-grupo grupo-endereco" data-postal-lookup-url="{{ route('waitlist.postal.lookup', ['municipio' => $municipality->slug, 'cep' => '__CEP__']) }}">
                    <legend>Endereço da família</legend>
                    <p class="texto-ajuda">Use a busca de CEP para preencher sugestões. Confira e complete todos os dados.</p>
                    <div class="formulario-grade">
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="postal_code">CEP *</label>
                            <div class="cep-busca">
                                <input class="entrada" id="postal_code" name="postal_code" maxlength="9" inputmode="numeric" required placeholder="00000-000" value="{{ old('postal_code') }}">
                                <button type="button" class="botao secundario" data-postal-search>Buscar CEP</button>
                            </div>
                            <p class="texto-ajuda" data-postal-status role="status" aria-live="polite"></p>
                        </div>
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="address_street">Logradouro *</label>
                            <input class="entrada" id="address_street" name="address_street" maxlength="180" required value="{{ old('address_street') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_number">Número (ou S/N) *</label>
                            <input class="entrada" id="address_number" name="address_number" maxlength="20" required value="{{ old('address_number') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_complement">Complemento (opcional)</label>
                            <input class="entrada" id="address_complement" name="address_complement" maxlength="180" value="{{ old('address_complement') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_district">Bairro *</label>
                            <input class="entrada" id="address_district" name="address_district" maxlength="120" required value="{{ old('address_district') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_city">Cidade *</label>
                            <input class="entrada" id="address_city" name="address_city" maxlength="120" required value="{{ old('address_city') }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_state">UF *</label>
                            <select class="entrada" id="address_state" name="address_state" required>
                                <option value="">Selecione</option>
                                @foreach ($states as $uf => $name)
                                    <option value="{{ $uf }}" @selected(old('address_state') === $uf)>{{ $uf }} - {{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="lista-espera-grupo">
                    <legend>Etapa, turno e escolas de preferência</legend>
                    <p class="texto-ajuda" data-stage-output role="status" aria-live="polite">Informe a data de nascimento para calcular a etapa.</p>
                    <p class="texto-ajuda">Data de corte configurada para o ingresso: <strong>{{ $cutoff->format('d/m/Y') }}</strong>. Até 1 ano: Berçário; 2: Maternal I; 3: Maternal II; 4: Pré I; 5: Pré II.</p>
                    <label class="lista-espera-confirmacao">
                        <input type="checkbox" name="confirm_stage" value="1" @checked((string) old('confirm_stage') === '1') required>
                        <span>Conferi a data de nascimento e confirmo a etapa calculada pelo sistema. *</span>
                    </label>
                    <div class="formulario-grade">
                        <div class="formulario-campo">
                            <label class="rotulo" for="desired_shift">Turno desejado *</label>
                            <select class="entrada" name="desired_shift" id="desired_shift" required>
                                <option value="">Selecione</option>
                                @foreach ($shifts as $key => $label)
                                    <option value="{{ $key }}" @selected(old('desired_shift') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @for ($i = 0; $i < 3; $i++)
                            <div class="formulario-campo largo">
                                <label class="rotulo" for="preference-{{ $i }}">Escola de preferência {{ $i + 1 }}{{ $i === 0 ? ' *' : ' (opcional)' }}</label>
                                <select class="entrada" name="preferences[]" id="preference-{{ $i }}" @if ($i === 0) required @endif>
                                    <option value="">Selecione uma unidade</option>
                                    @foreach ($schools as $school)
                                        <option value="{{ $school->id }}" @selected(old('preferences.'.$i) === $school->id)
                                            @disabled($i === 0 && ! in_array($school->id, $firstChoiceIds, true))>
                                            {{ $school->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endfor
                    </div>
                </fieldset>

                <fieldset class="lista-espera-grupo">
                    <legend>Critérios de prioridade (opcionais e sigilosos)</legend>
                    <p class="texto-ajuda">Marque apenas os critérios que a família informou e descreva brevemente a justificativa. Não insira diagnóstico, CID, laudo ou número de medida protetiva. A informação fica restrita à gestão autorizada.</p>
                    @foreach ($priorities as $code => $label)
                        <div class="lista-espera-prioridade">
                            <label class="lista-espera-confirmacao">
                                <input type="checkbox" name="priority_flags[{{ $code }}]" value="1" @checked((string) old('priority_flags.'.$code) === '1')>
                                <span>{{ $label }}</span>
                            </label>
                            <label class="rotulo" for="priority-{{ $code }}">Justificativa curta se marcado</label>
                            <textarea class="entrada" id="priority-{{ $code }}" name="priority_reasons[{{ $code }}]" rows="2" maxlength="200">{{ old('priority_reasons.'.$code) }}</textarea>
                        </div>
                    @endforeach
                </fieldset>

                @if (session('waitlist_duplicate_warning'))
                    <label class="lista-espera-confirmacao">
                        <input type="checkbox" name="confirm_duplicate" value="1">
                        <span>Conferi o possível pedido repetido e confirmo que este novo protocolo é necessário.</span>
                    </label>
                @endif

                <div class="formulario-acoes">
                    <button class="botao" type="submit">Registrar pedido e gerar protocolo</button>
                    <a class="botao secundario" href="{{ route('waitlist.index', ['municipio' => $municipality->slug]) }}">Cancelar</a>
                </div>
            </form>
        </div>
    </section>
    <script type="module" src="{{ asset('js/student-postal-code.js') }}?v={{ filemtime(public_path('js/student-postal-code.js')) }}"></script>
    <script defer src="{{ asset('js/lista-espera-etapa.js') }}?v={{ filemtime(public_path('js/lista-espera-etapa.js')) }}"></script>
@endsection
