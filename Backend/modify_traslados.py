import re

with open('/Users/joseespana/Documents/GitHub/SP/smartpyme/Backend/app/Http/Controllers/Api/Inventario/TrasladosController.php', 'r') as f:
    content = f.read()

# Add imports
imports_to_add = """use App\Services\Inventario\ConversionInventarioService;
use App\Models\Inventario\ProductoPresentacion;
"""
content = re.sub(r'(use App\\Models\\Inventario\\Lote;)', r'\1\n' + imports_to_add, content)

# 1. Update store
def modify_store(match):
    store_body = match.group(0)
    
    # Insert id_presentacion and cantidadBase logic
    calc_logic = """
        // Asignar presentacion si viene
        $traslado->id_presentacion = $request->id_presentacion ?: null;

        // Calcular cantidad base
        $factor = 1;
        if ($traslado->id_presentacion) {
            $presentacion = ProductoPresentacion::find($traslado->id_presentacion);
            if ($presentacion) {
                $factor = (float) $presentacion->factor_conversion;
            }
        }
        $cantidadOriginal = (float) $request->cantidad;
        $cantidadBase = ConversionInventarioService::calcularCantidadBase($cantidadOriginal, $factor);
"""
    store_body = store_body.replace('$traslado->fill($request->all());\n', '$traslado->fill($request->all());\n' + calc_logic)
    
    # Replace $request->cantidad with $cantidadBase in store
    store_body = re.sub(r'\$cantidadRequerida\s*=\s*\(float\)\s*\$request->cantidad;', '$cantidadRequerida = $cantidadBase;', store_body)
    store_body = store_body.replace('$loteDestino->stock += $request->cantidad;', '$loteDestino->stock += $cantidadBase;')
    store_body = store_body.replace("'stock' => $request->cantidad,", "'stock' => $cantidadBase,")
    store_body = store_body.replace("'stock_inicial' => $request->cantidad,", "'stock_inicial' => $cantidadBase,")
    
    store_body = store_body.replace('if ($origen->stock < $request->cantidad) {', 'if ($origen->stock < $cantidadBase) {')
    store_body = store_body.replace('$origen->stock -= $traslado->cantidad;', '$origen->stock -= $cantidadBase;')
    store_body = store_body.replace('$origen->kardex($traslado, $traslado->cantidad * -1);', '$origen->kardex($traslado, $cantidadBase * -1);')
    store_body = store_body.replace('$destino->stock += $traslado->cantidad;', '$destino->stock += $cantidadBase;')
    store_body = store_body.replace('$destino->kardex($traslado, $traslado->cantidad);', '$destino->kardex($traslado, $cantidadBase);')
    
    store_body = store_body.replace('$cantidad = $traslado->cantidad * $comp->cantidad;', '$cantidad = $cantidadBase * $comp->cantidad;')
    
    return store_body

content = re.sub(r'public function store\(Request \$request\)\{.*?(?=private function storeConDetalles)', modify_store, content, flags=re.DOTALL)

# 2. Update storeConDetalles
def modify_store_con_detalles(match):
    store_body = match.group(0)
    
    calc_logic = """
                $idPresentacion = $detalleData['id_presentacion'] ?? null;
                $factor = 1;
                if ($idPresentacion) {
                    $presentacion = ProductoPresentacion::find($idPresentacion);
                    if ($presentacion) {
                        $factor = (float) $presentacion->factor_conversion;
                    }
                }
                $cantidadOriginal = (float) $detalleData['cantidad'];
                $cantidadBase = ConversionInventarioService::calcularCantidadBase($cantidadOriginal, $factor);
"""
    store_body = store_body.replace('if ($producto->inventario_por_lotes && (!isset($detalleData[\'lote_id\']) || !$detalleData[\'lote_id\'])) {', calc_logic + '\n                if ($producto->inventario_por_lotes && (!isset($detalleData[\'lote_id\']) || !$detalleData[\'lote_id\'])) {')
    
    # Replace $detalleData['cantidad'] with $cantidadBase where appropriate
    store_body = store_body.replace('$cantidadRequerida = (float) $detalleData[\'cantidad\'];', '$cantidadRequerida = $cantidadBase;')
    store_body = store_body.replace('$loteDestino->stock += $detalleData[\'cantidad\'];', '$loteDestino->stock += $cantidadBase;')
    store_body = store_body.replace("'stock' => $detalleData['cantidad'],", "'stock' => $cantidadBase,")
    store_body = store_body.replace("'stock_inicial' => $detalleData['cantidad'],", "'stock_inicial' => $cantidadBase,")
    
    store_body = store_body.replace('if (!$origen || $origen->stock < $detalleData[\'cantidad\']) {', 'if (!$origen || $origen->stock < $cantidadBase) {')
    
    # Wait, the assignment of $traslado->cantidad should remain the original request quantity.
    # $traslado->id_presentacion = $idPresentacion; needs to be added
    traslado_assign = """                $traslado->id_presentacion = $idPresentacion;
                $traslado->cantidad = $detalleData['cantidad'];"""
    store_body = store_body.replace("$traslado->cantidad = $detalleData['cantidad'];", traslado_assign)
    
    store_body = store_body.replace('$origen->stock -= $detalleData[\'cantidad\'];', '$origen->stock -= $cantidadBase;')
    store_body = store_body.replace('$origen->kardex($traslado, $detalleData[\'cantidad\'] * -1);', '$origen->kardex($traslado, $cantidadBase * -1);')
    store_body = store_body.replace('$destino->stock += $detalleData[\'cantidad\'];', '$destino->stock += $cantidadBase;')
    store_body = store_body.replace('$destino->kardex($traslado, $detalleData[\'cantidad\']);', '$destino->kardex($traslado, $cantidadBase);')
    store_body = store_body.replace('$destino->stock = $detalleData[\'cantidad\'];', '$destino->stock = $cantidadBase;')
    
    return store_body

