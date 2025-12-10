<!DOCTYPE html>
<html>
<head>
    <title> Fumigadora Vector {{$cheque->correlativo}}</title>
    <style>

        *{ font-size: 13px; margin: 0; padding: 0;}

        #factura{
            font-family: serif;
            width: 20cm;
            height: 10cm;
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        #header>*{position: absolute;}

        #lugarfecha          {top: 2cm; left: 3.5cm; width: 9cm;}
        #anio          {top: 2cm; left: 12cm; width: 2cm;}
        #anombrede      {top: 3cm; left: 4.2cm; width: 14cm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
        #nombre_cuenta  {top: 4.5cm; left: 2.7cm; width: 9cm;}
        /* #concepto       {top: 6.5cm; left: 4.5cm; } */
        #total          {top: 2cm; left: 14.5cm; }
        #letras         {top: 3.8cm; left: 3.8cm; width: 14cm; word-break: break-all; white-space: normal;}
        
        .no-print{position: absolute;}  

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div id="header">

        <p id="lugarfecha">
            {{ $cheque->empresa->municipio }}, {{ $cheque->empresa->departamento }}, {{ \Carbon\Carbon::now()->format('d') }} de {{ \Carbon\Carbon::now()->translatedFormat('F') }}
        </p>
        <p id="anio">{{ \Carbon\Carbon::parse($cheque->fecha)->format('Y') }}</p>
        <p id="anombrede">{{ $cheque->anombrede }}</p>
        <!-- <p id="concepto">{{ $cheque->concepto }}</p> -->
        <p id="total">{{ $cheque->total }}</p>

        <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
    </div>

</section>

</body>
</html>
