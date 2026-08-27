<!DOCTYPE html>
<html>
<head>
    <title>Libro diario auxiliar</title>
</head>
<body>
<table>
    <thead>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Movimiento de una cuenta</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Empresa: {{ $empresa->nombre }}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Desde: {{$desde}} Hasta: {{$hasta}}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>{{$fecha}}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>{{$hora}}</strong></th>
    </tr>
    <tr></tr>
    </thead>
    <tbody>
    @foreach($cuentas as $cuenta)
        <table>
            <tr>
                <th class="no_bord">Cuenta:</th>
                <th class="no_bord">{{$cuenta->cuenta}}</th>
                <th class="no_bord">{{$cuenta->nombre}}</th>
                <th class="no_bord"></th>
                <th class="no_bord">Saldo anterior:</th>
                <th class="no_bord">{{$cuenta->saldo_anterior}}</th>
            </tr>
            <tr>
                <th>Partida</th>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Cargo</th>
                <th>Abono</th>
                <th>Saldo</th>
            </tr>

            @foreach($cuenta->detalles as $detalle)
                <tr>
                    <td class="id_partida">PART-{{$detalle->id_partida}}</td>
                    <td class="fecha_partida">{{$detalle->created_at->toFormattedDateString()}}</td>
                    <td class="concepto">{{$detalle->concepto}}</td>
                    <td class="cargo">{{$detalle->debe}}</td>
                    <td class="abono">{{$detalle->haber}}</td>
                    @if($cuenta->naturaleza=="Deudor")
                        <td class="saldo">{{$cuenta->saldo_actual=$cuenta->saldo_actual+$detalle->debe-$detalle->haber}}</td>
                    @else
                        <td class="saldo">{{$cuenta->saldo_actual=$cuenta->saldo_actual-$detalle->debe+$detalle->haber}}</td>
                    @endif
                </tr>
            @endforeach
            <tr>
                <th></th>
                <th></th>
                <th><strong>Total por cuenta:</strong></th>
                <th><strong>{{$cuenta->cargo}}</strong></th>
                <th><strong>{{$cuenta->abono}}</strong></th>
                <th><strong>{{$cuenta->saldo_actual}}</strong></th>
            </tr>
        </table>
@endforeach
    </tbody>
</table>
</body>
</html>
