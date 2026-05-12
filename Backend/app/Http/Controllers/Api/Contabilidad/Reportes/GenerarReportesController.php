<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Exports\Contabilidad\BalanceComprobacionExport;
use App\Exports\Contabilidad\DiarioAuxiliarExport;
use App\Exports\Contabilidad\DiarioMayorExport;
use App\Exports\Contabilidad\BalanceGeneralExport;
use App\Exports\Contabilidad\EstadoResultadosExport;
use App\Exports\Contabilidad\FlujoEfectivoExport;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Services\Contabilidad\BalanceGeneralNiifSvPresenter;
use App\Services\Contabilidad\EstadoResultadosNiifSvPresenter;
use App\Services\Contabilidad\FlujoEfectivoHibridoNiifSvPresenter;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Partidas\Partida;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Contabilidad\Catalogo\CuentaMayorizada;
use App\Models\Contabilidad\Catalogo\CuentaReporte;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Monolog\Handler\ZendMonitorHandler;

class GenerarReportesController extends Controller
{

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
        $startDate = Carbon::createFromFormat('Y-m-d', $fecha_inicio)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $fecha_fin)->endOfDay();
        
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

        $pdf = PDF::loadView('reportes.contabilidad.libro_diario', compact('reporteLibroDiario', 'empresa', 'month_name', 'month', 'year'));
        $pdf->setPaper('US Letter', 'landscape');

        return  $pdf->stream();
    }

    public function generarRepLibroDiarioExcel($fecha_inicio, $fecha_fin, $cuenta = null)
    {

        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrfail($empresa_id);
        $startDate = Carbon::createFromFormat('Y-m-d', $fecha_inicio)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $fecha_fin)->endOfDay();
        
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
            'month' => $month,
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

        //elegir entre los detalles de las partidas cuales tienen cuentas que empiezan con los cuatros digitos de las partidas padre
        foreach ($cuentas_padre->pluck('codigo') as $cod_padre) {
            $partidasFiltradas = $partidas->filter(function ($detalle) use ($cod_padre) {
                return strpos($detalle->codigo, (string)$cod_padre) === 0;
            });

            // Convertir el resultado a una colección nuevamente (opcional)
            $partidasFiltradas = $partidasFiltradas->values();

            //            LLENADO DE LOS DEBE Y HABER DE CADA CUENTA

            $sum_deb = 0;
            $sum_hab = 0;
            foreach ($partidasFiltradas as $det_part) {

                //                las cuentas de ACTIVO, COSTO Y GASTOS (son de saldo deudor), aumentan con un cargo (debe) y disminuyen con un abono(haber) y las cuentas de PASIVO,
                //                PATRIMONIO E INGRESOS(son de saldo acreedor) aumentan con un abono (haber) y disminuyen con un cargo(debe)

                $sum_deb += $det_part->debe;
                $sum_hab += $det_part->haber;
            }

            if (count($partidasFiltradas) != 0) {

                $cnt = $cuentas_padre->firstWhere('codigo', $cod_padre);


                $cuenta_reporte = new CuentaReporte();
                $cuenta_reporte->cuenta = $cod_padre;
                $cuenta_reporte->nombre = $cnt->nombre;
                $cuenta_reporte->naturaleza = $cnt->naturaleza;
                $cuenta_reporte->cargo = $sum_deb;
                $cuenta_reporte->abono = $sum_hab;
                $cuenta_reporte->saldo_actual = 0;
                $cuenta_reporte->saldo_anterior = 0;

                // Calcular saldos progresivos para cada detalle según naturaleza de la cuenta
                $saldo_actual = 0;
                foreach ($partidasFiltradas as $detalle) {
                    $debe_valor = (float)($detalle->debe ?? 0);
                    $haber_valor = (float)($detalle->haber ?? 0);
                    
                    // Calcular saldo según naturaleza de la cuenta
                    if ($cnt->naturaleza == 'Deudor') {
                        $saldo_actual = $saldo_actual + $debe_valor - $haber_valor;
                    } else {
                        $saldo_actual = $saldo_actual - $debe_valor + $haber_valor;
                    }
                    
                    // Agregar el saldo calculado al detalle
                    $detalle->saldo_calculado = $saldo_actual;
                }
                
                // Actualizar el saldo final de la cuenta para totales
                $cuenta_reporte->saldo_actual = $saldo_actual;
                $cuenta_reporte->detalles = $partidasFiltradas;


                array_push($cuentas, $cuenta_reporte);
            }
        }

        $empresa = Empresa::findOrfail($empresa_id);

        //if ($concepto != null) {
        //$pdf = PDF::loadView('reportes.contabilidad.libro_mayor', compact('cuentas', 'empresa', 'month_name', 'month', 'year', 'concepto'));
        // } else {
        $pdf = PDF::loadView('reportes.contabilidad.libro_diario_mayor', compact('cuentas', 'empresa', 'month_name', 'month', 'year'));
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

        //elegir entre los detalles de las partidas cuales tienen cuentas que empiezan con los cuatros digitos de las partidas padre
        foreach ($cuentas_padre->pluck('codigo') as $cod_padre) {
            $partidasFiltradas = $partidas->filter(function ($detalle) use ($cod_padre) {
                return strpos($detalle->codigo, (string)$cod_padre) === 0;
            });

            // Convertir el resultado a una colección nuevamente (opcional)
            $partidasFiltradas = $partidasFiltradas->values();

            //            LLENADO DE LOS DEBE Y HABER DE CADA CUENTA

            $sum_deb = 0;
            $sum_hab = 0;
            foreach ($partidasFiltradas as $det_part) {

                //                las cuentas de ACTIVO, COSTO Y GASTOS (son de saldo deudor), aumentan con un cargo (debe) y disminuyen con un abono(haber) y las cuentas de PASIVO,
                //                PATRIMONIO E INGRESOS(son de saldo acreedor) aumentan con un abono (haber) y disminuyen con un cargo(debe)

                $sum_deb += $det_part->debe;
                $sum_hab += $det_part->haber;
            }

            if (count($partidasFiltradas) != 0) {

                $cnt = $cuentas_padre->firstWhere('codigo', $cod_padre);


                $cuenta_reporte = new CuentaReporte();
                $cuenta_reporte->cuenta = $cod_padre;
                $cuenta_reporte->nombre = $cnt->nombre;
                $cuenta_reporte->naturaleza = $cnt->naturaleza;
                $cuenta_reporte->cargo = $sum_deb;
                $cuenta_reporte->abono = $sum_hab;
                $cuenta_reporte->saldo_actual = 0;
                $cuenta_reporte->saldo_anterior = 0;

                // Calcular saldos progresivos para cada detalle según naturaleza de la cuenta
                $saldo_actual = 0;
                foreach ($partidasFiltradas as $detalle) {
                    $debe_valor = (float)($detalle->debe ?? 0);
                    $haber_valor = (float)($detalle->haber ?? 0);
                    
                    // Calcular saldo según naturaleza de la cuenta
                    if ($cnt->naturaleza == 'Deudor') {
                        $saldo_actual = $saldo_actual + $debe_valor - $haber_valor;
                    } else {
                        $saldo_actual = $saldo_actual - $debe_valor + $haber_valor;
                    }
                    
                    // Agregar el saldo calculado al detalle
                    $detalle->saldo_calculado = $saldo_actual;
                }
                
                // Actualizar el saldo final de la cuenta para totales
                $cuenta_reporte->saldo_actual = $saldo_actual;
                $cuenta_reporte->detalles = $partidasFiltradas;


                array_push($cuentas, $cuenta_reporte);
            }
        }

        $empresa = Empresa::findOrfail($empresa_id);


        $data = [
            'empresa' => $empresa,
            'month_name' => $month_name,
            'month' => $month,
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
        $cuentas = collect($this->ordenarJerarquicamente($todasLasCuentas));

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
        $saldosIniciales = $this->obtenerSaldosIniciales($startDate, $empresa_id);

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
        foreach ($cuentas->sortByDesc('nivel') as $cuenta) {
            if($cuenta->id_cuenta_padre && isset($idACodigo[$cuenta->id_cuenta_padre])) {
                $codigo_padre = $idACodigo[$cuenta->id_cuenta_padre];
                $cuentas_saldos[$codigo_padre]['saldo_inicial'] += $cuentas_saldos[$cuenta->codigo]['saldo_inicial'];
                $cuentas_saldos[$codigo_padre]['debe'] += $cuentas_saldos[$cuenta->codigo]['debe'];
                $cuentas_saldos[$codigo_padre]['haber'] += $cuentas_saldos[$cuenta->codigo]['haber'];
                $cuentas_saldos[$codigo_padre]['saldoFinal'] += $cuentas_saldos[$cuenta->codigo]['saldoFinal'];
            }
        }

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
            if ($cuenta->naturaleza == 'Deudor') {
                $saldo_final = $saldo_inicial + $debe - $haber;
            } else {
                $saldo_final = $saldo_inicial + $haber - $debe;
            }

            // Calcular operaciones del mes según naturaleza de la cuenta
            if ($cuenta->naturaleza == 'Deudor') {
                $operaciones_mes = $debe - $haber;
            } else { // Acreedor
                $operaciones_mes = $haber - $debe;
            }

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

        $pdf = PDF::loadView('reportes.contabilidad.rep_balance_comprobacion', compact('balance', 'empresa', 'month_name', 'year', 'fecha_inicio', 'fecha_fin', 'totales'));
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
        $cuentas = collect($this->ordenarJerarquicamente($todasLasCuentas));

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
        $saldosIniciales = $this->obtenerSaldosIniciales($startDate, $empresa_id);

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
        foreach ($cuentas->sortByDesc('nivel') as $cuenta) {
            if($cuenta->id_cuenta_padre && isset($idACodigo[$cuenta->id_cuenta_padre])) {
                $codigo_padre = $idACodigo[$cuenta->id_cuenta_padre];
                $cuentas_saldos[$codigo_padre]['saldo_inicial'] += $cuentas_saldos[$cuenta->codigo]['saldo_inicial'];
                $cuentas_saldos[$codigo_padre]['debe'] += $cuentas_saldos[$cuenta->codigo]['debe'];
                $cuentas_saldos[$codigo_padre]['haber'] += $cuentas_saldos[$cuenta->codigo]['haber'];
                $cuentas_saldos[$codigo_padre]['saldoFinal'] += $cuentas_saldos[$cuenta->codigo]['saldoFinal'];
            }
        }

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
            if ($cuenta->naturaleza == 'Deudor') {
                $saldo_final = $saldo_inicial + $debe - $haber;
            } else {
                $saldo_final = $saldo_inicial + $haber - $debe;
            }

            // Calcular operaciones del mes según naturaleza de la cuenta
            if ($cuenta->naturaleza == 'Deudor') {
                $operaciones_mes = $debe - $haber;
            } else { // Acreedor
                $operaciones_mes = $haber - $debe;
            }

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
            'month' => $month,
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

        $pdf = PDF::loadView('reportes.contabilidad.movimiento_cuenta', compact('cuenta_reporte',  'desde', 'hasta', 'empresa', 'fecha', 'hora'));

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

        $balance = app(BalanceGeneralNiifSvPresenter::class)->build($empresa_id, $startDate, $endDate);

        $pdf = PDF::loadView('reportes.contabilidad.balance_general', compact('balance', 'empresa', 'fecha_inicio', 'fecha_fin'));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarBalanceGeneralExcel($fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();

        $balance = app(BalanceGeneralNiifSvPresenter::class)->build($empresa_id, $startDate, $endDate);

        return Excel::download(new BalanceGeneralExport($balance, (string) $empresa->nombre), 'balance_general.xlsx');
    }

    public function generarEstadoResultados(Request $request, $fecha_inicio, $fecha_fin, $type)
    {
        if ($type === 'pdf') {
            return $this->generarEstadoResultadosPDF($request, $fecha_inicio, $fecha_fin);
        }

        return $this->generarEstadoResultadosExcel($request, $fecha_inicio, $fecha_fin);
    }

    public function generarEstadoResultadosPDF(Request $request, $fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();

        $comparar = $request->boolean('comparar');
        $presenter = app(EstadoResultadosNiifSvPresenter::class);
        $estado = $presenter->build($empresa_id, $startDate, $endDate);
        $estado['mostrar_comparativa'] = $comparar;
        if ($comparar) {
            [$ps, $pe] = EstadoResultadosNiifSvPresenter::periodoAnterior($startDate, $endDate);
            $anterior = $presenter->build($empresa_id, $ps, $pe);
            $estado = EstadoResultadosNiifSvPresenter::applyComparative($estado, $anterior);
        }

        $pdf = PDF::loadView('reportes.contabilidad.estado_resultados', [
            'estado' => $estado,
            'empresa' => $empresa,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
        ]);
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarEstadoResultadosExcel(Request $request, $fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();

        $comparar = $request->boolean('comparar');
        $presenter = app(EstadoResultadosNiifSvPresenter::class);
        $estado = $presenter->build($empresa_id, $startDate, $endDate);
        $estado['mostrar_comparativa'] = $comparar;
        if ($comparar) {
            [$ps, $pe] = EstadoResultadosNiifSvPresenter::periodoAnterior($startDate, $endDate);
            $anterior = $presenter->build($empresa_id, $ps, $pe);
            $estado = EstadoResultadosNiifSvPresenter::applyComparative($estado, $anterior);
        }

        $fname = 'estado_resultados_' . $fecha_inicio . '_' . $fecha_fin . '.xlsx';

        return Excel::download(
            new EstadoResultadosExport($estado, (string) $empresa->nombre, $comparar),
            $fname
        );
    }

    public function generarFlujoEfectivo(Request $request, $fecha_inicio, $fecha_fin, $type)
    {
        if ($type === 'pdf') {
            return $this->generarFlujoEfectivoPDF($request, $fecha_inicio, $fecha_fin);
        }

        return $this->generarFlujoEfectivoExcel($request, $fecha_inicio, $fecha_fin);
    }

    public function generarFlujoEfectivoPDF(Request $request, $fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();

        $comparar = $request->boolean('comparar');
        $presenter = app(FlujoEfectivoHibridoNiifSvPresenter::class);
        $flujo = $presenter->build($empresa_id, $startDate, $endDate, $comparar);

        $pdf = PDF::loadView('reportes.contabilidad.flujo_efectivo', [
            'flujo' => $flujo,
            'empresa' => $empresa,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
        ]);
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarFlujoEfectivoExcel(Request $request, $fecha_inicio, $fecha_fin)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();

        $comparar = $request->boolean('comparar');
        $presenter = app(FlujoEfectivoHibridoNiifSvPresenter::class);
        $flujo = $presenter->build($empresa_id, $startDate, $endDate, $comparar);

        $fname = 'flujo_efectivo_' . $fecha_inicio . '_' . $fecha_fin . '.xlsx';

        return Excel::download(
            new FlujoEfectivoExport($flujo, (string) $empresa->nombre, $comparar),
            $fname
        );
    }

    /**
     * Ordena las cuentas jerárquicamente en un array plano (padre seguido de sus hijos)
     */
    private function ordenarJerarquicamente($cuentas, $padreId = null, $nivel = 0)
    {
        $resultado = [];
        foreach ($cuentas as $cuenta) {
            if (
                ($padreId === null && ($cuenta->id_cuenta_padre === null || $cuenta->id_cuenta_padre == 0)) ||
                ($cuenta->id_cuenta_padre == $padreId && $padreId !== null)
            ) {
                $cuenta->nivel_visual = $nivel;
                $resultado[] = $cuenta;
                $hijos = $this->ordenarJerarquicamente($cuentas, $cuenta->id, $nivel + 1);
                foreach ($hijos as $hijo) {
                    $resultado[] = $hijo;
                }
            }
        }
        return $resultado;
    }

    /**
     * Obtener saldos iniciales del período basado en fecha de inicio
     */
    private function obtenerSaldosIniciales($fechaInicio, $empresa_id)
    {
        $fechaInicioCarbon = Carbon::parse($fechaInicio);
        $year = $fechaInicioCarbon->year;
        $month = $fechaInicioCarbon->month;

        // Verificar si existe algún período anterior
        $hayPeriodoAnterior = \App\Models\Contabilidad\SaldoMensual::where('id_empresa', $empresa_id)
            ->where(function($q) use ($year, $month) {
                $q->where('year', '<', $year)
                  ->orWhere(function($q2) use ($year, $month) {
                      $q2->where('year', $year)->where('month', '<', $month);
                  });
            })
            ->exists();

        if (!$hayPeriodoAnterior) {
            // Primer período de la empresa - usar catálogo
            return [];
        }

        // Obtener el último período cerrado antes de la fecha de inicio
        $periodoAnterior = $this->obtenerPeriodoAnterior($year, $month);

        $saldosAnteriores = \App\Models\Contabilidad\SaldoMensual::where('year', $periodoAnterior['year'])
            ->where('month', $periodoAnterior['month'])
            ->where('id_empresa', $empresa_id)
            ->get()
            ->keyBy('id_cuenta');

        $saldosIniciales = [];
        foreach ($saldosAnteriores as $saldo) {
            // Asegurar que el saldo final nunca sea null
            $saldosIniciales[$saldo->id_cuenta] = (float)($saldo->saldo_final ?? 0);
        }

        return $saldosIniciales;
    }

    /**
     * Obtener período anterior
     */
    private function obtenerPeriodoAnterior($year, $month)
    {
        if ($month == 1) {
            return ['year' => $year - 1, 'month' => 12];
        }
        return ['year' => $year, 'month' => $month - 1];
    }


}
