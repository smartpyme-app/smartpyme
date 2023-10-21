<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Productos por Proveedor</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    *{ font-family: sans-serif; color: #333;}
    table {width: 100%; border-collapse: collapse; }
    td, th, td {border: 0.5px solid gray; padding: 5px 10px; font-size: 12px;} 
    p {margin: 5px 0px; }
    legend{text-align: center; width: 100%; font-weight: bold; margin-bottom: 10px;}

</style>

<body>
    <h1 class="text-center">{{ $empresa->nombre }}</h1>
    <h2 class="text-center">Productos por Proveedor</h2>
    <legend>Listado:</legend>

    <ol>
        @foreach($proveedores as $key => $proveedor)
        <li>
            <b>{{ $proveedor->nombre }}</b>
            <ul>
                @foreach ($proveedor->productos as $producto)
                <li>{{ $producto->nombre }}</li>
                @endforeach
            </ul>
            <br>
        </li>
        @endforeach
    </ol>

</body>
</html>