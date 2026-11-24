@extends('layouts.municipal')

@php
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $sugestoes = $census === null ? ['operating_status' => 1, 'administrative_dependency' => 3] : [];
    $valor = fn (string $campo) => (string) old($campo, $census?->{$campo} ?? ($sugestoes[$campo] ?? ''));
    $grupos = [
        'Funcionamento e localização' => [
            'operating_status' => ['Situação de funcionamento', [1 => 'Em atividade', 2 => 'Paralisada', 3 => 'Extinta']],
            'district_code' => ['Distrito do município', $districts],
            'location_zone' => ['Zona da escola', [1 => 'Urbana', 2 => 'Rural']],
            'differentiated_location' => ['Localização diferenciada', [1 => 'Área de assentamento', 2 => 'Terra indígena', 3 => 'Comunidade quilombola', 7 => 'Não está em área de localização diferenciada', 8 => 'Área onde se localizam povos e comunidades tradicionais']],
        ],
        'Vinculação e regulamentação' => [
            'administrative_dependency' => ['Dependência administrativa', [1 => 'Federal', 2 => 'Estadual', 3 => 'Municipal', 4 => 'Privada (fora do escopo desta versão)']],
            'regional_body_code' => ['Órgão regional de ensino', $regionalBodies],
            'regulation' => ['Regulamentação ou autorização (escola em atividade)', [0 => 'Não', 1 => 'Sim', 2 => 'Em tramitação']],
            'regulation_sphere' => ['Esfera administrativa do conselho ou órgão', [1 => 'Federal', 2 => 'Estadual', 3 => 'Municipal', 4 => 'Estadual e Municipal', 6 => 'Federal SETEC']],
            'linked_unit' => ['Unidade vinculada (escola em atividade)', [0 => 'Sem vínculo com outra instituição', 1 => 'Vinculada a escola de educação básica', 2 => 'Ofertante de educação superior']],
        ],
    ];
@endphp

