<!DOCTYPE html>

<html>

<head>

    <meta charset="utf-8">

    <title>{{ $data['titulo'] ?? 'Reporte activos' }}</title>

    <style>

        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }

        h1 { font-size: 16px; margin: 0 0 4px; }

        .meta { color: #555; margin-bottom: 12px; }

        table { width: 100%; border-collapse: collapse; }

        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }

        th { background: #f5f5f5; }

        .num { text-align: right; }

        .totales { margin-top: 10px; font-weight: bold; }

    </style>

</head>

<body>

    <h1>{{ $data['titulo'] ?? 'Reporte' }}</h1>

    <div class="meta">

        {{ $empresa->nombre ?? '' }} — {{ now()->format('d/m/Y H:i') }}

        @if(!empty($data['cantidad']))

            — {{ $data['cantidad'] }} registro(s)

        @endif

    </div>



    <table>

        <thead>

            <tr>

                @foreach($data['columnas'] ?? [] as $col)

                    @php($esNum = in_array(strtolower($col), ['valor compra', 'valor en libros', 'monto', 'acumulada', 'dep. acumulada', 'monto venta', 'cantidad'], true))

                    <th @class(['num' => $esNum])>{{ $col }}</th>

                @endforeach

            </tr>

        </thead>

        <tbody>

            @forelse($data['lineas'] ?? [] as $fila)

                <tr>

                    @foreach($fila as $i => $celda)

                        @php($colNum = isset($data['columnas'][$i]) && in_array(strtolower($data['columnas'][$i]), ['valor compra', 'valor en libros', 'monto', 'acumulada', 'dep. acumulada', 'monto venta', 'cantidad'], true))

                        <td @class(['num' => $colNum])>{{ $colNum && $celda !== '' ? number_format((float) $celda, 2) : $celda }}</td>

                    @endforeach

                </tr>

            @empty

                <tr><td colspan="{{ count($data['columnas'] ?? []) }}">Sin registros</td></tr>

            @endforelse

        </tbody>

    </table>



    @if(!empty($data['totales']))

        <div class="totales">

            @foreach($data['totales'] as $k => $v)

                {{ ucfirst(str_replace('_', ' ', $k)) }}: {{ is_numeric($v) ? number_format((float) $v, 2) : $v }}@if(!$loop->last) | @endif

            @endforeach

        </div>

    @endif

</body>

</html>

