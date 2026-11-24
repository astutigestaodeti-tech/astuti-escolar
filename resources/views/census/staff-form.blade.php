@extends('layouts.municipal')

@php
    use App\Models\Tenant\StaffCensus;
    use App\Http\Controllers\StudentRecordController;

    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $nacionalidade = old('nacionalidade', $record?->nationality);
    $nascimentoUf = old('nascimento_uf', $birthMunicipality['uf'] ?? null);
    $nascimentoMunicipio = old('nascimento_municipio', $birthMunicipality['nome'] ?? null);
    $escolaridade = (string) old('escolaridade', $record?->education_level);
    $cpf = old('cpf', $record?->cpf ? \App\Validation\Cpf::normalize($record->cpf) : null);
    $condicoes = old('condicoes', $record?->conditions ?? []);
    $cursos = old('cursos', $record?->higher_courses ?? []);
    $complementacao = array_map('strval', old('complementacao', $record?->pedagogical_areas ?? []));
    $pos = old('pos', $record?->postgraduates ?? []);
    $linhasPos = min(StaffCensus::MAX_POSTGRADUATES, max(3, count($pos) + 1));
    $formacao = old('formacao', $record === null || $record->continuing_education === null ? [] : ($record->continuing_education === [] ? ['nenhum'] : $record->continuing_education));
    $cursosPorArea = collect($courses)->groupBy('area');
    $conferencia = function (?string $nome, ?string $uf) use ($tabelas): ?string {
        if (blank($nome)) {
            return null;
        }
        $codigo = $tabelas->municipioPorNome($nome, $uf);
        if ($codigo === null) {
            return 'Não está na tabela de municípios do Inep para esta UF. Confira a grafia.';
        }
        $m = $tabelas->municipio($codigo);

        return 'Na tabela do Inep: '.$m['nome'].'/'.$m['uf'].', código '.$codigo.'.';
    };
@endphp

@section('title', 'Dados do Censo de '.$member->full_name)
@section('body-class', 'pagina-censo-turmas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/censo-turmas.css') }}?v={{ filemtime(public_path('css/censo-turmas.css')) }}">
@endpush

