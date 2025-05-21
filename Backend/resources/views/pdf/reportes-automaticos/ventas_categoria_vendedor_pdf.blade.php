<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .vendedor {
            text-align: left;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .total-col {
            font-weight: bold;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    
    <table>
        <thead>
            <tr>
                @foreach($encabezados as $encabezado)
                    <th>{{ $encabezado }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($datos as $fila)
                <tr class="{{ $fila['Vendedor'] === 'TOTAL' ? 'total-row' : '' }}">
                    <td class="vendedor">{{ $fila['Vendedor'] }}</td>
                    
                    @foreach($encabezados as $key => $encabezado)
                        @if($key > 0) {{-- Saltar la primera columna (Vendedor) --}}
                            <td class="{{ $encabezado === 'TOTAL' ? 'total-col' : '' }}">
                                @if(isset($fila[$encabezado]))
                                    {{ $fila[$encabezado] }}
                                @else
                                    0.00
                                @endif
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>