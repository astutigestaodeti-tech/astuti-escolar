@extends('layouts.municipal')

@section('title', $school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $school->name }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Unidade escolar</span>
            <h1>{{ $school->name }}</h1>
            <p>Dados da unidade, turmas e consulta de alunos.</p>
        </div>
        <span class="etiqueta ok">Ativa</span>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif

    @include('schools.today-items', ['items' => $todayItems, 'unit' => $school, 'heading' => 'Hoje na escola', 'sectionId' => 'hoje-na-escola'])

    <div class="ficha-escola">
        <section class="folha" aria-labelledby="titulo-dados">
            <div class="folha-cabecalho"><div><h2 id="titulo-dados">Dados da unidade</h2><p>Informações cadastradas pela Secretaria.</p></div></div>
            <dl>
                <div><dt>Nome</dt><dd>{{ $school->name }}</dd></div>
                <div><dt>Código INEP</dt><dd class="numero">{{ $school->inep_code ?: 'Não informado' }}</dd></div>
                <div><dt>Endereço</dt><dd>
                    {{ $school->addressLine() !== '' ? $school->addressLine() : 'Não informado' }}
                    @can('manageStudents', [$school, $municipality])
                        <small class="ficha-idade"><a href="{{ route('schools.geo.edit', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->addressLine() !== '' ? 'Alterar' : 'Informar' }}</a></small>
                    @endcan
                </dd></div>
                <div><dt>Situação</dt><dd>Em funcionamento</dd></div>
                <div><dt>Localização no mapa</dt><dd>
                    {{ $school->latitude !== null ? 'Marcada' : 'Não marcada' }}
                    @can('manageStudents', [$school, $municipality])
                        <small class="ficha-idade"><a href="{{ route('schools.geo.edit', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->latitude !== null ? 'Ajustar no mapa' : 'Marcar no mapa' }}</a></small>
                    @endcan
                </dd></div>
            </dl>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-turmas">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-turmas">Turmas</h2>
            <p>Turmas ativas da escola, com etapa, turno e vagas.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.classes.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar turmas desta escola <x-icon name="seta" :size="16" /></a>
        </section>

        @if (Gate::allows('viewNetworkPanel', [\App\Models\Tenant\School::class, $municipality]) || app(\App\Academics\PedagogicalAccess::class)->directs(auth()->user(), $municipality, $school))
            <section class="folha cartao" aria-labelledby="titulo-gestao-pedagogica">
                <div class="folha-corpo">
                    <span class="acao-icone"><x-icon name="indicadores" /></span>
                    <h2 id="titulo-gestao-pedagogica">Gestão pedagógica</h2>
                    <p>Conselhos de classe, distorção idade-série e aulas previstas e dadas.</p>
                    <a class="botao secundario" href="{{ route('pedagogical.school', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar gestão pedagógica</a>
                </div>
            </section>
        @endif

        @can('viewEquity', [$school, $municipality])
            <section class="folha cartao" aria-labelledby="titulo-equidade-escola">
                <div class="folha-corpo">
                    <span class="acao-icone"><x-icon name="indicadores" /></span>
                    <h2 id="titulo-equidade-escola">Equidade e VAAR</h2>
                    <p>Indicadores agregados da escola e das turmas, com proteção de grupos pequenos.</p>
                    <a class="botao secundario" href="{{ route('equity.vaar.school', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar indicadores</a>
                </div>
            </section>
        @endcan

        @can('manageStudents', [$school, $municipality])
            <section class="folha cartao" aria-labelledby="titulo-alimentacao-escola">
                <div class="folha-corpo">
                    <span class="acao-icone"><x-icon name="turmas" /></span>
                    <h2 id="titulo-alimentacao-escola">Alimentação escolar</h2>
                    <p>Cardápio da rede, registro diário das refeições e relatório para o PNAE.</p>
                    <a class="botao secundario" href="{{ route('school.meals.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar refeições</a>
                </div>
            </section>
            <section class="folha cartao" aria-labelledby="titulo-integral-escola">
                <span class="acao-icone"><x-icon name="turmas" /></span>
                <h2 id="titulo-integral-escola">Tempo integral</h2>
                <p>Veja as matrículas integrais, a jornada das turmas e a evolução desta unidade.</p>
                <a class="botao secundario" href="{{ route('integral.school', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar tempo integral</a>
            </section>
        @endcan

        <section class="folha cartao" aria-labelledby="titulo-calendario">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-calendario">Calendário escolar</h2>
            <p>Dias letivos previstos, feriados, recessos e reposições da escola e da rede.</p>
            <a class="botao secundario" href="{{ route('school.calendar.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar calendário <x-icon name="seta" :size="16" /></a>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-servidores">
            <span class="acao-icone"><x-icon name="alunos" /></span>
            <h2 id="titulo-servidores">Servidores</h2>
            <p>Equipe da escola e atribuição de professores às turmas.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.staff.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar servidores desta escola <x-icon name="seta" :size="16" /></a>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-alunos">
            <span class="acao-icone"><x-icon name="alunos" /></span>
            <h2 id="titulo-alunos">Alunos</h2>
            <p>Consulte os alunos com vínculo ativo nesta unidade escolar.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar alunos desta escola <x-icon name="seta" :size="16" /></a>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-saida">
            <span class="acao-icone"><x-icon name="alunos" /></span>
            <h2 id="titulo-saida">Saída de alunos</h2>
            <p>No portão: confira pela foto quem veio buscar e registre a entrega.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.exits.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Abrir saída de alunos <x-icon name="seta" :size="16" /></a>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-matriculas">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-matriculas">Matrículas</h2>
            <p>Consulte os vínculos ativos, anos letivos e datas de início desta escola.</p>
            <a class="botao secundario" style="margin-top:12px" href="{{ route('school.enrollments.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Consultar matrículas desta escola <x-icon name="seta" :size="16" /></a>
        </section>
        @can('manageStudents', [$school, $municipality])
            <section class="folha cartao" aria-labelledby="titulo-censo-escola">
                <div class="folha-corpo">
                    <span class="acao-icone"><x-icon name="indicadores" /></span>
                    <h2 id="titulo-censo-escola">Censo Escolar</h2>
                    <p>Confira os dados de identificação da unidade para o Educacenso e as pendências do registro 00.</p>
                    <a class="botao secundario" href="{{ route('school.census.school.edit', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Escola no Censo</a>
                </div>
            </section>
            <section class="folha cartao pendencias-resumo" aria-labelledby="titulo-pendencias">
                <span class="acao-icone"><x-icon name="alunos" /></span>
                <h2 id="titulo-pendencias">Pendências do cadastro</h2>
                @if ($pendencySummary['withPendencies'] === 0)
                    <p><strong>Cadastro completo.</strong> Nenhum aluno com dado a completar.</p>
                @else
                    <p><strong>{{ $pendencySummary['withPendencies'] }} {{ $pendencySummary['withPendencies'] === 1 ? 'aluno' : 'alunos' }}</strong> com dados a completar, de {{ $pendencySummary['total'] }} com matrícula ativa.</p>
                @endif
                <a class="botao secundario" style="margin-top:12px" href="{{ route('school.pendencies.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Ver o que falta completar <x-icon name="seta" :size="16" /></a>
            </section>
            <section class="folha cartao" aria-labelledby="titulo-alfabetizacao">
                <div class="folha-corpo">
                    <span class="acao-icone"><x-icon name="indicadores" /></span>
                    <h2 id="titulo-alfabetizacao">Alfabetização</h2>
                    <p>Sondagem de escrita e leitura do 1º e 2º ano e acompanhamento pedagógico.</p>
                    <a class="botao secundario" href="{{ route('school.literacy.panel', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Abrir alfabetização</a>
                </div>
            </section>
            <section class="folha cartao" aria-labelledby="titulo-busca-ativa">
                <div class="folha-corpo">
                    <span class="acao-icone"><x-icon name="indicadores" /></span>
                    <h2 id="titulo-busca-ativa">Busca ativa</h2>
                    <p>Acompanhe alertas de risco de abandono, contatos com famílias e encaminhamentos.</p>
                    <a class="botao secundario" href="{{ route('school.active-search.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Abrir busca ativa</a>
                </div>
            </section>
            <section class="folha cartao" aria-labelledby="titulo-sistema-presenca">
                <div class="folha-corpo">
                    <span class="acao-icone"><x-icon name="indicadores" /></span>
                    <h2 id="titulo-sistema-presenca">Sistema Presença</h2>
                    <p>Frequência de cada mês do período do Bolsa Família, para lançar no sistema do MEC.</p>
                    <a class="botao secundario" href="{{ route('school.presence.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Abrir Sistema Presença</a>
                </div>
            </section>
            <section class="folha cartao alerta-faltas-resumo" aria-labelledby="titulo-alerta-faltas">
                <span class="acao-icone"><x-icon name="info" /></span>
                <h2 id="titulo-alerta-faltas">Alerta de faltas</h2>
                <p><strong>{{ $absenceAlertCount }} {{ $absenceAlertCount === 1 ? 'aluno em alerta' : 'alunos em alerta' }}</strong> para acompanhamento da frequência e busca ativa.</p>
                <a class="botao secundario" style="margin-top:12px" href="{{ route('school.absences.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Ver alunos em alerta <x-icon name="seta" :size="16" /></a>
            </section>
            <section class="folha cartao" aria-labelledby="titulo-importar-alunos">
                <span class="acao-icone"><x-icon name="alunos" /></span>
                <h2 id="titulo-importar-alunos">Importar alunos</h2>
                <p>Envie uma planilha, confira repetições e grave os vínculos com auditoria.</p>
                <a class="botao secundario" style="margin-top:12px" href="{{ route('school.imports.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Importar alunos desta escola <x-icon name="seta" :size="16" /></a>
            </section>
            <section class="folha cartao" aria-labelledby="titulo-importar-transporte">
                <span class="acao-icone"><x-icon name="turmas" /></span>
                <h2 id="titulo-importar-transporte">Transporte escolar</h2>
                <p>{{ $transportUsers === 0 ? 'Nenhum aluno marcado como usuário do transporte.' : $transportUsers.' '.($transportUsers === 1 ? 'aluno usa' : 'alunos usam').' o transporte escolar.' }}</p>
                @if ($transportUsers > 0)
                    <a class="botao secundario" style="margin-top:12px" href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id, 'transporte' => 'usa']) }}">Ver alunos que usam transporte <x-icon name="seta" :size="16" /></a>
                @endif
                <a class="botao secundario" style="margin-top:12px" href="{{ route('school.transport.routes.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Transporte por rota <x-icon name="seta" :size="16" /></a>
                <a class="botao secundario" style="margin-top:12px" href="{{ route('school.imports.transport.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Importar lista de transporte <x-icon name="seta" :size="16" /></a>
            </section>
        @endcan
    </div>

    @can('manageStudents', [$school, $municipality])
        <div class="nota"><x-icon name="escudo" /><div><strong>Alterações protegidas</strong><p>Importações e alterações de alunos e matrículas pedem o código do segundo fator e ficam registradas no histórico.</p></div></div>
    @else
        <div class="nota"><x-icon name="info" /><div><strong>Somente consulta</strong><p>Sua conta pode consultar os dados desta escola, mas não alterar alunos e matrículas.</p></div></div>
    @endcan
@endsection
