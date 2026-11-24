@props(['municipality', 'school' => null, 'perfil' => null, 'aviso' => true])
@php
    // Rodapé institucional: endereço da unidade (quando o documento é de uma escola) e contatos da Secretaria.
    $perfil ??= rescue(fn () => \App\Models\Tenant\InstitutionProfile::current(),
        fn () => new \App\Models\Tenant\InstitutionProfile(['id' => 1, 'department_name' => 'Secretaria Municipal de Educação']), false);
    $unidade = $school?->addressLine() ?: null;
    $contatos = array_filter([
        $perfil->formattedCnpj() ? 'CNPJ '.$perfil->formattedCnpj() : null,
        $perfil->formattedPhone() ? 'Telefone '.$perfil->formattedPhone() : null,
        $perfil->email,
        $perfil->displayWebsite(),
    ]);
    $secretaria = $perfil->addressLine();
    $faltam = $perfil->missing();
    $podeCompletar = $aviso && $faltam !== [] && auth()->user()?->can('viewNetworkPanel', [\App\Models\Tenant\School::class, $municipality]);
@endphp
@if ($unidade || $secretaria || $contatos)
    <footer {{ $attributes->class('doc-rodape') }}>
        @if ($unidade)<p><span class="doc-rodape-rotulo">{{ $school->name }}:</span> {{ $unidade }}</p>@endif
        @if ($secretaria)<p><span class="doc-rodape-rotulo">{{ $perfil->department_name }}:</span> {{ $secretaria }}</p>@endif
        @if ($contatos)<p class="doc-rodape-contatos">@foreach ($contatos as $contato)<span>{{ $contato }}</span>@endforeach</p>@endif
    </footer>
@endif
@if ($podeCompletar)
    <p class="doc-pendencia so-tela" role="note"><x-icon name="info" :size="16" />
        <span>Faltam no rodapé: {{ implode(', ', $faltam) }}. <a href="{{ route('institution.edit', ['municipio' => $municipality->slug]) }}">Completar os dados da Prefeitura</a></span></p>
@endif
