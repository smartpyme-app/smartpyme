<!DOCTYPE html>
<html lang="es">
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
{{-- <body> --}}
<body onload="javascript:print();">

    <section style="border:1px solid #ffffff00;background-image: url('/img/factura.jpg'); background-repeat: no-repeat; background-size: 100% 100%; height: 21.59cm; width: 13.97cm; position: relative;">
        <p id="fecha">19/01/2022</p>
        <p id="cliente">Cliente Prueba Factura</p>
        <p id="direccion">Calle, casa, dirección</p>
        <p id="departamento">Departamento</p>
        <p id="nit">1234-1234-123-1</p>

        <p id="condicion">
            <span style="left: 300pt;">Contado</span>
        </p>
        
        <table>
            <tr>
                <td class="cantidad">2</td>
                <td class="producto">Uno</td>
                <td class="precio">$ 15.00</td>
                <td class="sujetas"></td>
                <td class="exentas"></td>
                <td class="gravadas">$30.00</th>
            </tr>
            <tr>
                <td class="cantidad">2</td>
                <td class="producto">Uno</td>
                <td class="precio">$ 15.00</td>
                <td class="sujetas"></td>
                <td class="exentas"></td>
                <td class="gravadas">$30.00</th>
            </tr>
            <tr>
                <td class="cantidad">2</td>
                <td class="producto">Uno</td>
                <td class="precio">$ 15.00</td>
                <td class="sujetas"></td>
                <td class="exentas"></td>
                <td class="gravadas">$30.00</th>
            </tr>
        </table>

        <p id="suma">       $ 30.00</p>
        

        {{-- <p id="propina">    $ 1.00</p> --}}
        <p id="total"><b>   $ 30.00</b></p>

        <p id="letras"> Treinta y Cuatro 50/100</p>
        <p id="correlativo">123</p>


    </section>

    <button class="no-print" onClick="window.close();" autofocus>Cerrar</button>


</div>
</body>
</html>