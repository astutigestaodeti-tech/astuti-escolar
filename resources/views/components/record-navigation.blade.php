@props(['links', 'position', 'total', 'selectionLabel', 'listUrl', 'searchUrl'])

<nav class="registro-nav" aria-label="Navegação entre cadastros">
    <span class="registro-nav-contador" title="{{ $selectionLabel }}">
        @if ($position !== null)
            <span class="sr-only">{{ $selectionLabel }}. Cadastro </span>{{ $position }} de {{ $total }}
        @else
            Fora da seleção
        @endif
    </span>
    <div class="registro-nav-passos">
        @foreach (['first' => ['primeiro', 'Primeiro', 'Primeiro cadastro'], 'previous' => ['voltar', 'Anterior', 'Cadastro anterior'], 'next' => ['seta', 'Próximo', 'Próximo cadastro'], 'last' => ['ultimo', 'Último', 'Último cadastro']] as $key => [$icon, $short, $label])
            @if ($links[$key] ?? null)
                <a class="registro-nav-botao registro-nav-azul" href="{{ $links[$key] }}" aria-label="{{ $label }}" title="{{ $label }}" data-record-nav="{{ $key }}"><x-icon :name="$icon" :size="15" /><span>{{ $short }}</span></a>
            @else
                <span class="registro-nav-botao registro-nav-inativo" role="link" aria-disabled="true" aria-label="{{ $label }}" title="{{ $label }}" data-record-nav="{{ $key }}"><x-icon :name="$icon" :size="15" /><span>{{ $short }}</span></span>
            @endif
        @endforeach
    </div>
    <div class="registro-nav-acoes">
        <a class="registro-nav-botao registro-nav-neutro" href="{{ $searchUrl }}" aria-label="Localizar cadastro" title="Localizar cadastro"><x-icon name="busca" :size="15" /> Localizar</a>
        <a class="registro-nav-botao registro-nav-neutro" href="{{ $listUrl }}" aria-label="Voltar à lista" title="Voltar à lista"><x-icon name="lista" :size="15" /> Lista</a>
        {{ $slot }}
    </div>
</nav>
