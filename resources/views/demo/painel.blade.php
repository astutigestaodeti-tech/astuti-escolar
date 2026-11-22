<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#13243b">
    <title>{{ $screens[$screen] }} | Astuti Gestão Escolar | Monte Sereno</title>
    <link rel="preload" href="{{ asset('fonts/atkinson-hyperlegible-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/source-serif-4-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/astuti.css') }}?v={{ filemtime(public_path('css/astuti.css')) }}">
</head>
<body>
@php
    $navIcons = ['painel' => 'painel', 'escolas' => 'escola', 'alunos' => 'alunos', 'matriculas' => 'matriculas', 'importacoes' => 'importacoes', 'documentos' => 'documentos', 'indicadores' => 'indicadores'];
    $maiorEscola = max(array_column($schools, 'students'));
@endphp
<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<div class="app">
    <aside class="lateral" aria-label="Menu principal">
        <div class="municipio">
            <img class="municipio-brasao" src="{{ asset('images/brasao-monte-sereno.webp') }}" alt="Brasão de Monte Sereno" width="64" height="62">
            <div>
                <div class="municipio-nome">Prefeitura Municipal de Monte Sereno</div>
                <div class="municipio-orgao">Secretaria Municipal de Educação</div>
            </div>
        </div>

        <nav class="menu" aria-label="Telas de demonstração">
            <span class="menu-titulo">Gestão escolar</span>
            @foreach ($screens as $slug => $label)
                <a class="menu-item {{ $screen === $slug ? 'ativo' : '' }}" href="{{ route('demo', ['tela' => $slug]) }}" @if ($screen === $slug) aria-current="page" @endif>
                    <x-icon :name="$navIcons[$slug]" />
                    <span>{{ $label }}</span>
                    @if ($slug === 'importacoes')<span class="contador" aria-label="3 importações aguardando">3</span>@endif
                </a>
            @endforeach
        </nav>

        <div class="lateral-rodape">
            <x-astuti-marca />
            <p>Menos redigitação, mais tempo para a comunidade escolar.</p>
        </div>
    </aside>

    <div class="area">
        <header class="topo">
            <nav class="trilha" aria-label="Você está em">
                <a href="{{ route('demo') }}">Monte Sereno</a>
                <x-icon name="seta" :size="16" />
                <span aria-current="page">{{ $screens[$screen] }}</span>
            </nav>
            <div class="topo-acoes">
                <span class="ano-letivo"><x-icon name="calendario" :size="17" /><span>Ano letivo</span> <strong>2026</strong></span>
                <span class="usuario">
                    <span class="usuario-iniciais" aria-hidden="true">SE</span>
                    <span class="usuario-texto">Secretaria Escolar<small>Perfil de demonstração</small></span>
                </span>
            </div>
        </header>

        <main class="conteudo" id="conteudo">
            <div class="aviso-demo" role="note">
                <x-icon name="alerta" />
                <span><strong>Ambiente de demonstração.</strong> Município fictício, sem acesso a dados reais. <strong>Dados 100% fictícios.</strong></span>
                <span class="selo-demo">Demonstração</span>
            </div>

            @if ($screen === 'painel')
                <section class="rosto" aria-labelledby="titulo-rede">
                    <div class="rosto-texto">
                        <span class="rosto-ano">Ano letivo 2026 em andamento</span>
                        <h1 id="titulo-rede">Rede municipal de ensino de Monte Sereno</h1>
                        <p>O retrato do dia: matrículas, escolas e o que ainda espera a conferência da secretaria.</p>
                    </div>
                    <img class="rosto-brasao" src="{{ asset('images/brasao-monte-sereno.webp') }}" alt="" width="132" height="128">
                </section>

                <dl class="folha resumo" aria-label="Resumo da rede, com números fictícios">
                    <div class="resumo-item"><dt>Alunos atuais</dt><dd class="numero">1.284</dd><small>Exemplo ilustrativo</small></div>
                    <div class="resumo-item"><dt>Escolas cadastradas</dt><dd class="numero">4</dd><small>Distribuídas pela rede</small></div>
                    <div class="resumo-item"><dt>Turmas organizadas</dt><dd class="numero">48</dd><small>Educação infantil e fundamental</small></div>
                    <div class="resumo-item pendente"><dt>Registros para conferir</dt><dd class="numero">26</dd><a class="link" href="{{ route('demo', ['tela' => 'importacoes']) }}">Conferir pendências <x-icon name="seta" :size="16" /></a></div>
                </dl>

                <h2 class="secao-titulo">O que você precisa fazer hoje?</h2>
                <p class="secao-sub">As tarefas mais frequentes da secretaria, em um só lugar.</p>
                <ul class="folha acoes">
                    <li><a class="acao" href="{{ route('demo', ['tela' => 'alunos']) }}"><span class="acao-icone"><x-icon name="alunos" /></span><span><strong>Alunos atuais</strong><small>Localizar cadastros e informações</small></span><x-icon name="seta" class="acao-seta" /></a></li>
                    <li><a class="acao" href="{{ route('demo', ['tela' => 'importacoes']) }}"><span class="acao-icone"><x-icon name="importacoes" /></span><span><strong>Importar documentos</strong><small>Preparar arquivos para conferência</small></span><x-icon name="seta" class="acao-seta" /></a></li>
                    <li><a class="acao" href="{{ route('demo', ['tela' => 'importacoes']) }}"><span class="acao-icone"><x-icon name="conferir" /></span><span><strong>Conferir pendências</strong><small>Validar informações antes de gravar</small></span><x-icon name="seta" class="acao-seta" /></a></li>
                    <li><a class="acao" href="{{ route('demo', ['tela' => 'matriculas']) }}"><span class="acao-icone"><x-icon name="matriculas" /></span><span><strong>Matrículas</strong><small>Acompanhar a movimentação escolar</small></span><x-icon name="seta" class="acao-seta" /></a></li>
                </ul>

                <div class="colunas">
                    <section class="folha" aria-labelledby="titulo-escolas">
                        <div class="folha-cabecalho">
                            <div><h2 id="titulo-escolas">Escolas da rede</h2><p>As unidades municipais e seus alunos</p></div>
                            <a class="link" href="{{ route('demo', ['tela' => 'escolas']) }}">Ver todas <x-icon name="seta" :size="16" /></a>
                        </div>
                        <ul class="escolas-lista">
                            @foreach (array_slice($schools, 0, 3) as $school)
                                <li><span class="escola-sigla" aria-hidden="true">{{ $school['initial'] }}</span><span class="escola-nome"><strong>{{ $school['name'] }}</strong><small>{{ $school['type'] }}, {{ $school['area'] }}</small></span><span class="escola-total numero">{{ $school['students'] }} <small>alunos</small></span></li>
                            @endforeach
                        </ul>
                    </section>

                    <section class="folha" aria-labelledby="titulo-importacao">
                        <div class="folha-cabecalho">
                            <div><h2 id="titulo-importacao">Importação inteligente</h2><p>Como os documentos das escolas entram no sistema</p></div>
                            <span class="etiqueta neutra">Prévia</span>
                        </div>
                        <ol class="etapas">
                            <li><span class="etapa-numero">1</span><span><strong>Receber arquivos</strong><small>Word, PDF, Excel e imagens</small></span></li>
                            <li><span class="etapa-numero">2</span><span><strong>Extrair e organizar</strong><small>Dados sugeridos, ainda não aprovados</small></span></li>
                            <li><span class="etapa-numero">3</span><span><strong>Conferência da secretaria</strong><small>Nenhum registro é gravado sem validação</small></span></li>
                        </ol>
                        <div class="folha-nota"><a class="link" href="{{ route('demo', ['tela' => 'importacoes']) }}">Conhecer o fluxo de importação <x-icon name="seta" :size="16" /></a></div>
                    </section>
                </div>

            @elseif ($screen === 'escolas')
                <div class="folha-titulo">
                    <div><span class="contexto">Rede municipal</span><h1>Escolas de Monte Sereno</h1><p>Unidades que compõem a rede fictícia de ensino.</p></div>
                    <span class="marcador"><x-icon name="escola" :size="16" /> 4 unidades</span>
                </div>
                <div class="grade">
                    @foreach ($schools as $school)
                        <article class="folha cartao">
                            <div class="cartao-topo"><span class="escola-sigla" aria-hidden="true">{{ $school['initial'] }}</span><span class="etiqueta ok">Exemplo ativo</span></div>
                            <h2>{{ $school['name'] }}</h2>
                            <p>{{ $school['type'] }}, bairro {{ $school['area'] }}</p>
                            <div class="cartao-dados">
                                <div><strong class="numero">{{ $school['students'] }}</strong><span>alunos</span></div>
                                <div><strong class="numero">{{ $school['classes'] }}</strong><span>turmas</span></div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="secao-sub" style="margin-top:16px">Unidades fictícias para apresentação.</p>

            @elseif ($screen === 'alunos')
                <div class="folha-titulo">
                    <div><span class="contexto">Secretaria escolar</span><h1>Alunos atuais</h1><p>Consulte os registros escolares pelo nome do aluno ou da escola.</p></div>
                    <span class="marcador"><x-icon name="info" :size="16" /> Cadastro fictício</span>
                </div>
                <section class="folha" aria-labelledby="titulo-lista">
                    <div class="folha-cabecalho"><div><h2 id="titulo-lista">Lista de alunos</h2><p>Todos os nomes e registros nesta tela são inventados.</p></div></div>
                    <form method="GET" action="{{ route('demo') }}" class="busca" role="search">
                        <input type="hidden" name="tela" value="alunos">
                        <label for="student-search" class="sr-only">Buscar nome ou escola</label>
                        <span class="campo"><x-icon name="busca" :size="18" /><input id="student-search" name="busca" type="search" value="{{ $search }}" placeholder="Nome do aluno ou da escola"></span>
                        <button class="botao" type="submit">Pesquisar</button>
                    </form>
                    <div class="rolagem">
                        <table class="tabela">
                            <thead><tr><th scope="col">Aluno</th><th scope="col">Matrícula de exemplo</th><th scope="col">Escola</th><th scope="col">Turma</th><th scope="col">Situação</th></tr></thead>
                            <tbody>
                            @forelse ($students as $student)
                                <tr>
                                    <td><span class="pessoa"><span class="iniciais" aria-hidden="true">{{ $student['initial'] }}</span><strong>{{ $student['name'] }}</strong></span></td>
                                    <td class="codigo">{{ $student['id'] }}</td>
                                    <td>{{ $student['school'] }}</td>
                                    <td>{{ $student['class'] }}</td>
                                    <td><span class="etiqueta {{ $student['status'] === 'Ativa' ? 'ok' : 'conferir' }}">{{ $student['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="vazio">Nenhum aluno encontrado com "{{ $search }}". Confira a grafia ou pesquise pelo nome da escola.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="folha-nota">Exibição demonstrativa. Nenhum CPF ou dado pessoal verdadeiro.</div>
                </section>

            @elseif ($screen === 'matriculas')
                <div class="folha-titulo">
                    <div><span class="contexto">Ano letivo 2026</span><h1>Matrículas e movimentações</h1><p>Acompanhamento por escola, ano e turma.</p></div>
                    <span class="marcador"><x-icon name="matriculas" :size="16" /> 1.284 matrículas de exemplo</span>
                </div>
                <dl class="folha resumo indicadores-mini" aria-label="Situação das matrículas">
                    <div class="resumo-item"><dt>Ativas</dt><dd class="numero">1.243</dd><small>Situação regular</small></div>
                    <div class="resumo-item"><dt>Em conferência</dt><dd class="numero">26</dd><small>Aguardam a secretaria</small></div>
                    <div class="resumo-item"><dt>Movimentações</dt><dd class="numero">15</dd><small>Transferências e ajustes fictícios</small></div>
                </dl>
                <section class="folha" aria-labelledby="titulo-mov">
                    <div class="folha-cabecalho"><div><h2 id="titulo-mov">Movimentações recentes</h2><p>Cada mudança fica registrada, com origem e data.</p></div><span class="etiqueta neutra">Exemplos</span></div>
                    <div class="rolagem">
                        <table class="tabela">
                            <thead><tr><th scope="col">Aluno fictício</th><th scope="col">Escola</th><th scope="col">Turma</th><th scope="col">Evento</th><th scope="col">Situação</th></tr></thead>
                            <tbody>
                            @foreach ($students as $student)
                                <tr><td><strong>{{ $student['name'] }}</strong></td><td>{{ $student['school'] }}</td><td>{{ $student['class'] }}</td><td>Registro de matrícula</td><td><span class="etiqueta {{ $student['status'] === 'Ativa' ? 'ok' : 'conferir' }}">{{ $student['status'] }}</span></td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

            @elseif ($screen === 'importacoes')
                <div class="folha-titulo">
                    <div><span class="contexto">Menos digitação</span><h1>Importação inteligente</h1><p>Da digitalização à conferência, sem perder a origem de cada informação.</p></div>
                    <span class="marcador"><x-icon name="info" :size="16" /> Simulação visual</span>
                </div>
                <section class="folha" aria-labelledby="titulo-etapas">
                    <div class="folha-cabecalho"><div><h2 id="titulo-etapas">Etapas da importação</h2><p>O caminho de cada documento enviado pelas escolas.</p></div><span class="etiqueta neutra">Prévia do produto</span></div>
                    <ol class="etapas-linha">
                        <li data-n="1"><strong>Receber</strong><small>Arquivo original guardado</small></li>
                        <li data-n="2"><strong>Reconhecer</strong><small>Extrair informações</small></li>
                        <li data-n="3"><strong>Conferir</strong><small>Conferência humana</small></li>
                        <li data-n="4"><strong>Validar</strong><small>A secretaria aprova</small></li>
                    </ol>
                    <div class="envio">
                        <span class="envio-icone"><x-icon name="importacoes" :size="24" /></span>
                        <h3>Envie os arquivos da escola, sem redigitar</h3>
                        <p>Planilhas Excel, documentos Word, PDF e imagens digitalizadas.</p>
                        <div class="formatos"><span>XLSX</span><span>CSV</span><span>DOCX</span><span>PDF</span><span>JPG</span><span>PNG</span></div>
                        <button class="botao" type="button" disabled title="Função disponível em uma etapa futura">Selecionar arquivos (em breve)</button>
                        <small class="envio-nota">Simulação visual. Nenhum arquivo será enviado ou armazenado.</small>
                    </div>
                </section>
                <div class="nota"><x-icon name="escudo" /><div><strong>O sistema nunca decide sozinho.</strong><p>Os documentos originais ficam vinculados ao que foi extraído. Dados duvidosos esperam a secretaria, e nenhum histórico é emitido antes da validação.</p></div></div>

            @elseif ($screen === 'documentos')
                <div class="folha-titulo">
                    <div><span class="contexto">Acervo escolar</span><h1>Documentos e históricos</h1><p>O arquivo escolar organizado por aluno e por ano.</p></div>
                    <span class="marcador"><x-icon name="acervo" :size="16" /> Arquivo fictício</span>
                </div>
                <ul class="folha documentos">
                    <li><span class="acao-icone"><x-icon name="ficha" /></span><div><strong>Fichas individuais</strong><p>Registro e conferência das informações encontradas nas fichas.</p><small>PDF, JPG e PNG</small></div></li>
                    <li><span class="acao-icone"><x-icon name="documentos" /></span><div><strong>Históricos escolares</strong><p>Reconstrução assistida e emissão somente após aprovação formal.</p><small>DOCX e PDF</small></div></li>
                    <li><span class="acao-icone"><x-icon name="acervo" /></span><div><strong>Livros e atas</strong><p>Preservação da origem dos registros antigos digitalizados.</p><small>PDF, JPG e PNG</small></div></li>
                </ul>
                <div class="nota"><x-icon name="conferir" /><div><strong>Emissão com rastreabilidade.</strong><p>O histórico emitido vai indicar as fontes usadas, quem conferiu e a data da validação. Esta função ainda está em desenvolvimento.</p></div></div>

            @else
                <div class="folha-titulo">
                    <div><span class="contexto">Gestão com base em informação</span><h1>Indicadores da rede</h1><p>Como acompanhar as escolas ao longo do ano letivo.</p></div>
                    <span class="marcador"><x-icon name="info" :size="16" /> Valores fictícios</span>
                </div>
                <section class="folha" aria-labelledby="titulo-ind">
                    <div class="folha-cabecalho"><div><h2 id="titulo-ind">Alunos por escola</h2><p>Distribuição demonstrativa do ano letivo de 2026.</p></div></div>
                    <ul class="barras">
                        @foreach ($schools as $school)
                            <li>
                                <div class="barra-rotulo"><strong>{{ $school['name'] }}</strong><span class="numero">{{ $school['students'] }} alunos</span></div>
                                <div class="barra-trilho" role="img" aria-label="{{ $school['students'] }} alunos"><div class="barra" style="width: {{ round($school['students'] / $maiorEscola * 100) }}%"></div></div>
                            </li>
                        @endforeach
                    </ul>
                </section>
                <div class="nota"><x-icon name="info" /><div><strong>Sem dados oficiais nesta prévia.</strong><p>As obrigações do Censo Escolar, FUNDEB, TCE-SP e IEG-M serão detalhadas em módulos próprios, com fontes e competências verificadas.</p></div></div>
            @endif

            <footer class="rodape"><span>© 2026 Astuti Gestão Escolar</span><span>Monte Sereno é um município fictício. Interface de demonstração local.</span></footer>
        </main>
    </div>
</div>
</body>
</html>