@section('trilha')
    <a href="{{ route('schools.show', $parametros) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.staff.index', $parametros) }}">Servidores</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Dados do Censo</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Censo Escolar</span>
            <h1>Dados do Censo de {{ $member->full_name }}</h1>
            <p>O que o Educacenso pede de gestores e profissionais em sala. Cursos, instituições e municípios são conferidos nas tabelas oficiais do Inep.</p>
        </div>
        <a class="botao secundario" href="{{ route('school.staff.index', $parametros) }}">Voltar aos servidores</a>
    </div>

    @if ($errors->any())
        <div class="erro" role="alert">
            <x-icon name="alerta" :size="18" />
            <div><strong>Revise o formulário</strong><ul>@foreach ($errors->all() as $mensagem)<li>{{ $mensagem }}</li>@endforeach</ul></div>
        </div>
    @endif

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif
    @if ($managerEligible)
        <form method="POST" action="{{ route('school.staff.census.manager', [...$parametros, 'servidor' => $member->id]) }}">
            @csrf
            @method('PUT')
            <section class="folha" aria-labelledby="titulo-gestao">
                <div class="folha-cabecalho"><div><h2 id="titulo-gestao">Gestão escolar</h2><p>Registro 40. Confirme o cargo; critério e situação funcional só quando se aplicam.</p></div></div>
                <div class="folha-corpo">
                    <div class="formulario-grade">
                        @foreach (['position' => 'Cargo', 'access_criterion' => 'Critério de acesso ao cargo', 'employment_status' => 'Situação funcional'] as $campo => $rotulo)
                            <div class="formulario-campo">
                                <label class="rotulo" for="gestao-{{ $campo }}">{{ $rotulo }}</label>
                                <select class="entrada" id="gestao-{{ $campo }}" name="{{ $campo }}">
                                    <option value="">Não informado / não se aplica</option>
                                    @foreach ($managerOptions[$campo] as $codigo => $nome)
                                        <option value="{{ $codigo }}" @selected((string) old($campo, $manager?->{$campo}) === (string) $codigo)>{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <ul class="censo-pendencias">
                        @foreach ($managerRegistro->pendencias as $p)
                            <li>{{ $p->recusa() ? 'Pendência' : 'Aviso' }} (campo {{ $p->campo }}): {{ $p->mensagem }}</li>
                        @endforeach
                    </ul>
                    <div class="formulario-acoes censo-secao">
                        <button class="botao" type="submit">Salvar gestão escolar</button>
                    </div>
                </div>
            </section>
        </form>
    @endif

    <form method="POST" action="{{ route('school.staff.census.update', [...$parametros, 'servidor' => $member->id]) }}" novalidate>
        @csrf
        @method('PUT')

        <section class="folha" aria-labelledby="titulo-identificacao">
            <div class="folha-cabecalho"><div><h2 id="titulo-identificacao">Identificação</h2><p>O CPF é obrigatório para gestor e para profissional brasileiro em sala. Com CPF, o Inep confere nome, nascimento e filiação na Receita Federal.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="cpf">CPF</label>
                        <input class="entrada" id="cpf" name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" value="{{ $cpf }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="nascimento">Data de nascimento</label>
                        <input class="entrada" id="nascimento" name="nascimento" type="date" value="{{ old('nascimento', $record?->birth_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="sexo">Sexo *</label>
                        <select class="entrada" id="sexo" name="sexo" required>
                            <option value="">Escolha</option>
                            @foreach (StudentRecordController::SEX_LABELS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('sexo', $record?->sex) === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="cor">Cor/raça *</label>
                        <select class="entrada" id="cor" name="cor" required>
                            <option value="">Escolha</option>
                            @foreach (StudentRecordController::RACE_LABELS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('cor', $record?->race_color) === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="povo_indigena">Povo indígena</label>
                        <select class="entrada" id="povo_indigena" name="povo_indigena" aria-describedby="povo-ajuda">
                            <option value="">Não se aplica</option>
                            @foreach ($peoples as $codigo => $nome)
                                <option value="{{ $codigo }}" @selected((string) old('povo_indigena', $record?->indigenous_people_code) === (string) $codigo)>{{ $nome }}</option>
                            @endforeach
                        </select>
                        <p class="texto-ajuda" id="povo-ajuda">Só é gravado com cor/raça indígena.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-filiacao">
            <div class="folha-cabecalho"><div><h2 id="titulo-filiacao">Filiação</h2><p>Como está no documento de identidade.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="filiacao">Filiação *</label>
                        <select class="entrada" id="filiacao" name="filiacao" required>
                            <option value="">Escolha</option>
                            @foreach (\App\Models\Tenant\StudentCensus::FILIATION as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected((string) old('filiacao', $record?->filiation) === (string) $valor)>{{ $valor === 1 ? 'Filiação informada (mãe e/ou pai)' : $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="filiacao_1">Filiação 1 (de preferência, a mãe)</label>
                        <input class="entrada" id="filiacao_1" name="filiacao_1" maxlength="100" autocomplete="off" value="{{ old('filiacao_1', $record?->parent1_name) }}">
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="filiacao_2">Filiação 2 (de preferência, o pai)</label>
                        <input class="entrada" id="filiacao_2" name="filiacao_2" maxlength="100" autocomplete="off" value="{{ old('filiacao_2', $record?->parent2_name) }}">
                    </div>
                </div>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-nascimento">
            <div class="folha-cabecalho"><div><h2 id="titulo-nascimento">Nacionalidade e nascimento</h2><p>Município de nascimento só para quem nasceu no Brasil; país só para estrangeiro.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="nacionalidade">Nacionalidade *</label>
                        <select class="entrada" id="nacionalidade" name="nacionalidade" required>
                            <option value="">Escolha</option>
                            @foreach (StudentRecordController::NATIONALITY_LABELS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($nacionalidade === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="nascimento_uf">UF de nascimento</label>
                        <select class="entrada" id="nascimento_uf" name="nascimento_uf">
                            <option value="">Escolha</option>
                            @foreach ($states as $sigla => $nome)
                                <option value="{{ $sigla }}" @selected($nascimentoUf === $sigla)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="nascimento_municipio">Município de nascimento</label>
                        <input class="entrada" id="nascimento_municipio" name="nascimento_municipio" maxlength="120" autocomplete="off" value="{{ $nascimentoMunicipio }}" aria-describedby="nascimento-conferencia">
                        @if ($texto = $conferencia($nascimentoMunicipio, $nascimentoUf))
                            <p class="texto-ajuda" id="nascimento-conferencia">{{ $texto }}</p>
                        @endif
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="pais_nacionalidade">País de nacionalidade (estrangeiro)</label>
                        <select class="entrada" id="pais_nacionalidade" name="pais_nacionalidade">
                            <option value="">Escolha</option>
                            @foreach ($countries as $codigo => $nome)
                                @continue($codigo === \App\Models\Tenant\StudentCensus::BRASIL)
                                <option value="{{ $codigo }}" @selected((string) old('pais_nacionalidade', $record?->nationality === 'estrangeira' ? $record->nationality_country_code : null) === (string) $codigo)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-deficiencia">
            <div class="folha-cabecalho"><div><h2 id="titulo-deficiencia">Deficiência, TEA e altas habilidades</h2><p>Dado de saúde: aparece só nesta tela e a auditoria não guarda o conteúdo. Deixe em branco se o servidor não tem ou não declarou.</p></div></div>
            <div class="folha-corpo">
                <fieldset class="censo-opcoes">
                    <legend class="sr-only">Deficiência, TEA e altas habilidades</legend>
                    @foreach (\App\Models\Tenant\StudentSpecialEducation::CONDITIONS as $valor => $rotulo)
                        <label><input type="checkbox" name="condicoes[]" value="{{ $valor }}" @checked(in_array($valor, $condicoes, true))> {{ $rotulo }}</label>
                    @endforeach
                </fieldset>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-escolaridade">
            <div class="folha-cabecalho"><div><h2 id="titulo-escolaridade">Escolaridade</h2><p>Maior nível concluído. O tipo de ensino médio vale para quem concluiu o médio ou o superior.</p></div></div>
            <div class="folha-corpo">
                <div class="formulario-grade">
                    <div class="formulario-campo">
                        <label class="rotulo" for="escolaridade">Maior escolaridade concluída</label>
                        <select class="entrada" id="escolaridade" name="escolaridade">
                            <option value="">Escolha</option>
                            @foreach (StaffCensus::EDUCATION_LEVELS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($escolaridade === (string) $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="formulario-campo">
                        <label class="rotulo" for="ensino_medio">Tipo de ensino médio cursado</label>
                        <select class="entrada" id="ensino_medio" name="ensino_medio">
                            <option value="">Escolha</option>
                            @foreach (StaffCensus::HIGH_SCHOOL_TYPES as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected((string) old('ensino_medio', $record?->high_school_type) === (string) $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-cursos">
            <div class="folha-cabecalho"><div><h2 id="titulo-cursos">Cursos superiores concluídos</h2><p>Até 3, só com educação superior. A instituição é o código da Tabela de IES do Inep (o mesmo do e-MEC).</p></div></div>
            <div class="folha-corpo">
                @for ($i = 0; $i < StaffCensus::MAX_COURSES; $i++)
                    @php($curso = $cursos[$i] ?? [])
                    <div class="formulario-grade censo-tres censo-linha">
                        <div class="formulario-campo">
                            <label class="rotulo" for="curso_{{ $i }}">Curso {{ $i + 1 }}</label>
                            <select class="entrada" id="curso_{{ $i }}" name="cursos[{{ $i }}][curso]">
                                <option value="">Nenhum</option>
                                @foreach ($cursosPorArea as $area => $lista)
                                    <optgroup label="{{ $area }}">
                                        @foreach ($lista as $item)
                                            <option value="{{ $item['codigo'] }}" @selected(($curso['curso'] ?? null) === $item['codigo'])>{{ $item['nome'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="curso_ano_{{ $i }}">Ano de conclusão</label>
                            <input class="entrada" id="curso_ano_{{ $i }}" name="cursos[{{ $i }}][ano]" inputmode="numeric" maxlength="4" value="{{ $curso['ano'] ?? '' }}">
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="curso_ies_{{ $i }}">Código da instituição (IES)</label>
                            <input class="entrada" id="curso_ies_{{ $i }}" name="cursos[{{ $i }}][ies]" inputmode="numeric" maxlength="7" value="{{ $curso['ies'] ?? '' }}" aria-describedby="curso_ies_nome_{{ $i }}">
                            @if (filled($curso['ies'] ?? null))
                                @php($ies = $tabelas->ies((int) $curso['ies']))
                                <p class="texto-ajuda" id="curso_ies_nome_{{ $i }}">{{ $ies ? 'Na tabela do Inep: '.$ies['nome'].'.' : 'Código fora da Tabela de IES do Inep.' }}</p>
                            @endif
                        </div>
                    </div>
                @endfor

                <fieldset class="censo-complementacao">
                    <legend class="rotulo">Complementação pedagógica (até 3 áreas)</legend>
                    <div class="formulario-grade censo-tres iguais">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="formulario-campo">
                                <label class="rotulo" for="complementacao_{{ $i }}">Área {{ $i + 1 }}</label>
                                <select class="entrada" id="complementacao_{{ $i }}" name="complementacao[{{ $i }}]">
                                    <option value="">Nenhuma</option>
                                    @foreach ($areas as $codigo => $nome)
                                        <option value="{{ $codigo }}" @selected(($complementacao[$i] ?? null) === (string) $codigo)>{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endfor
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-pos">
            <div class="folha-cabecalho"><div><h2 id="titulo-pos">Pós-graduação concluída</h2><p>Até 6. Salve para abrir mais linhas.</p></div></div>
            <div class="folha-corpo">
                @for ($i = 0; $i < $linhasPos; $i++)
                    @php($item = $pos[$i] ?? [])
                    <div class="formulario-grade censo-tres censo-linha">
                        <div class="formulario-campo">
                            <label class="rotulo" for="pos_tipo_{{ $i }}">Pós-graduação {{ $i + 1 }}</label>
                            <select class="entrada" id="pos_tipo_{{ $i }}" name="pos[{{ $i }}][tipo]">
                                <option value="">Nenhuma</option>
                                @foreach (StaffCensus::POSTGRADUATE_TYPES as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected((string) ($item['tipo'] ?? '') === (string) $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="pos_area_{{ $i }}">Área</label>
                            <select class="entrada" id="pos_area_{{ $i }}" name="pos[{{ $i }}][area]">
                                <option value="">Escolha</option>
                                @foreach ($postgraduateAreas as $codigo => $nome)
                                    <option value="{{ $codigo }}" @selected((string) ($item['area'] ?? '') === (string) $codigo)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="formulario-campo">
                            <label class="rotulo" for="pos_ano_{{ $i }}">Ano de conclusão</label>
                            <input class="entrada" id="pos_ano_{{ $i }}" name="pos[{{ $i }}][ano]" inputmode="numeric" maxlength="4" value="{{ $item['ano'] ?? '' }}">
                        </div>
                    </div>
                @endfor
                <fieldset class="censo-opcoes censo-linha">
                    <legend class="sr-only">Sem pós-graduação</legend>
                    <label><input type="checkbox" name="sem_pos" value="1" @checked((bool) old('sem_pos', $record?->no_postgraduate))> Não tem pós-graduação concluída</label>
                </fieldset>
            </div>
        </section>

        <section class="folha censo-secao" aria-labelledby="titulo-formacao">
            <div class="folha-cabecalho"><div><h2 id="titulo-formacao">Formação continuada</h2><p>Áreas em que o servidor concluiu formação continuada. Nada marcado: não informada.</p></div></div>
            <div class="folha-corpo">
                <fieldset class="censo-opcoes">
                    <legend class="sr-only">Formação continuada</legend>
                    @foreach (StaffCensus::CONTINUING_EDUCATION as $valor => $item)
                        <label><input type="checkbox" name="formacao[]" value="{{ $valor }}" @checked(in_array($valor, $formacao, true))> {{ $item['rotulo'] }}</label>
                    @endforeach
                    <label><input type="checkbox" name="formacao[]" value="nenhum" @checked(in_array('nenhum', $formacao, true))> Nenhum</label>
                </fieldset>
            </div>
        </section>

        <div class="formulario-acoes censo-secao">
            <p class="texto-ajuda">Gravar pede o código do segundo fator. A auditoria registra quais campos mudaram, nunca o conteúdo.</p>
            <button class="botao" type="submit">Salvar dados do Censo</button>
        </div>
    </form>
@endsection
