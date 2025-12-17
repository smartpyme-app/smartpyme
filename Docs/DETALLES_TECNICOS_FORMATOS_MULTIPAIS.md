# Detalles Técnicos: Formatos y Estructuras Multi-País

## Documento Complementario - Detalles de Implementación

Este documento complementa la auditoría principal con detalles técnicos específicos sobre formatos, estructuras de datos y ejemplos de código para la implementación multi-país.

---

## Estructura de Base de Datos

### Tabla: `configuracion_impuestos_pais`

```sql
CREATE TABLE `configuracion_impuestos_pais` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_pais` VARCHAR(3) NOT NULL COMMENT 'SV o HN',
  `tipo_impuesto` VARCHAR(50) NOT NULL COMMENT 'IVA, ISV, ISR, etc.',
  `nombre_impuesto` VARCHAR(100) NOT NULL COMMENT 'Nombre oficial del impuesto',
  `porcentaje` DECIMAL(5,2) NOT NULL,
  `activo` BOOLEAN DEFAULT TRUE,
  `fecha_vigencia_desde` DATE NOT NULL,
  `fecha_vigencia_hasta` DATE NULL,
  `descripcion` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_impuesto_pais_vigencia` (`cod_pais`, `tipo_impuesto`, `fecha_vigencia_desde`),
  KEY `idx_cod_pais` (`cod_pais`),
  KEY `idx_tipo_impuesto` (`tipo_impuesto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales
INSERT INTO `configuracion_impuestos_pais` (`cod_pais`, `tipo_impuesto`, `nombre_impuesto`, `porcentaje`, `fecha_vigencia_desde`) VALUES
('SV', 'IVA', 'Impuesto al Valor Agregado', 13.00, '2024-01-01'),
('SV', 'ISR', 'Impuesto sobre la Renta', 30.00, '2024-01-01'),
('SV', 'PERCEPCION', 'Percepción IVA', 1.00, '2024-01-01'),
('SV', 'RETENCION_IVA', 'Retención IVA', 1.00, '2024-01-01'),
('HN', 'ISV', 'Impuesto sobre Ventas', 15.00, '2024-01-01'),
('HN', 'ISR', 'Impuesto sobre la Renta', 25.00, '2024-01-01');
```

### Tabla: `normativas_pais`

```sql
CREATE TABLE `normativas_pais` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_pais` VARCHAR(3) NOT NULL,
  `modulo` VARCHAR(50) NOT NULL COMMENT 'libro_iva, anexo, retencion, etc.',
  `configuracion` JSON NOT NULL COMMENT 'Configuración específica del módulo',
  `activo` BOOLEAN DEFAULT TRUE,
  `version` VARCHAR(10) DEFAULT '1.0',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_normativa_pais_modulo` (`cod_pais`, `modulo`),
  KEY `idx_cod_pais` (`cod_pais`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabla: `libro_actas_asamblea` (Honduras)

```sql
CREATE TABLE `libro_actas_asamblea` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_empresa` INT UNSIGNED NOT NULL,
  `numero_acta` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `tipo_asamblea` ENUM('Ordinaria', 'Extraordinaria') NOT NULL,
  `asistentes` JSON NOT NULL COMMENT 'Array de asistentes con sus participaciones',
  `acuerdos` TEXT NOT NULL,
  `firmas` JSON NULL COMMENT 'Información de firmas',
  `archivo_pdf` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`id_empresa`),
  KEY `idx_fecha` (`fecha`),
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabla: `libro_registro_accionistas` (Honduras)

```sql
CREATE TABLE `libro_registro_accionistas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_empresa` INT UNSIGNED NOT NULL,
  `nombre_accionista` VARCHAR(255) NOT NULL,
  `numero_identificacion` VARCHAR(50) NOT NULL,
  `tipo_identificacion` VARCHAR(20) NOT NULL COMMENT 'RTN, Pasaporte, etc.',
  `numero_acciones` INT NOT NULL,
  `porcentaje_participacion` DECIMAL(5,2) NOT NULL,
  `fecha_registro` DATE NOT NULL,
  `fecha_transferencia` DATE NULL,
  `tipo_operacion` ENUM('Registro', 'Transferencia', 'Baja') DEFAULT 'Registro',
  `observaciones` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`id_empresa`),
  KEY `idx_fecha_registro` (`fecha_registro`),
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Servicios y Clases

### Service: `NormativaPaisService.php`

```php
<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\NormativaPais;
use Illuminate\Support\Facades\Cache;

