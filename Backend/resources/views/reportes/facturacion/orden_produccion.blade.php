<!DOCTYPE html>
<html>
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
   <title>Orden de Producción #{{ $orden->correlativo }}</title>
   <style>
       *{
           margin: 0cm;
           font-family: system-ui;
       }
       body {
           margin: 50px;
       }
       table{
           width: 100%;
           border-collapse: collapse;
       }
       .table th, .table td{
           border: 0px;
           padding: 10px 5px;
           text-align: left;
       }
       .text-right{ text-align: right !important; }
       .border-bottom{ border-bottom: 1px solid #000 !important; }
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
           <tr><td><h4>Cliente</h4></td></tr>
           <tr>
               <td>
                   <p>{{ $orden->nombre_cliente }}</p>
                   <p>{{ $orden->cliente->direccion }}</p>
               </td>
               <td>
                   <p>NCR: {{ $orden->cliente->ncr }}</p>
                   <p>DUI: {{ $orden->cliente->dui }}</p>
                   <p>Tel: {{ $orden->cliente->telefono }}</p>
               </td>
               <td class="text-right">
                   <p>Orden #{{ $orden->correlativo }}</p>
                   <p>Fecha: {{ \Carbon\Carbon::parse($orden->fecha)->format('d/m/Y') }}</p>
                   <p>Entrega: {{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }}</p>
               </td>
           </tr>
       </tbody>
   </table>

   <br>

   <table class="table">
       <thead>
           <tr>
               <th class="border-bottom">Descripción</th>
               <th class="border-bottom text-right">Cantidad</th>
               @foreach($orden->detalles->first()->customFields->groupBy('custom_field_id') as $customField)
                   <th class="border-bottom text-right">{{ $customField->first()->customField->name }}</th>
               @endforeach
               <th class="border-bottom text-right">Precio</th>
               <th class="border-bottom text-right">Total</th>
           </tr>
       </thead>
       <tbody>
           @foreach($orden->detalles as $detalle)
           <tr>
               <td class="border-bottom">{{ $detalle->descripcion }}</td>
               <td class="border-bottom text-right">{{ number_format($detalle->cantidad, 0) }}</td>
               @foreach($detalle->customFields as $customField)
                   <td class="border-bottom text-right">{{ $customField->value }}</td>
               @endforeach
               <td class="border-bottom text-right">${{ number_format($detalle->precio, 2) }}</td>
               <td class="border-bottom text-right">${{ number_format($detalle->total, 2) }}</td>
           </tr>
           @if ($detalle->descuento > 0)
               <tr>
                   <td>DESCUENTO</td>
                   <td colspan="4" class="text-right">-${{ number_format($detalle->descuento, 2) }}</td>
               </tr>
           @endif
           @endforeach
       </tbody>
       <tfoot>
           <tr>
               <td colspan="4" class="text-right">Subtotal</td>
               <td class="text-right">${{ number_format($orden->subtotal, 2) }}</td>
           </tr>
           <tr>
               <td colspan="4" class="text-right">Total</td>
               <td class="text-right"><b>${{ number_format($orden->total, 2) }}</b></td>
           </tr>
       </tfoot>
   </table>

   <br>
   <h4>Observaciones:</h4>
   <p>{!! nl2br(e($orden->observaciones)) !!}</p>
   <br><br><br>
   
   <h4>Firma:</h4>
   <p>____________________________</p>

</body>
</html>