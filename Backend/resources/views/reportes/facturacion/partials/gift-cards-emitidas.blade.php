{{-- Gift cards emitidas en esta venta (código para el cliente / regalo) --}}
@php
    $giftCardsTicket = $venta->relationLoaded('giftCardsEmitidas')
        ? $venta->giftCardsEmitidas
        : $venta->giftCardsEmitidas()->get();
@endphp
@if ($giftCardsTicket->isNotEmpty())
    <hr style="margin: 5px;">
    <p class="text-center"><b>GIFT CARD{{ $giftCardsTicket->count() > 1 ? 'S' : '' }}</b></p>
    @foreach ($giftCardsTicket as $giftCard)
        <table style="width: 100%; margin: auto;">
            <tr>
                <td>Código:</td>
                <td class="text-right"><b>{{ $giftCard->codigo }}</b></td>
            </tr>
            <tr>
                <td>Saldo:</td>
                <td class="text-right">
                    {{ ($venta->empresa->currency->currency_symbol ?? '$') }}{{ number_format((float) $giftCard->saldo, 2) }}
                </td>
            </tr>
        </table>
        @if (! $loop->last)
            <br>
        @endif
    @endforeach
    <p class="text-center"><small>Conserve este código para canjear su gift card.</small></p>
@endif
