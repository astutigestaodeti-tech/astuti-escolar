@props(['subtitulo' => 'Gestão Escolar'])
{{-- Marca Astuti: um "A" que também é um telhado de escola, com a página do caderno como travessão. --}}
<span {{ $attributes->merge(['class' => 'marca']) }}>
    <svg class="marca-simbolo" width="34" height="34" viewBox="0 0 34 34" aria-hidden="true" focusable="false">
        <path d="M6.5 27.5 17 6.5l10.5 21" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M10.6 20.2c4.3-1.9 8.5-1.9 12.8 0" fill="none" stroke="var(--ouro-vivo)" stroke-width="2.4" stroke-linecap="round"/>
        <circle cx="27" cy="9" r="2.1" fill="var(--ouro-vivo)"/>
    </svg>
    <span class="marca-texto">
        <strong>Astuti</strong>
        <small>{{ $subtitulo }}</small>
    </span>
</span>
