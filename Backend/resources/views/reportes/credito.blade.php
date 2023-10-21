<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	{{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
	<title>Credito Fiscal</title>
	<style>

		*{ font-size: .4cm; margin: 0cm; padding: 0cm; font-weight: 500;}
		html, body {
		    width: 16.5cm;
		    height: 14.2cm;
		    display: block;
		    font-family: serif;
		    margin: 0cm;
		    padding: 0cm;
		}

		#cliente		{position: absolute; top: 3.3cm; left: 2.5cm; width: 7.5cm}
		#direccion		{position: absolute; top: 3.9cm; left: 2.5cm; }
		#departamento	{position: absolute; top: 4.7cm; left: 8cm; }
		#fecha			{position: absolute; top: 3.3cm; left: 11.5cm; }
		#giro			{position: absolute; top: 3.7cm; left: 11.5cm; }
		#nrc			{position: absolute; top: 4.1cm; left: 11.5cm; }
		#nit			{position: absolute; top: 4.6cm; left: 11.5cm; }
		#condicion		{position: absolute; top: 5cm; left: 12.5cm;}

		table 	{position: absolute; top: 6cm; left: 1.2cm; text-align: left; border-collapse: collapse;}
		table td{height: 0.5cm;}

		.cantidad{ width: 1.4cm; text-align: left;}
		.producto{ width: 6.1cm;}
		.precio{ width: 1.5cm; text-align: right;}
		.sujetas{ width: 1.5cm; text-align: right;}
		.exentas{ width: 1.5cm; text-align: right;}
		.gravadas{ width: 2cm; text-align: right;}
		
		#letras		{position: absolute; top: 8.5cm; left: 2cm; width: 5cm;}
		#correlativo{position: absolute; top: 9.3cm; left: 2cm;; width: 5cm;;}
		#info 		{position: absolute; top: 9.2cm; left: 3cm; width: 5cm;;}

		#suma		{position: absolute; top: 8.5cm; left: 13.2cm; width: 2cm; text-align: right;}
		#iva		{position: absolute; top: 9cm; left: 13.2cm; width: 2cm; text-align: right;}
		#iva_retenido{position: absolute; top: 9.7cm; left: 13.2cm; width: 2cm; text-align: right;}
		#no_sujeta	{position: absolute; top: 10.1cm; left: 13.2cm; width: 2cm; text-align: right;}
		#exenta		{position: absolute; top: 10.4cm; left: 13.2cm; width: 2cm; text-align: right;}
		#fovial 	{position: absolute; top: 10.8cm; left: 13.2cm; width: 2cm; text-align: right;}
		#propina 	{position: absolute; top: 11.2cm; left: 13.2cm; width: 2cm; text-align: right;}
		#total 		{position: absolute; top: 11.6cm; left: 13.2cm; width: 2cm; text-align: right;}

		.no-print{position: absolute;}

	</style>
	
	<style media="print"> .no-print{display: none; } </style>

</head>
<body>
{{-- <body onload="javascript:print();" style="margin-left: -0.8cm; margin-top: 0.5cm"> --}}

	<section style="border:1px solid #ffffff00; background-image: url('/img/credito.jpg'); background-repeat: no-repeat; background-size: 100% 100%; height: 14.2cm; position: relative;">
		<p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
		<p id="cliente">{{ $venta->nombre_cliente }}</p>
		<p id="direccion">{{ $venta->cliente->direccion }}</p>
		<p id="departamento">{{ $venta->cliente->departamento }}</p>
		<p id="nit">{{ $venta->cliente->dui }}</p>
		<p id="nrc">{{ $venta->cliente->registro }}</p>
		<p id="giro">{{ \Illuminate\Support\Str::limit($venta->cliente->giro, 20, $end = '...') }}</p>
		<p id="condicion">
			@if ($venta->estado == "Pendiente")
				Credito
			@else
				Contado
			@endif
		</p>
					
		<table>
			@foreach($venta->detalles as $detalle)
			<tr>
				<td class="cantidad">	{{ number_format($detalle->cantidad, 2) }}</td>
				<td class="producto">	{{ $detalle->nombre_producto  }}</td>
				<td class="precio">		$ {{ $detalle->gravada ? number_format($detalle->precio / 1.13 , 2) : number_format($detalle->precio, 2) }}</td>
				<td class="sujetas">	@if($detalle->no_sujeta) $ {{ number_format($detalle->no_sujeta, 2) }} @endif</td>
				<td class="exentas">	@if($detalle->exenta) $ {{ number_format($detalle->exenta, 2) }}  @endif</td>
				<td class="gravadas">	@if($detalle->gravada) $ {{ number_format($detalle->gravada, 2) }} @endif</th>
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

		<p id="suma">		$ {{ number_format($venta->subtotal, 2) }}</p>
		<p id="iva">		$ {{ number_format($venta->iva, 2) }}</p>
		
		@if ($venta->iva_retenido > 0)
			<p id="iva_retenido">	$ {{ number_format($venta->iva_retenido, 2) }}</p>
		@endif
		@if ($venta->no_sujeta > 0)
			<p id="no_sujeta">	$ {{ number_format($venta->no_sujeta, 2) }}</p>
		@endif
		@if ($venta->exenta > 0)
			<p id="exenta">	$ {{ number_format($venta->exenta, 2) }}</p>
		@endif

		<p id="propina">	$ {{ number_format($venta->propina, 2) }}</p>
		<p id="total">   	<b>$ {{ number_format($venta->total, 2) }}</b></p>

		<p id="letras">{{ $venta->total_letras }}</p>
		<p id="correlativo">{{ $venta->correlativo }}</p>

	</section>
	
	<button class="no-print" onClick="window.close();" autofocus>Cerrar</button>

</body>
</html>