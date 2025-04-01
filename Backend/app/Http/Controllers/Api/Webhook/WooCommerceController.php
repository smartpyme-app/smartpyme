<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsToWooCommerce;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Services\WooCommerceApiClient;
use Illuminate\Http\Request;
use App\Services\WooCommerceTransformer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\CommonMark\Block\Element\Document;

class WooCommerceController extends Controller
{
    protected $transformer;

    public function __construct(WooCommerceTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function procesarVenta($tokenEmpresa, Request $request)
    {
        Log::info("Webhook recibido para token: {$tokenEmpresa}");

        if ($request->webhook_id != null) {
            return response()->json(['message' => 'Webhook válido'], 200);
        }

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)->where('woocommerce_status', 'connected')->first();

        if (!$empresa) {
            Log::error("Token de empresa no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }
        $usuario = User::where('id_empresa', $empresa->id)->where('woocommerce_status', 'connected')->first();

        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }


        if ($empresa->facturacion_electronica) {
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)->where('nombre', 'Factura')->where('activo', true)->first();
        } else {
            //Buscar Ticket
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)->where('nombre', 'Ticket')->where('activo', true)->first();
        }
        try {
            DB::beginTransaction();

            $request->merge(['id_empresa' => $usuario->id_empresa, 'id_usuario' => $usuario->id, 'id_bodega' => $usuario->id_bodega, 'id_sucursal' => $usuario->id_sucursal, 'id_documento' => $documento->id, 'id_canal' => $empresa->woocommerce_canal_id]);

            $clienteData = $this->transformer->transformarCliente($request->all());
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );

            // 2. Crear Venta
            $ventaData = $this->transformer->transformarVenta($request->all(), $cliente->id, $documento->id, $documento->correlativo);
            $venta = Venta::create($ventaData);

            foreach ($request->line_items as $item) {
                //$producto = Producto::where('codigo', $item['sku'])->where('id_empresa', $usuario->id_empresa)->first();
                //primero buscar por woocommerce_id si no por sku

                $producto = Producto::where('woocommerce_id', $item['id'])->where('id_empresa', $usuario->id_empresa)->first();

                if (!$producto) {
                    $producto = Producto::where('codigo', $item['sku'])->where('id_empresa', $usuario->id_empresa)->first();
                }

                if (!$producto) {
                    return response()->json([
                        'status' => 'error',
                        'mensaje' => 'Producto no encontrado: ' . $item['sku']
                    ], 500);
                    // throw new \Exception("Producto no encontrado: {$item['sku']}");
                    //crear el producto
                    $productoData = $this->transformer->transformarProducto($item, $usuario->id_empresa, $usuario->id, $usuario->id_sucursal);
                    $producto = Producto::create($productoData);
                }

                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id);
                $detalleData['id_producto'] = $producto->id;
                $venta->detalles()->create($detalleData);

                $inventarioData = $this->transformer->actualizarInventario(
                    $producto->id,
                    $item['quantity'],
                    $venta->id_bodega
                );

                Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->decrement('stock', $item['quantity']);

                $inventario = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->first();

                if ($inventario) {
                    $inventario->kardex($venta, $item['quantity'], $item['price']);
                }

