<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Exports\Contabilidad\BalanceComprobacionExport;
use App\Exports\Contabilidad\DiarioAuxiliarExport;
use App\Exports\Contabilidad\DiarioMayorExport;
use App\Exports\Contabilidad\BalanceGeneralExport;
use App\Exports\Contabilidad\EstadoResultadosExport;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Partidas\Partida;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Contabilidad\Catalogo\CuentaMayorizada;
use App\Models\Contabilidad\Catalogo\CuentaReporte;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Monolog\Handler\ZendMonitorHandler;
use App\Services\Contabilidad\ReporteContabilidadService;

class GenerarReportesController extends Controller
{
    protected $reporteContabilidadService;

    public function __construct(ReporteContabilidadService $reporteContabilidadService)
    {
        $this->reporteContabilidadService = $reporteContabilidadService;
    }

    public function mayorizacion($codigo_c)
    {
        // la idea es que pueda recibir un codigo de cuenta y buscar durante el mes el general de la cuenta con saldos

        //$codigo_c = 110101;

        //dd($cuentas);
        $startDate = Carbon::createFromFormat('Y-m-d', '2024-06-18')->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', '2024-06-19')->endOfDay();

        $detalles = Detalle::where('id_cuenta', $codigo_c)->whereBetween('created_at', [$startDate, $endDate])->get(); //colocar la fecha para el balance respectivo del mes

        //naturaleza de la cuenta
        $cuenta = Cuenta::where('codigo', $codigo_c)->where('id_empresa', auth()->user()->id_empresa)->first();

        //debe
        $debe = $detalles->sum('abono');

        //haber
        $haber = $detalles->sum('cargo');

        //establecer la naturaleza para realizar los calculos de la cuenta segun su saldo
        if ($cuenta->naturaleza == 'Deudor') {
            $saldo_calc = $debe - $haber;
        } else {
            $saldo_calc = $haber - $debe;
        }

        //si la cuenta de es de una naturaleza se suma el debe y se resta el haber
        // si una cuenta es de una naturaleza se suba el haber y se resta el debe

        $mayorizada = new CuentaMayorizada();
        $mayorizada->codigo = $codigo_c;
        $mayorizada->nombre = $cuenta->nombre;
        $mayorizada->saldo = $saldo_calc;
        $mayorizada->cargo = $haber;
        $mayorizada->abono = $debe;
        $mayorizada->naturaleza_saldo = $cuenta->naturaleza;

        return collect($mayorizada);
    }

    public function generarRepLibroDiario($fecha_inicio, $fecha_fin, $cuenta, $type)
    {
        if ($type === 'pdf') {
            return $this->generarRepLibroDiarioPDF($fecha_inicio, $fecha_fin, $cuenta);
        } else {
            return $this->generarRepLibroDiarioExcel($fecha_inicio, $fecha_fin, $cuenta);
        }
    }

