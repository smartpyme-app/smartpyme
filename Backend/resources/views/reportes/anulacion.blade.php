<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <script language="javascript">setTimeout("self.close();",2000)</script>
    <title>Credito</title>
    <style>
        *{ font-size: 9pt; }
        @font-face{
            font-family:'Dot';
            src: url('{{ asset('fonts/Dot.ttf') }}') format('truetype');
        }
        html, body {
            width: 14cm; /* was 8.5in */
            height: 14cm; /* was 5.5in */
            display: block;
            /*font-family: "Dot";*/
            font-family: serif;
            margin: 0pt;
            padding: 0pt;
        }
        

        h1 {position: absolute; top: 160pt; left: 70pt; font-size: 45pt; transform: rotate(-45deg);}

    </style>
    
    <style media="print"> .no-print{display: none; } </style>

</head>
<body onload="javascript:print();">
{{-- <body> --}}

    <section style="border:1px solid #ffffff00; background-image: url('/img/credito.jpg'); background-repeat: no-repeat; background-size: 14cm 14cm; height: 14cm; position: relative;">
        <h1 >ANULADA</h1>
    </section>
    
    <button class="no-print" onClick="window.close();" autofocus>Cerrar</button>

</body>
</html>