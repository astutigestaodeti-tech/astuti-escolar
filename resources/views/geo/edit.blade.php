@extends('layouts.municipal')

@section('title', 'Localização no mapa')

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Localização no mapa</span>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <link rel="stylesheet" href="{{ asset('css/geo-endereco.css') }}?v={{ filemtime(public_path('css/geo-endereco.css')) }}">
@endpush

@section('content')
    @php
        $temPonto = $subject->latitude !== null;
        $ponto = $suggestion && $suggestion['found'] ? $suggestion : ($temPonto ? ['lat' => (float) $subject->latitude, 'lon' => (float) $subject->longitude] : null);
        $origem = $suggestion && $suggestion['found'] ? ($suggestion['source'] ?? 'endereco') : ($subject->geo_source ?? 'mapa');
        $porCoordenada = $suggestion && $suggestion['found'] && ($suggestion['source'] ?? null) === 'mapa';
        $soRua = $suggestion && $suggestion['found'] && ($suggestion['precision'] ?? null) === 'rua';
        $aproximado = $suggestion && $suggestion['found'] && in_array($suggestion['precision'] ?? null, ['bairro', 'cidade'], true);
    @endphp
    <div class="folha-titulo">
        <div>
            <span class="contexto">{{ $kind === 'aluno' ? 'Residência do aluno' : 'Endereço da escola' }}</span>
            <h1>Localização de {{ $subjectName }}</h1>
            <p>{{ $kind === 'aluno' ? 'Procure o endereço, cole as coordenadas do Google Maps ou clique no mapa. Arraste o marcador até a porta da casa e salve.' : 'Digite o CEP ou clique na escola no mapa: o que faltar se completa sozinho. Confira e salve uma vez só.' }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="status-erro" role="alert"><x-icon name="info" :size="18" /> {{ $errors->first() }}</div>
    @endif
    @if ($suggestion && ! $suggestion['found'] && ($suggestion['link'] ?? false))
        <div class="status-erro" role="status"><x-icon name="info" :size="18" /> Não consegui ler o ponto deste link do Google Maps. No Google Maps, clique com o botão direito no local, clique nos números que aparecem no alto (eles são copiados) e cole aqui.</div>
    @elseif ($aproximado)
        <div class="status-aviso" role="status"><x-icon name="info" :size="18" /> A rua ainda não está no mapa do OpenStreetMap, então mostrei {{ $suggestion['precision'] === 'bairro' ? 'o bairro' : 'a cidade' }}. Arraste o marcador até a {{ $kind === 'aluno' ? 'casa' : 'escola' }}{{ $kind === 'escola' ? ', ou use "Abrir Google Maps" e cole aqui os números do local,' : '' }} e salve.</div>
    @elseif ($suggestion && ! $suggestion['found'])
        <div class="status-erro" role="status"><x-icon name="info" :size="18" /> Não encontrei "{{ $suggestion['query'] }}" no mapa. Tente só a rua e a cidade, cole as coordenadas do Google Maps ou clique direto no mapa.</div>
    @endif

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif

    @if ($kind === 'escola')
        @php $ufs = \App\Address\BrazilianStates::NAMES; @endphp
        <section class="folha geo-endereco" aria-labelledby="titulo-endereco-escola">
            <div class="folha-cabecalho">
                <div>
                    <h2 id="titulo-endereco-escola">Endereço da escola</h2>
                    <p>{{ $school->addressLine() !== '' ? $school->addressLine() : 'Ainda não cadastrado. Digite o CEP ou marque a escola no mapa e use "Preencher pelo mapa".' }}</p>
                </div>
            </div>
            <div class="folha-corpo" data-postal-lookup-url="{{ route('school.students.postal.lookup', ['municipio' => $municipality->slug, 'escola' => $school->id, 'cep' => '__CEP__']) }}"
                 @isset($reverseUrl) data-reverse-url="{{ $reverseUrl }}" @endisset>
                <div class="geo-endereco-grade">
                    <div class="formulario-campo geo-cep">
                        <label class="rotulo" for="postal_code">CEP</label>
                        <div class="cep-busca">
                            <input class="entrada" id="postal_code" name="postal_code" form="form-geo" type="text" maxlength="9" inputmode="numeric" autocomplete="postal-code" placeholder="00000-000" value="{{ old('postal_code', $school->postal_code) }}">
                            <button type="button" class="botao secundario" data-postal-search>Buscar CEP</button>
                        </div>
                    </div>
                    <div class="formulario-campo geo-preencher">
                        <span class="rotulo">Ou pelo ponto marcado</span>
                        <button type="button" class="botao secundario" data-reverse-fill>Preencher pelo mapa</button>
                    </div>
                    <div class="formulario-campo geo-rua">
                        <label class="rotulo" for="address_street">Logradouro</label>
                        <input class="entrada" id="address_street" name="address_street" form="form-geo" maxlength="180" autocomplete="address-line1" value="{{ old('address_street', $school->address_street) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="address_number">Número</label>
                        <input class="entrada" id="address_number" name="address_number" form="form-geo" maxlength="20" value="{{ old('address_number', $school->address_number) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="address_complement">Complemento</label>
                        <input class="entrada" id="address_complement" name="address_complement" form="form-geo" maxlength="180" value="{{ old('address_complement', $school->address_complement) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="address_district">Bairro</label>
                        <input class="entrada" id="address_district" name="address_district" form="form-geo" maxlength="120" value="{{ old('address_district', $school->address_district) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="address_city">Cidade</label>
                        <input class="entrada" id="address_city" name="address_city" form="form-geo" maxlength="120" autocomplete="address-level2" value="{{ old('address_city', $school->address_city) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="address_state">UF</label>
                        <select class="entrada" id="address_state" name="address_state" form="form-geo">
                            <option value="">Não informado</option>
                            @foreach ($ufs as $sigla => $nomeUf)
                                <option value="{{ $sigla }}" @selected(old('address_state', $school->address_state) === $sigla)>{{ $sigla }} - {{ $nomeUf }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="texto-ajuda" data-postal-status role="status" aria-live="polite"></p>
            </div>
        </section>
    @endif

    @if ($kind === 'aluno')
        <section class="folha geo-endereco" aria-labelledby="titulo-endereco-aluno">
            <div class="folha-cabecalho">
                <div>
                    <h2 id="titulo-endereco-aluno">Endereço do cadastro</h2>
                    <p>{{ $addressText !== '' ? $addressText : 'Ainda não cadastrado. Você pode marcar a casa direto no mapa.' }}</p>
                </div>
                <a class="botao secundario" href="{{ route('school.students.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $subject->id]) }}#endereco">{{ $addressText !== '' ? 'Corrigir no cadastro' : 'Informar no cadastro' }}</a>
            </div>
            @if ($school->latitude !== null)
                <p class="texto-ajuda geo-legenda folha-corpo">O círculo azul no mapa é a {{ $school->name }}. O marcador é a casa do aluno.</p>
            @endif
        </section>
    @endif

    <section class="folha geo-folha" aria-labelledby="titulo-mapa">
        <h2 id="titulo-mapa" class="sr-only">Mapa</h2>
        <form class="busca geo-busca" method="GET" action="{{ url()->current() }}" role="search">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="procurar">Endereço, coordenadas ou link do Google Maps</label>
                <input id="procurar" type="search" name="procurar" maxlength="2000" value="{{ $suggestion['query'] ?? $addressText }}" placeholder="Endereço, coordenadas ou link do Google Maps" aria-describedby="procurar-dica">
            </div>
            <button class="botao secundario" type="submit"><x-icon name="busca" :size="16" /> Procurar no mapa</button>
            @if ($kind === 'escola')
                @php
                    $googleConsulta = $ponto ? sprintf('%.6F,%.6F', $ponto['lat'], $ponto['lon']) : ($school->addressLine() !== '' ? $school->addressLine() : trim($school->name.' '.($school->address_city ?? $municipality->name)));
                @endphp
                <a class="botao secundario" id="abrir-google-maps" target="_blank" rel="noopener noreferrer"
                   href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($googleConsulta) }}">Abrir Google Maps</a>
            @endif
        </form>
        <p class="texto-ajuda geo-dica" id="procurar-dica">{{ $kind === 'escola' ? '"Abrir Google Maps" abre o mesmo local numa nova aba. ' : '' }}No Google Maps, clique com o botão direito no local e clique nos números do alto (por exemplo, -23.31234, -46.12345); eles são copiados. Cole aqui e clique em Procurar. Também vale colar o link de "Compartilhar".</p>

        <div id="mapa-localizacao" class="geo-mapa" role="application" aria-label="Mapa para marcar a localização"
             data-tiles="{{ config('geo.tiles_url') }}" data-atribuicao="{{ config('geo.tiles_attribution') }}"
             @if ($ponto) data-lat="{{ $ponto['lat'] }}" data-lon="{{ $ponto['lon'] }}" @endif
             @if ($kind === 'aluno' && $school->latitude !== null) data-escola-lat="{{ $school->latitude }}" data-escola-lon="{{ $school->longitude }}" data-escola-nome="{{ $school->name }}" @endif></div>

        <form method="POST" action="{{ $saveUrl }}" class="geo-salvar" id="form-geo">
            @csrf
            <input type="hidden" name="latitude" id="latitude" value="{{ $ponto['lat'] ?? '' }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $ponto['lon'] ?? '' }}">
            <input type="hidden" name="geo_source" id="geo_source" value="{{ $origem }}">
            <p class="texto-ajuda" id="geo-situacao">{{ $porCoordenada ? 'Coordenadas reconhecidas. Confira o ponto no mapa e salve.' : ($aproximado ? 'Ponto aproximado. Arraste o marcador até o local exato e salve.' : ($soRua ? 'Encontrei a rua, mas o mapa não tem o número exato. Arraste o marcador até a porta da '.($kind === 'aluno' ? 'casa' : 'escola').' e salve.' : ($ponto ? 'Ponto marcado. Ajuste se precisar e salve.' : 'Nenhum ponto marcado ainda.'))) }}</p>
            <div class="formulario-acoes">
                <button class="botao" type="submit">{{ $kind === 'escola' ? 'Salvar endereço e localização' : 'Salvar localização' }}</button>
                <a class="botao secundario" href="{{ $backUrl }}">Cancelar</a>
                @if ($temPonto)
                    <button class="botao secundario geo-remover" type="submit" name="remover" value="1" formnovalidate>Remover localização</button>
                @endif
            </div>
        </form>
        @if ($kind === 'aluno')
            <p class="texto-ajuda geo-lgpd">A localização da casa é usada só para calcular a distância até a escola e planejar o transporte. Ela fica visível apenas para a secretaria e a gestão; professores veem só a distância.</p>
        @endif
    </section>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/mapa-localizacao.js') }}"></script>
    @if ($kind === 'escola')
        <script type="module" src="{{ asset('js/student-postal-code.js') }}?v={{ filemtime(public_path('js/student-postal-code.js')) }}"></script>
        <script src="{{ asset('js/endereco-pelo-mapa.js') }}?v={{ filemtime(public_path('js/endereco-pelo-mapa.js')) }}"></script>
    @endif
@endsection