class NormativaPaisService
{
    /**
     * Obtiene la configuración de un módulo para un país específico
     */
    public static function obtenerConfiguracion(string $codPais, string $modulo): array
    {
        $cacheKey = "normativa_{$codPais}_{$modulo}";
        
        return Cache::remember($cacheKey, 3600, function () use ($codPais, $modulo) {
            $normativa = NormativaPais::where('cod_pais', $codPais)
                ->where('modulo', $modulo)
                ->where('activo', true)
                ->first();
            
            if (!$normativa) {
                return self::getConfiguracionDefault($codPais, $modulo);
            }
            
            return array_merge(
                self::getConfiguracionDefault($codPais, $modulo),
                json_decode($normativa->configuracion, true)
            );
        });
    }
    
    /**
     * Obtiene configuración por defecto según país
     */
    private static function getConfiguracionDefault(string $codPais, string $modulo): array
    {
        $defaults = [
            'SV' => [
                'libro_iva' => [
                    'nombre_campo_nit' => 'NRC',
                    'clase_documento_dte' => '4',
                    'clase_documento_impreso' => '1',
                    'formato_anexo' => 'el_salvador',
                    'delimitador_csv' => ';',
                    'codificacion' => 'UTF-8',
                ],
                'anexo' => [
                    'columnas' => [
                        'A' => 'Fecha',
                        'B' => 'Clase',
                        'C' => 'Tipo',
                        'D' => 'Resolución',
                        'E' => 'Serie',
                        'F' => 'Número Interno Desde',
                        'G' => 'Número Interno Hasta',
                        'H' => 'Número Control Desde',
                        'I' => 'Número Control Hasta',
                        // ... más columnas
                    ],
                ],
            ],
            'HN' => [
                'libro_iva' => [
                    'nombre_campo_nit' => 'RTN',
                    'clase_documento_dte' => '4',
                    'clase_documento_impreso' => '1',
                    'formato_anexo' => 'honduras',
                    'delimitador_csv' => ',',
                    'codificacion' => 'UTF-8',
                ],
                'anexo' => [
                    'columnas' => [
                        'A' => 'Fecha',
                        'B' => 'Tipo Documento',
                        'C' => 'Número Documento',
                        'D' => 'RTN',
                        'E' => 'Nombre',
                        // ... columnas específicas Honduras
                    ],
                ],
            ],
        ];
        
        return $defaults[$codPais][$modulo] ?? [];
    }
    
    /**
     * Obtiene el porcentaje de un impuesto para un país
     */
    public static function obtenerPorcentajeImpuesto(string $codPais, string $tipoImpuesto): float
    {
        $impuesto = \App\Models\Contabilidad\ConfiguracionImpuestosPais::where('cod_pais', $codPais)
            ->where('tipo_impuesto', $tipoImpuesto)
            ->where('activo', true)
            ->where('fecha_vigencia_desde', '<=', now())
            ->where(function ($query) {
                $query->whereNull('fecha_vigencia_hasta')
                    ->orWhere('fecha_vigencia_hasta', '>=', now());
            })
            ->orderBy('fecha_vigencia_desde', 'desc')
            ->first();
        
        return $impuesto ? (float) $impuesto->porcentaje : 0.0;
    }
}
```

### Service: `LibroIvaFormatoService.php`

```php
<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\Empresa;
use App\Services\Contabilidad\NormativaPaisService;

class LibroIvaFormatoService
{
    /**
     * Obtiene el formato de anexo según el país
     */
    public function obtenerFormatoAnexo(Empresa $empresa): array
    {
        $codPais = $empresa->cod_pais ?? 'SV';
        return NormativaPaisService::obtenerConfiguracion($codPais, 'anexo');
    }
    
    /**
     * Formatea los datos del anexo según el país
     */
    public function formatearDatosAnexo(array $datos, Empresa $empresa): array
    {
        $codPais = $empresa->cod_pais ?? 'SV';
        $configuracion = $this->obtenerFormatoAnexo($empresa);
        
        if ($codPais === 'SV') {
            return $this->formatearAnexoElSalvador($datos, $configuracion);
        } elseif ($codPais === 'HN') {
            return $this->formatearAnexoHonduras($datos, $configuracion);
        }
        
        return $datos;
    }
    
    /**
     * Formatea anexo para El Salvador
     */
    private function formatearAnexoElSalvador(array $datos, array $configuracion): array
    {
        return [
            'A' => $datos['fecha'] ?? '',
            'B' => $datos['clase_documento'] ?? '',
            'C' => $datos['tipo'] ?? '01',
            'D' => $datos['resolucion'] ?? '',
            'E' => $datos['serie'] ?? '',
            'F' => $datos['numero_interno_desde'] ?? '',
            'G' => $datos['numero_interno_hasta'] ?? '',
            'H' => $datos['numero_control_desde'] ?? '',
            'I' => $datos['numero_control_hasta'] ?? '',
            // ... más campos según formato El Salvador
        ];
    }
    