@section('title', 'Escola no Censo - '.$school->name)
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Escola no Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Escola no Censo</h1>
            <p>Identificação da escola no registro 00 do Educacenso {{ $ano }}.</p>
        </div>
        <a class="botao secundario" href="{{ route('schools.show', $parametros) }}">Voltar à escola</a>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise o formulário</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @endif
    @if ($census === null)
        <div class="censo-sugestao" role="note">
            <x-icon name="info" :size="18" />
            <p>Primeiro preenchimento: situação em atividade e dependência municipal são sugestões. Confirme a zona (urbana ou rural) e os demais dados antes de salvar. Nada foi gravado ainda.</p>
        </div>
    @endif

    <section class="folha" aria-labelledby="titulo-cadastro">
        <div class="folha-cabecalho"><div><h2 id="titulo-cadastro">Dados do cadastro da escola</h2><p>Estes dados vêm da ficha da escola e do ano letivo de {{ $ano }}.</p></div></div>
        <div class="folha-corpo">
            <dl class="formulario-grade">
                <div><dt>Nome</dt><dd>{{ $school->name }}</dd></div>
                <div><dt>Código Inep</dt><dd>{{ $school->inep_code ?: 'Não informado' }}</dd></div>
                <div><dt>Endereço</dt><dd>{{ $school->addressLine() ?: 'Não informado' }}</dd></div>
                <div><dt>Município</dt><dd>{{ $school->address_city ?: 'Não informado' }} {{ $school->address_state }}</dd>
                    <dd class="texto-ajuda">{{ $municipioInep ? 'Na tabela do Inep: '.$municipioInep['nome'].'/'.$municipioInep['uf'].', código '.$municipioInep['codigo'].'.' : 'Não está na tabela de municípios do Inep. Confira a grafia e a UF no cadastro da escola.' }}</dd>
                </div>
                <div><dt>Início do ano letivo</dt><dd>{{ $year?->start_date?->format('d/m/Y') ?? 'Não informado' }}</dd></div>
                <div><dt>Fim do ano letivo</dt><dd>{{ $year?->end_date?->format('d/m/Y') ?? 'Não informado' }}</dd></div>
            </dl>
            <a class="botao secundario" href="{{ route('schools.geo.edit', $parametros) }}">Conferir endereço da escola</a>
        </div>
    </section>

    <form method="POST" action="{{ route('school.census.school.update', $parametros) }}" novalidate>
        @csrf
        @method('PUT')
        @foreach ($grupos as $titulo => $campos)
            <section class="folha censo-secao" aria-labelledby="grupo-{{ $loop->index }}">
                <div class="folha-cabecalho"><div><h2 id="grupo-{{ $loop->index }}">{{ $titulo }}</h2></div></div>
                <div class="folha-corpo">
                    <div class="formulario-grade">
                        @foreach ($campos as $campo => [$rotulo, $opcoes])
                            <div class="formulario-campo">
                                <label class="rotulo" for="{{ $campo }}">{{ $rotulo }}</label>
                                <select class="entrada" id="{{ $campo }}" name="{{ $campo }}">
                                    <option value="">Não informado / não se aplica</option>
                                    @if ($valor($campo) !== '' && ! array_key_exists($valor($campo), $opcoes))
                                        <option value="{{ $valor($campo) }}" selected>{{ $valor($campo) }} (fora da lista atual)</option>
                                    @endif
                                    @foreach ($opcoes as $codigo => $nome)
                                        <option value="{{ $codigo }}" @selected($valor($campo) === (string) $codigo)>{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        @if ($loop->last)
                            <div class="formulario-campo">
                                <label class="rotulo" for="host_school_code">Código Inep da escola sede (vínculo 1)</label>
                                <input class="entrada" id="host_school_code" name="host_school_code" maxlength="8" inputmode="numeric" value="{{ $valor('host_school_code') }}">
                            </div>
                            <div class="formulario-campo">
                                <label class="rotulo" for="ies_code">Código da IES (vínculo 2)</label>
                                <input class="entrada" id="ies_code" name="ies_code" maxlength="9" inputmode="numeric" value="{{ $valor('ies_code') }}">
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach

        <section class="folha censo-secao" aria-labelledby="titulo-contato">
            <div class="folha-cabecalho"><div><h2 id="titulo-contato">Contato institucional</h2><p>Use somente telefones e e-mail da escola, não de alunos ou servidores.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    @foreach (['phone_ddd' => ['DDD', 2], 'phone' => ['Telefone', 9], 'phone_other' => ['Outro telefone de contato', 9], 'email' => ['E-mail da escola', 100]] as $campo => [$rotulo, $limite])
                        <div class="formulario-campo">
                            <label class="rotulo" for="{{ $campo }}">{{ $rotulo }}</label>
                            <input class="entrada" id="{{ $campo }}" name="{{ $campo }}" maxlength="{{ $limite }}" inputmode="{{ $campo === 'email' ? 'email' : 'numeric' }}" value="{{ $valor($campo) }}">
                        </div>
                    @endforeach
                </div>
                <p class="texto-ajuda">DDD e telefones: só dígitos. O e-mail segue a lista mais restritiva de caracteres do leiaute do Inep.</p>
            </div>
        </section>
        <div class="formulario-acoes censo-secao">
            <p class="texto-ajuda">Gravar pede o segundo fator. A auditoria registra apenas quais campos foram alterados.</p>
            <button class="botao" type="submit">Salvar dados da escola no Censo</button>
        </div>
    </form>

    <section class="folha censo-secao" aria-labelledby="titulo-conferencia">
        <div class="folha-cabecalho"><div><h2 id="titulo-conferencia">Conferência do registro 00</h2><p>Resultado dos dados já gravados, sem gerar ou enviar arquivo.</p></div></div>
        <div class="folha-corpo">
            <p><strong>{{ $registro->pronto() ? 'Sem pendências impeditivas' : 'Escola ainda não pronta para o Educacenso' }}</strong></p>
            @if ($registro->pendencias !== [])
                <ul>
                    @foreach ($registro->pendencias as $pendencia)
                        <li>{{ $pendencia->recusa() ? 'Pendência' : 'Aviso' }}{{ $pendencia->campo !== null ? ' (campo '.$pendencia->campo.')' : '' }}: {{ $pendencia->mensagem }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endsection
