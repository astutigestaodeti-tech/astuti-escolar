<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#103d4d">
    <title>{{ $screens[$screen] }} | Astuti Escolar | Monte Sereno</title>
    <link rel="stylesheet" href="{{ asset('css/demo.css') }}">
</head>
<body>
@php
    $navIcons = ['painel' => '◫', 'escolas' => '▦', 'alunos' => '♙', 'matriculas' => '⇄', 'importacoes' => '↥', 'documentos' => '▤', 'indicadores' => '◴'];
@endphp

<div class="app-shell">
    <aside class="sidebar" aria-label="Menu principal">
        <div class="brand">
            <span class="brand-symbol" aria-hidden="true">A<span class="brand-dot">.</span></span>
            <span><strong>astuti<span class="brand-dot">.</span></strong><small>GESTÃO ESCOLAR</small></span>
        </div>

        <div class="side-group-label">AMBIENTE</div>
        <div class="municipal-choice">
            <img class="crest-mini" src="{{ asset('images/brasao-monte-sereno.webp') }}" alt="Brasão de Monte Sereno" width="44" height="43">
            <span><strong>Monte Sereno</strong><small>Rede municipal fictícia</small></span>
            <span class="chevron" aria-hidden="true">⌄</span>
        </div>

        <nav class="nav-list" aria-label="Telas de demonstração">
            <div class="side-group-label">GESTÃO ESCOLAR</div>
            @foreach ($screens as $slug => $label)
                <a class="nav-link {{ $screen === $slug ? 'is-active' : '' }}" href="{{ route('demo', ['tela' => $slug]) }}" @if ($screen === $slug) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">{{ $navIcons[$slug] }}</span>
                    <span>{{ $label }}</span>
                    @if ($slug === 'importacoes')<span class="nav-counter">3</span>@endif
                </a>
            @endforeach
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card"><span class="help-icon" aria-hidden="true">✦</span><strong>Mais tempo para educar.</strong><p>Menos redigitação, mais cuidado com a comunidade escolar.</p></div>
            <div class="sidebar-footer">Astuti Tecnologia <span>•</span> Protótipo 2026</div>
        </div>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <div class="breadcrumb"><span>Monte Sereno</span><span class="crumb-sep">/</span><strong>{{ $screens[$screen] }}</strong></div>
            <div class="top-actions"><span class="top-date">Ano letivo <strong>2026</strong></span><span class="top-avatar" title="Usuário fictício" aria-label="Usuário fictício">AS</span><span class="top-user">Secretaria Escolar<small>Perfil de demonstração</small></span></div>
        </header>

        <main class="main-content">
            <div class="demo-notice" role="note"><span class="notice-mark" aria-hidden="true">✳</span><span><strong>Ambiente de demonstração.</strong> Município fictício, sem acesso a dados reais. <strong>Dados 100% fictícios.</strong></span><span class="demo-badge">DEMO</span></div>
            <div class="municipal-identity" aria-label="Identidade municipal fictícia">
                <div class="municipal-lockup">
                    <img class="crest-header" src="{{ asset('images/brasao-monte-sereno.webp') }}" alt="Brasão de Monte Sereno" width="58" height="56">
                    <div>
                        <span class="municipal-eyebrow">IDENTIDADE MUNICIPAL FICTÍCIA</span>
                        <strong>Prefeitura Municipal de Monte Sereno</strong>
                        <small>Secretaria Municipal de Educação · Ano letivo 2026</small>
                    </div>
                </div>
                <span class="identity-year">REDE DE ENSINO · 2026</span>
            </div>

            @if ($screen === 'painel')
                <section class="welcome-block" aria-labelledby="welcome-title">
                    <div class="welcome-copy"><span class="eyebrow">ANO LETIVO 2026 · MONTE SERENO</span><h1 id="welcome-title">Bom dia, Secretaria! <span aria-hidden="true">☀</span></h1><p>Veja o que está acontecendo na rede municipal de ensino.</p></div>
                    <div class="year-tag"><span class="year-tag-icon" aria-hidden="true">◷</span><div><span>Ano letivo</span><strong>2026</strong></div><span class="year-active">Em andamento</span></div>
                </section>

                <section class="stats-grid" aria-label="Indicadores fictícios">
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon tone-blue" aria-hidden="true">♙</span><span class="stat-caption">REDE MUNICIPAL</span></div><strong class="stat-number">1.284</strong><span class="stat-label">Alunos atuais</span><span class="stat-foot good">↗ 4,2% <span>exemplo ilustrativo</span></span></article>
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon tone-peach" aria-hidden="true">▦</span><span class="stat-caption">UNIDADES</span></div><strong class="stat-number">04</strong><span class="stat-label">Escolas cadastradas</span><span class="stat-foot"><span>Distribuídas pela rede</span></span></article>
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon tone-purple" aria-hidden="true">◫</span><span class="stat-caption">ANO 2026</span></div><strong class="stat-number">48</strong><span class="stat-label">Turmas organizadas</span><span class="stat-foot"><span>Educação infantil e fundamental</span></span></article>
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon tone-amber" aria-hidden="true">◷</span><span class="stat-caption">ATENÇÃO</span></div><strong class="stat-number">26</strong><span class="stat-label">Registros para conferir</span><span class="stat-foot pending"><span>Exemplo de pendências</span></span></article>
                </section>

                <div class="section-header"><div><span class="eyebrow">ACESSO RÁPIDO</span><h2>O que você precisa fazer hoje?</h2><p>As tarefas mais frequentes da secretaria, em um só lugar.</p></div></div>
                <section class="quick-grid" aria-label="Ações principais">
                    <a href="{{ route('demo', ['tela' => 'alunos']) }}" class="quick-card"><span class="quick-icon tone-blue" aria-hidden="true">♙</span><span class="quick-text"><strong>Alunos atuais</strong><small>Localizar cadastros e informações</small></span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                    <a href="{{ route('demo', ['tela' => 'importacoes']) }}" class="quick-card"><span class="quick-icon tone-mint" aria-hidden="true">↥</span><span class="quick-text"><strong>Importar documentos</strong><small>Preparar arquivos para conferência</small></span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                    <a href="{{ route('demo', ['tela' => 'importacoes']) }}" class="quick-card"><span class="quick-icon tone-amber" aria-hidden="true">✓</span><span class="quick-text"><strong>Conferir pendências</strong><small>Validar informações antes de gravar</small></span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                    <a href="{{ route('demo', ['tela' => 'matriculas']) }}" class="quick-card"><span class="quick-icon tone-purple" aria-hidden="true">⇄</span><span class="quick-text"><strong>Matrículas</strong><small>Acompanhar a movimentação escolar</small></span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                </section>

                <section class="two-columns" aria-label="Resumo da rede">
                    <div class="panel"><div class="panel-header"><div><h3>Escolas da rede</h3><p>Visão rápida das unidades municipais</p></div><a href="{{ route('demo', ['tela' => 'escolas']) }}" class="text-link">Ver todas →</a></div>
                        <div class="school-mini-list">@foreach (array_slice($schools, 0, 3) as $school)<div class="school-mini"><span class="school-avatar tone-{{ $school['tone'] }}">{{ $school['initial'] }}</span><span class="mini-label"><strong>{{ $school['name'] }}</strong><small>{{ $school['area'] }} · {{ $school['type'] }}</small></span><span class="mini-number">{{ $school['students'] }} <small>alunos</small></span></div>@endforeach</div>
                    </div>
                    <div class="panel"><div class="panel-header"><div><h3>Importação inteligente</h3><p>Como será o processo de migração</p></div><span class="small-pill">Prévia</span></div><div class="process-steps"><div><span class="process-number is-done">1</span><span><strong>Receber arquivos</strong><small>Word, PDF, Excel e imagens</small></span></div><div><span class="process-number is-done">2</span><span><strong>Extrair e organizar</strong><small>Dados sugeridos, ainda não aprovados</small></span></div><div><span class="process-number">3</span><span><strong>Conferência da secretaria</strong><small>Nenhum registro é publicado sem validar</small></span></div></div><a class="inline-cta" href="{{ route('demo', ['tela' => 'importacoes']) }}">Conhecer o fluxo <span aria-hidden="true">→</span></a></div>
                </section>

            @elseif ($screen === 'escolas')
                <div class="page-heading"><div><span class="eyebrow">REDE MUNICIPAL</span><h1>Escolas de Monte Sereno</h1><p>Unidades que compõem a rede fictícia de ensino.</p></div><span class="count-chip">04 unidades</span></div>
                <div class="schools-grid">@foreach ($schools as $school)<article class="school-card"><div class="school-card-top"><span class="school-avatar large tone-{{ $school['tone'] }}">{{ $school['initial'] }}</span><span class="status-dot"><span aria-hidden="true">●</span> Exemplo ativo</span></div><h2>{{ $school['name'] }}</h2><p>{{ $school['type'] }} · Bairro {{ $school['area'] }}</p><div class="school-card-stats"><div><strong>{{ $school['students'] }}</strong><span>Alunos</span></div><div><strong>{{ $school['classes'] }}</strong><span>Turmas</span></div></div><span class="card-bottom-note">Unidade fictícia para apresentação</span></article>@endforeach</div>

            @elseif ($screen === 'alunos')
                <div class="page-heading"><div><span class="eyebrow">SECRETARIA ESCOLAR</span><h1>Alunos atuais</h1><p>Exemplos de como consultar os registros escolares com rapidez.</p></div><span class="count-chip">Cadastro fictício</span></div>
                <section class="panel table-panel"><div class="panel-header"><div><h3>Lista de alunos</h3><p>Todos os nomes e registros nesta tela são inventados.</p></div></div><form method="GET" action="{{ route('demo') }}" class="search-bar"><input type="hidden" name="tela" value="alunos"><label for="student-search" class="sr-only">Buscar nome ou escola</label><input id="student-search" name="busca" type="search" value="{{ $search }}" placeholder="Pesquisar nome ou escola..."><button type="submit">Pesquisar</button></form><div class="responsive-table"><table><thead><tr><th>Aluno</th><th>Matrícula de exemplo</th><th>Escola</th><th>Turma</th><th>Situação</th></tr></thead><tbody>@forelse ($students as $student)<tr><td><div class="student-cell"><span class="student-avatar">{{ $student['initial'] }}</span><strong>{{ $student['name'] }}</strong></div></td><td class="monospace">{{ $student['id'] }}</td><td>{{ $student['school'] }}</td><td>{{ $student['class'] }}</td><td><span class="table-status {{ $student['status'] === 'Ativa' ? 'active' : 'review' }}">{{ $student['status'] }}</span></td></tr>@empty<tr><td colspan="5" class="empty-cell">Nenhum exemplo encontrado para esta busca.</td></tr>@endforelse</tbody></table></div><div class="table-footer">Exibição demonstrativa. Nenhum CPF ou dado pessoal verdadeiro.</div></section>

            @elseif ($screen === 'matriculas')
                <div class="page-heading"><div><span class="eyebrow">ANO LETIVO 2026</span><h1>Matrículas e movimentações</h1><p>Acompanhamento centralizado por escola, ano e turma.</p></div><span class="count-chip">1.284 matrículas de exemplo</span></div>
                <div class="summary-row"><div class="small-stat"><span>Ativas</span><strong>1.243</strong><small class="good">Exemplo de situação regular</small></div><div class="small-stat"><span>Em conferência</span><strong>26</strong><small class="pending">Aguardam secretaria</small></div><div class="small-stat"><span>Movimentações</span><strong>15</strong><small>Transferências e ajustes fictícios</small></div></div>
                <section class="panel table-panel"><div class="panel-header"><div><h3>Movimentações recentes</h3><p>Um histórico ilustrativo e rastreável.</p></div><span class="small-pill">Exemplos</span></div><div class="responsive-table"><table><thead><tr><th>Aluno fictício</th><th>Escola</th><th>Turma</th><th>Evento</th><th>Situação</th></tr></thead><tbody>@foreach ($students as $student)<tr><td><strong>{{ $student['name'] }}</strong></td><td>{{ $student['school'] }}</td><td>{{ $student['class'] }}</td><td>Registro de matrícula</td><td><span class="table-status {{ $student['status'] === 'Ativa' ? 'active' : 'review' }}">{{ $student['status'] }}</span></td></tr>@endforeach</tbody></table></div></section>

            @elseif ($screen === 'importacoes')
                <div class="page-heading"><div><span class="eyebrow">MENOS DIGITAÇÃO</span><h1>Importação inteligente</h1><p>Da digitalização à conferência, sem perder a origem de cada informação.</p></div><span class="count-chip">Simulação visual</span></div>
                <section class="panel import-panel"><div class="panel-header"><div><h3>Etapas da importação</h3><p>Fluxo planejado para os documentos das escolas.</p></div><span class="small-pill">Prévia do produto</span></div><ol class="import-stepper"><li><span>1</span><strong>Receber</strong><small>Arquivo original</small></li><li><span>2</span><strong>Reconhecer</strong><small>Extrair informações</small></li><li><span>3</span><strong>Conferir</strong><small>Conferência humana</small></li><li><span>4</span><strong>Validar</strong><small>Secretaria aprova</small></li></ol><div class="drop-zone"><span class="drop-symbol" aria-hidden="true">↥</span><h3>Seus arquivos, sem redigitar tudo</h3><p>Planilhas Excel, documentos Word, PDF e imagens digitalizadas.</p><span class="file-formats">XLSX</span><span class="file-formats">DOCX</span><span class="file-formats">PDF</span><span class="file-formats">JPG</span><span class="file-formats">PNG</span><div><button type="button" disabled title="Função disponível em uma etapa futura">Selecionar arquivos em breve</button></div><small class="disabled-note">Simulação visual. Nenhum arquivo será enviado ou armazenado.</small></div></section>
                <div class="import-advice"><span aria-hidden="true">✳</span><div><strong>O sistema nunca decidirá sozinho por você.</strong><p>Documentos originais permanecem vinculados à extração. Dados duvidosos aguardam a secretaria, e uma nova via do histórico só será emitida após validação.</p></div></div>

            @elseif ($screen === 'documentos')
                <div class="page-heading"><div><span class="eyebrow">ACERVO ESCOLAR</span><h1>Documentos e históricos</h1><p>Uma visão do futuro arquivo escolar organizado por aluno e ano.</p></div><span class="count-chip">Arquivo fictício</span></div>
                <section class="documents-grid"><article class="document-card"><span class="document-icon tone-blue">▤</span><h2>Fichas individuais</h2><p>Registro e conferência das informações encontradas nas fichas.</p><span>PDF · JPG · PNG</span></article><article class="document-card"><span class="document-icon tone-mint">▣</span><h2>Históricos escolares</h2><p>Reconstrução assistida e emissão após aprovação formal.</p><span>DOCX · PDF</span></article><article class="document-card"><span class="document-icon tone-peach">▥</span><h2>Livros e atas</h2><p>Preservação da origem dos registros antigos digitalizados.</p><span>PDF · JPG · PNG</span></article></section><div class="import-advice"><span aria-hidden="true">✓</span><div><strong>Emissão com rastreabilidade.</strong><p>O histórico emitido identificará fontes usadas, responsável pela conferência e data da validação. Esta função ainda está em desenvolvimento.</p></div></div>

            @else
                <div class="page-heading"><div><span class="eyebrow">GESTÃO BASEADA EM INFORMAÇÕES</span><h1>Indicadores da rede</h1><p>Uma apresentação ilustrativa de como acompanhar as escolas.</p></div><span class="count-chip">Valores fictícios</span></div>
                <section class="panel indicators-panel"><div class="panel-header"><div><h3>Alunos por escola</h3><p>Distribuição demonstrativa, ano letivo de 2026.</p></div></div><div class="bar-list">@foreach ($schools as $school)<div class="bar-item"><div class="bar-heading"><strong>{{ $school['name'] }}</strong><span>{{ $school['students'] }} alunos</span></div><div class="bar-track"><div class="bar-fill tone-{{ $school['tone'] }}" style="width: {{ round($school['students'] / 462 * 100) }}%"></div></div></div>@endforeach</div></section><div class="import-advice"><span aria-hidden="true">i</span><div><strong>Sem dados oficiais nesta prévia.</strong><p>As obrigações do Censo Escolar, FUNDEB, TCE-SP e IEGM serão detalhadas em módulos específicos, com fontes e competências verificadas.</p></div></div>
            @endif

            <footer class="main-footer"><span>© 2026 Astuti Gestão Escolar</span><span>Monte Sereno é um município fictício. Interface de demonstração local.</span></footer>
        </main>
    </div>
</div>
</body>
</html>
