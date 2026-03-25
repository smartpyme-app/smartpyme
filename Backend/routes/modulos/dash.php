<?php 

use App\Http\Controllers\Api\DashController;
use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Http\Controllers\Api\Ventas\Cotizaciones\CotizacionesController;

    
    Route::get('/dash',                        [DashController::class, 'index']);
    
    Route::get('/dash/organizaciones',         [DashController::class, 'organizaciones']);

    Route::get('/admin',                       [DashController::class, 'admin']);
    
    Route::get('/corte',         [DashController::class, 'corte']);
    Route::get('/corte/documento/{id_usuario?}/{id_sucursal?}/{fecha?}', [DashController::class, 'cortePdf'])->name('corte');

    Route::get('/dash/vendedor',                [DashController::class, 'vendedor']);
    Route::get('/dash/vendedor/productos',                [ProductosController::class, 'vendedor']);
    Route::get('/dash/vendedor/productos/buscar/{txt}',   [ProductosController::class, 'vendedorBuscador']);

    Route::get('/dash/vendedor/Cotizaciones',                [CotizacionesController::class, 'vendedor']);
    Route::get('/dash/vendedor/Cotizaciones/buscar/{txt}',   [CotizacionesController::class, 'vendedorBuscador']);

    Route::get('/dash/cajero/{id}',             [DashController::class, 'cajero']);

    Route::get('/barcode/{codigo}',             [DashController::class, 'barcode']);

    Route::get('/buscador/{txt}',             [DashController::class, 'buscador']);


    Route::get('/set-campos-nuevos', function () {
        $ventas = App\Models\Ventas\Venta::with('detalles', 'empresa')
        ->whereBetween('created_at', [
            now()->subMonths(3)->startOfMonth(),
            now()->endOfMonth()
        ])          
        ->get();

        foreach ($ventas as $venta) {
            if ($venta->iva > 0) {
              $venta->tipo_operacion = 'Gravada'; // Aplica IVA
            } else {
              $venta->tipo_operacion = 'No Gravada'; // No aplica IVA
            }

            // Tipo de renta
            if ($venta->detalles->count() > 0) {
                $detalle = $venta->detalles->first();

                if ($detalle->tipo == 'Servicio') {
                    $venta->tipo_renta = $venta->empresa->tipo_renta_servicios ?? null;
                } else {
                    $venta->tipo_renta = $venta->empresa->tipo_renta_productos ?? null;
                }
            }

            $venta->save();
        }

        $compras = App\Models\Compras\Compra::with('empresa')
        ->whereBetween('created_at', [
          now()->subMonths(3)->startOfMonth(),
          now()->endOfMonth()
      ])          
      ->get();

        foreach ($compras as $compra) {
            if ($compra->iva > 0) {
              $compra->tipo_operacion = 'Gravada'; // Aplica IVA
            } else {
              $compra->tipo_operacion = 'No Gravada'; // No aplica IVA
            }

            $compra->tipo_clasificacion = 'Costo';
            $compra->tipo_costo_gasto = 'Costo artículos producidos/comprados interno';
            $compra->tipo_sector =  $compra->empresa->tipo_sector ?? null;
            $compra->save();

        }

        $gastos = App\Models\Compras\Gastos\Gasto::with('empresa')
        ->whereBetween('created_at', [
          now()->subMonths(3)->startOfMonth(),
          now()->endOfMonth()
      ])          
      ->get();

        foreach ($gastos as $gasto) {
            if ($gasto->iva > 0) {
              $gasto->tipo_operacion = 'Gravada'; // Aplica IVA
            } else {
              $gasto->tipo_operacion = 'No Gravada'; // No aplica IVA
            }

            $gasto->tipo_clasificacion = 'Gasto';
            $gasto->tipo_costo_gasto = 'Gastos de venta sin donación';
            $gasto->tipo_sector =  $gasto->empresa->tipo_sector ?? null;
            $gasto->save();

        }

         return response()->json($ventas, 200);
    });

