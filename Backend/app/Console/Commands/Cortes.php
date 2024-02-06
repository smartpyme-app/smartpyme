<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Empresa;
use App\Models\Venta;

use Illuminate\Support\Facades\Mail;
use App\Mail\CortesMail;

class Cortes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:cortes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar los cortes diarios a clientes automáticamente';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $empresas = Empresa::whereActivo(true)->orderBy('id', 'desc')->get();

        foreach ($empresas as $empresa) {
            $data = new \stdClass();
            $data->fecha = date("Y-m-d");
            $data->fecha = '2022-02-19';
            $data->empresa = Empresa::findOrfail($empresa->id);
            // Ventas
                $ventas = Venta::where('id_empresa', $data->empresa->id)
                                // ->where('id_sucursal', Auth::user()->id_sucursal)
                                ->where('estado', '!=', 'Anulada')
                                ->where('estado', '!=', 'Pre-venta')
                                ->whereDate('fecha', $data->fecha)
                                ->get();

                $data->num_ventas = $ventas->count();
                $data->total = $ventas->sum('total');
                $data->ventas_canal = $ventas->groupBy('id_canal')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()->canal()->first()->nombre,
                        'cantidad' => $group->count(),
                        'total' => $group->sum('total')
                    ];
                })->sortByDesc('total')->values()->all();

                $data->ventas_forma_pago = $ventas->groupBy('forma_pago')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()['forma_pago'],
                        'cantidad' => $group->count(),
                        'total' => $group->sum('total')
                    ];
                })->sortByDesc('total')->values()->all();
                
                $data->ventas_documento = $ventas->groupBy('id_documento')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()->documento()->first()->nombre,
                        'inicio' => $group->first()->correlativo,
                        'fin' => $group->last()->correlativo,
                        'cantidad' => $group->count(),
                        'total' => $group->sum('total')
                    ];
                })->sortByDesc('id')->values()->all();

            if ($empresa->email) {
                Mail::to($empresa->email)->send(new CortesMail($data));
                sleep(1);
            }
        }

    }
}
