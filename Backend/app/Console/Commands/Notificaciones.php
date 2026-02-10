<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Notificacion;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Venta;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class Notificaciones extends Command
{

    protected $signature = 'generate:notificaciones';
    protected $description = 'Generar las notificaciones';

    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        
        $fechaStart = Carbon::today();
        $fechaEnd = Carbon::today()->addDay(3);

        $gastos = Gasto::withoutGlobalScopes()->where('estado', 'Pendiente')
                            ->whereBetween('fecha_pago', [$fechaStart, $fechaEnd])
                            ->get();
       
            foreach($gastos as $gasto){
                $descripcion = $gasto->tipo_documento . ' #' . $gasto->referencia . ' de ' . $gasto->nombre_proveedor . ' por $' . number_format($gasto->total, 2) . ' está por vencer el ' . Carbon::parse($gasto->fecha_pago)->format('d/m/Y') . '.';
                $exite = Notificacion::where('descripcion', $descripcion)->first();
                if (!$exite) {
                    Notificacion::create([
                        'titulo' => '💰 Gasto próximo a vencer',
                        'descripcion' => $descripcion,
                        'tipo' => 'Cuentas por pagar',
                        'categoria' => 'Gastos',
                        'prioridad' => 'Alta',
                        'leido' => false,
                        'referencia' => 'gasto',
                        'id_referencia' => $gasto->id,
                        'id_empresa' => $gasto->id_empresa,
                        'id_sucursal' => $gasto->id_sucursal,
                    ]);
                }
            }

        $compras = Compra::withoutGlobalScopes()->where('estado', 'Pendiente')
                            ->whereBetween('fecha_pago', [$fechaStart, $fechaEnd])
                            ->get();

                foreach($compras as $compra){
                    $descripcion = $compra->tipo_documento . ' #' . $compra->referencia .' de ' . $compra->nombre_proveedor .' por $' . number_format($compra->total, 2).' está por vencer el ' . Carbon::parse($compra->fecha_pago)->format('d/m/Y') . '.';
                    $exite = Notificacion::where('descripcion', $descripcion)->first();
                    if (!$exite) {
                        Notificacion::create([
                            'titulo' => '💰 Compra próximo a vencer',
                            'descripcion' => $descripcion,
                            'tipo' => 'Cuentas por pagar',
                            'categoria' => 'Compras',
                            'prioridad' => 'Alta',
                            'leido' => false,
                            'referencia' => 'compra',
                            'id_referencia' => $compra->id,
                            'id_empresa' => $compra->id_empresa,
                            'id_sucursal' => $compra->id_sucursal,
                        ]);
                    }
                }
       
        // Solo facturas (no cotizaciones) pendientes de cobro
        $ventas = Venta::withoutGlobalScopes()->where('estado', 'Pendiente')
                            ->where('cotizacion', 0)
                            ->whereBetween('fecha_pago', [$fechaStart, $fechaEnd])
                            ->get();

                foreach($ventas as $venta){
                    $descripcion = $venta->nombre_documento . ' #' . $venta->correlativo .' de ' .$venta->nombre_cliente .' por $' . number_format($venta->total, 2) .' está por vencer el ' . Carbon::parse($venta->fecha_pago)->format('d/m/Y') . '.';
                    $exite = Notificacion::where('descripcion', $descripcion)->first();
                    if (!$exite) {
                        Notificacion::create([
                            'titulo' => '💰 Venta pendiente de cobro',
                            'descripcion' => $descripcion,
                            'tipo' => 'Cuentas por cobrar',
                            'categoria' => 'Ventas',
                            'prioridad' => 'Alta',
                            'leido' => false,
                            'referencia' => 'venta',
                            'id_referencia' => $venta->id,
                            'id_empresa' => $venta->id_empresa,
                            'id_sucursal' => $venta->id_sucursal,
                        ]);
                    }
                }

        // Cotizaciones pendientes → notificación de seguimiento
        $cotizaciones = Venta::withoutGlobalScopes()->where('estado', 'Pendiente')
                            ->where('cotizacion', 1)
                            ->whereBetween('fecha_pago', [$fechaStart, $fechaEnd])
                            ->get();

                foreach($cotizaciones as $venta){
                    $descripcion = $venta->nombre_documento . ' #' . $venta->correlativo .' de ' .$venta->nombre_cliente .' por $' . number_format($venta->total, 2) .' está por vencer el ' . Carbon::parse($venta->fecha_pago)->format('d/m/Y') . '.';
                    $exite = Notificacion::where('descripcion', $descripcion)->first();
                    if (!$exite) {
                        Notificacion::create([
                            'titulo' => '📋 Seguimiento',
                            'descripcion' => $descripcion,
                            'tipo' => 'Seguimiento',
                            'categoria' => 'Cotizaciones',
                            'prioridad' => 'Alta',
                            'leido' => false,
                            'referencia' => 'venta',
                            'id_referencia' => $venta->id,
                            'id_empresa' => $venta->id_empresa,
                            'id_sucursal' => $venta->id_sucursal,
                        ]);
                    }
                }


        $productos = Producto::withoutGlobalScopes()->whereHas('inventarios', function ($query) {
                                $query->whereRaw('stock_minimo > 0')
                                        ->whereRaw('stock <= stock_minimo');
                            })
                            ->where('enable', true)->get();
                
                foreach($productos as $producto){

                    foreach ($producto->inventarios()->whereRaw('stock <= stock_minimo')->get() as $inventario) {
                        $descripcion = 'Stock actual: ' . $inventario->stock .'. Mínimo permitido: ' . $inventario->stock_minimo .'. ¡Actúa ahora para evitar agotamientos!';
                        $exite = Notificacion::where('descripcion', $descripcion)->first();
                        if (!$exite) {
                            Notificacion::create([
                                'titulo' => '🚨 ' . $producto->nombre . ' con stock bajo',
                                'descripcion' => $descripcion,
                                'tipo' => 'Inventario bajo',
                                'categoria' => 'Inventarios',
                                'prioridad' => 'Media',
                                'leido' => false,
                                'referencia' => 'producto',
                                'id_referencia' => $producto->id,
                                'id_empresa' => $producto->id_empresa,
                                'id_sucursal' => $producto->id_sucursal,
                            ]);
                        }
                    }

                }

        $data = [
            'titulo' => 'Notificaciones.',
            'descripcion' => 'Se generaron automáticamente los recordatorios.',
        ];

        /*Mail::send('mails.notificacion', ['data' => $data ], function ($m) use ($data) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
            ->to('alvarado.websis@gmail.com', 'Jesus Alvarado')
            ->to(env('MAIL_TO_ADDRESS'), 'SmartPyme')
            // ->cc('alvarado.websis@gmail.com')
            // ->replyTo($request->correo)
            ->subject('Notificaciones generados');
        });*/

        \Log::info('Notificaciones generadas automáticamente', [
            'fecha' => Carbon::now()->format('Y-m-d H:i:s'),
            'descripcion' => 'Se generaron automáticamente los recordatorios'
        ]);

    }
}