    public function generarRepLibroDiarioPDF($fecha_inicio, $fecha_fin, $cuenta = null)
    {

        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrfail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        $query = Partida::with(['detalles' => function ($query) use ($cuenta) {
            $query->select('id', 'id_partida', 'id_cuenta', 'codigo', 'nombre_cuenta', 'concepto', 'debe', 'haber');

            if ($cuenta && $cuenta !== 'all') {
                $query->where('id_cuenta', $cuenta);
            }
        }])
            ->where('id_empresa', $empresa_id)
            ->whereIn('estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('fecha', [$startDate, $endDate])
            ->orderBy('fecha', 'desc');


        if ($cuenta && $cuenta !== 'all') {
            $query->whereHas('detalles', function ($query) use ($cuenta) {
                $query->where('id_cuenta', $cuenta);
            });
        }

        $partidas = $query->get();

        $reporteLibroDiario = $partidas->map(function ($partida) {
            return [
                'partida_num' => '#' . $partida->id,
                'correlativo' => $partida->correlativo,
                'fecha' => $partida->fecha,
                'concepto' => $partida->concepto,
                'detalles' => $partida->detalles->map(function ($detalle) {
                    return [
                        'codigo' => $detalle->codigo,
                        'nombre_cuenta' => $detalle->nombre_cuenta,
                        'concepto' => $detalle->concepto,
                        'debe' => $detalle->debe,
                        'haber' => $detalle->haber,
                    ];
                }),
            ];
        });

        $pdf = \PDF::loadView('reportes.contabilidad.libro_diario', compact('reporteLibroDiario', 'empresa', 'month_name', 'year'));
        $pdf->setPaper('US Letter', 'landscape');

        return  $pdf->stream();
    }

    public function generarRepLibroDiarioExcel($fecha_inicio, $fecha_fin, $cuenta = null)
    {

        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrfail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        $query = Partida::with(['detalles' => function ($query) use ($cuenta) {
            $query->select('id', 'id_partida', 'id_cuenta', 'codigo', 'nombre_cuenta', 'concepto', 'debe', 'haber');

            if ($cuenta && $cuenta !== 'all') {
                $query->where('id_cuenta', $cuenta);
            }
        }])
            ->where('id_empresa', $empresa_id)
            ->whereIn('estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('fecha', [$startDate, $endDate])
            ->orderBy('fecha', 'desc');


        if ($cuenta && $cuenta !== 'all') {
            $query->whereHas('detalles', function ($query) use ($cuenta) {
                $query->where('id_cuenta', $cuenta);
            });
        }

        $partidas = $query->get();



        $reporteLibroDiario = $partidas->map(function ($partida) {
            return [
                'partida_num' => '#' . $partida->id,
                'correlativo' => $partida->correlativo,
                'fecha' => $partida->fecha,
                'concepto' => $partida->concepto,
                'detalles' => $partida->detalles->map(function ($detalle) {
                    return [
                        'codigo' => $detalle->codigo,
                        'nombre_cuenta' => $detalle->nombre_cuenta,
                        'concepto' => $detalle->concepto,
                        'debe' => $detalle->debe,
                        'haber' => $detalle->haber,
                    ];
                }),
            ];
        });

        $data = [
            'empresa' => $empresa,
            'month_name' => $month_name,
            'year' => $year,
            'reporteLibroDiario' => $reporteLibroDiario,
        ];

        return Excel::download(new DiarioAuxiliarExport($data), 'libro_diario.xlsx');
    }

    public function generarRepLibroDiarioMayor($fecha_inicio, $fecha_fin, $cuenta, $type)
    {
        if ($type === 'pdf') {
            return $this->generarRepLibroDiarioMayorPDF($fecha_inicio, $fecha_fin, $cuenta);
        } else {
            return $this->generarRepLibroDiarioMayorExcel($fecha_inicio, $fecha_fin, $cuenta);
        }
    }

    public function generarRepLibroDiarioMayorPDF($fecha_inicio, $fecha_fin, $cuenta = null)
    {

        $cuentas = [];

        //nivel de cuenta padre, las siguientes van a aceptar datos pero esta no
        $nivel_datos = 2;

        $empresa_id = auth()->user()->id_empresa;
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        //cuentas que no aceptan datos segun nivel
        $cuentas_padre = Cuenta::where('nivel', $nivel_datos)->where('id_empresa', auth()->user()->id_empresa)->get();

        $partidas = Detalle::with(['partida:id,correlativo,fecha,concepto'])
            ->whereHas('partida', function ($query) use ($empresa_id, $startDate, $endDate, $cuenta) {
                $query->where('id_empresa', $empresa_id)
                    ->whereIn('estado', ['Aplicada', 'Cerrada'])
                    //->where('id_cuenta', $cuenta)
                    ->whereBetween('fecha', [$startDate, $endDate]);

                if ($cuenta && $cuenta !== 'all') {
                    $query->where('id_cuenta', $cuenta);
                }
            })->get();

        // Procesar cuentas padre y generar reporte
        $cuentas = $this->reporteContabilidadService->procesarCuentasPadreParaLibroMayor($cuentas_padre, $partidas);

        $empresa = Empresa::findOrfail($empresa_id);

        //if ($concepto != null) {
        //$pdf = PDF::loadView('reportes.contabilidad.libro_mayor', compact('cuentas', 'empresa', 'month_name', 'year', 'concepto'));
        // } else {
        $pdf = \PDF::loadView('reportes.contabilidad.libro_diario_mayor', compact('cuentas', 'empresa', 'month_name', 'year'));
        //}

        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarRepLibroDiarioMayorExcel($fecha_inicio, $fecha_fin, $cuenta = null)
    {

        $cuentas = [];

        //nivel de cuenta padre, las siguientes van a aceptar datos pero esta no
        $nivel_datos = 2;

        $empresa_id = auth()->user()->id_empresa;
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        //cuentas que no aceptan datos segun nivel
        $cuentas_padre = Cuenta::where('nivel', $nivel_datos)->where('id_empresa', auth()->user()->id_empresa)->get();

        $partidas = Detalle::with(['partida:id,correlativo,fecha,concepto'])
            ->whereHas('partida', function ($query) use ($empresa_id, $startDate, $endDate, $cuenta) {
                $query->where('id_empresa', $empresa_id)
                    ->whereIn('estado', ['Aplicada', 'Cerrada'])
                    ->whereBetween('fecha', [$startDate, $endDate]);

                if ($cuenta && $cuenta !== 'all') {
                    $query->where('id_cuenta', $cuenta);
                }
            })->get();

        // Procesar cuentas padre y generar reporte
        $cuentas = $this->reporteContabilidadService->procesarCuentasPadreParaLibroMayor($cuentas_padre, $partidas);

        $empresa = Empresa::findOrfail($empresa_id);


        $data = [
            'empresa' => $empresa,
            'month_name' => $month_name,
            'year' => $year,
            'cuentas' => $cuentas,
        ];

        // Exportar a Excel
        return Excel::download(new DiarioMayorExport($data), 'libro_diario_mayor.xlsx');
    }

    public function generarRepBalanceComprobacion($fecha_inicio, $fecha_fin, $cuenta, $type)
    {
        if ($type === 'pdf') {
            return $this->generarRepBalanceComprobacionPDF($fecha_inicio, $fecha_fin, $cuenta);
        } else {
            return $this->generarRepBalanceComprobacionExcel($fecha_inicio, $fecha_fin, $cuenta);
        }
    }

    public function generarRepBalanceComprobacionPDF($fecha_inicio, $fecha_fin, $cuenta = null)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        // Obtener todas las cuentas y aplicar ordenamiento jerárquico
        $cuentasQuery = Cuenta::where('id_empresa', $empresa_id)->orderBy('codigo');
        if ($cuenta && $cuenta !== 'all') {
            $cuentasQuery->where('id', $cuenta);
        }
        $todasLasCuentas = $cuentasQuery->get();
        $cuentas = collect($this->reporteContabilidadService->ordenarJerarquicamente($todasLasCuentas));

        // Obtener los movimientos del rango de fechas filtrado (APLICADAS Y CERRADAS)
        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->whereIn('partidas.estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('partidas.fecha', [$startDate, $endDate])
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta'); // Indexado por id_cuenta

        // Inicializamos un array para almacenar los saldos por cuenta
        $cuentas_saldos = [];

        // Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->reporteContabilidadService->obtenerSaldosIniciales($year, $month, $empresa_id);

        // Crear un mapa de ID a código para facilitar las búsquedas
        $idACodigo = [];
        foreach ($cuentas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        // Asignamos los valores iniciales obtenidos de la consulta de partidas
        foreach ($cuentas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;

            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;

            $cuentas_saldos[$codigo] = [
                'padre' => $cuenta->id_cuenta_padre ?? 0,
                'saldo_inicial' => (float)$saldoInicial, // ✅ CORREGIDO
                'debe' => $partida_detalles[$id]->total_debe ?? 0,
                'haber' => $partida_detalles[$id]->total_haber ?? 0,
                'saldoFinal' => ($partida_detalles[$id]->total_debe ?? 0) - ($partida_detalles[$id]->total_haber ?? 0),
            ];
        }

        // Ahora sumamos los valores a las cuentas padre
        $this->reporteContabilidadService->consolidarSaldosHaciaPadre($cuentas, $cuentas_saldos, $idACodigo);

        // Variables para totales
        $total_saldo_inicial = 0;
        $total_debe = 0;
        $total_haber = 0;
        $total_saldo_acumulado = 0;

        $balance = [];
        foreach ($cuentas as $cuenta) {
            $codigo = $cuenta->codigo;
            $saldo_inicial = $cuentas_saldos[$codigo]['saldo_inicial'] ?? 0;
            $debe = $cuentas_saldos[$codigo]['debe'] ?? 0;
            $haber = $cuentas_saldos[$codigo]['haber'] ?? 0;

            // Calcular saldo final según naturaleza de la cuenta
            $saldo_final = $this->reporteContabilidadService->calcularSaldoFinal($saldo_inicial, $debe, $haber, $cuenta->naturaleza);

            // Calcular operaciones del mes según naturaleza de la cuenta
            $operaciones_mes = $this->reporteContabilidadService->calcularOperacionesMes($debe, $haber, $cuenta->naturaleza);

            // Sumar a totales SOLO las cuentas padre (nivel 0) que ya tienen consolidados sus valores
            if ($cuenta->nivel == 0) {
                $total_saldo_inicial += $saldo_inicial;
                $total_debe += $debe;
                $total_haber += $haber;
                $total_saldo_acumulado += $saldo_final;
            }

            $balance[] = [
                'codigo' => $codigo,
                'nombre' => $cuenta->nombre,
                'nivel' => $cuenta->nivel,
                'nivel_visual' => $cuenta->nivel_visual ?? 0,
                'naturaleza' => $cuenta->naturaleza,
                'saldo_inicial' => $saldo_inicial,
                'debe' => $debe,
                'haber' => $haber,
                'operaciones_mes' => $operaciones_mes,
                'saldo_final' => $saldo_final,
                'es_cuenta_padre' => $cuenta->nivel == 0,
            ];
        }

        // Crear array de totales
        $totales = [
            'saldo_inicial' => $total_saldo_inicial,
            'debe' => $total_debe,
            'haber' => $total_haber,
            'saldo_acumulado' => $total_saldo_acumulado,
            'saldo_final' => $total_saldo_acumulado,
            'diferencia' => $total_debe - $total_haber
        ];

        $pdf = \PDF::loadView('reportes.contabilidad.rep_balance_comprobacion', compact('balance', 'empresa', 'month_name', 'year', 'totales'));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarRepBalanceComprobacionExcel($fecha_inicio, $fecha_fin, $cuenta = null)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        // Obtener todas las cuentas y aplicar ordenamiento jerárquico
        $cuentasQuery = Cuenta::where('id_empresa', $empresa_id)->orderBy('codigo');
        if ($cuenta && $cuenta !== 'all') {
            $cuentasQuery->where('id', $cuenta);
        }
        $todasLasCuentas = $cuentasQuery->get();
        $cuentas = collect($this->reporteContabilidadService->ordenarJerarquicamente($todasLasCuentas));

        // Obtener los movimientos del rango de fechas filtrado (APLICADAS Y CERRADAS)
        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->whereIn('partidas.estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('partidas.fecha', [$startDate, $endDate])
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta'); // Indexado por id_cuenta

        // Inicializamos un array para almacenar los saldos por cuenta
        $cuentas_saldos = [];

        // Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->reporteContabilidadService->obtenerSaldosIniciales($year, $month, $empresa_id);

        // Crear un mapa de ID a código para facilitar las búsquedas
        $idACodigo = [];
        foreach ($cuentas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        // Asignamos los valores iniciales obtenidos de la consulta de partidas
        foreach ($cuentas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;

            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;

            $cuentas_saldos[$codigo] = [
                'padre' => $cuenta->id_cuenta_padre ?? 0,
                'saldo_inicial' => (float)$saldoInicial, // ✅ CORREGIDO
                'debe' => $partida_detalles[$id]->total_debe ?? 0,
                'haber' => $partida_detalles[$id]->total_haber ?? 0,
                'saldoFinal' => ($partida_detalles[$id]->total_debe ?? 0) - ($partida_detalles[$id]->total_haber ?? 0),
            ];
        }

        // Ahora sumamos los valores a las cuentas padre
        $this->reporteContabilidadService->consolidarSaldosHaciaPadre($cuentas, $cuentas_saldos, $idACodigo);

        // Variables para totales
        $total_saldo_inicial = 0;
        $total_debe = 0;
        $total_haber = 0;
        $total_saldo_acumulado = 0;

        $balance = [];
        foreach ($cuentas as $cuenta) {
            $codigo = $cuenta->codigo;
            $saldo_inicial = $cuentas_saldos[$codigo]['saldo_inicial'] ?? 0;
            $debe = $cuentas_saldos[$codigo]['debe'] ?? 0;
            $haber = $cuentas_saldos[$codigo]['haber'] ?? 0;

            // Calcular saldo final según naturaleza de la cuenta
            $saldo_final = $this->reporteContabilidadService->calcularSaldoFinal($saldo_inicial, $debe, $haber, $cuenta->naturaleza);

            // Calcular operaciones del mes según naturaleza de la cuenta
            $operaciones_mes = $this->reporteContabilidadService->calcularOperacionesMes($debe, $haber, $cuenta->naturaleza);

            // Sumar a totales SOLO las cuentas padre (nivel 0) que ya tienen consolidados sus valores
            if ($cuenta->nivel == 0) {
                $total_saldo_inicial += $saldo_inicial;
                $total_debe += $debe;
                $total_haber += $haber;
                $total_saldo_acumulado += $saldo_final;
            }

            $balance[] = [
                'codigo' => $codigo,
                'nombre' => $cuenta->nombre,
                'nivel' => $cuenta->nivel,
                'nivel_visual' => $cuenta->nivel_visual ?? 0,
                'naturaleza' => $cuenta->naturaleza, // ✅ AGREGADO: Naturaleza en cada cuenta
                'saldo_inicial' => $saldo_inicial,
                'debe' => $debe,
                'haber' => $haber,
                'operaciones_mes' => $operaciones_mes,
                'saldo_final' => $saldo_final,
                'es_cuenta_padre' => $cuenta->nivel == 0,
            ];
        }

        // Agregar los totales a la data para mostrar en el Excel
        $data = [
            'empresa' => $empresa,
            'month_name' => $month_name,
            'year' => $year,
            'balanceComprobacion' => $balance,
            'totales' => [
                'saldo_inicial' => $total_saldo_inicial,
                'debe' => $total_debe,
                'haber' => $total_haber,
                'saldo_acumulado' => $total_saldo_acumulado,
                'saldo_final' => $total_saldo_acumulado,
                'diferencia' => $total_debe - $total_haber
            ]
        ];

        // Exportar a Excel
        return Excel::download(new BalanceComprobacionExport($data), 'balance_comprobacion.xlsx');
    }

    public function generarRepMovCuenta($fecha_inicio, $fecha_fin, $cuenta_cod)
    {

        $empresa_id = auth()->user()->id_empresa;

        $cuenta = Cuenta::where('codigo', $cuenta_cod)->where('id_empresa', auth()->user()->id_empresa)->first();

        //        dd($cuenta_cod);

        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();

        $det_agrup = Detalle::whereHas('partida', function ($query) use ($empresa_id, $startDate, $endDate) {
            $query->where('id_empresa', $empresa_id)
                  ->whereBetween('fecha', [$startDate, $endDate]);
        })
            ->where('codigo', $cuenta_cod)
            ->get();

        $empresa = Empresa::findOrfail($empresa_id);

        // Fecha en formato dd/mm/yyyy
        $fecha = date('d/m/Y');

        // Hora en formato 12 horas con a.m./p.m.
        $hora = date('h:i:s a');

        $sum_deb = 0;
        $sum_hab = 0;
        foreach ($det_agrup as $detalle) {
            //                las cuentas de ACTIVO, COSTO Y GASTOS (son de saldo deudor), aumentan con un cargo (debe) y disminuyen con un abono(haber) y las cuentas de PASIVO,
            //                PATRIMONIO E INGRESOS(son de saldo acreedor) aumentan con un abono (haber) y disminuyen con un cargo(debe)

            $sum_deb += $detalle->debe;
            $sum_hab += $detalle->haber;
        }

        //        dd($cuenta);

        $cuenta_reporte = new CuentaReporte();
        $cuenta_reporte->cuenta = $cuenta_cod;
        $cuenta_reporte->nombre = $cuenta->nombre;
        $cuenta_reporte->detalles = $det_agrup;
        $cuenta_reporte->naturaleza = $cuenta->naturaleza;
        $cuenta_reporte->cargo = $sum_deb;
        $cuenta_reporte->abono = $sum_hab;
        $cuenta_reporte->saldo_actual = 0;  //este dato llega a la blade actualizado con el dato de salgo anterior para que se haga el calculo en la blade
        $cuenta_reporte->saldo_anterior = 0;

        $desde = Carbon::parse($fecha_inicio)->format('d/m/Y');
        $hasta = Carbon::parse($fecha_fin)->format('d/m/Y');

        $pdf = \PDF::loadView('reportes.contabilidad.movimiento_cuenta', compact('cuenta_reporte',  'desde', 'hasta', 'empresa', 'fecha', 'hora'));

        $pdf->setPaper('US Letter', 'landscape');

        return $pdf->stream();
    }

    public function generarBalanceGeneral($fecha_inicio, $fecha_fin, $type)
    {
        if ($type === 'pdf') {
            return $this->generarBalanceGeneralPDF($fecha_inicio, $fecha_fin);
        } else {
            return $this->generarBalanceGeneralExcel($fecha_inicio, $fecha_fin);
        }
    }

    public function generarBalanceGeneralPDF($fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        // Obtener todas las cuentas con jerarquía completa
        $cuentasJerarquicas = Cuenta::where('id_empresa', $empresa_id)
            ->orderBy('codigo')
            ->get();

        // Obtener los movimientos del rango de fechas filtrado para todas las cuentas
        $partida_detalles = $this->reporteContabilidadService->obtenerMovimientosPartidas($startDate, $endDate, $empresa_id);

        // Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->reporteContabilidadService->obtenerSaldosIniciales($year, $month, $empresa_id);

        // Calcular saldos consolidados similar al balance de comprobación
        $cuentas_saldos = [];

        // Crear mapa de ID a código
        $idACodigo = [];
        foreach ($cuentasJerarquicas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        // Asignar valores iniciales
        foreach ($cuentasJerarquicas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;

            // Usar lógica correcta para saldo inicial
            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;

            $cuentas_saldos[$codigo] = [
                'saldo_inicial' => (float)$saldoInicial,
                'debe' => $partida_detalles[$id]->total_debe ?? 0,
                'haber' => $partida_detalles[$id]->total_haber ?? 0,
            ];
        }

        // Consolidar hacia cuentas padre
        $this->reporteContabilidadService->consolidarSaldosHaciaPadre($cuentasJerarquicas, $cuentas_saldos, $idACodigo);

        // Clasificar por rubros del Balance General
        $balance_general = $this->reporteContabilidadService->clasificarCuentasPorRubroBalanceGeneral($cuentasJerarquicas, $cuentas_saldos);

        $pdf = \PDF::loadView('reportes.contabilidad.balance_general', compact('balance_general', 'empresa', 'month_name', 'year'));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarBalanceGeneralExcel($fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        // Reutilizar la misma lógica del PDF pero para Excel
        $cuentas = Cuenta::where('id_empresa', $empresa_id)
            ->where('nivel', 0)
            ->orderBy('codigo')
            ->get();

        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->whereIn('partidas.estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('partidas.fecha', [$startDate, $endDate])
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta');

        // Obtener todas las cuentas con jerarquía completa
        $cuentasJerarquicas = Cuenta::where('id_empresa', $empresa_id)
            ->orderBy('codigo')
            ->get();

        // Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->reporteContabilidadService->obtenerSaldosIniciales($year, $month, $empresa_id);

        $cuentas_saldos = [];
        $idACodigo = [];

        foreach ($cuentasJerarquicas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        foreach ($cuentasJerarquicas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;

            // Usar lógica correcta para saldo inicial
            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;

            $cuentas_saldos[$codigo] = [
                'saldo_inicial' => (float)$saldoInicial, // ✅ CORREGIDO
                'debe' => $partida_detalles[$id]->total_debe ?? 0,
                'haber' => $partida_detalles[$id]->total_haber ?? 0,
            ];
        }

        // Consolidar hacia cuentas padre
        $this->reporteContabilidadService->consolidarSaldosHaciaPadre($cuentasJerarquicas, $cuentas_saldos, $idACodigo);

        // Clasificar por rubros del Balance General
        $balance_general = $this->reporteContabilidadService->clasificarCuentasPorRubroBalanceGeneral($cuentas, $cuentas_saldos);

        $data = [
            'empresa' => $empresa,
            'month_name' => $month_name,
            'year' => $year,
            'balance_general' => $balance_general,
        ];

        return Excel::download(new BalanceGeneralExport($data), 'balance_general.xlsx');
    }

    public function generarEstadoResultados($fecha_inicio, $fecha_fin, $type)
    {
        if ($type === 'pdf') {
            return $this->generarEstadoResultadosPDF($fecha_inicio, $fecha_fin);
        } else {
            return $this->generarEstadoResultadosExcel($fecha_inicio, $fecha_fin);
        }
    }

    public function generarEstadoResultadosPDF($fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        // Obtener todas las cuentas padre (nivel 0) con sus saldos consolidados
        $cuentas = Cuenta::where('id_empresa', $empresa_id)
            ->where('nivel', 0) // Solo cuentas padre
            ->orderBy('codigo')
            ->get();

        // Obtener los movimientos del rango de fechas filtrado para todas las cuentas
        $partida_detalles = $this->reporteContabilidadService->obtenerMovimientosPartidas($startDate, $endDate, $empresa_id);

        // Clasificar cuentas por rubros del Estado de Resultados
        $estado_resultados = $this->reporteContabilidadService->clasificarCuentasPorRubroEstadoResultados($cuentas, $partida_detalles);

        $pdf = \PDF::loadView('reportes.contabilidad.estado_resultados', compact(
            'estado_resultados',
            'empresa',
            'month_name',
            'month',
            'year'
        ));

        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarEstadoResultadosExcel($fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();
        
        // Calcular mes y año para mostrar en las vistas
        $month = $startDate->month;
        $year = $startDate->year;
        $month_name = $startDate->translatedFormat('F');

        // Obtener todas las cuentas padre (nivel 0) con sus saldos consolidados
        $cuentas = Cuenta::where('id_empresa', $empresa_id)
            ->where('nivel', 0) // Solo cuentas padre
            ->orderBy('codigo')
            ->get();

        // Obtener los movimientos del rango de fechas filtrado para todas las cuentas
        $partida_detalles = $this->reporteContabilidadService->obtenerMovimientosPartidas($startDate, $endDate, $empresa_id);

        // Clasificar cuentas por rubros del Estado de Resultados
        $estado_resultados = $this->reporteContabilidadService->clasificarCuentasPorRubroEstadoResultados($cuentas, $partida_detalles);

        $data = [
            'estado_resultados' => $estado_resultados,
            'empresa' => $empresa,
            'month_name' => $month_name,
            'month' => $month,
            'year' => $year
        ];

        return Excel::download(new EstadoResultadosExport($data), 'estado_resultados_' . $fecha_inicio . '_' . $fecha_fin . '.xlsx');
    }



}
