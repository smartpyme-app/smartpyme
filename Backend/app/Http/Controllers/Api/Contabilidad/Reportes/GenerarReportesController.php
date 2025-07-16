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
use Barryvdh\DomPDF\Facade as PDF;
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

    public function generarRepLibroDiario($month, $year, $cuenta, $type)
    {
        if ($type === 'pdf') {
            return $this->generarRepLibroDiarioPDF($month, $year, $cuenta);
        } else {
            return $this->generarRepLibroDiarioExcel($month, $year, $cuenta);
        }
    }

    public function generarRepLibroDiarioPDF($month, $year, $cuenta = null)
    {
        // Log::info(['month' => $month, 'year' => $year, 'cuenta' => $cuenta]);

        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrfail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        $query = Partida::with(['detalles' => function ($query) use ($cuenta) {
            $query->select('id', 'id_partida', 'id_cuenta', 'codigo', 'nombre_cuenta', 'concepto', 'debe', 'haber');

            if ($cuenta && $cuenta !== 'all') {
                $query->where('id_cuenta', $cuenta);
            }
        }])
            ->where('id_empresa', $empresa_id)
            ->where('estado', 'Aplicada')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
            ->orderBy('fecha', 'desc');


        if ($cuenta && $cuenta !== 'all') {
            $query->whereHas('detalles', function ($query) use ($cuenta) {
                // Log::info('Cuenta: ' . $cuenta);
                $query->where('id_cuenta', $cuenta);
            });
        }

        $partidas = $query->get();

        $reporteLibroDiario = $partidas->map(function ($partida) {
            return [
                'partida_num' => '#' . $partida->id,
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

        $pdf = PDF::loadView('reportes.contabilidad.libro_diario', compact('reporteLibroDiario', 'empresa', 'month_name', 'year'));
        $pdf->setPaper('US Letter', 'landscape');

        return  $pdf->stream();
    }

    public function generarRepLibroDiarioExcel($month, $year, $cuenta = null)
    {
        //Log::info(['month' => $month, 'year' => $year, 'cuenta' => $cuenta]);

        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrfail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        $query = Partida::with(['detalles' => function ($query) use ($cuenta) {
            $query->select('id', 'id_partida', 'id_cuenta', 'codigo', 'nombre_cuenta', 'concepto', 'debe', 'haber');

            if ($cuenta && $cuenta !== 'all') {
                $query->where('id_cuenta', $cuenta);
            }
        }])
            ->where('id_empresa', $empresa_id)
            ->where('estado', 'Aplicada')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
            ->orderBy('fecha', 'desc');


        if ($cuenta && $cuenta !== 'all') {
            $query->whereHas('detalles', function ($query) use ($cuenta) {
                //Log::info('Cuenta: ' . $cuenta);
                $query->where('id_cuenta', $cuenta);
            });
        }

        $partidas = $query->get();



        $reporteLibroDiario = $partidas->map(function ($partida) {
            return [
                'partida_num' => '#' . $partida->id,
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

    public function generarRepLibroDiarioMayor($month, $year, $cuenta, $type)
    {
        if ($type === 'pdf') {
            return $this->generarRepLibroDiarioMayorPDF($month, $year, $cuenta);
        } else {
            return $this->generarRepLibroDiarioMayorExcel($month, $year, $cuenta);
        }
    }

    public function generarRepLibroDiarioMayorPDF($month, $year, $cuenta = null)
    {

        $cuentas = [];

        //nivel de cuenta padre, las siguientes van a aceptar datos pero esta no
        $nivel_datos = 2;

        $empresa_id = auth()->user()->id_empresa;
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        //cuentas que no aceptan datos segun nivel
        $cuentas_padre = Cuenta::where('nivel', $nivel_datos)->where('id_empresa', auth()->user()->id_empresa)->get();

        $partidas = Detalle::whereHas('partida', function ($query) use ($empresa_id, $month, $year, $cuenta) {
            $query->where('id_empresa', $empresa_id)
                ->where('estado', 'Aplicada')
                //->where('id_cuenta', $cuenta)
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $month);

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
                $cuenta_reporte->detalles = $partidasFiltradas;
                $cuenta_reporte->naturaleza = $cnt->naturaleza;
                $cuenta_reporte->cargo = $sum_deb;
                $cuenta_reporte->abono = $sum_hab;
                $cuenta_reporte->saldo_actual = 0;
                $cuenta_reporte->saldo_anterior = 0;


                array_push($cuentas, $cuenta_reporte);
            }
        }

        $empresa = Empresa::findOrfail($empresa_id);

        //if ($concepto != null) {
        //$pdf = PDF::loadView('reportes.contabilidad.libro_mayor', compact('cuentas', 'empresa', 'month_name', 'year', 'concepto'));
        // } else {
        $pdf = PDF::loadView('reportes.contabilidad.libro_diario_mayor', compact('cuentas', 'empresa', 'month_name', 'year'));
        //}

        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarRepLibroDiarioMayorExcel($month, $year, $cuenta = null)
    {

        $cuentas = [];

        //nivel de cuenta padre, las siguientes van a aceptar datos pero esta no
        $nivel_datos = 2;

        $empresa_id = auth()->user()->id_empresa;
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        //cuentas que no aceptan datos segun nivel
        $cuentas_padre = Cuenta::where('nivel', $nivel_datos)->where('id_empresa', auth()->user()->id_empresa)->get();

        $partidas = Detalle::whereHas('partida', function ($query) use ($empresa_id, $month, $year, $cuenta) {
            $query->where('id_empresa', $empresa_id)
                ->where('estado', 'Aplicada')
                ->whereYear('fecha', $year)
                ->whereMonth('fecha', $month);

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
                $cuenta_reporte->detalles = $partidasFiltradas;
                $cuenta_reporte->naturaleza = $cnt->naturaleza;
                $cuenta_reporte->cargo = $sum_deb;
                $cuenta_reporte->abono = $sum_hab;
                $cuenta_reporte->saldo_actual = 0;
                $cuenta_reporte->saldo_anterior = 0;


                array_push($cuentas, $cuenta_reporte);
            }
        }

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

    public function generarRepBalanceComprobacion($month, $year, $cuenta, $type)
    {
        if ($type === 'pdf') {
            return $this->generarRepBalanceComprobacionPDF($month, $year, $cuenta);
        } else {
            return $this->generarRepBalanceComprobacionExcel($month, $year, $cuenta);
        }
    }

    public function generarRepBalanceComprobacionPDF($month, $year, $cuenta = null)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        // Obtener todas las cuentas y aplicar ordenamiento jerárquico
        $cuentasQuery = Cuenta::where('id_empresa', $empresa_id)->orderBy('codigo');
        if ($cuenta && $cuenta !== 'all') {
            $cuentasQuery->where('id', $cuenta);
        }
        $todasLasCuentas = $cuentasQuery->get();
        $cuentas = collect($this->ordenarJerarquicamente($todasLasCuentas));

        // Obtener los movimientos del mes filtrado
        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->where('partidas.estado', 'Aplicada') // ✅ CORREGIDO: Usar 'Aplicada' consistentemente
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
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

        // ✅ CORREGIDO: Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->obtenerSaldosIniciales($year, $month, $empresa_id);

        // Crear un mapa de ID a código para facilitar las búsquedas
        $idACodigo = [];
        foreach ($cuentas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        // Asignamos los valores iniciales obtenidos de la consulta de partidas
        foreach ($cuentas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;

            // ✅ CORREGIDO: Usar lógica correcta para saldo inicial
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
        // ❌ ELIMINADO: $total_saldo_actual (no tiene sentido contable)
        $total_saldo_acumulado = 0;

        $balance = [];
        foreach ($cuentas as $cuenta) {
            $codigo = $cuenta->codigo;
            $saldo_inicial = $cuentas_saldos[$codigo]['saldo_inicial'] ?? 0;
            $debe = $cuentas_saldos[$codigo]['debe'] ?? 0;
            $haber = $cuentas_saldos[$codigo]['haber'] ?? 0;

            // ✅ CORREGIDO: Calcular saldo final según naturaleza de la cuenta
            if ($cuenta->naturaleza == 'Deudor') {
                $saldo_final = $saldo_inicial + $debe - $haber;
            } else {
                $saldo_final = $saldo_inicial + $haber - $debe;
            }

            // Sumar a totales SOLO las cuentas padre (nivel 0) que ya tienen consolidados sus valores
            if ($cuenta->nivel == 0) {
                $total_saldo_inicial += $saldo_inicial;
                $total_debe += $debe;
                $total_haber += $haber;
                // ❌ ELIMINADO: total_saldo_actual (no tiene sentido contable)
                $total_saldo_acumulado += $saldo_final; // ✅ Usar saldo_final directamente
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
                // ❌ ELIMINADO: 'saldo_actual' (problemático)
                'saldo_final' => $saldo_final, // ✅ Solo este campo
                'es_cuenta_padre' => $cuenta->nivel == 0,
            ];
        }

        // Crear array de totales
        $totales = [
            'saldo_inicial' => $total_saldo_inicial,
            'debe' => $total_debe,
            'haber' => $total_haber,
            // ❌ ELIMINADO: 'naturaleza' => $cuenta->naturaleza, (no pertenece aquí)
            // ❌ ELIMINADO: 'saldo_actual' => $total_saldo_actual,
            'saldo_acumulado' => $total_saldo_acumulado,
            'saldo_final' => $total_saldo_acumulado, // ✅ Mismo valor, para compatibilidad
            'diferencia' => $total_debe - $total_haber
        ];

        $pdf = PDF::loadView('reportes.contabilidad.rep_balance_comprobacion', compact('balance', 'empresa', 'month_name', 'year', 'totales'));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarRepBalanceComprobacionExcel($month, $year, $cuenta = null)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        // Obtener todas las cuentas y aplicar ordenamiento jerárquico
        $cuentasQuery = Cuenta::where('id_empresa', $empresa_id)->orderBy('codigo');
        if ($cuenta && $cuenta !== 'all') {
            $cuentasQuery->where('id', $cuenta);
        }
        $todasLasCuentas = $cuentasQuery->get();
        $cuentas = collect($this->ordenarJerarquicamente($todasLasCuentas));

        // Obtener los movimientos del mes filtrado
        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->where('partidas.estado', 'Aplicada') // ✅ CORREGIDO: Usar 'Aplicada' consistentemente
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
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

        // ✅ CORREGIDO: Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->obtenerSaldosIniciales($year, $month, $empresa_id);

        // Crear un mapa de ID a código para facilitar las búsquedas
        $idACodigo = [];
        foreach ($cuentas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        // Asignamos los valores iniciales obtenidos de la consulta de partidas
        foreach ($cuentas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;

            // ✅ CORREGIDO: Usar lógica correcta para saldo inicial
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
        // ❌ ELIMINADO: $total_saldo_actual (no tiene sentido contable)
        $total_saldo_acumulado = 0;

        $balance = [];
        foreach ($cuentas as $cuenta) {
            $codigo = $cuenta->codigo;
            $saldo_inicial = $cuentas_saldos[$codigo]['saldo_inicial'] ?? 0;
            $debe = $cuentas_saldos[$codigo]['debe'] ?? 0;
            $haber = $cuentas_saldos[$codigo]['haber'] ?? 0;

            // ✅ CORREGIDO: Calcular saldo final según naturaleza de la cuenta
            if ($cuenta->naturaleza == 'Deudor') {
                $saldo_final = $saldo_inicial + $debe - $haber;
            } else {
                $saldo_final = $saldo_inicial + $haber - $debe;
            }

            // Sumar a totales SOLO las cuentas padre (nivel 0) que ya tienen consolidados sus valores
            if ($cuenta->nivel == 0) {
                $total_saldo_inicial += $saldo_inicial;
                $total_debe += $debe;
                $total_haber += $haber;
                // ❌ ELIMINADO: total_saldo_actual (no tiene sentido contable)
                $total_saldo_acumulado += $saldo_final; // ✅ Usar saldo_final directamente
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
                // ❌ ELIMINADO: 'naturaleza' => $cuenta->naturaleza, (no pertenece aquí)
                // ❌ ELIMINADO: 'saldo_actual' => $total_saldo_actual,
                'saldo_acumulado' => $total_saldo_acumulado,
                'saldo_final' => $total_saldo_acumulado, // ✅ Mismo valor, para compatibilidad
                'diferencia' => $total_debe - $total_haber
            ]
        ];

        // Exportar a Excel
        return Excel::download(new BalanceComprobacionExport($data), 'balance_comprobacion.xlsx');
    }

    public function generarRepMovCuenta($startDate, $endDate, $cuenta_cod)
    {

        $empresa_id = auth()->user()->id_empresa;

        $cuenta = Cuenta::where('codigo', $cuenta_cod)->where('id_empresa', auth()->user()->id_empresa)->first();

        //        dd($cuenta_cod);

        $det_agrup = Detalle::whereHas('partida', function ($query) use ($empresa_id) {
            $query->where('id_empresa', $empresa_id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])->where('codigo', $cuenta_cod)
            ->get();

        $empresa = Empresa::findOrfail($empresa_id);

        $month = $startDate;
        $year = $endDate;

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

    public function generarBalanceGeneral($month, $year, $type)
    {
        if ($type === 'pdf') {
            return $this->generarBalanceGeneralPDF($month, $year);
        } else {
            return $this->generarBalanceGeneralExcel($month, $year);
        }
    }

    public function generarBalanceGeneralPDF($month, $year)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        // Obtener todas las cuentas con jerarquía completa
        $cuentasJerarquicas = Cuenta::where('id_empresa', $empresa_id)
            ->orderBy('codigo')
            ->get();

        // Obtener los movimientos del mes filtrado para todas las cuentas
        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->where('partidas.estado', 'Aplicada')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta');

        // ✅ CORREGIDO: Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->obtenerSaldosIniciales($year, $month, $empresa_id);

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

            // ✅ CORREGIDO: Usar lógica correcta para saldo inicial
            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;

            $cuentas_saldos[$codigo] = [
                'saldo_inicial' => (float)$saldoInicial, // ✅ CORREGIDO
                'debe' => $partida_detalles[$id]->total_debe ?? 0,
                'haber' => $partida_detalles[$id]->total_haber ?? 0,
            ];
        }

        // Consolidar hacia cuentas padre
        foreach ($cuentasJerarquicas->sortByDesc('nivel') as $cuenta) {
            if($cuenta->id_cuenta_padre && isset($idACodigo[$cuenta->id_cuenta_padre])) {
                $codigo_padre = $idACodigo[$cuenta->id_cuenta_padre];
                $cuentas_saldos[$codigo_padre]['saldo_inicial'] += $cuentas_saldos[$cuenta->codigo]['saldo_inicial'];
                $cuentas_saldos[$codigo_padre]['debe'] += $cuentas_saldos[$cuenta->codigo]['debe'];
                $cuentas_saldos[$codigo_padre]['haber'] += $cuentas_saldos[$cuenta->codigo]['haber'];
            }
        }

        // Clasificar por rubros del Balance General
        $balance_general = [
            'activos' => [],
            'pasivos' => [],
            'patrimonio' => [],
            'totales' => [
                'activos' => 0,
                'pasivos' => 0,
                'patrimonio' => 0
            ]
        ];

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

            $cuenta_data = [
                'codigo' => $codigo,
                'nombre' => $cuenta->nombre,
                'saldo_final' => $saldo_final,
                'naturaleza' => $cuenta->naturaleza
            ];

            // Clasificar según rubro
            $rubro = strtolower(trim($cuenta->rubro));

            if (strpos($rubro, 'activo') !== false) {
                $balance_general['activos'][] = $cuenta_data;
                $balance_general['totales']['activos'] += $saldo_final;
            }
            elseif (strpos($rubro, 'pasivo') !== false) {
                $balance_general['pasivos'][] = $cuenta_data;
                $balance_general['totales']['pasivos'] += $saldo_final;
            }
            elseif (strpos($rubro, 'capital') !== false ||
                    strpos($rubro, 'patrimonio') !== false ||
                    strpos($rubro, 'resultado') !== false) {
                $balance_general['patrimonio'][] = $cuenta_data;
                $balance_general['totales']['patrimonio'] += $saldo_final;
            }
        }

        // Verificar ecuación contable
        $balance_general['ecuacion_cuadra'] = abs($balance_general['totales']['activos'] -
            ($balance_general['totales']['pasivos'] + $balance_general['totales']['patrimonio'])) < 0.01;

        $pdf = PDF::loadView('reportes.contabilidad.balance_general', compact('balance_general', 'empresa', 'month_name', 'year'));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarBalanceGeneralExcel($month, $year)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        // Reutilizar la misma lógica del PDF pero para Excel
        $cuentas = Cuenta::where('id_empresa', $empresa_id)
            ->where('nivel', 0)
            ->orderBy('codigo')
            ->get();

        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->where('partidas.estado', 'Aplicada')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
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

        // ✅ CORREGIDO: Obtener saldos iniciales correctos (del período anterior o catálogo)
        $saldosIniciales = $this->obtenerSaldosIniciales($year, $month, $empresa_id);

        $cuentas_saldos = [];
        $idACodigo = [];

        foreach ($cuentasJerarquicas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        foreach ($cuentasJerarquicas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;

            // ✅ CORREGIDO: Usar lógica correcta para saldo inicial
            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;

            $cuentas_saldos[$codigo] = [
                'saldo_inicial' => (float)$saldoInicial, // ✅ CORREGIDO
                'debe' => $partida_detalles[$id]->total_debe ?? 0,
                'haber' => $partida_detalles[$id]->total_haber ?? 0,
            ];
        }

        // Consolidar hacia cuentas padre
        foreach ($cuentasJerarquicas->sortByDesc('nivel') as $cuenta) {
            if($cuenta->id_cuenta_padre && isset($idACodigo[$cuenta->id_cuenta_padre])) {
                $codigo_padre = $idACodigo[$cuenta->id_cuenta_padre];
                $cuentas_saldos[$codigo_padre]['saldo_inicial'] += $cuentas_saldos[$cuenta->codigo]['saldo_inicial'];
                $cuentas_saldos[$codigo_padre]['debe'] += $cuentas_saldos[$cuenta->codigo]['debe'];
                $cuentas_saldos[$codigo_padre]['haber'] += $cuentas_saldos[$cuenta->codigo]['haber'];
            }
        }

        // Clasificar por rubros del Balance General
        $balance_general = [
            'activos' => [],
            'pasivos' => [],
            'patrimonio' => [],
            'totales' => [
                'activos' => 0,
                'pasivos' => 0,
                'patrimonio' => 0
            ]
        ];

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

            $cuenta_data = [
                'codigo' => $codigo,
                'nombre' => $cuenta->nombre,
                'saldo_final' => $saldo_final,
                'naturaleza' => $cuenta->naturaleza
            ];

            $rubro = strtolower(trim($cuenta->rubro));

            if (strpos($rubro, 'activo') !== false) {
                $balance_general['activos'][] = $cuenta_data;
                $balance_general['totales']['activos'] += $saldo_final;
            }
            elseif (strpos($rubro, 'pasivo') !== false) {
                $balance_general['pasivos'][] = $cuenta_data;
                $balance_general['totales']['pasivos'] += $saldo_final;
            }
            elseif (strpos($rubro, 'capital') !== false ||
                    strpos($rubro, 'patrimonio') !== false ||
                    strpos($rubro, 'resultado') !== false) {
                $balance_general['patrimonio'][] = $cuenta_data;
                $balance_general['totales']['patrimonio'] += $saldo_final;
            }
        }

        $balance_general['ecuacion_cuadra'] = abs($balance_general['totales']['activos'] -
            ($balance_general['totales']['pasivos'] + $balance_general['totales']['patrimonio'])) < 0.01;

        $data = [
            'empresa' => $empresa,
            'month_name' => $month_name,
            'year' => $year,
            'balance_general' => $balance_general,
        ];

        return Excel::download(new BalanceGeneralExport($data), 'balance_general.xlsx');
    }

    public function generarEstadoResultados($month, $year, $type)
    {
        if ($type === 'pdf') {
            return $this->generarEstadoResultadosPDF($month, $year);
        } else {
            return $this->generarEstadoResultadosExcel($month, $year);
        }
    }

    public function generarEstadoResultadosPDF($month, $year)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        // Obtener todas las cuentas padre (nivel 0) con sus saldos consolidados
        $cuentas = Cuenta::where('id_empresa', $empresa_id)
            ->where('nivel', 0) // Solo cuentas padre
            ->orderBy('codigo')
            ->get();

        // Obtener los movimientos del mes filtrado para todas las cuentas
        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->where('partidas.estado', 'Aplicada')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta');

        $estado_resultados = [
            'ingresos' => [],
            'costos_gastos' => [],
            'totales' => [
                'ingresos' => 0,
                'costos_gastos' => 0,
                'utilidad_perdida' => 0
            ]
        ];

        // Procesar cada cuenta
        foreach ($cuentas as $cuenta) {
            $movimientos = $partida_detalles->get($cuenta->id);

            if (!$movimientos) {
                $debe = 0;
                $haber = 0;
            } else {
                $debe = $movimientos->total_debe ?? 0;
                $haber = $movimientos->total_haber ?? 0;
            }

            // Calcular saldo según naturaleza de la cuenta
            if ($cuenta->naturaleza === 'Deudor') {
                $saldo_final = $debe - $haber;
            } else {
                $saldo_final = $haber - $debe;
            }

            // Solo incluir cuentas con saldo diferente de cero
            if ($saldo_final != 0) {
                $cuenta_info = [
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'saldo_final' => $saldo_final,
                    'naturaleza' => $cuenta->naturaleza,
                    'rubro' => $cuenta->rubro
                ];

                // Clasificar según el rubro
                if ($cuenta->rubro === 'Ingresos') {
                    $estado_resultados['ingresos'][] = $cuenta_info;
                    $estado_resultados['totales']['ingresos'] += abs($saldo_final);
                } elseif ($cuenta->rubro === 'Costos y gastos') {
                    $estado_resultados['costos_gastos'][] = $cuenta_info;
                    $estado_resultados['totales']['costos_gastos'] += abs($saldo_final);
                }
            }
        }

        // Calcular utilidad/pérdida
        $estado_resultados['totales']['utilidad_perdida'] =
            $estado_resultados['totales']['ingresos'] - $estado_resultados['totales']['costos_gastos'];

        $pdf = PDF::loadView('reportes.contabilidad.estado_resultados', compact(
            'estado_resultados',
            'empresa',
            'month_name',
            'month',
            'year'
        ));

        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function generarEstadoResultadosExcel($month, $year)
    {
        $empresa_id = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresa_id);
        $month_name = Carbon::createFromDate($year, $month)->monthName;

        // Obtener todas las cuentas padre (nivel 0) con sus saldos consolidados
        $cuentas = Cuenta::where('id_empresa', $empresa_id)
            ->where('nivel', 0) // Solo cuentas padre
            ->orderBy('codigo')
            ->get();

        // Obtener los movimientos del mes filtrado para todas las cuentas
        $partida_detalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->where('partidas.estado', 'Aplicada')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta');

        $estado_resultados = [
            'ingresos' => [],
            'costos_gastos' => [],
            'totales' => [
                'ingresos' => 0,
                'costos_gastos' => 0,
                'utilidad_perdida' => 0
            ]
        ];

        // Procesar cada cuenta
        foreach ($cuentas as $cuenta) {
            $movimientos = $partida_detalles->get($cuenta->id);

            if (!$movimientos) {
                $debe = 0;
                $haber = 0;
            } else {
                $debe = $movimientos->total_debe ?? 0;
                $haber = $movimientos->total_haber ?? 0;
            }

            // Calcular saldo según naturaleza de la cuenta
            if ($cuenta->naturaleza === 'Deudor') {
                $saldo_final = $debe - $haber;
            } else {
                $saldo_final = $haber - $debe;
            }

            // Solo incluir cuentas con saldo diferente de cero
            if ($saldo_final != 0) {
                $cuenta_info = [
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'saldo_final' => $saldo_final,
                    'naturaleza' => $cuenta->naturaleza,
                    'rubro' => $cuenta->rubro
                ];

                // Clasificar según el rubro
                if ($cuenta->rubro === 'Ingresos') {
                    $estado_resultados['ingresos'][] = $cuenta_info;
                    $estado_resultados['totales']['ingresos'] += abs($saldo_final);
                } elseif ($cuenta->rubro === 'Costos y gastos') {
                    $estado_resultados['costos_gastos'][] = $cuenta_info;
                    $estado_resultados['totales']['costos_gastos'] += abs($saldo_final);
                }
            }
        }

        // Calcular utilidad/pérdida
        $estado_resultados['totales']['utilidad_perdida'] =
            $estado_resultados['totales']['ingresos'] - $estado_resultados['totales']['costos_gastos'];

        return (new EstadoResultadosExport($estado_resultados, $empresa, $month_name, $month, $year))->download('estado_resultados_' . $month . '_' . $year . '.xlsx');
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
     * Obtener saldos iniciales del período (igual que CierreMesService)
     */
    private function obtenerSaldosIniciales($year, $month, $empresa_id)
    {
        // Si es enero del primer año, usar catálogo
        if ($month == 1) {
            // Verificar si existe algún período anterior en cualquier año
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
        }

        // Obtener período anterior
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
