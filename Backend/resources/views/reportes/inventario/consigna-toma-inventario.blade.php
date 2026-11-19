<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Toma de inventario en consigna</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }
        body { margin: 24px 28px; }
        .titulo-toma {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            color: #1565c0;
            margin: 0 0 14px;
            line-height: 1.35;
        }
        .fecha-toma {
            margin: 0 0 16px;
            font-size: 10px;
            font-weight: bold;
        }
        .fecha-linea {
            display: inline-block;
            min-width: 200px;
            border-bottom: 1px solid #000;
        }
        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        table.datos th,
        table.datos td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
        }
        table.datos th {
            font-size: 8.5px;
            font-weight: bold;
            text-align: center;
            background: #fff;
        }
        .col-num { width: 9%; text-align: center; }
        .col-desc { width: 34%; }
        .col-lote { width: 11%; text-align: center; }
        .col-vence { width: 12%; text-align: center; }
        .col-qty { width: 11%; text-align: center; }
        .col-vacio { width: 11%; text-align: center; min-height: 18px; }
        .codigo-desc { font-weight: bold; font-size: 10px; margin-bottom: 2px; }
        .nombre-desc { font-size: 8.5px; line-height: 1.25; }
        .comentarios-label { font-weight: bold; margin: 8px 0 4px; font-size: 10px; }
        .comentarios-box {
            border: 1px solid #000;
            min-height: 72px;
            padding: 8px;
            margin-bottom: 28px;
        }
        .firmas {
            width: 100%;
            margin-top: 12px;
            margin-bottom: 36px;
        }
        .firmas td {
            width: 50%;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            vertical-align: top;
            padding-top: 40px;
            border-top: 1px solid #000;
        }
        .pie {
            text-align: center;
            font-size: 8.5px;
            line-height: 1.45;
            margin-top: 8px;
        }
        .pie .empresa-nombre { font-weight: bold; font-size: 9px; margin-bottom: 4px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @php
        $formatearCantidad = static function ($valor): string {
            $n = (float) $valor;
            if (abs($n - round($n)) < 0.0001) {
                return (string) (int) round($n);
            }
            return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
        };
        $formatearVence = static function ($valor): string {
            if ($valor === null || $valor === '') {
                return '';
            }
            try {
                return \Carbon\Carbon::parse($valor)->format('Y-m-d');
            } catch (\Throwable) {
                return (string) $valor;
            }
        };
        $lineaDireccion = trim(implode(', ', array_filter([
            $empresa->direccion ?? null,
            $empresa->distrito ?? null,
            $empresa->municipio ?? null,
            $empresa->departamento ?? null,
        ])));
        $nombreEmpresaReporte = strtoupper(trim((string) ($empresa->nombre ?? 'EMPRESA')));
        $nombreClientePie = static function (array $seccion): string {
            return strtoupper(trim((string) ($seccion['cliente_nombre'] ?? 'CLIENTE')));
        };
    @endphp

    @foreach ($secciones as $i => $seccion)
        @php
            $payload = $seccion['payload'] ?? [];
        @endphp
        <h1 class="titulo-toma">
            TOMA DE INVENTARIO {{ strtoupper($seccion['cliente_nombre'] ?? '') }}
        </h1>

        <p class="fecha-toma">
            FECHA DE TOMA DE INVENTARIO: <span class="fecha-linea">&nbsp;</span>
        </p>

        @php
            $colsLote = !empty($payload['incluir_lote']);
            $colspanVacio = $colsLote ? 7 : 5;
        @endphp
        <table class="datos">
            <thead>
                <tr>
                    <th class="col-num">Nº CON</th>
                    <th class="col-desc">DESCRIPCIÓN DEL PRODUCTO</th>
                    @if ($colsLote)
                        <th class="col-lote">LOTE</th>
                        <th class="col-vence">VENCE</th>
                    @endif
                    <th class="col-qty">CANTIDAD<br>SISTEMA</th>
                    <th class="col-vacio">TOMA<br>ACTUAL</th>
                    <th class="col-vacio">FACTURAR</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payload['resumen'] ?? [] as $fila)
                    @php
                        $numCon = $fila['numero_consigna'] ?? '';
                        $codigo = trim((string) ($fila['codigo'] ?? ''));
                        $producto = trim((string) ($fila['producto'] ?? ''));
                    @endphp
                    <tr>
                        <td class="col-num">{{ $numCon }}</td>
                        <td class="col-desc">
                            @if ($codigo !== '')
                                <div class="codigo-desc">{{ $codigo }}</div>
                            @endif
                            <div class="nombre-desc">{{ $producto }}</div>
                        </td>
                        @if ($colsLote)
                            <td class="col-lote">{{ $fila['lote'] ?? '' }}</td>
                            <td class="col-vence">{{ $formatearVence($fila['vence'] ?? null) }}</td>
                        @endif
                        <td class="col-qty">{{ $formatearCantidad($fila['disponible'] ?? 0) }}</td>
                        <td class="col-vacio">&nbsp;</td>
                        <td class="col-vacio">&nbsp;</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspanVacio }}" class="text-center">Sin productos en consigna con saldo</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="comentarios-label">Comentarios:</div>
        <div class="comentarios-box">&nbsp;</div>

        <table class="firmas">
            <tr>
                <td>RESPONSABLE {{ $nombreEmpresaReporte }}</td>
                <td>RESPONSABLE {{ $nombreClientePie($seccion) }}<br>NOMBRE, FIRMA Y SELLO</td>
            </tr>
        </table>

        <div class="pie">
            <div class="empresa-nombre">{{ $nombreEmpresaReporte }}</div>
            @if ($lineaDireccion !== '')
                <div>{{ strtoupper($lineaDireccion) }}</div>
            @endif
            @if (!empty($empresa->correo) || !empty($empresa->telefono))
                <div>
                    @if (!empty($empresa->correo))
                        E-mail: {{ $empresa->correo }}
                    @endif
                    @if (!empty($empresa->correo) && !empty($empresa->telefono))
                        ,
                    @endif
                    @if (!empty($empresa->telefono))
                        Teléfono: {{ $empresa->telefono }}
                    @endif
                </div>
            @endif
        </div>

        @if ($i < count($secciones) - 1)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>