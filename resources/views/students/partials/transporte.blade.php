{{-- Situação do transporte escolar da matrícula, gravada pela importação da lista da Secretaria. --}}
@switch($situacao)
    @case('active')<span class="etiqueta ok">Usa transporte</span>@break
    @case('not_requested')<span class="etiqueta neutra">Não solicitado</span>@break
    @case('cancelled')<span class="etiqueta conferir">Cancelado</span>@break
    @default<span class="texto-suave">Sem informação</span>
@endswitch
