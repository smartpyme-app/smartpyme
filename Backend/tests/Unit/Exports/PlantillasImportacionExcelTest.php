<?php

namespace Tests\Unit\Exports;

use App\Exports\PlantillaProductosImportExport;
use App\Exports\ProveedoresEmpresasPlantillaExport;
use App\Exports\ProveedoresPersonasPlantillaExport;
use App\Exports\ServiciosPlantillaExport;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PlantillasImportacionExcelTest extends TestCase
{
    public function test_servicios_plantilla_es_xlsx_valido(): void
    {
        $raw = Excel::raw(new ServiciosPlantillaExport(), \Maatwebsite\Excel\Excel::XLSX);

        $this->assertNotSame('', $raw);
        $this->assertSame('PK', substr($raw, 0, 2), 'La plantilla de servicios debe ser un ZIP/XLSX');
    }

    public function test_productos_plantilla_es_xlsx_valido_con_usuario(): void
    {
        // setUser evita el evento de login que intenta persistir el usuario.
        $user = new User();
        $user->id = 1;
        $user->id_empresa = 1;
        Auth::setUser($user);

        $raw = Excel::raw(new PlantillaProductosImportExport(), \Maatwebsite\Excel\Excel::XLSX);

        $this->assertNotSame('', $raw);
        $this->assertSame('PK', substr($raw, 0, 2), 'La plantilla de productos debe ser un ZIP/XLSX');
    }

    public function test_plantillas_proveedores_incluyen_columnas_bancarias(): void
    {
        foreach (['Banco', 'Tipo_cuenta', 'Numero_cuenta', 'Titular_cuenta', 'Forma_pago'] as $columna) {
            $this->assertContains($columna, (new ProveedoresPersonasPlantillaExport())->headings());
            $this->assertContains($columna, (new ProveedoresEmpresasPlantillaExport())->headings());
        }
    }
}