                // Inventario::updateOrCreate(
                //     ['id_producto' => $producto->id, 'id_bodega' => $venta->id_bodega],
                //     ['stock' => $inventarioData['stock']]
                // );
            }

            $documento = Documento::findOrfail($venta->id_documento);
            $documento->increment('correlativo');

            DB::commit();

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta procesada correctamente',
                'venta_id' => $venta->id
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error procesando venta de WooCommerce: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function exportarWooCommerce(Request $request)
    {
        // Verificar que el usuario tiene configuración de WooCommerce
        $user = Auth::user();
        $empresa = Empresa::find($user->id_empresa);

        if (
            empty($empresa->woocommerce_api_key) ||
            empty($empresa->woocommerce_store_url) ||
            empty($empresa->woocommerce_consumer_key) ||
            empty($empresa->woocommerce_consumer_secret)
        ) {

            return response()->json([
                'status' => 'error',
                'mensaje' => 'No tienes configurada la integración con WooCommerce'
            ], 400);
        }

        if ($empresa->woocommerce_status != 'connected') {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa debe estar activa con integración de WooCommerce'
            ], 400);
        }

        // Obtener la sucursal actual del usuario
        $sucursalId = $user->id_bodega;

        // Encolar el trabajo
        ExportProductsToWooCommerce::dispatch($user->id, $sucursalId);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Exportación de productos iniciada. Este proceso puede tomar varios minutos.'
        ]);
    }

    public function ventas(Request $request)
    {
        // Verificamos el token de la solicitud
        $tokenEmpresa = $request->token ?? $request->header('X-WC-Token');

        if (!$tokenEmpresa) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de empresa no proporcionado'
            ], 401);
        }

        Log::info("Solicitud de procesamiento masivo de ventas recibida para token: {$tokenEmpresa}");

        // Validar que la solicitud contiene un array de ventas
        if (!$request->has('ventas') || !is_array($request->ventas)) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'El formato de la solicitud es incorrecto. Se espera un array de ventas.'
            ], 400);
        }

        // Verificar la empresa
        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('woocommerce_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        $resultados = [];
        $errores = [];
        $procesadas = 0;
        $fallidas = 0;
        foreach ($request->ventas as $index => $ventaData) {
            try {
                DB::beginTransaction();

                $idVendedor = null;
                if (isset($ventaData['codigo_vendedor']) && !empty($ventaData['codigo_vendedor'])) {
                    $vendedor = User::where('codigo', $ventaData['codigo_vendedor'])
                        ->where('id_empresa', $empresa->id)
                        ->where('enable', 1)
                        ->first();

                    if ($vendedor) {
                        $idVendedor = $vendedor->id;
                    } else {
                        Log::warning("Vendedor con código {$ventaData['codigo_vendedor']} no encontrado. Usando usuario predeterminado.");
                    }
                }

                $idSucursal = null;
                if (isset($ventaData['codigo_sucursal']) && !empty($ventaData['codigo_sucursal'])) {
                    $sucursalEspecifica = Sucursal::where('cod_estable_mh', $ventaData['codigo_sucursal'])
                        ->where('id_empresa', $empresa->id)
                        ->where('activo', 1)
                        ->first();

                    if ($sucursalEspecifica) {
                        $idSucursal = $sucursalEspecifica->id;
                        $sucursal = $sucursalEspecifica;
                    } else {
                        Log::warning("Sucursal con código {$ventaData['codigo_sucursal']} no encontrada. Usando sucursal predeterminada.");
                    }
                }

                // Obtener el documento apropiado para la sucursal específica de esta venta
                if ($empresa->facturacion_electronica) {
                    $documento = Documento::where('id_sucursal', $idSucursal)
                        ->where('nombre', 'Factura')
                        ->where('activo', true)
                        ->first();
                } else {
                    $documento = Documento::where('id_sucursal', $idSucursal)
                        ->where('nombre', 'Ticket')
                        ->where('activo', true)
                        ->first();
                }

                // Verificar que existe un documento válido para esta sucursal
                if (!$documento) {
                    throw new \Exception("No se encontró un documento válido para la sucursal seleccionada (ID: {$idSucursal})");
                }

               
                $idBodega = null;
                if (isset($ventaData['codigo_bodega']) && !empty($ventaData['codigo_bodega'])) {
                    $bodegaEspecifica = DB::table('sucursal_bodegas')
                        ->where('cod_estable_mh', $ventaData['codigo_bodega'])
                        ->where('id_sucursal', $idSucursal) // Debe pertenecer a la sucursal seleccionada
                        ->where('id_empresa', $empresa->id)
                        ->where('activo', 1)
                        ->first();

                    if ($bodegaEspecifica) {
                        $idBodega = $bodegaEspecifica->id;
                        $bodega = $bodegaEspecifica;
                    } else {
                        Log::warning("Bodega con código {$ventaData['codigo_bodega']} no encontrada. Usando bodega predeterminada.");
                    }
                }

                // Obtener ID del canal de WooCommerce
                $canalId = $empresa->woocommerce_canal_id;

                // Buscar canal por nombre si viene en la venta
                if (isset($ventaData['codigo_canal']) && !empty($ventaData['codigo_canal'])) {
                    $canalEspecifico = DB::table('canales')
                        ->where('nombre', $ventaData['codigo_canal'])
                        ->where('id_empresa', $empresa->id)
                        ->first();

                    if ($canalEspecifico) {
                        $canalId = $canalEspecifico->id;
                    } else {
                        Log::warning("Canal con código {$ventaData['codigo_canal']} no encontrado. Usando canal WooCommerce predeterminado.");
                    }
                }

                // Si no hay canal especificado o no se encontró, asegurarse de tener un canal WooCommerce
                if (!$canalId) {
                    $canal = DB::table('canales')
                        ->where('nombre', 'WooCommerce')
                        ->where('id_empresa', $empresa->id)
                        ->first();

                    if ($canal) {
                        $canalId = $canal->id;
                    } else {
                        // Crear un canal para WooCommerce si no existe
                        $canalId = DB::table('canales')->insertGetId([
                            'nombre' => 'WooCommerce',
                            'descripcion' => 'Canal de ventas de WooCommerce',
                            'id_empresa' => $empresa->id,
                            'enable' => 1,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    // Actualizar la empresa con el canal de WooCommerce
                    $empresa->woocommerce_canal_id = $canalId;
                    $empresa->save();
                }

                // Mezclar los datos de la venta con la información del sistema
                $ventaCompleta = array_merge($ventaData, [
                    'id_empresa' => $empresa->id,
                    'id_usuario' => $idVendedor,      // Usuario que registra la venta
                    'id_vendedor' => $idVendedor,     // Vendedor asignado a la venta
                    'id_bodega' => $idBodega,         // Bodega identificada
                    'id_sucursal' => $idSucursal,     // Sucursal identificada
                    'id_documento' => $documento->id,
                    'id_canal' => $canalId            // Canal identificado
                ]);

                // 1. Procesar cliente
                $clienteData = $this->transformer->transformarCliente($ventaCompleta);
                $cliente = Cliente::updateOrCreate(
                    ['correo' => $clienteData['correo'], 'id_empresa' => $empresa->id],
                    $clienteData
                );

                // 2. Crear Venta
                $ventaTransformada = $this->transformer->transformarVenta(
                    $ventaCompleta,
                    $cliente->id,
                    $documento->id,
                    $documento->correlativo
                );
                $ventaTransformada['woocommerce_id'] = $ventaData['id']; // Guardar el ID de WooCommerce
                $venta = Venta::create($ventaTransformada);

                // 3. Procesar líneas de productos
                if (!isset($ventaData['line_items']) || !is_array($ventaData['line_items'])) {
                    throw new \Exception("La venta {$ventaData['id']} no contiene líneas de productos válidas");
                }

                foreach ($ventaData['line_items'] as $item) {
                    // Buscar primero por woocommerce_id, luego por SKU
                    $producto = Producto::where('woocommerce_id', $item['product_id'])
                        ->where('id_empresa', $empresa->id)
                        ->first();

                    if (!$producto) {
                        $producto = Producto::where('codigo', $item['sku'])
                            ->where('id_empresa', $empresa->id)
                            ->first();
                    }

                    // Si no se encuentra el producto, crearlo
                    if (!$producto) {
                        $productoData = $this->transformer->transformarProducto(
                            $item,
                            $empresa->id,
                            $idVendedor,
                            $idSucursal
                        );
                        $productoData['woocommerce_id'] = $item['product_id'];
                        $producto = Producto::create($productoData);
                    }

                    // Crear detalle de venta
                    $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id);
                    $detalleData['id_producto'] = $producto->id;
                    $detalleData['id_vendedor'] = $ventaCompleta['id_vendedor']; // Asignar el vendedor al detalle
                    $venta->detalles()->create($detalleData);

                    // Actualizar inventario
                    $inventario = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->first();

                    if ($inventario) {
                        $inventario->decrement('stock', $item['quantity']);
                        $inventario->kardex($venta, $item['quantity'], $item['price']);
                    } else {
                        // Crear nuevo registro de inventario con stock negativo
                        $nuevoInventario = new Inventario([
                            'id_producto' => $producto->id,
                            'id_bodega' => $venta->id_bodega,
                            'stock' => -$item['quantity']
                        ]);
                        $nuevoInventario->save();
                        $nuevoInventario->kardex($venta, $item['quantity'], $item['price']);
                    }
                }

                // Incrementar el correlativo del documento
                $documento->increment('correlativo');

                DB::commit();

                // Obtener información adicional para incluir en la respuesta
                $infoVendedor = User::select('id', 'name', 'codigo')->find($idVendedor);
                $infoSucursal = Sucursal::select('id', 'nombre')->find($sucursal->id);
                $infoBodega = DB::table('sucursal_bodegas')->select('id', 'nombre')->find($bodega->id);
                $infoCanal = DB::table('canales')->select('id', 'nombre')->find($canalId);

                $resultados[] = [
                    'woocommerce_id' => $ventaData['id'],
                    'estado' => 'procesada',
                    'mensaje' => 'Venta procesada correctamente',
                    'venta_id' => $venta->id,
                    'asignaciones' => [
                        'sucursal' => [
                            'id' => $infoSucursal->id,
                            'nombre' => $infoSucursal->nombre
                        ],
                        'vendedor' => [
                            'id' => $infoVendedor->id,
                            'nombre' => $infoVendedor->name,
                            'codigo' => $infoVendedor->codigo
                        ],
                        'bodega' => [
                            'id' => $infoBodega->id,
                            'nombre' => $infoBodega->nombre
                        ],
                        'canal' => [
                            'id' => $infoCanal->id,
                            'nombre' => $infoCanal->nombre
                        ]
                    ]
                ];

                $procesadas++;
            } catch (\Exception $e) {
                DB::rollBack();

                $ventaId = isset($ventaData['id']) ? $ventaData['id'] : 'N/A';
                Log::error("Error procesando venta #{$index} (ID: {$ventaId}): " . $e->getMessage());

                $errores[] = [
                    'woocommerce_id' => $ventaData['id'] ?? 'desconocido',
                    'estado' => 'error',
                    'mensaje' => $e->getMessage()
                ];

                $fallidas++;
            }
        }

        return response()->json([
            'status' => 'completed',
            'total_procesadas' => $procesadas,
            'total_fallidas' => $fallidas,
            'resultados' => $resultados,
            'errores' => $errores
        ], $fallidas > 0 ? 207 : 200); 
    }
}
