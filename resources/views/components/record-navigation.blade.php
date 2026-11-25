@props(['links', 'position', 'total', 'selectionLabel', 'listUrl', 'searchUrl', 'searchValue' => '', 'searchFilters' => [], 'searchPlaceholder' => 'Nome ou número do cadastro'])

<div class="registro-nav">
    <form class="registro-nav-pesquisa" action="{{ $searchUrl }}" method="GET" role="search" aria-label="Pesquisar cadastro">
        @foreach ($searchFilters as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <label class="sr-only" for="registro-nav-busca">Pesquisar cadastro</label>
        <input id="registro-nav-busca" type="search" name="busca" value="{{ $searchValue }}" placeholder="{{ $searchPlaceholder }}" maxlength="120" autocomplete="off">
        <button type="submit" class="registro-nav-botao registro-nav-azul"><x-icon name="busca" :size="16" /> Pesquisar</button>
    </form>

    <nav class="registro-nav-passos" aria-label="Navegação entre cadastros">
        @foreach (['first' => ['primeiro', 'Primeiro', 'Primeiro cadastro'], 'previous' => ['voltar', 'Anterior', 'Cadastro anterior'], 'next' => ['seta', 'Próximo', 'Próximo cadastro'], 'last' => ['ultimo', 'Último', 'Último cadastro']] as $key => [$icon, $short, $label])
            @if ($key === 'next')
                <span class="registro-nav-contador" title="{{ $selectionLabel }}">
                    @if ($position !== null)
                        <span class="sr-only">{{ $selectionLabel }}. Cadastro </span>{{ $position }} de {{ $total }}
                    @else
                        Fora da seleção
                    @endif
                </span>
            @endif
            @if ($links[$key] ?? null)
                <a class="registro-nav-botao registro-nav-azul" href="{{ $links[$key] }}" aria-label="{{ $label }}" title="{{ $label }}" data-record-nav="{{ $key }}"><x-icon :name="$icon" :size="16" /><span>{{ $short }}</span></a>
            @else
                <span class="registro-nav-botao registro-nav-inativo" role="link" aria-disabled="true" aria-label="{{ $label }}" title="{{ $label }}" data-record-nav="{{ $key }}"><x-icon :name="$icon" :size="16" /><span>{{ $short }}</span></span>
            @endif
        @endforeach
    </nav>

    <div class="registro-nav-acoes" role="group" aria-label="Ações do cadastro">
        {{ $slot }}
        <a class="registro-nav-botao registro-nav-neutro" href="{{ $listUrl }}" aria-label="Voltar à lista" title="Voltar à lista"><x-icon name="lista" :size="16" /> Lista</a>
    </div>
</div>
