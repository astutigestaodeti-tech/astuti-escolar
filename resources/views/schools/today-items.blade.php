@php
    $params = ['municipio' => $municipality->slug, 'escola' => $unit->id];
@endphp
<section class="folha hoje-escola" id="{{ $sectionId }}" aria-labelledby="{{ $sectionId }}-titulo">
    <div class="folha-cabecalho">
        <div><h2 id="{{ $sectionId }}-titulo">{{ $heading }}</h2><p>O que precisa de atenção, em ordem de prioridade.</p></div>
    </div>
    <div class="folha-corpo">
        @if ($items === [])
            <p><strong>Tudo em dia</strong></p>
        @else
            <ol class="hoje-lista">
                @foreach ($items as $type => $item)
                    <li id="{{ $sectionId }}-{{ $type }}">
                        <strong>{{ $item['count'] ?? 'Protegido' }}</strong> - {{ \App\Schools\TodayAtSchool::LABELS[$type] }}
                        @if (count($item['links']) > 1)<details><summary>Ver atalhos</summary>@endif
                        @foreach ($item['links'] as $link)
                            @php
                                $linkParams = in_array($link['route'], ['team.indicators', 'waitlist.index'], true) ? ['municipio' => $municipality->slug] : $params;
                            @endphp
                            <div><a href="{{ route($link['route'], [...$linkParams, ...$link['params']]) }}">{{ $link['label'] }}</a></div>
                        @endforeach
                        @if (count($item['links']) > 1)</details>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
