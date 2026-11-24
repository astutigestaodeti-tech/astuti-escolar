@props(['municipality', 'municipalityName' => null, 'schoolName' => null, 'schoolInep' => null, 'perfil' => null])
@php
    // Timbre único de todos os documentos: brasão, Prefeitura, Secretaria e unidade emissora.
    $perfil ??= rescue(fn () => \App\Models\Tenant\InstitutionProfile::current(),
        fn () => new \App\Models\Tenant\InstitutionProfile(['id' => 1, 'department_name' => 'Secretaria Municipal de Educação']), false);
    $brasao = 'images/brasao-'.$municipality->slug.'.webp';
    $temBrasao = preg_match('/\A[a-z0-9-]+\z/', (string) $municipality->slug) === 1 && is_file(public_path($brasao));
@endphp
<header {{ $attributes->class('doc-timbre') }}>
    @if ($temBrasao)
        <img class="doc-timbre-brasao" src="{{ asset($brasao) }}" alt="Brasão municipal" width="68" height="68">
    @endif
    <div class="doc-timbre-texto">
        <strong class="doc-timbre-ente">{{ $municipalityName ?? $municipality->legal_name }}</strong>
        <span class="doc-timbre-orgao">{{ $perfil->department_name }}</span>
        @if ($schoolName)
            <span class="doc-timbre-unidade">{{ $schoolName }}@if ($schoolInep)<span class="doc-timbre-inep">INEP {{ $schoolInep }}</span>@endif</span>
        @endif
    </div>
</header>
