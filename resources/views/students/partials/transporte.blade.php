{{-- Situação e dados operacionais do transporte da matrícula, nunca da ficha de saúde. --}}
@php
    $matriculaTransporte = $matricula ?? ($enrollment ?? null);
    $apenasLinha = $apenasLinha ?? false;
@endphp
@switch($situacao)
    @case('active')<span class="etiqueta ok">Usa transporte</span>@break
    @case('not_requested')<span class="etiqueta neutra">Não solicitado</span>@break
    @case('cancelled')<span class="etiqueta conferir">Cancelado</span>@break
    @default<span class="texto-suave">Sem informação</span>
@endswitch
@if ($matriculaTransporte?->transport_route)
    <small class="lista-ra">Linha: {{ $matriculaTransporte->transport_route }}</small>
@endif
@if (! $apenasLinha && $matriculaTransporte)
    @if ($matriculaTransporte->transport_stop)
        <small class="lista-ra">Ponto de embarque: {{ $matriculaTransporte->transport_stop }}</small>
    @endif
    @if ($matriculaTransporte->transport_adapted_vehicle === true)
        <span class="etiqueta neutra">Veículo adaptado</span>
    @endif
@endif
