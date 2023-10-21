<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	{{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
	<title>Crédito Fiscal</title>
	<style>

		*{ font-size: 12px; margin: 0cm; padding: 0cm; font-weight: 500;}
		html, body {
		    width: {{number_format($venta->width, 1)}}cm;
		    height: {{number_format($venta->height, 1)}}cm;
/*            border: 1px solid red;*/
		    display: block;
		    font-family: serif;
		    margin: 0cm 0cm 0cm 0cm;
		    padding: 0cm;
		}
        p{
/*            height: 0.5cm;*/
        }

        #fecha          {position: absolute; top: {{ number_format($venta->pos_fecha_y, 1) }}cm; left: {{ number_format($venta->pos_fecha_x, 1) }}cm; }
        #cliente        {position: absolute; top: {{ number_format($venta->pos_cliente_y, 1) }}cm; left: {{ number_format($venta->pos_cliente_x, 1) }}cm; width: 12cm; text-wrap: nowrap; overflow: hidden;}
        #direccion      {position: absolute; top: {{ number_format($venta->pos_direccion_y, 1) }}cm; left: {{ number_format($venta->pos_direccion_x, 1) }}cm; width: 12cm; overflow: hidden;}
        #dui            {position: absolute; top: {{ number_format($venta->pos_dui_y, 1) }}cm; left: {{ number_format($venta->pos_dui_x, 1) }}cm; width: 4cm }

		#giro			{position: absolute; top: {{ number_format($venta->pos_giro_y, 1) }}cm; left: {{ number_format($venta->pos_giro_x, 1) }}cm; }
		#nrc			{position: absolute; top: {{ number_format($venta->pos_ncr_y, 1) }}cm; left: {{ number_format($venta->pos_ncr_x, 1) }}cm; }
		#condicion		{position: absolute; top: 5cm; left: 12.5cm;}

        #dui            {position: absolute; top: {{ number_format($venta->pos_dui_y, 1) }}cm; left: {{ number_format($venta->pos_dui_x, 1) }}cm; width: 4cm }

        table   {position: absolute; top: {{ number_format($venta->pos_detalles_y, 1) }}cm; left: {{ number_format($venta->pos_detalles_x, 1) }}cm; text-align: left; border-collapse: collapse;}
        table td{height: {{ number_format($venta->pos_detalles_linea_alto, 1) }}cm;}

        .cantidad{ width: {{ number_format($venta->pos_detalles_cantidad, 1) }}cm; text-align: center;}
        .producto{ width: {{ number_format($venta->pos_detalles_producto, 1) }}cm;}
        .precio{ width: {{ number_format($venta->pos_detalles_precio, 1) }}cm; text-align: right;}
        .sujetas{ width: {{ number_format($venta->pos_detalles_sujetas, 1) }}cm; text-align: right;}
        .exentas{ width: {{ number_format($venta->pos_detalles_exentas, 1) }}cm; text-align: right;}
        .gravadas{ width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
        
        #letras     {position: absolute; top: {{ number_format($venta->pos_letras_y, 1) }}cm; left: {{ number_format($venta->pos_letras_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_producto, 1) }}cm;}
        #correlativo{position: absolute; top: {{ number_format($venta->pos_correlativo_y, 1) }}cm; left: {{ number_format($venta->pos_letras_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_producto, 1) }}cm;}

        #suma       {position: absolute; top: {{ number_format($venta->pos_sumas_y, 1) }}cm; left: {{ number_format($venta->pos_sumas_x, 1)}}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
		#iva		  {position: absolute; top: 15cm; left: {{ number_format($venta->pos_sumas_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
        #subtotal     {position: absolute; top: 15.6cm; left: {{ number_format($venta->pos_sumas_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
        #iva_percibido{position: absolute; top: 16.3cm; left: {{ number_format($venta->pos_sumas_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
        #iva_retenido {position: absolute; top: 17cm; left: {{ number_format($venta->pos_sumas_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
        #no_sujeta    {position: absolute; top: 17.6cm; left: {{ number_format($venta->pos_sumas_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
        #exenta       {position: absolute; top: 18.2cm; left: {{ number_format($venta->pos_sumas_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}
        #total        {position: absolute; top: 18.8cm; left: {{ number_format($venta->pos_sumas_x, 1) }}cm; width: {{ number_format($venta->pos_detalles_gravadas, 1) }}cm; text-align: right;}


		.no-print{position: absolute; top:0;}

	</style>
	
	<style media="print"> .no-print{display: none; } </style>

</head>
{{-- <body> --}}
<body onload="javascript:print();">

	<section style="border:1px solid #ffffff00; background-image: url('/img/credito.jpg'); background-repeat: no-repeat; background-size: 100% 100%; height: 14.2cm; position: relative;">
		<p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
		<p id="cliente">{{ $venta->nombre_cliente }}</p>
		<p id="direccion">{{ $venta->cliente->municipio }} {{ $venta->cliente->departamento }} {{ $venta->cliente->direccion }}</p>
		<p id="dui">{{ $venta->cliente->nit }}</p>
		<p id="nrc">{{ $venta->cliente->registro }}</p>
		<p id="giro">{{ \Illuminate\Support\Str::limit($venta->cliente->giro, 50, $end = '...') }}</p>
		{{-- <p id="condicion">
			@if ($venta->estado == "Pendiente")
				Credito
			@else
				Contado
			@endif
		</p> --}}
					
		<table>
			@foreach($venta->detalles as $detalle)
			<tr>
				<td class="cantidad">	{{ number_format($detalle->cantidad, 0) }}</td>
				<td class="producto">	{{ $detalle->nombre_producto  }}</td>
				<td class="precio">		${{number_format($detalle->precio , 2) }}</td>
				<td class="sujetas">	@if($detalle->no_sujeta > 0) ${{ number_format($detalle->no_sujeta, 2) }} @endif</td>
				<td class="exentas">	@if($detalle->exenta > 0) ${{ number_format($detalle->exenta, 2) }}  @endif</td>
				<td class="gravadas">	@if($detalle->gravada) ${{ number_format($detalle->gravada, 2) }} @endif</th>
			</tr>
			@if ($detalle->descuento > 0)
				<tr>
					<td class="cantidad"></td>
					<td class="producto">DESCUENTOS</td>
					<td class="precio"></td>
					<td class="sujetas"></td>
					<td class="exentas"></td>
					<td class="gravadas">- ${{ number_format($detalle->descuento, 2) }} </th>
				</tr>
			@endif
			@endforeach
		</table>

		<p id="suma">		${{ number_format($venta->subtotal, 2) }}</p>
		<p id="iva">		${{ number_format($venta->iva, 2) }}</p>
        <p id="subtotal">        ${{ number_format($venta->subtotal + $venta->iva, 2) }}</p>
		
		@if ($venta->iva_percibido > 0)
			<p id="iva_percibido">	${{ number_format($venta->iva_percibido, 2) }}</p>
		@endif
        @if ($venta->iva_retenido > 0)
            <p id="iva_retenido">   ${{ number_format($venta->iva_retenido, 2) }}</p>
        @endif
		@if ($venta->no_sujeta > 0)
			<p id="no_sujeta">	${{ number_format($venta->no_sujeta, 2) }}</p>
		@endif
		@if ($venta->exenta > 0)
			<p id="exenta">	${{ number_format($venta->exenta, 2) }}</p>
		@endif

		{{-- <p id="propina">	${{ number_format($venta->propina, 2) }}</p> --}}
		<p id="total">   	<b>${{ number_format($venta->total, 2) }}</b></p>

		<p id="letras">{{ $venta->total_letras }}</p>
		{{-- <p id="correlativo">{{ $venta->correlativo }}</p> --}}

	</section>
	
    <p class="no-print">
        <button onClick="window.print();" autofocus>Imprimir</button>
        <button onClick="window.close();" autofocus>Cerrar</button>
        <br><br>
    </p>

</body>
</html>