    /**
     * Formatea anexo para Honduras
     */
    private function formatearAnexoHonduras(array $datos, array $configuracion): array
    {
        return [
            'A' => $datos['fecha'] ?? '',
            'B' => $datos['tipo_documento'] ?? '',
            'C' => $datos['numero_documento'] ?? '',
            'D' => $datos['rtn'] ?? '',
            'E' => $datos['nombre'] ?? '',
            // ... más campos según formato Honduras
        ];
    }
    
    /**
     * Obtiene el nombre del campo de identificación según país
     */
    public function obtenerNombreCampoIdentificacion(Empresa $empresa): string
    {
        $codPais = $empresa->cod_pais ?? 'SV';
        $configuracion = NormativaPaisService::obtenerConfiguracion($codPais, 'libro_iva');
        return $configuracion['nombre_campo_nit'] ?? 'NRC';
    }
}
```

### Actualización: `ImpuestosService.php`

```php
<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Services\Contabilidad\NormativaPaisService;
use Illuminate\Support\Facades\Log;

class ImpuestosService
{
    /**
     * Obtiene el porcentaje de IVA/ISV configurado para la empresa según su país
     */
    public function obtenerPorcentajeImpuesto($empresaId, $tipoImpuesto = 'IVA')
    {
        $empresa = Empresa::withoutGlobalScope('empresa')->find($empresaId);

        if (!$empresa) {
            Log::warning('Empresa no encontrada', ['empresa_id' => $empresaId]);
            return 0.0;
        }

        $codPais = $empresa->cod_pais ?? 'SV';
        
        // Mapear tipo de impuesto según país
        if ($codPais === 'HN' && $tipoImpuesto === 'IVA') {
            $tipoImpuesto = 'ISV'; // Honduras usa ISV en lugar de IVA
        }

        return NormativaPaisService::obtenerPorcentajeImpuesto($codPais, $tipoImpuesto);
    }
    
    /**
     * Calcula el precio sin impuesto desde un precio con impuesto
     */
    public function calcularPrecioSinImpuesto($precioConImpuesto, $empresaId)
    {
        $precioConImpuesto = floatval($precioConImpuesto);
        $porcentaje = $this->obtenerPorcentajeImpuesto($empresaId);
        
        if ($porcentaje <= 0) {
            return $precioConImpuesto;
        }
        
        return $precioConImpuesto / (1 + ($porcentaje / 100));
    }
    
    /**
     * Calcula el impuesto desde un precio sin impuesto
     */
    public function calcularImpuesto($precioSinImpuesto, $empresaId)
    {
        $precioSinImpuesto = floatval($precioSinImpuesto);
        $porcentaje = $this->obtenerPorcentajeImpuesto($empresaId);
        
        return $precioSinImpuesto * ($porcentaje / 100);
    }
}
```

---

## Ejemplos de Modificación de Exports

### Ejemplo: `AnexoConsumidoresExport.php` (Modificado)

```php
<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Services\Contabilidad\LibroIvaFormatoService;
use App\Services\Contabilidad\NormativaPaisService;

class AnexoConsumidoresExport implements FromCollection, WithMapping, WithCustomCsvSettings
{
    public $request;
    private $formatoService;
    private $empresa;

    public function filter(Request $request)
    {
        $this->request = $request;
        $this->empresa = Auth::user()->empresa()->first();
        $this->formatoService = new LibroIvaFormatoService();
    }

    public function collection()
    {
        $request = $this->request;

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', function($q) {
                $q->where('nombre', 'Factura')->orWhere('nombre', 'Factura de exportación');
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderByDesc('fecha')
            ->get();
            
        return $ventas;
    }

