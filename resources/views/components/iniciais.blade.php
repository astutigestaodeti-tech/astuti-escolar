@props(['texto' => ''])
@php
    // Siglas de escola: ignora palavras genéricas para que "EMEF Prof. Sebastião Arantes" vire "SA".
    $ignorar = ['emef', 'emei', 'emeief', 'cei', 'ee', 'escola', 'municipal', 'estadual', 'rural', 'prof', 'prof.', 'profª', 'profa', 'professor', 'professora', 'dona', 'de', 'da', 'do', 'das', 'dos', 'e'];
    $palavras = array_values(array_filter(preg_split('/\s+/u', trim((string) $texto)) ?: [], fn ($p) => $p !== '' && ! in_array(mb_strtolower($p), $ignorar, true)));
    if ($palavras === []) {
        $palavras = preg_split('/\s+/u', trim((string) $texto)) ?: ['?'];
    }
    $sigla = mb_strtoupper(mb_substr($palavras[0], 0, 1).(isset($palavras[1]) ? mb_substr($palavras[1], 0, 1) : ''));
@endphp
<span {{ $attributes->merge(['class' => 'escola-sigla']) }} aria-hidden="true">{{ $sigla }}</span>
