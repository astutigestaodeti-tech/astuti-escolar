@php
    $params = ['municipio' => $municipality->slug, 'escola' => $unit->id];
@endphp
<section class="folha hoje-escola" id="{{ $sectionId }}" aria-labelledby="{{ $sectionId }}-titulo">
    <div class="folha-cabecalho">
        <div><h2 id="{{ $sectionId }}-titulo">{{ $heading }}</h2><p>O que precisa de atenção, em ordem de prioridade.</p></div>
    </div>
    <div class="folha-corpo">
        @if ($items === [])
            <p class="hoje-em-dia"><x-icon name="conferir" :size="18" /> <strong>Tudo em dia</strong></p>
        @else
            <ol class="hoje-lista">
                @foreach ($items as $type => $item)
                    <li class="hoje-item" id="{{ $sectionId }}-{{ $type }}">
                        @if ($item['count'] === null)
                            <span class="hoje-numero hoje-protegido" title="Grupo pequeno demais para mostrar a quantidade">Protegido</span>
                        @else
                            <span class="hoje-numero">{{ $item['count'] }}</span>
                        @endif
                        <span class="hoje-texto">{{ \App\Schools\TodayAtSchool::LABELS[$type] }}</span>
                        <span class="hoje-acoes">
                            @if (count($item['links']) > 1)
                                <details><summary>{{ count($item['links']) }} atalhos</summary>
                            @endif
                            @foreach ($item['links'] as $link)
                                @php
                                    $linkParams = in_array($link['route'], ['team.indicators', 'waitlist.index'], true) ? ['municipio' => $municipality->slug] : $params;
                                @endphp
                                <a href="{{ route($link['route'], [...$linkParams, ...$link['params']]) }}">{{ $link['label'] }}</a>
                            @endforeach
                            @if (count($item['links']) > 1)
                                </details>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
