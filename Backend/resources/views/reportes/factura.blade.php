<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	{{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
	<title>Factura</title>
	<style>

        *{ font-size: .4cm; margin: 0cm; padding: 0cm; font-weight: 400;}
        html, body {
            /*width: 16.5cm;*/
            /*height: 14.2cm;*/
            height: 21.59cm;
            width: 13.97cm;
            display: block;
            font-family: serif;
            margin: 0cm;
            padding: 0cm;
        }

        /*p{border: 1px solid red; }*/

        #fecha          {position: absolute; top: 4.1cm; left: 10cm; }
        #cliente        {position: absolute; top: 4.8cm; left: 2.2cm; width: 10cm}
        #direccion      {position: absolute; top: 5.4cm; left: 2.5cm; width: 9cm}
        #departamento   {position: absolute; top: 5.7cm; left: 2.2cm; width: 5cm}
        #giro           {position: absolute; top: 3.7cm; left: 11.5cm; }
        #nrc            {position: absolute; top: 6.2cm; left: 9cm; }
        #nit            {position: absolute; top: 5.8cm; left: 9cm; width: 4cm }
        #condicion      {position: absolute; top: 6.4cm; left: 3.6cm;}

        table   {position: absolute; top: 7.6cm; left: 1.2cm; text-align: left; border-collapse: collapse;}
        table td{height: 0.7cm;}

        .cantidad{ width: 1cm; text-align: left;}
        .producto{ width: 6.2cm;}
        .precio{ width: 1.5cm; text-align: right;}
        .sujetas{ width: 0.6cm; text-align: right;}
        .exentas{ width: 0.7cm; text-align: right;}
        .gravadas{ width: 1.4cm; text-align: right;}
        
        #letras     {position: absolute; top: 17cm; left: 2cm; width: 5cm;}
        #correlativo{position: absolute; top: 17.5cm; left: 2cm;; width: 5cm;;}
        #info       {position: absolute; top: 17.7cm; left: 2cm; width: 5cm;;}

        #suma       {position: absolute; top: 16.1cm; left: 10.6cm; width: 2cm; text-align: right;}
        #propina    {position: absolute; top: 16.5cm; left: 10.6cm; width: 2cm; text-align: right;}
        #no_sujeta  {position: absolute; top: 17.2cm; left: 10.6cm; width: 2cm; text-align: right;}
        #exenta     {position: absolute; top: 18cm; left: 10.6cm; width: 2cm; text-align: right;}
        #total      {position: absolute; top: 19.5cm; left: 10.6cm; width: 2cm; text-align: right;}

        .no-print{position: absolute;}

    </style>
	
	<style media="print"> .no-print{display: none; } </style>

</head>
<body>
{{-- <body onload="javascript:print();"> --}}

	<section style="border:1px solid #ffffff00;background-image: url('/img/factura.jpg'); background-repeat: no-repeat; background-size: 100% 100%; height: 21.59cm; width: 13.97cm; position: relative;">
		<p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
		<p id="cliente">{{ $venta->nombre_cliente }}</p>
        @if ($venta->cliente)
    		<p id="direccion">{{ $venta->cliente->direccion }} {{ $venta->cliente->municipio }}</p>
	   	    <p id="departamento">{{ $venta->cliente->departamento }}</p>
		    <p id="nit">{{ $venta->cliente->dui }}</p>
        @endif

		<p id="condicion">
			@if ($venta->estado == "Pendiente")
				<span style="left: 300pt;">Credito</span>
			@else
				<span style="left: 300pt;">Contado</span>
			@endif
		</p>
		
		<table>
			@foreach($venta->detalles as $detalle)
			<tr>
				<td class="cantidad">	{{ number_format($detalle->cantidad, 1) }}</td>
				<td class="producto">	{{ $detalle->nombre_producto  }}</td>
				<td class="precio">		$ {{ number_format($detalle->precio, 2 ) }}</td>
				<td class="sujetas">	@if($detalle->no_sujeta) $ {{ number_format($detalle->no_sujeta + $detalle->iva + $detalle->descuento, 2) }} @endif</td>
				<td class="exentas">	@if($detalle->exenta) $ {{ number_format($detalle->exenta + $detalle->iva + $detalle->descuento, 2) }} @endif</td>
				<td class="gravadas">	@if($detalle->gravada) $ {{ number_format($detalle->gravada + $detalle->iva + $detalle->descuento, 2) }} @endif</th>
			</tr>
			@endforeach
			@if ($venta->descuento > 0)
				<tr>
					<td class="cantidad"></td>
					<td class="producto">DESCUENTOS</td>
					<td class="precio"></td>
					<td class="sujetas"></td>
					<td class="exentas"></td>
					<td class="gravadas">- $ {{ number_format($venta->descuento, 2) }} </th>
				</tr>
			@endif
		</table>

		<p id="suma">		$ {{ number_format($venta->total, 2) }}</p>
		
		@if ($venta->no_sujeta > 0)
			<p id="no_sujeta">	$ {{ number_format($venta->no_sujeta, 2) }}</p>
		@endif
		@if ($venta->exenta > 0)
			<p id="exenta">	$ {{ number_format($venta->exenta, 2) }}</p>
		@endif

		<p id="propina">	$ {{ number_format($venta->propina, 2) }}</p>
		<p id="total"><b>	$ {{ number_format($venta->total, 2) }}</b></p>

		<p id="letras">{{ $venta->total_letras }}</p>
		{{-- <p id="correlativo">{{ $venta->correlativo }}</p> --}}
		<p id="info">
		    {{ $venta->placa ? 'Placa: ' . $venta->placa : '' }}
		    {{ $venta->kilometraje ? ' Kilometraje: ' . $venta->kilometraje : '' }}
		    {{ $venta->observacion ? ' Observacion: ' . $venta->observacion : '' }}
		</p>

	</section>

	<button class="no-print" onClick="window.close();" autofocus>Cerrar</button>


</div>
</body>
</html>