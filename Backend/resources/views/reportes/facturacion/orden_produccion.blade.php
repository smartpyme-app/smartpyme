<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Orden de Producción #{{ $orden->codigo }}</title>
    <style>
        * {
            margin: 0cm;
            font-family: system-ui;
        }

        body {
            margin: 50px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 0px;
            padding: 10px 5px;
            text-align: left;
        }

        .text-right {
            text-align: right !important;
        }

        .border-bottom {
            border-bottom: 1px solid #000 !important;
        }
    </style>
</head>

<body>
    <table>
        <tbody>
            <tr>
                <td>
                    <h1>{{ $orden->empresa->nombre }}</h1>
                    <p>{{ $orden->empresa->direccion }}</p>
                    <p>{{ $orden->empresa->telefono }}</p>
                </td>
                <!-- <td class="text-right">
                   @if ($orden->empresa->logo)
                       <img width="150" height="150" src="{{ asset('img/'.$orden->empresa->logo) }}" alt="Logo">
                   @endif
               </td> -->
            </tr>
        </tbody>
    </table>

    <table>
        <tbody>
            <tr>
                <td>
                    <h4>Cliente</h4>
                </td>
            </tr>
            <tr>
                <td>
                    <p>{{ $orden->nombre_cliente }}</p>
                    <p>{{ $orden->cliente->direccion }}</p>
                </td>
                <td>
                    <p>NCR: {{ $orden->cliente->ncr ?: 'No registrado' }}</p>
                    <p>DUI: {{ $orden->cliente->dui ?: 'No registrado' }}</p>
                    <p>Tel: {{ $orden->cliente->telefono ?: 'No registrado' }}</p>
                </td>
                <td class="text-right">
                    <p>Orden #{{ $orden->codigo }}</p>
                    <p>Fecha de creación: {{ \Carbon\Carbon::parse($orden->fecha)->format('d/m/Y') }}</p>
                    <p>Fecha de entrega: {{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }}</p>
                </td>
            </tr>
        </tbody>
    </table>

    <br>

    <table class="table">
        <thead>
            <tr>
                <th class="border-bottom">Código</th>
                <th class="border-bottom">Descripción</th>
                <th class="border-bottom text-right">Cantidad</th>
                @php
                $uniqueCustomFields = collect([]);
                foreach($orden->detalles as $detalle) {
                foreach($detalle->customFields as $field) {
                $uniqueCustomFields->push([
                'id' => $field->custom_field_id,
                'name' => $field->customField->name
                ]);
                }
                }
                $uniqueCustomFields = $uniqueCustomFields->unique('id')->sortBy('id');
                @endphp
                @foreach($uniqueCustomFields as $field)
                <th class="border-bottom text-right">{{ $field['name'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($orden->detalles as $detalle)
            <tr>
                <td class="border-bottom">{{ $detalle->producto->codigo }}</td>
                <td class="border-bottom">{{ $detalle->producto->nombre }}</td>
                <td class="border-bottom text-right">{{ number_format($detalle->cantidad, 0) }}</td>
                @foreach($uniqueCustomFields as $field)
                @php
                $customValue = $detalle->customFields->first(function($cf) use ($field) {
                return $cf->custom_field_id == $field['id'];
                });
                @endphp
                <td class="border-bottom text-right">
                    @if($customValue)
                    @if($customValue->custom_field_value_id)
                    {{ $customValue->customFieldValue->value }}
                    @else
                    {{ $customValue->value }}
                    @endif
                    @else
                    -
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <br>
    <h4>Observaciones:</h4>
    <p>{!! nl2br(e($orden->observaciones)) !!}</p>
    <br><br><br>

    <h4>Firma:</h4>
    <p>____________________________</p>

</body>

</html>