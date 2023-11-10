@component('mail::message')
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# Hola
@endif
@endif

{{-- Intro Lines --}}
Este correo electrónico te llega debido a que hemos registrado una solicitud de restablecimiento de contraseña asociada a tu cuenta.

{{-- Action Button --}}
@isset($actionText)
<?php
    switch ($level) {
        case 'success':
        case 'error':
            $color = $level;
            break;
        default:
            $color = 'primary';
    }
?>
@component('mail::button', ['url' => $actionUrl, 'color' => $color])
Restablecer contraseña
@endcomponent
@endisset

Este enlace de restablecimiento de contraseña caducará en 60 minutos.

Si no realizaste esta solicitud, simplemente ignora este correo electrónico.

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else

Saludos,<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
@slot('subcopy')
@lang(
    "Si tiene problemas para hacer clic en el botón \"Restablecer contraseña\"  copie y pegue la siguiente URL en su navegador web:",
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
@endslot
@endisset
@endcomponent