    public function map($venta): array
    {
        setlocale(LC_NUMERIC, 'C');

        $documento = $venta->documento;
        $cliente = optional($venta->cliente);
        $codPais = $this->empresa->cod_pais ?? 'SV';
        $configuracion = NormativaPaisService::obtenerConfiguracion($codPais, 'libro_iva');

        // Lógica común
        $tipo = '01'; // CF
        $esFacturaExportacion = $documento && strtolower(trim($documento->nombre ?? '')) === 'factura de exportación';

        if ($esFacturaExportacion) {
            $tipo = '11';
        }

        // Clasificar ventas
        if ($esFacturaExportacion) {
            $venta->exenta = 0;
            $venta->gravada = 0;
        } elseif ($venta->iva > 0) {
            $venta->exenta = 0;
            $venta->gravada = $venta->total;
        } else {
            $venta->gravada = 0;
            $venta->exenta = $venta->total;
        }

        // Formatear según país
        if ($codPais === 'SV') {
            return $this->formatearElSalvador($venta, $configuracion, $tipo, $esFacturaExportacion);
        } elseif ($codPais === 'HN') {
            return $this->formatearHonduras($venta, $configuracion, $tipo, $esFacturaExportacion);
        }

        return [];
    }

    private function formatearElSalvador($venta, $configuracion, $tipo, $esFacturaExportacion): array
    {
        $tieneFE = $this->tieneFacturacionElectronica() && $venta->sello_mh;
        $codigoGeneracion = $tieneFE && isset($venta->dte['identificacion']['codigoGeneracion']) 
            ? str_replace('-', '', $venta->dte['identificacion']['codigoGeneracion']) 
            : '';
        $correlativo = trim($venta->correlativo);

        return [
            \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), // A Fecha
            $this->obtenerClaseDocumento($venta), // B Clase
            $tipo, // C Tipo
            $tieneFE ? str_replace('-', '', $venta->dte['identificacion']['numeroControl'] ?? '') : '', // D Resolucion
            $tieneFE ? ($venta->dte['sello'] ?? '') : '', // E Serie
            $tieneFE ? $codigoGeneracion : $correlativo, // F Numero Interno Desde
            $tieneFE ? $codigoGeneracion : $correlativo, // G Numero Interno Hasta
            $tieneFE ? '' : $correlativo, // H Numero Control Desde
            $tieneFE ? '' : $correlativo, // I Numero Control Hasta
            NULL, // J Caja registradora
            $venta->exenta ? number_format($venta->exenta, 2, '.', '') : '0.00', // K Exentas
            '0.00', // L No Exentas no sujetas
            $venta->no_sujeta ? number_format($venta->no_sujeta, 2, '.', '') : '0.00', // M No Sujetas
            $esFacturaExportacion ? '0.00' : number_format($venta->gravada, 2, '.', ''), // N Gravadas
            $esFacturaExportacion ? number_format($venta->total, 2, '.', ''): '0.00', // O Exportacion
            '0.00', // P Exportacion externas
            '0.00', // Q Exportacion servicios
            '0.00', // R Ventas zonas francas
            '0.00', // S Ventas a terceros
            $venta->total ? number_format($venta->total, 2, '.', '') : '0.00', // T Total
            $this->tipoOperacion($venta->tipo_operacion), // U Tipo operacion
            $this->tipoRenta($venta->tipo_renta), // V Tipo ingreso
            2, // W num de Anexo
        ];
    }

    private function formatearHonduras($venta, $configuracion, $tipo, $esFacturaExportacion): array
    {
        // Formato específico para Honduras según requerimientos SAR
        return [
            \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), // A Fecha
            $tipo, // B Tipo Documento
            trim($venta->correlativo), // C Número Documento
            optional($venta->cliente)->nit ?? '', // D RTN
            $venta->nombre_cliente ?? '', // E Nombre
            // ... más campos según formato Honduras
        ];
    }

    public function getCsvSettings(): array
    {
        $codPais = $this->empresa->cod_pais ?? 'SV';
        $configuracion = NormativaPaisService::obtenerConfiguracion($codPais, 'libro_iva');
        
        return [
            'delimiter' => $configuracion['delimitador_csv'] ?? ';',
            'enclosure' => '',
            'use_bom' => false,
            'encoding' => $configuracion['codificacion'] ?? 'UTF-8',
        ];
    }

    // ... métodos auxiliares (obtenerClaseDocumento, tipoOperacion, tipoRenta, etc.)
}
```

---

## Constantes por País

### Archivo: `ContabilidadConstants.php`

```php
<?php

namespace App\Constants;

class ContabilidadConstants
{
    // Códigos de países
    const PAIS_EL_SALVADOR = 'SV';
    const PAIS_HONDURAS = 'HN';

    // Tipos de impuestos
    const IMPUESTO_IVA = 'IVA';
    const IMPUESTO_ISV = 'ISV';
    const IMPUESTO_ISR = 'ISR';
    const IMPUESTO_PERCEPCION = 'PERCEPCION';
    const IMPUESTO_RETENCION = 'RETENCION_IVA';