content = re.sub(r'private function storeConDetalles\(Request \$request\)\s*\{.*?(?=public function delete)', modify_store_con_detalles, content, flags=re.DOTALL)

# 3. Update delete
def modify_delete(match):
    store_body = match.group(0)
    
    calc_logic = """
        $factor = 1;
        if ($traslado->id_presentacion) {
            $presentacion = ProductoPresentacion::find($traslado->id_presentacion);
            if ($presentacion) {
                $factor = (float) $presentacion->factor_conversion;
            }
        }
        $cantidadBase = ConversionInventarioService::calcularCantidadBase((float) $traslado->cantidad, $factor);
"""
    store_body = store_body.replace('$traslado->save();\n\n        $producto = Producto::where(\'id\', $traslado->id_producto)->with(\'composiciones\')->firstOrFail();', '$traslado->save();\n\n        $producto = Producto::where(\'id\', $traslado->id_producto)->with(\'composiciones\')->firstOrFail();' + calc_logic)
    
    store_body = store_body.replace('$loteOrigen->stock += $traslado->cantidad;', '$loteOrigen->stock += $cantidadBase;')
    store_body = store_body.replace('$loteDestino->stock -= $traslado->cantidad;', '$loteDestino->stock -= $cantidadBase;')
    
    store_body = store_body.replace('$origen->stock += $traslado->cantidad;', '$origen->stock += $cantidadBase;')
    store_body = store_body.replace('$origen->kardex($traslado, $traslado->cantidad * -1);', '$origen->kardex($traslado, $cantidadBase * -1);')
    store_body = store_body.replace('$destino->stock -= $traslado->cantidad;', '$destino->stock -= $cantidadBase;')
    store_body = store_body.replace('$destino->kardex($traslado, $traslado->cantidad);', '$destino->kardex($traslado, $cantidadBase);')
    
    store_body = store_body.replace('$cantidad = $traslado->cantidad * $comp->cantidad;', '$cantidad = $cantidadBase * $comp->cantidad;')
    
    return store_body

content = re.sub(r'public function delete\(\$id\)\{.*?(?=public function update)', modify_delete, content, flags=re.DOTALL)

# 4. Update update
def modify_update(match):
    store_body = match.group(0)
    
    calc_logic = """
            $factor = 1;
            if ($traslado->id_presentacion) {
                $presentacion = ProductoPresentacion::find($traslado->id_presentacion);
                if ($presentacion) {
                    $factor = (float) $presentacion->factor_conversion;
                }
            }
            $cantidadBase = ConversionInventarioService::calcularCantidadBase((float) $traslado->cantidad, $factor);
"""
    store_body = store_body.replace('$producto = Producto::where(\'id\', $traslado->id_producto)->with(\'composiciones\')->firstOrFail();', '$producto = Producto::where(\'id\', $traslado->id_producto)->with(\'composiciones\')->firstOrFail();' + calc_logic)
    
    store_body = store_body.replace('$cantidadRequerida = (float) $traslado->cantidad;', '$cantidadRequerida = $cantidadBase;')
    store_body = store_body.replace('$loteDestino->stock += $traslado->cantidad;', '$loteDestino->stock += $cantidadBase;')
    store_body = store_body.replace("'stock' => $traslado->cantidad,", "'stock' => $cantidadBase,")
    store_body = store_body.replace("'stock_inicial' => $traslado->cantidad,", "'stock_inicial' => $cantidadBase,")
    
    store_body = store_body.replace('if (!$origen || $origen->stock < $traslado->cantidad) {', 'if (!$origen || $origen->stock < $cantidadBase) {')
    store_body = store_body.replace('$origen->stock -= $traslado->cantidad;', '$origen->stock -= $cantidadBase;')
    store_body = store_body.replace('$origen->kardex($traslado, $traslado->cantidad * -1);', '$origen->kardex($traslado, $cantidadBase * -1);')
    store_body = store_body.replace('$destino->stock += $traslado->cantidad;', '$destino->stock += $cantidadBase;')
    store_body = store_body.replace('$destino->kardex($traslado, $traslado->cantidad);', '$destino->kardex($traslado, $cantidadBase);')
    
    store_body = store_body.replace('>= $traslado->cantidad * $comp->cantidad', '>= $cantidadBase * $comp->cantidad')
    store_body = store_body.replace('$cantidad = $traslado->cantidad * $comp->cantidad;', '$cantidad = $cantidadBase * $comp->cantidad;')
    
    return store_body

content = re.sub(r'public function update\(Request \$request, \$id\)\s*\{.*?(?=public function export)', modify_update, content, flags=re.DOTALL)

with open('/Users/joseespana/Documents/GitHub/SP/smartpyme/Backend/app/Http/Controllers/Api/Inventario/TrasladosController.php', 'w') as f:
    f.write(content)
