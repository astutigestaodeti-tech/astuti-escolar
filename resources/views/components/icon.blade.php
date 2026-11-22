@props(['name', 'size' => 20])
@php
    // Ícones de traço desenhados para o Astuti Gestão Escolar (24 x 24, traço 1,75).
    $paths = [
        'painel' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'escola' => '<path d="M3 20.5h18"/><path d="M5 20.5V10l7-5 7 5v10.5"/><path d="M10 20.5v-4.5h4v4.5"/><circle cx="12" cy="11" r="1.6"/>',
        'alunos' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c.8-3 3-4.6 5.5-4.6s4.7 1.6 5.5 4.6"/><circle cx="17" cy="9" r="2.4"/><path d="M16.2 14.6c2.1.2 3.7 1.5 4.3 3.9"/>',
        'matriculas' => '<path d="M7 4 3 8l4 4"/><path d="M3 8h13"/><path d="m17 20 4-4-4-4"/><path d="M21 16H8"/>',
        'importacoes' => '<path d="M12 15V4"/><path d="M7.5 8.5 12 4l4.5 4.5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'documentos' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/>',
        'indicadores' => '<path d="M4 20h16"/><path d="M7 16v-5"/><path d="M12 16V6"/><path d="M17 16V9"/>',
        'turmas' => '<path d="M4 5a2 2 0 0 1 2-2h14v15H6a2 2 0 0 0-2 2z"/><path d="M4 20a2 2 0 0 0 2 2h14"/><path d="M8.5 7.5h7"/><path d="M8.5 11h5"/>',
        'conferir' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.2 12.4 2.6 2.6 5-5.2"/>',
        'calendario' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'busca' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'sair' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="m10 16 4-4-4-4"/><path d="M14 12H4"/>',
        'seta' => '<path d="m9.5 6 6 6-6 6"/>',
        'voltar' => '<path d="m14.5 6-6 6 6 6"/>',
        'info' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5"/><circle cx="12" cy="7.9" r=".6" fill="currentColor"/>',
        'alerta' => '<path d="M10.3 4.4 2.7 17.6a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 4.4a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4"/><circle cx="12" cy="16.8" r=".6" fill="currentColor"/>',
        'escudo' => '<path d="M12 3 4.5 6v5.8c0 4.4 3.1 7.7 7.5 9.2 4.4-1.5 7.5-4.8 7.5-9.2V6z"/><path d="m9 12 2.1 2.1L15.2 10"/>',
        'relogio' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'acervo' => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9"/><path d="M10 13h4"/>',
        'ficha' => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="9.5" r="2.5"/><path d="M8 17c.7-1.9 2.2-3 4-3s3.3 1.1 4 3"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'icone']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths[$name] ?? $paths['info'] !!}</svg>
