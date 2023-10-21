<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventario de {{ $bodega->nombre }}</title>
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
    <h2 class="text-center">Inventario de {{ $bodega->nombre }}</h2>
    <legend>Datos:</legend>
    <table>
        <tr>
            <td width="50%"><b>Fecha:</b> {{ $bodega->fecha->format('d/m/Y') }}</td>
            <td width="50%"><b>Realizada por:</b> {{ $bodega->usuario }}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td width="50%"><b>N° Productos:</b> {{ $bodega->productos->count() }}</td>
            <td width="50%"><b>Existencias:</b> {{ $bodega->productos->sum('stock') }}</td>
        </tr>
    </table>
    {{-- <table>
        <tr>
            <td width="33%"><b>Costo:</b>  ${{ number_format($bodega->productos->sum('costoTotal'), 2) }}</td>
            <td width="33%"><b>Precio:</b> ${{ number_format($bodega->productos->sum('precioTotal'), 2) }}</td>
            <td width="33%"><b>Utilidad:</b> ${{ number_format($bodega->productos->sum('precioTotal') - $bodega->productos->sum('costoTotal'), 2) }}</td>
        </tr>
    </table> --}}
    <br>
    <legend>Listado:</legend>
    <table>
        <thead>
            <tr>
                <th class="text-center">N°</th>
                <th>Producto</th>
                <th class="text-center">Categoria</th>
                <th class="text-center">Stock</th>
                <th class="text-center">Costo</th>
                <th class="text-center">Total</th>
                <th class="text-center">Precio</th>
                <th class="text-center">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bodega->productos as $key => $producto)
            <tr>
                <td class="text-center">{{ $key + 1}}</td>
                <td>{{ $producto['nombre'] }}</td>
                <td class="text-center">{{ $producto['categoria'] }} / {{ $producto['subcategoria'] }}</td>
                <td class="text-center">{{ $producto['stock'] }}</td>
                <td class="text-center">${{ number_format($producto['costo'], 2) }}</td>
                <td class="text-center">${{ number_format($producto['costoTotal'], 2) }}</td>
                <td class="text-center">${{ number_format($producto['precio'], 2) }}</td>
                <td class="text-center">${{ number_format($producto['precioTotal'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>