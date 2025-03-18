<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CostoIA;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReporteCostosIA extends Command
{
    protected $signature = 'ia:costos {--empresa=} {--desde=} {--hasta=}';
    
    protected $description = 'Genera un reporte de costos de IA por empresa y periodo';
    
    public function handle()
    {
        $idEmpresa = $this->option('empresa');
        $desde = $this->option('desde') ? Carbon::parse($this->option('desde')) : Carbon::now()->startOfMonth();
        $hasta = $this->option('hasta') ? Carbon::parse($this->option('hasta')) : Carbon::now();
        
        $query = CostoIA::query()
            ->select(
                'id_empresa',
                DB::raw('SUM(tokens_entrada) as total_tokens_entrada'),
                DB::raw('SUM(tokens_salida) as total_tokens_salida'),
                DB::raw('SUM(costo_estimado) as costo_total'),
                DB::raw('COUNT(*) as total_consultas')
            )
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupBy('id_empresa');
            
        if ($idEmpresa) {
            $query->where('id_empresa', $idEmpresa);
        }
        
        $resultados = $query->get();
        
        if ($resultados->isEmpty()) {
            $this->info('No se encontraron registros para el periodo especificado.');
            return 0;
        }
        
        $this->info('Reporte de Costos de IA');
        $this->info('Periodo: ' . $desde->format('Y-m-d') . ' a ' . $hasta->format('Y-m-d'));
        $this->info('');
        
        $headers = ['Empresa ID', 'Consultas', 'Tokens Entrada', 'Tokens Salida', 'Costo Estimado ($)'];
        $data = [];
        
        foreach ($resultados as $resultado) {
            $data[] = [
                $resultado->id_empresa,
                $resultado->total_consultas,
                $resultado->total_tokens_entrada,
                $resultado->total_tokens_salida,
                number_format($resultado->costo_total, 6)
            ];
        }
        
        $this->table($headers, $data);
        
        return 0;
    }
}