    // Configuración por país
    const CONFIGURACION_PAIS = [
        'SV' => [
            'nombre' => 'El Salvador',
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'autoridad_fiscal' => 'MH',
            'nombre_autoridad' => 'Ministerio de Hacienda',
            'campo_identificacion' => 'NRC',
            'iva_porcentaje' => 13,
            'isr_porcentaje' => 30,
        ],
        'HN' => [
            'nombre' => 'Honduras',
            'moneda' => 'HNL',
            'simbolo_moneda' => 'L',
            'autoridad_fiscal' => 'SAR',
            'nombre_autoridad' => 'Servicio de Administración de Rentas',
            'campo_identificacion' => 'RTN',
            'isv_porcentaje' => 15,
            'isr_porcentaje' => 25,
        ],
    ];

    // Tipos de documentos por país
    const TIPOS_DOCUMENTOS = [
        'SV' => [
            '01' => 'Factura',
            '03' => 'Crédito Fiscal',
            '05' => 'Nota de Crédito',
            '06' => 'Nota de Débito',
            '11' => 'Factura de Exportación',
        ],
        'HN' => [
            '01' => 'Factura',
            '02' => 'Factura Especial',
            '03' => 'Nota de Crédito',
            '04' => 'Nota de Débito',
            // ... más tipos según Honduras
        ],
    ];
}
```

---

## Migraciones Recomendadas

### Migración: `2025_01_XX_create_configuracion_impuestos_pais_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfiguracionImpuestosPaisTable extends Migration
{
    public function up()
    {
        Schema::create('configuracion_impuestos_pais', function (Blueprint $table) {
            $table->id();
            $table->string('cod_pais', 3);
            $table->string('tipo_impuesto', 50);
            $table->string('nombre_impuesto', 100);
            $table->decimal('porcentaje', 5, 2);
            $table->boolean('activo')->default(true);
            $table->date('fecha_vigencia_desde');
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['cod_pais', 'tipo_impuesto', 'fecha_vigencia_desde'], 'unique_impuesto_pais_vigencia');
            $table->index('cod_pais');
            $table->index('tipo_impuesto');
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuracion_impuestos_pais');
    }
}
```

---

## Testing

### Test: `LibroIvaFormatoServiceTest.php`

```php
<?php

namespace Tests\Unit\Services\Contabilidad;

use Tests\TestCase;
use App\Services\Contabilidad\LibroIvaFormatoService;
use App\Models\Admin\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LibroIvaFormatoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_obtiene_formato_anexo_el_salvador()
    {
        $empresa = Empresa::factory()->create(['cod_pais' => 'SV']);
        $service = new LibroIvaFormatoService();
        
        $formato = $service->obtenerFormatoAnexo($empresa);
        
        $this->assertArrayHasKey('columnas', $formato);
        $this->assertEquals('NRC', $service->obtenerNombreCampoIdentificacion($empresa));
    }

    public function test_obtiene_formato_anexo_honduras()
    {
        $empresa = Empresa::factory()->create(['cod_pais' => 'HN']);
        $service = new LibroIvaFormatoService();
        
        $formato = $service->obtenerFormatoAnexo($empresa);
        
        $this->assertArrayHasKey('columnas', $formato);
        $this->assertEquals('RTN', $service->obtenerNombreCampoIdentificacion($empresa));
    }

    public function test_formatea_datos_anexo_el_salvador()
    {
        $empresa = Empresa::factory()->create(['cod_pais' => 'SV']);
        $service = new LibroIvaFormatoService();
        
        $datos = [
            'fecha' => '2024-01-15',
            'clase_documento' => '4',
            'tipo' => '01',
        ];
        
        $formateado = $service->formatearDatosAnexo($datos, $empresa);
        
        $this->assertArrayHasKey('A', $formateado);
        $this->assertEquals('15/01/2024', $formateado['A']);
    }
}
```

---

## Consideraciones de Performance

### Caching

```php
// En NormativaPaisService
public static function obtenerConfiguracion(string $codPais, string $modulo): array
{
    $cacheKey = "normativa_{$codPais}_{$modulo}";
    
    return Cache::remember($cacheKey, 3600, function () use ($codPais, $modulo) {
        // Lógica de obtención
    });
}

// Invalidar cache cuando se actualice configuración
public static function invalidarCache(string $codPais, ?string $modulo = null)
{
    if ($modulo) {
        Cache::forget("normativa_{$codPais}_{$modulo}");
    } else {
        Cache::forget("normativa_{$codPais}_*");
    }
}
```

---

**Documento preparado por:** Sistema de Auditoría Smartpyme  
**Última actualización:** Enero 2025  
**Versión:** 1.0

