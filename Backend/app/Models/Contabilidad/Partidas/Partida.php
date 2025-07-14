<?php

namespace App\Models\Contabilidad\Partidas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;
use Illuminate\Support\Facades\DB;

class Partida extends Model
{
    use HasFactory;
    protected $table = 'partidas';
    protected $fillable = [
        'fecha',
        'tipo',
        'correlativo',
        'concepto',
        'estado',
        'referencia',
        'id_referencia',
        'id_usuario',
        'id_empresa',
    ];

    protected $appends = ['nombre_usuario', 'ruta_referencia'];
    
    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }

        static::creating(function ($partida) {
            if (empty($partida->correlativo)) {
                $partida->correlativo = $partida->generarCorrelativo();
            }
        });
        
    }

    public function generarCorrelativo()
    {
        return DB::transaction(function () {
            $tipoMap = [
                'Ingreso' => 'I',
                'Egreso' => 'E',
                'Diario' => 'D',
                'CxC' => 'C',
                'CxP' => 'P',
                'Cierre' => 'R'
            ];

            $año = date('Y', strtotime($this->fecha));
            $mes = date('m', strtotime($this->fecha));
            $tipoLetra = $tipoMap[$this->tipo] ?? 'D';

            // Bloquear tabla para evitar concurrencia (Opción 1 elegida)
            DB::table('partidas')->lockForUpdate()
                ->where('id_empresa', $this->id_empresa)
                ->where('tipo', $this->tipo)
                ->where('fecha', 'LIKE', $año . '-' . $mes . '%')
                ->get();

            // Obtener último secuencial del mes/tipo
            $ultimoCorrelativo = static::where('id_empresa', $this->id_empresa)
                ->where('tipo', $this->tipo)
                ->where('fecha', 'LIKE', $año . '-' . $mes . '%')
                ->where('correlativo', 'LIKE', 'P' . $tipoLetra . $año . $mes . '%')
                ->orderBy('correlativo', 'desc')
                ->value('correlativo');

            $siguienteSecuencial = 1;

            if ($ultimoCorrelativo) {
                // Extraer el secuencial del correlativo (últimos 4 dígitos)
                $ultimoSecuencial = (int) substr($ultimoCorrelativo, -4);
                $siguienteSecuencial = $ultimoSecuencial + 1;
            }

            // Generar correlativo: PE202507001
            return 'P' . $tipoLetra . $año . $mes . str_pad($siguienteSecuencial, 4, '0', STR_PAD_LEFT);
        });
    }

    public static function reordenarCorrelativos($año, $mes, $tipo, $idEmpresa)
    {
        return DB::transaction(function () use ($año, $mes, $tipo, $idEmpresa) {
            $tipoMap = [
                'Ingreso' => 'I',
                'Egreso' => 'E',
                'Diario' => 'D',
                'CxC' => 'C',
                'CxP' => 'P',
                'Cierre' => 'R'
            ];

            $tipoLetra = $tipoMap[$tipo] ?? 'D';

            // Obtener partidas ordenadas por fecha y id
            $partidas = static::where('id_empresa', $idEmpresa)
                ->where('tipo', $tipo)
                ->where('estado', '!=', 'Anulada')
                ->where('fecha', 'LIKE', $año . '-' . $mes . '%')
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();

            $secuencial = 1;
            foreach ($partidas as $partida) {
                $nuevoCorrelativo = 'P' . $tipoLetra . $año . $mes . str_pad($secuencial, 4, '0', STR_PAD_LEFT);
                $partida->update(['correlativo' => $nuevoCorrelativo]);
                $secuencial++;
            }

            return $partidas->count();
        });
    }

    public static function reordenarTodosLosCorrelativos($idEmpresa)
    {
        return DB::transaction(function () use ($idEmpresa) {
            $tipoMap = [
                'Ingreso' => 'I',
                'Egreso' => 'E',
                'Diario' => 'D',
                'CxC' => 'C',
                'CxP' => 'P',
                'Cierre' => 'R'
            ];

            $totalReordenadas = 0;
            $tipos = ['Ingreso', 'Egreso', 'Diario', 'CxC', 'CxP', 'Cierre'];

            foreach ($tipos as $tipo) {
                $tipoLetra = $tipoMap[$tipo];

                // Obtener todas las partidas del tipo, agrupadas por año-mes
                $partidas = static::where('id_empresa', $idEmpresa)
                    ->where('tipo', $tipo)
                    ->where('estado', '!=', 'Anulada')
                    ->orderBy('fecha')
                    ->orderBy('id')
                    ->get()
                    ->groupBy(function($partida) {
                        return date('Y-m', strtotime($partida->fecha));
                    });

                foreach ($partidas as $añoMes => $partidasDelMes) {
                    $secuencial = 1;
                    $año = substr($añoMes, 0, 4);
                    $mes = substr($añoMes, 5, 2);

                    foreach ($partidasDelMes as $partida) {
                        $nuevoCorrelativo = 'P' . $tipoLetra . $año . $mes . str_pad($secuencial, 4, '0', STR_PAD_LEFT);
                        $partida->update(['correlativo' => $nuevoCorrelativo]);
                        $secuencial++;
                        $totalReordenadas++;
                    }
                }
            }

            return $totalReordenadas;
        });
    }

    public function getRutaReferenciaAttribute(){
        if ($this->referencia == 'Venta') {
            return 'venta';
        }
        if ($this->referencia == 'Abono de Venta') {
            return 'venta/abono';
        }
        if ($this->referencia == 'Abono de Compra') {
            return 'compra/abono';
        }
        if ($this->referencia == 'Compra') {
            return 'compra';
        }
        if ($this->referencia == 'Cheque') {
            return 'bancos/cheque';
        }

    }

    public function getNombreUsuarioAttribute()
    {   
        return $this->usuario()->pluck('name')->first();
    }
    
    public function detalles(){
        return $this->hasMany('App\Models\Contabilidad\Partidas\Detalle', 'id_partida');
    }
    
    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
    
}
