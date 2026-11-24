@extends('layouts.municipal')

@section('title', 'Dados da Prefeitura')
@section('body-class', 'pagina-dados-prefeitura')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/dados-prefeitura.css') }}?v={{ filemtime(public_path('css/dados-prefeitura.css')) }}">
@endpush

@php
    $campo = fn (string $nome, mixed $padrao = null) => old($nome, $padrao ?? $perfil->{$nome});
    $faltam = $perfil->missing();
@endphp

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Gestão municipal</span><h1>Dados da Prefeitura</h1>
            <p>Estes dados formam o timbre e o rodapé de todos os documentos que o sistema emite. Preencha uma vez; as escolas não precisam digitar nada.</p></div>
    </div>

    @if (session('status'))<div class="status-sucesso" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="erro" role="alert"><x-icon name="alerta" :size="18" /><span>Confira os campos marcados. {{ $errors->first() }}</span></div>@endif

    <div class="dados-prefeitura-grade">
        <form method="POST" action="{{ route('institution.update', ['municipio' => $municipality->slug]) }}" id="form-prefeitura" novalidate>
            @csrf
            <section class="folha" aria-labelledby="titulo-identificacao">
                <div class="folha-cabecalho"><div><h2 id="titulo-identificacao">Identificação</h2><p>O nome da Prefeitura vem do cadastro da rede: {{ $municipality->legal_name }}.</p></div></div>
                <div class="folha-corpo formulario-grade">
                    <div class="formulario-campo largo">
                        <label class="rotulo" for="department_name">Órgão que emite os documentos *</label>
                        <input class="entrada" id="department_name" name="department_name" maxlength="120" required value="{{ $campo('department_name') }}" data-previa="orgao" @error('department_name') aria-invalid="true" @enderror>
                        @error('department_name')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="cnpj">CNPJ</label>
                        <input class="entrada numero" id="cnpj" name="cnpj" inputmode="numeric" maxlength="18" placeholder="00.000.000/0000-00" value="{{ old('cnpj', $perfil->formattedCnpj()) }}" data-previa="cnpj" data-mascara="cnpj" aria-describedby="cnpj-ajuda" @error('cnpj') aria-invalid="true" @enderror>
                        <p class="texto-ajuda" id="cnpj-ajuda" data-cnpj-status>Da Prefeitura ou do Fundo Municipal de Educação. Conferimos os dígitos enquanto você digita.</p>
                        @error('cnpj')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="folha" aria-labelledby="titulo-contato">
                <div class="folha-cabecalho"><div><h2 id="titulo-contato">Contato</h2><p>Aparece no rodapé, para quem recebe o documento saber a quem recorrer.</p></div></div>
                <div class="folha-corpo formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="phone">Telefone com DDD</label>
                        <input class="entrada numero" id="phone" name="phone" type="tel" inputmode="tel" maxlength="16" placeholder="(11) 4000-1234" value="{{ old('phone', $perfil->formattedPhone()) }}" data-previa="telefone" data-mascara="telefone" @error('phone') aria-invalid="true" @enderror>
                        @error('phone')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="email">E-mail da Secretaria</label>
                        <input class="entrada" id="email" name="email" type="email" maxlength="160" autocomplete="off" placeholder="educacao@prefeitura.sp.gov.br" value="{{ $campo('email') }}" data-previa="email" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulario-campo largo">
                        <label class="rotulo" for="website">Site</label>
                        <input class="entrada" id="website" name="website" maxlength="160" inputmode="url" placeholder="www.prefeitura.sp.gov.br" value="{{ old('website', $perfil->displayWebsite()) }}" data-previa="site" @error('website') aria-invalid="true" @enderror>
                        <p class="texto-ajuda">Pode digitar sem "https://"; no papel ele aparece sem o prefixo.</p>
                        @error('website')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="folha" aria-labelledby="titulo-endereco">
                <div class="folha-cabecalho"><div><h2 id="titulo-endereco">Endereço da Secretaria</h2><p>Comece pelo CEP: o sistema sugere rua, bairro, cidade e estado.</p></div></div>
                <div class="folha-corpo" data-postal-lookup-url="{{ route('waitlist.postal.lookup', ['municipio' => $municipality->slug, 'cep' => '__CEP__']) }}">
                    <div class="formulario-grade">
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="postal_code">CEP</label>
                            <div class="cep-busca">
                                <input class="entrada numero" id="postal_code" name="postal_code" maxlength="9" inputmode="numeric" autocomplete="postal-code" placeholder="00000-000" value="{{ $campo('postal_code') }}" data-previa="endereco" @error('postal_code') aria-invalid="true" @enderror>
                                <button type="button" class="botao secundario" data-postal-search>Buscar CEP</button>
                            </div>
                            <p class="texto-ajuda" data-postal-status role="status" aria-live="polite"></p>
                            @error('postal_code')<p class="campo-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulario-campo largo">
                            <label class="rotulo" for="address_street">Logradouro</label>
                            <input class="entrada" id="address_street" name="address_street" maxlength="180" value="{{ $campo('address_street') }}" data-previa="endereco">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_number">Número</label>
                            <input class="entrada" id="address_number" name="address_number" maxlength="20" value="{{ $campo('address_number') }}" data-previa="endereco">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_complement">Complemento</label>
                            <input class="entrada" id="address_complement" name="address_complement" maxlength="120" value="{{ $campo('address_complement') }}" data-previa="endereco">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="address_district">Bairro</label>
                            <input class="entrada" id="address_district" name="address_district" maxlength="120" value="{{ $campo('address_district') }}" data-previa="endereco">
                        </div>
                        <div class="formulario-campo dados-prefeitura-cidade">
                            <div>
                                <label class="rotulo" for="address_city">Cidade</label>
                                <input class="entrada" id="address_city" name="address_city" maxlength="120" value="{{ $campo('address_city') }}" data-previa="endereco">
                            </div>
                            <div>
                                <label class="rotulo" for="address_state">UF</label>
                                <select class="entrada" id="address_state" name="address_state" data-previa="endereco" @error('address_state') aria-invalid="true" @enderror>
                                    <option value=""></option>
                                    @foreach ($states as $sigla => $nome)<option value="{{ $sigla }}" @selected($campo('address_state') === $sigla)>{{ $sigla }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="folha" aria-labelledby="titulo-assinatura">
                <div class="folha-cabecalho"><div><h2 id="titulo-assinatura">Assinatura da Secretaria</h2><p>Usada no relatório mensal enviado ao prefeito e à Câmara.</p></div></div>
                <div class="folha-corpo formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="secretary_name">Nome de quem assina</label>
                        <input class="entrada" id="secretary_name" name="secretary_name" maxlength="160" autocomplete="off" value="{{ $campo('secretary_name') }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="secretary_title">Cargo</label>
                        <input class="entrada" id="secretary_title" name="secretary_title" maxlength="120" placeholder="Secretária Municipal de Educação" value="{{ $campo('secretary_title') }}">
                    </div>
                </div>
            </section>

            <div class="formulario-acoes dados-prefeitura-salvar">
                <button class="botao" type="submit">Salvar dados da Prefeitura</button>
                <p class="texto-ajuda">Salvar pede o segundo fator recente e fica registrado na auditoria.</p>
            </div>
        </form>

        <aside class="dados-prefeitura-previa" aria-labelledby="titulo-previa">
            <section class="folha">
                <div class="folha-cabecalho"><div><h2 id="titulo-previa">Prévia do papel</h2><p>Atualiza enquanto você digita.</p></div></div>
                <div class="folha-corpo">
                    <div class="previa-papel" aria-hidden="true">
                        <x-documento.timbre :municipality="$municipality" :perfil="$perfil" school-name="EMEF exemplo da rede" school-inep="00000000" />
                        <div class="previa-linhas"><span></span><span></span><span></span><span class="curta"></span></div>
                        <footer class="doc-rodape" data-previa-rodape>
                            <p data-previa-saida="endereco">{{ $perfil->addressLine() ? $perfil->department_name.': '.$perfil->addressLine() : '' }}</p>
                            <p class="doc-rodape-contatos" data-previa-saida="contatos">@foreach (array_filter([$perfil->formattedCnpj() ? 'CNPJ '.$perfil->formattedCnpj() : null, $perfil->formattedPhone() ? 'Telefone '.$perfil->formattedPhone() : null, $perfil->email, $perfil->displayWebsite()]) as $contato)<span>{{ $contato }}</span>@endforeach</p>
                        </footer>
                    </div>
                    @if ($faltam !== [])
                        <p class="doc-pendencia" data-previa-pendencia><x-icon name="info" :size="16" /><span>Ainda faltam: {{ implode(', ', $faltam) }}.</span></p>
                    @else
                        <p class="status-sucesso dados-prefeitura-completo"><x-icon name="conferir" :size="16" /> Rodapé completo.</p>
                    @endif
                    <h3 class="dados-prefeitura-usos-titulo">Onde este timbre aparece</h3>
                    <ul class="dados-prefeitura-usos">
                        <li>Declaração de matrícula</li>
                        <li>Histórico escolar</li>
                        <li>Boletim e ata de resultados finais</li>
                        <li>Encaminhamento ao Conselho Tutelar</li>
                        <li>Relatório mensal da Secretaria</li>
                    </ul>
                </div>
            </section>
        </aside>
    </div>

    <script type="module" src="{{ asset('js/student-postal-code.js') }}"></script>
    <script type="module" src="{{ asset('js/dados-prefeitura.js') }}"></script>
@endsection
