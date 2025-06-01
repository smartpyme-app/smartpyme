<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Empresa;
use Illuminate\Support\Str;

class GenerateWhatsAppCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:generate-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar códigos únicos de WhatsApp para todas las empresas';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🚀 Generando códigos de WhatsApp para empresas...');
        $this->newLine();

        
        $empresas = Empresa::whereNull('codigo')->get();

        if ($empresas->isEmpty()) {
            $this->info('✅ Todas las empresas ya tienen códigos asignados.');
            return Command::SUCCESS;
        }

        $this->info("📊 Empresas a procesar: {$empresas->count()}");
        $this->newLine();

        $tableData = [];
        $generatedCodes = [];

        foreach ($empresas as $empresa) {
            $newCode = $this->generateUniqueCode($empresa, $generatedCodes);
            $generatedCodes[] = $newCode;

            $tableData[] = [
                'ID' => $empresa->id,
                'Empresa' => Str::limit($empresa->nombre, 30),
                'Código Actual' => $empresa->codigo ?? '(sin código)',
                'Código Nuevo' => $newCode,
                'Estado' => $empresa->codigo ? '🔄 Actualizar' : '🆕 Nuevo'
            ];
        }

       
        $this->table([
            'ID',
            'Empresa',
            'Código Actual',
            'Código Nuevo',
            'Estado'
        ], $tableData);

        
        if (!$this->confirm('¿Desea proceder con la generación de códigos?', true)) {
            $this->info('❌ Operación cancelada.');
            return Command::SUCCESS;
        }

        
        $this->withProgressBar($empresas, function ($empresa) use ($generatedCodes, $empresas) {
            $index = $empresas->search($empresa);
            $newCode = $generatedCodes[$index];

            try {
                $empresa->update(['codigo' => $newCode]);
            } catch (\Exception $e) {
                $this->error("Error actualizando empresa {$empresa->id}: {$e->getMessage()}");
            }
        });

        $this->newLine(2);
        $this->info('✅ Códigos generados exitosamente!');
        $this->info("📈 Total procesadas: {$empresas->count()} empresas");

        
        $this->showFinalStats();

        return Command::SUCCESS;
    }

 
    private function generateUniqueCode($empresa, $existingCodes = [])
    {
        
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $empresa->nombre);
        
        
        $prefix = strtoupper(substr($cleanName, 0, 3));
        if (strlen($cleanName) < 3) {
            $prefix = 'EMP';
        }
        
  
        $empresaId = str_pad($empresa->id, 3, '0', STR_PAD_LEFT);
        
        
        $nameHash = strtoupper(substr(md5($empresa->nombre), 0, 4));
        
        
        $timestamp = substr(time(), -4);
        
        
        $baseCode = $prefix . $empresaId . $nameHash . $timestamp;
        
        
        $codigo = $baseCode;
        $counter = 1;
        
        while ($this->codeExists($codigo, $empresa->id) || in_array($codigo, $existingCodes)) {
            
            $codigo = $baseCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }
        
        return $codigo;
    }

    
    private function codeExists($codigo, $empresaId)
    {
        return Empresa::where('codigo', $codigo)
            ->where('id', '!=', $empresaId)
            ->exists();
    }

    
    private function showFinalStats()
    {
        $totalEmpresas = Empresa::count();
        $empresasConCodigo = Empresa::whereNotNull('codigo')->count();
        $empresasSinCodigo = $totalEmpresas - $empresasConCodigo;

        $this->newLine();
        $this->info('📊 Estadísticas finales:');
        $this->line("   • Total empresas: {$totalEmpresas}");
        $this->line("   • Con código: {$empresasConCodigo}");
        $this->line("   • Sin código: {$empresasSinCodigo}");

        if ($empresasSinCodigo > 0) {
            $this->warn("⚠️ Aún hay {$empresasSinCodigo} empresas sin código.");
        }
    }
}