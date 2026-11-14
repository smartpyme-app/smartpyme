# Planilla Única SPU (CSV) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generar desde SmartPyme el CSV de "Crear Planilla por Archivo" del SPU (SSF El Salvador) a partir de las planillas aprobadas/pagadas de un mes y sucursal, con vista previa editable, validación y descarga.

**Architecture:** Un servicio (`SpuPlanillaService`) con la lógica pura (derivar fila, validar, escribir CSV) más dos métodos que leen BD; un controlador delgado con 5 endpoints bajo `planillas/spu`; `config/spu.php` para los supuestos de formato. Las correcciones del usuario no se persisten: el frontend recibe la vista previa, la edita y la reenvía para validar y descargar. Datos fijos del empleador (número patronal, tipo de persona, régimen, correlativo de sucursal) e institución previsional / apellido de casada del empleado sí se persisten.

**Tech Stack:** Laravel 12 (PHP 8.3 vía Herd), PHPUnit 11, Angular standalone components, `ApiService` existente.

## Global Constraints

- CSV: 21 columnas en el orden del manual SPU (enero 2024), sin encabezado, separador `;`, decimales con punto y 2 decimales, `\r\n`, UTF-8 sin BOM. Por decisión del usuario, estos supuestos son constantes en `SpuPlanillaService` (no hay `config/spu.php`).
- Sin comillas salvo que el valor contenga el separador, comillas o salto de línea (no usar `fputcsv`: entrecomilla valores con espacios).
- Documentos, NIT/DUI y número patronal sin guiones; tratados siempre como string.
- Columnas 17 y 18 siempre 2 dígitos; columna 19 con 2 dígitos cuando es > 0.
- Columnas 1–4 las arma el backend desde empresa/sucursal; nunca se aceptan del cliente.
- `id_empresa` siempre de `auth()->user()->id_empresa`.
- Sin dependencias nuevas. PHP CLI por defecto es 7.4: usar `C:\Users\ingal\.config\herd\bin\php83.bat`.
- Textos de UI y mensajes en español.

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `Backend/database/migrations/2026_11_13_120000_add_spu_fields_to_empresas_sucursales_empleados.php` | Crear | Columnas nuevas |
| `Backend/app/Models/Admin/Empresa.php`, `Backend/app/Models/Admin/Sucursal.php`, `Backend/app/Models/Planilla/Empleado.php` | Modificar | `$fillable` |
| `Backend/app/Http/Requests/Planilla/StoreEmpleadoRequest.php`, `UpdateEmpleadoRequest.php` | Modificar | Reglas de los 2 campos nuevos |
| `Backend/app/Services/Planilla/SpuPlanillaService.php` | Crear | Derivar, validar, CSV, consultas |
| `Backend/tests/Unit/Services/Planilla/SpuPlanillaServiceTest.php` | Crear | Check de formato y reglas |
| `Backend/app/Http/Controllers/Api/Planilla/SpuPlanillaController.php` | Crear | Endpoints |
| `Backend/routes/modulos/planilla/planillas.php` | Modificar | Rutas `spu/*` |
| `Frontend/src/app/views/planillas/empleados/administrar-empleado.component.html` | Modificar | Institución previsional y apellido de casada (solo SV) |
| `Frontend/src/app/views/planillas/spu/planilla-spu.component.ts` / `.html` | Crear | Pantalla SPU |
| `Frontend/src/app/views/planillas/planillas.routing.module.ts` | Modificar | Ruta `planilla/spu` |
| `Frontend/src/app/views/planillas/planilla-nav/planilla-nav.component.ts` / `.html` | Modificar | Enlace "Planilla Única SPU" (solo SV) |

---

### Task 1: Campos persistentes

**Files:**
- Create: `Backend/database/migrations/2026_11_13_120000_add_spu_fields_to_empresas_sucursales_empleados.php`
- Modify: `Backend/app/Models/Admin/Empresa.php` (`$fillable`, línea ~28), `Backend/app/Models/Admin/Sucursal.php` (`$fillable`, línea ~17), `Backend/app/Models/Planilla/Empleado.php` (`$fillable`, línea ~26)
- Modify: `Backend/app/Http/Requests/Planilla/StoreEmpleadoRequest.php:30`, `Backend/app/Http/Requests/Planilla/UpdateEmpleadoRequest.php:29`

**Interfaces:**
- Produces: columnas `empresas.numero_patronal_isss`, `empresas.tipo_persona` (`natural|juridica`), `empresas.regimen_especial_isss` (`domestico|marino_mercante|regidores`), `sucursales.correlativo_centro_trabajo_isss`, `empleados.institucion_previsional` (`COF|MAX|ISP|ISS`), `empleados.apellido_casada`.

- [ ] **Step 1: Crear la migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('numero_patronal_isss', 9)->nullable();
            $table->string('tipo_persona', 10)->nullable();
            $table->string('regimen_especial_isss', 20)->nullable();
        });

        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('correlativo_centro_trabajo_isss', 3)->nullable();
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->string('institucion_previsional', 3)->nullable();
            $table->string('apellido_casada', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['numero_patronal_isss', 'tipo_persona', 'regimen_especial_isss']);
        });

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn('correlativo_centro_trabajo_isss');
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['institucion_previsional', 'apellido_casada']);
        });
    }
};
```

- [ ] **Step 2: Agregar a `$fillable`**

`Empresa.php`, después de `'ncr',`:
```php
        'numero_patronal_isss',
        'tipo_persona',
        'regimen_especial_isss',
```
`Sucursal.php`, después de `'giro',`:
```php
        'correlativo_centro_trabajo_isss',
```
`Empleado.php`, después de `'afp',`:
```php
        'institucion_previsional',
        'apellido_casada',
```

- [ ] **Step 3: Reglas en los FormRequest de empleado**

En `StoreEmpleadoRequest.php` y `UpdateEmpleadoRequest.php`, después de la regla `'afp'`:
```php
            'institucion_previsional' => ['nullable', 'in:COF,MAX,ISP,ISS'],
            'apellido_casada' => ['nullable', 'string', 'max:40'],
```

- [ ] **Step 4: Correr la migración**

Run: `cd Backend; & C:\Users\ingal\.config\herd\bin\php83.bat artisan migrate`
Expected: `2026_11_13_120000_add_spu_fields_to_empresas_sucursales_empleados ... DONE`

- [ ] **Step 5: Commit** (solo si el usuario lo autoriza)

```bash
git add Backend/database/migrations/2026_11_13_120000_add_spu_fields_to_empresas_sucursales_empleados.php Backend/app/Models/Admin/Empresa.php Backend/app/Models/Admin/Sucursal.php Backend/app/Models/Planilla/Empleado.php Backend/app/Http/Requests/Planilla/StoreEmpleadoRequest.php Backend/app/Http/Requests/Planilla/UpdateEmpleadoRequest.php
git commit -m "feat(planilla): campos ISSS/SPU en empresa, sucursal y empleado"
```

---

### Task 2: Lógica pura del SPU (TDD)

**Files:**
- Create: `Backend/config/spu.php`
- Create: `Backend/app/Services/Planilla/SpuPlanillaService.php`
- Test: `Backend/tests/Unit/Services/Planilla/SpuPlanillaServiceTest.php`

**Interfaces:**
- Fila (array, mismo shape en backend y frontend): `id_empleado, empleado, numero_documento, tipo_documento, afiliacion_isss, institucion_previsional, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, apellido_casada, salario, pago_adicional, monto_vacacion, dias, horas, dias_vacacion, codigo_1, codigo_2, advertencias`.
- Empleador (array): `nit_dui, numero_patronal, periodo (MMAAAA), centro_trabajo, tipo_persona, regimen_especial`.
- Error (array): `fila (int 1-based|null), empleado (string|null), columna (string), mensaje (string)`.
- Produces: `SpuPlanillaService::construirFila(array $empleado, array $suma, int $anio, int $mes): array`, `validar(array $filas, array $empleador, bool $complementaria): array`, `contenidoCsv(array $filas, array $empleador): string`, `descargar(array $filas, array $empleador): StreamedResponse`.

- [ ] **Step 1: Crear `config/spu.php`**

```php
<?php

// Supuestos del formato SPU que el manual no define; confirmar con una carga de prueba.
return [
    'separador' => env('SPU_SEPARADOR', ';'),
    'encabezado' => env('SPU_ENCABEZADO', false),
    // 'UTF-8' o 'Windows-1252' si el SPU rechaza tildes o ñ.
    'codificacion' => env('SPU_CODIFICACION', 'UTF-8'),
    // Cero en columnas 15 y 16 sin código habilitante: '0' o '0.00'.
    'cero_monto' => env('SPU_CERO_MONTO', '0'),
    // Cero en columna 19 sin código habilitante: '0' o '00'.
    'cero_dias' => env('SPU_CERO_DIAS', '0'),
    'fin_linea' => "\r\n",
];
```

- [ ] **Step 2: Escribir el test (falla)**

```php
<?php

namespace Tests\Unit\Services\Planilla;

use App\Services\Planilla\SpuPlanillaService;
use Tests\TestCase;

class SpuPlanillaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['spu' => [
            'separador' => ';', 'encabezado' => false, 'codificacion' => 'UTF-8',
            'cero_monto' => '0', 'cero_dias' => '0', 'fin_linea' => "\r\n",
        ]]);
    }

    private function empleador(array $cambios = []): array
    {
        return array_merge([
            'nit_dui' => '06142009610630', 'numero_patronal' => '123456789', 'periodo' => '062023',
            'centro_trabajo' => '1', 'tipo_persona' => 'juridica', 'regimen_especial' => null,
        ], $cambios);
    }

    private function fila(array $cambios = []): array
    {
        return array_merge([
            'id_empleado' => 1, 'empleado' => 'Juan Carlos Perez Lopez',
            'numero_documento' => '01234567-8', 'tipo_documento' => '01', 'afiliacion_isss' => '123456789',
            'institucion_previsional' => 'COF', 'primer_nombre' => 'Juan', 'segundo_nombre' => 'Carlos',
            'primer_apellido' => 'Perez', 'segundo_apellido' => 'Lopez', 'apellido_casada' => '',
            'salario' => 1000, 'pago_adicional' => 0, 'monto_vacacion' => 0,
            'dias' => 30, 'horas' => 8, 'dias_vacacion' => 0, 'codigo_1' => '00', 'codigo_2' => '00',
        ], $cambios);
    }

    private function columnasConError(array $filas, ?array $empleador = null, bool $complementaria = false): array
    {
        $errores = (new SpuPlanillaService())->validar($filas, $empleador ?? $this->empleador(), $complementaria);

        return array_column($errores, 'columna');
    }

    public function test_csv_sigue_el_formato_del_manual(): void
    {
        $csv = (new SpuPlanillaService())->contenidoCsv([
            $this->fila(['segundo_nombre' => 'Carlos Alberto']),
            $this->fila(['monto_vacacion' => 120.5, 'dias_vacacion' => 5, 'codigo_1' => '09']),
            $this->fila(['codigo_1' => '13', 'institucion_previsional' => 'COF']),
        ], $this->empleador());

        $this->assertSame(
            "06142009610630;123456789;062023;001;012345678;01;123456789;COF;Juan;Carlos Alberto;Perez;Lopez;;1000.00;0;0;30;08;0;00;00\r\n"
            . "06142009610630;123456789;062023;001;012345678;01;123456789;COF;Juan;Carlos;Perez;Lopez;;1000.00;0;120.50;30;08;05;09;00\r\n"
            . "06142009610630;123456789;062023;001;012345678;01;123456789;;Juan;Carlos;Perez;Lopez;;1000.00;0;0;30;08;0;13;00\r\n",
            $csv
        );
        foreach (explode("\r\n", trim($csv)) as $linea) {
            $this->assertCount(21, explode(';', $linea));
        }
    }

    public function test_csv_entrecomilla_solo_si_el_valor_trae_el_separador(): void
    {
        $csv = (new SpuPlanillaService())->contenidoCsv([$this->fila(['segundo_apellido' => 'Lopez;Diaz'])], $this->empleador());

        $this->assertStringContainsString(';"Lopez;Diaz";', $csv);
    }

    public function test_fila_valida_no_tiene_errores(): void
    {
        $this->assertSame([], $this->columnasConError([$this->fila()]));
    }

    public function test_institucion_obligatoria_salvo_codigos_13_15_23(): void
    {
        $this->assertContains('Institución previsional', $this->columnasConError([$this->fila(['institucion_previsional' => ''])]));
        $this->assertNotContains('Institución previsional', $this->columnasConError([$this->fila(['institucion_previsional' => '', 'codigo_2' => '13'])]));
    }

    public function test_pago_adicional_y_vacacion_dependen_del_codigo(): void
    {
        $this->assertContains('Pago adicional', $this->columnasConError([$this->fila(['pago_adicional' => 50])]));
        $this->assertContains('Pago adicional', $this->columnasConError([$this->fila(['codigo_1' => '02'])]));
        $this->assertNotContains('Pago adicional', $this->columnasConError([$this->fila(['codigo_1' => '02', 'pago_adicional' => 50])]));
        $this->assertContains('Pago adicional', $this->columnasConError([$this->fila(['codigo_1' => '09', 'pago_adicional' => 50, 'monto_vacacion' => 10, 'dias_vacacion' => 2])]));

        $this->assertContains('Monto de vacación', $this->columnasConError([$this->fila(['monto_vacacion' => 10])]));
        $this->assertContains('Días de vacación', $this->columnasConError([$this->fila(['codigo_1' => '09', 'monto_vacacion' => 10])]));
    }

    public function test_salario_dias_y_horas_mayores_a_cero(): void
    {
        $columnas = $this->columnasConError([$this->fila(['salario' => 0, 'dias' => 0, 'horas' => 0])]);

        $this->assertContains('Salario', $columnas);
        $this->assertContains('Días', $columnas);
        $this->assertContains('Horas', $columnas);
    }

    public function test_codigos_del_catalogo_y_documento(): void
    {
        $columnas = $this->columnasConError([$this->fila(['codigo_1' => '', 'codigo_2' => '24', 'numero_documento' => '123'])]);

        $this->assertContains('Código de observación 1', $columnas);
        $this->assertContains('Código de observación 2', $columnas);
        $this->assertContains('Número de documento', $columnas);
    }

    public function test_codigo_12_requiere_06_consecutivo_del_mismo_trabajador(): void
    {
        $con06 = $this->fila(['codigo_1' => '06']);
        $con12 = $this->fila(['codigo_1' => '12']);

        $this->assertNotContains('Códigos de observación', $this->columnasConError([$con06, $con12]));
        $this->assertContains('Códigos de observación', $this->columnasConError([$con12, $con06]));
        $this->assertContains('Códigos de observación', $this->columnasConError([$con06, $this->fila(['codigo_1' => '12', 'id_empleado' => 2])]));
    }

    public function test_codigo_18_solo_en_complementaria(): void
    {
        $fila = $this->fila(['codigo_1' => '18']);

        $this->assertContains('Códigos de observación', $this->columnasConError([$fila]));
        $this->assertNotContains('Códigos de observación', $this->columnasConError([$fila], null, true));
    }

    public function test_codigos_segun_tipo_de_persona_y_regimen(): void
    {
        $natural = $this->empleador(['tipo_persona' => 'natural', 'nit_dui' => '012345678']);

        $this->assertContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '23'])]));
        $this->assertNotContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '23'])], $natural));
        $this->assertContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '15'])], $natural));
        $this->assertNotContains('Códigos de observación', $this->columnasConError(
            [$this->fila(['codigo_1' => '15'])],
            $this->empleador(['tipo_persona' => 'natural', 'nit_dui' => '012345678', 'regimen_especial' => 'domestico'])
        ));
        $this->assertContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '16'])], $natural));
    }

    public function test_empleador_incompleto_da_errores_generales(): void
    {
        $columnas = $this->columnasConError([$this->fila()], $this->empleador(['numero_patronal' => '', 'centro_trabajo' => '', 'tipo_persona' => null]));

        $this->assertContains('Número patronal', $columnas);
        $this->assertContains('Centro de trabajo', $columnas);
        $this->assertContains('Tipo de persona', $columnas);
    }

    public function test_construir_fila_deriva_codigos_nombres_y_documentos(): void
    {
        $empleado = [
            'id' => 7, 'nombres' => 'Maria  Jose Elena', 'apellidos' => 'Gomez Ramos', 'apellido_casada' => null,
            'dui' => '01234567-8', 'isss' => '987-654-321', 'institucion_previsional' => 'MAX',
            'es_pensionado' => false, 'fecha_ingreso' => '2023-06-10', 'fecha_baja' => null, 'horas_jornada' => '8.00',
        ];

        $fila = SpuPlanillaService::construirFila($empleado, ['salario' => 500, 'pago_adicional' => 25.5, 'dias' => 21], 2023, 6);

        $this->assertSame(['08', '02'], [$fila['codigo_1'], $fila['codigo_2']]);
        $this->assertSame(['Maria', 'Jose Elena', 'Gomez', 'Ramos'], [$fila['primer_nombre'], $fila['segundo_nombre'], $fila['primer_apellido'], $fila['segundo_apellido']]);
        $this->assertSame('012345678', $fila['numero_documento']);
        $this->assertSame('987654321', $fila['afiliacion_isss']);
        $this->assertSame([21, 8, 25.5], [$fila['dias'], $fila['horas'], $fila['pago_adicional']]);
        $this->assertSame([], $fila['advertencias']);

        $fila = SpuPlanillaService::construirFila(
            ['es_pensionado' => true, 'fecha_baja' => '2023-06-30'] + $empleado,
            ['salario' => 500, 'pago_adicional' => 10, 'dias' => 30],
            2023,
            6
        );
        $this->assertSame(['08', '07'], [$fila['codigo_1'], $fila['codigo_2']]);
        $this->assertCount(1, $fila['advertencias']);

        $sinCodigos = SpuPlanillaService::construirFila(['fecha_ingreso' => '2020-01-01'] + $empleado, ['salario' => 500, 'pago_adicional' => 0, 'dias' => 15], 2023, 6);
        $this->assertSame(['00', '00'], [$sinCodigos['codigo_1'], $sinCodigos['codigo_2']]);
        $this->assertCount(1, $sinCodigos['advertencias']);
    }
}
```

- [ ] **Step 3: Correr el test y verificar que falla**

Run: `cd Backend; & C:\Users\ingal\.config\herd\bin\php83.bat artisan test --filter=SpuPlanillaServiceTest`
Expected: FAIL con `Class "App\Services\Planilla\SpuPlanillaService" not found`

- [ ] **Step 4: Implementar `SpuPlanillaService`**

```php
<?php

namespace App\Services\Planilla;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Planilla\PlanillaDetalle;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV para "Crear Planilla por Archivo" del SPU (SSF El Salvador, manual enero 2024).
 */
class SpuPlanillaService
{
    public const TIPOS_DOCUMENTO = ['01', '02', '03', '04'];
    public const INSTITUCIONES = ['COF', 'MAX', 'ISP', 'ISS'];

    private const SIN_INSTITUCION = ['13', '15', '23'];
    private const CON_PAGO_ADICIONAL = ['02', '10', '21'];
    private const CON_VACACION = ['09', '10', '20', '21'];
    private const SOLO_NATURAL = ['15', '23'];
    private const SOLO_JURIDICA = ['16', '17'];
    private const REGIMEN_POR_CODIGO = ['15' => 'domestico', '16' => 'marino_mercante', '17' => 'regidores'];

    private const PLANILLA_APROBADA = 2;
    private const PLANILLA_PAGADA = 3;
    private const DETALLE_RETIRADO = 0;

    private const NOMBRES = [
        'primer_nombre' => 'Primer nombre',
        'segundo_nombre' => 'Segundo nombre',
        'primer_apellido' => 'Primer apellido',
        'segundo_apellido' => 'Segundo apellido',
        'apellido_casada' => 'Apellido de casada',
    ];

    private const ENCABEZADOS = [
        'NIT/DUI empleador', 'Número patronal', 'Período', 'Centro de trabajo', 'Número de documento',
        'Tipo de documento', 'Afiliación ISSS', 'Institución previsional', 'Primer nombre', 'Segundo nombre',
        'Primer apellido', 'Segundo apellido', 'Apellido de casada', 'Salario', 'Pago adicional',
        'Monto de vacación', 'Días', 'Horas', 'Días de vacación', 'Código de observación 1', 'Código de observación 2',
    ];

    public function empleador(Empresa $empresa, Sucursal $sucursal, int $anio, int $mes): array
    {
        return [
            'nit_dui' => self::digitos($empresa->nit),
            'numero_patronal' => self::digitos($empresa->numero_patronal_isss),
            'periodo' => sprintf('%02d%04d', $mes, $anio),
            'centro_trabajo' => self::digitos($sucursal->correlativo_centro_trabajo_isss),
            'tipo_persona' => $empresa->tipo_persona,
            'regimen_especial' => $empresa->regimen_especial_isss,
        ];
    }

    /** Una fila por empleado sumando las planillas aprobadas o pagadas del mes y sucursal (dos quincenas = una fila). */
    public function filas(int $idEmpresa, int $idSucursal, int $anio, int $mes): array
    {
        return PlanillaDetalle::query()
            ->whereHas('planilla', fn ($q) => $q->where('id_empresa', $idEmpresa)
                ->where('id_sucursal', $idSucursal)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->whereIn('estado', [self::PLANILLA_APROBADA, self::PLANILLA_PAGADA]))
            ->where('estado', '!=', self::DETALLE_RETIRADO)
            ->with(['empleado' => fn ($q) => $q->withTrashed()])
            ->get()
            ->filter(fn ($detalle) => $detalle->empleado)
            ->groupBy('id_empleado')
            ->map(fn ($detalles) => self::construirFila($detalles->first()->empleado->toArray(), [
                'salario' => $detalles->sum(fn ($d) => (float) $d->salario_devengado),
                'pago_adicional' => $detalles->sum(fn ($d) => (float) $d->monto_horas_extra + (float) $d->comisiones
                    + (float) $d->bonificaciones + (float) $d->otros_ingresos),
                'dias' => $detalles->sum(fn ($d) => (float) $d->dias_laborados),
            ], $anio, $mes))
            ->sortBy('empleado')
            ->values()
            ->all();
    }

    public static function construirFila(array $e, array $suma, int $anio, int $mes): array
    {
        [$primerNombre, $segundoNombre] = self::partir($e['nombres'] ?? '');
        [$primerApellido, $segundoApellido] = self::partir($e['apellidos'] ?? '');
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();
        $enMes = fn ($fecha) => $fecha && Carbon::parse($fecha)->between($inicio, $fin);
        $pagoAdicional = round((float) ($suma['pago_adicional'] ?? 0), 2);
        $dias = min(30, (int) round((float) ($suma['dias'] ?? 0)));

        $codigos = [];
        if ($enMes($e['fecha_ingreso'] ?? null)) {
            $codigos[] = '08';
        }
        if ($enMes($e['fecha_baja'] ?? null)) {
            $codigos[] = '07';
        }
        if (!empty($e['es_pensionado'])) {
            $codigos[] = '04';
        }
        if ($pagoAdicional > 0) {
            $codigos[] = '02';
        }

        $advertencias = [];
        if (count($codigos) > 2) {
            $advertencias[] = 'Se detectaron los códigos ' . implode(', ', $codigos) . '; el SPU admite dos. Revise cuáles declarar.';
        }
        if ($dias < 30 && !array_intersect($codigos, ['07', '08'])) {
            $advertencias[] = "Tiene {$dias} días: si fue licencia (05) o incapacidad (06), agregue el código.";
        }

        return [
            'id_empleado' => $e['id'] ?? null,
            'empleado' => trim(($e['nombres'] ?? '') . ' ' . ($e['apellidos'] ?? '')),
            'numero_documento' => self::documento($e['dui'] ?? ''),
            'tipo_documento' => '01',
            'afiliacion_isss' => self::digitos($e['isss'] ?? ''),
            'institucion_previsional' => $e['institucion_previsional'] ?? '',
            'primer_nombre' => $primerNombre,
            'segundo_nombre' => $segundoNombre,
            'primer_apellido' => $primerApellido,
            'segundo_apellido' => $segundoApellido,
            'apellido_casada' => $e['apellido_casada'] ?? '',
            'salario' => round((float) ($suma['salario'] ?? 0), 2),
            'pago_adicional' => $pagoAdicional,
            'monto_vacacion' => 0,
            'dias' => $dias,
            'horas' => (int) round((float) ($e['horas_jornada'] ?? 0) ?: 8),
            'dias_vacacion' => 0,
            'codigo_1' => $codigos[0] ?? '00',
            'codigo_2' => $codigos[1] ?? '00',
            'advertencias' => $advertencias,
        ];
    }

    public function validar(array $filas, array $empleador, bool $complementaria): array
    {
        $filas = array_values($filas);
        $errores = [];
        $error = function (?int $i, string $columna, string $mensaje) use (&$errores, $filas) {
            $errores[] = [
                'fila' => $i === null ? null : $i + 1,
                'empleado' => $i === null ? null : ($filas[$i]['empleado'] ?? null),
                'columna' => $columna,
                'mensaje' => $mensaje,
            ];
        };

        $persona = $empleador['tipo_persona'] ?? null;
        if (!in_array($persona, ['natural', 'juridica'], true)) {
            $error(null, 'Tipo de persona', 'Configure si el empleador es persona natural o jurídica.');
        }
        if (!in_array(strlen($empleador['nit_dui'] ?? ''), $persona === 'juridica' ? [14] : [9, 14], true)) {
            $error(null, 'NIT/DUI empleador', $persona === 'juridica' ? 'El NIT de la empresa debe tener 14 dígitos.' : 'Debe ser un DUI de 9 dígitos o un NIT de 14.');
        }
        if (!preg_match('/^\d{1,9}$/', $empleador['numero_patronal'] ?? '')) {
            $error(null, 'Número patronal', 'Configure el número patronal ISSS (solo números, máximo 9).');
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\d{4}$/', $empleador['periodo'] ?? '')) {
            $error(null, 'Período', 'El período debe tener formato MMAAAA.');
        }
        if (!preg_match('/^\d{1,3}$/', $empleador['centro_trabajo'] ?? '')) {
            $error(null, 'Centro de trabajo', 'Configure el correlativo ISSS del centro de trabajo de la sucursal (máximo 3 dígitos).');
        }
        if (!$filas) {
            $error(null, 'Filas', 'No hay empleados en planillas aprobadas o pagadas para ese mes y sucursal.');
        }

        foreach ($filas as $i => $f) {
            $codigos = [(string) ($f['codigo_1'] ?? ''), (string) ($f['codigo_2'] ?? '')];
            $tiene = fn (array $lista) => (bool) array_intersect($codigos, $lista);

            foreach ($codigos as $n => $codigo) {
                if (!preg_match('/^(0\d|1\d|2[0-3])$/', $codigo)) {
                    $error($i, 'Código de observación ' . ($n + 1), 'Debe ser un código del catálogo (00 a 23); use 00 si no aplica.');
                }
            }

            $tipo = (string) ($f['tipo_documento'] ?? '');
            $documento = self::documento($f['numero_documento'] ?? '');
            if (!in_array($tipo, self::TIPOS_DOCUMENTO, true)) {
                $error($i, 'Tipo de documento', 'Use 01 DUI, 02 Pasaporte, 03 Carné de minoridad o 04 Carné de residente.');
            } elseif ($tipo === '01' ? !preg_match('/^\d{9}$/', $documento) : !preg_match('/^[A-Z0-9]{1,20}$/', $documento)) {
                $error($i, 'Número de documento', $tipo === '01' ? 'El DUI debe tener 9 dígitos.' : 'Obligatorio, máximo 20 caracteres.');
            }
            if (!preg_match('/^\d{1,9}$/', self::digitos($f['afiliacion_isss'] ?? ''))) {
                $error($i, 'Afiliación ISSS', 'Obligatorio, solo números, máximo 9.');
            }
            if (!$tiene(self::SIN_INSTITUCION) && !in_array($f['institucion_previsional'] ?? '', self::INSTITUCIONES, true)) {
                $error($i, 'Institución previsional', 'Seleccione COF, MAX, ISP o ISS (solo va vacía con códigos 13, 15 o 23).');
            }

            foreach (['primer_nombre' => 'Primer nombre', 'primer_apellido' => 'Primer apellido'] as $campo => $columna) {
                if (trim((string) ($f[$campo] ?? '')) === '') {
                    $error($i, $columna, 'Obligatorio.');
                }
            }
            foreach (self::NOMBRES as $campo => $columna) {
                if (mb_strlen(trim((string) ($f[$campo] ?? ''))) > 40) {
                    $error($i, $columna, 'Máximo 40 caracteres.');
                }
            }

            foreach (['salario' => 'Salario', 'pago_adicional' => 'Pago adicional', 'monto_vacacion' => 'Monto de vacación'] as $campo => $columna) {
                $valor = $f[$campo] ?? 0;
                if (!is_numeric($valor) || $valor < 0 || strlen(self::monto($valor)) > 9) {
                    $error($i, $columna, 'Monto inválido (de 0 a 999999.99).');
                }
            }
            if (is_numeric($f['salario'] ?? null) && (float) $f['salario'] <= 0) {
                $error($i, 'Salario', 'Debe ser mayor a 0.');
            }

            $pago = (float) ($f['pago_adicional'] ?? 0);
            if ($tiene(self::CON_PAGO_ADICIONAL) && $pago <= 0) {
                $error($i, 'Pago adicional', 'Los códigos 02, 10 y 21 requieren un pago adicional mayor a 0.');
            }
            if (!$tiene(self::CON_PAGO_ADICIONAL) && $pago != 0) {
                $error($i, 'Pago adicional', 'Solo se permite con código 02, 10 o 21.');
            }

            $montoVacacion = (float) ($f['monto_vacacion'] ?? 0);
            $diasVacacion = $f['dias_vacacion'] ?? 0;
            if ($tiene(self::CON_VACACION)) {
                if ($montoVacacion <= 0) {
                    $error($i, 'Monto de vacación', 'Los códigos 09, 10, 20 y 21 requieren monto de vacación.');
                }
                if (!self::entero($diasVacacion, 1, 99)) {
                    $error($i, 'Días de vacación', 'Los códigos 09, 10, 20 y 21 requieren días de vacación (1 a 99).');
                }
            } else {
                if ($montoVacacion != 0) {
                    $error($i, 'Monto de vacación', 'Solo se permite con código 09, 10, 20 o 21.');
                }
                if ((int) $diasVacacion !== 0) {
                    $error($i, 'Días de vacación', 'Solo se permite con código 09, 10, 20 o 21.');
                }
            }

            if (!self::entero($f['dias'] ?? null, 1, 31)) {
                $error($i, 'Días', 'Entero de 1 a 31.');
            }
            if (!self::entero($f['horas'] ?? null, 1, 24)) {
                $error($i, 'Horas', 'Entero de 1 a 24.');
            }

            if (in_array('12', $codigos, true)) {
                $anterior = $filas[$i - 1] ?? null;
                $codigosAnterior = [(string) ($anterior['codigo_1'] ?? ''), (string) ($anterior['codigo_2'] ?? '')];
                if (!$anterior || ($anterior['id_empleado'] ?? null) != ($f['id_empleado'] ?? null) || !in_array('06', $codigosAnterior, true)) {
                    $error($i, 'Códigos de observación', 'El código 12 requiere, justo antes, una fila del mismo trabajador con código 06.');
                }
            }
            if (in_array('18', $codigos, true) && !$complementaria) {
                $error($i, 'Códigos de observación', 'El código 18 solo se permite en planillas complementarias.');
            }
            if ($tiene(self::SOLO_NATURAL) && $persona !== 'natural') {
                $error($i, 'Códigos de observación', 'Los códigos 15 y 23 son solo para empleadores persona natural.');
            }
            if ($tiene(self::SOLO_JURIDICA) && $persona !== 'juridica') {
                $error($i, 'Códigos de observación', 'Los códigos 16 y 17 son solo para empleadores persona jurídica.');
            }
            foreach (self::REGIMEN_POR_CODIGO as $codigo => $regimen) {
                if (in_array((string) $codigo, $codigos, true) && ($empleador['regimen_especial'] ?? null) !== $regimen) {
                    $error($i, 'Códigos de observación', "El código {$codigo} requiere que el empleador esté registrado en el régimen especial {$regimen}.");
                }
            }
        }

        return $errores;
    }

    public function contenidoCsv(array $filas, array $empleador): string
    {
        $separador = (string) config('spu.separador', ';');
        $lineas = config('spu.encabezado') ? [self::ENCABEZADOS] : [];
        foreach ($filas as $fila) {
            $lineas[] = $this->lineaCsv($fila, $empleador);
        }

        $csv = '';
        foreach ($lineas as $campos) {
            $csv .= implode($separador, array_map(fn ($v) => self::campoCsv((string) $v, $separador), $campos))
                . config('spu.fin_linea', "\r\n");
        }

        $codificacion = (string) config('spu.codificacion', 'UTF-8');

        return $codificacion === 'UTF-8' ? $csv : mb_convert_encoding($csv, $codificacion, 'UTF-8');
    }

    public function descargar(array $filas, array $empleador): StreamedResponse
    {
        // ponytail: arma el archivo en memoria; miles de filas pesan pocos cientos de KB. Si crece, escribir por fila a php://output.
        $csv = $this->contenidoCsv($filas, $empleador);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "planilla-unica-{$empleador['periodo']}.csv", [
            'Content-Type' => 'text/csv; charset=' . config('spu.codificacion', 'UTF-8'),
        ]);
    }

    private function lineaCsv(array $f, array $empleador): array
    {
        $codigos = [(string) $f['codigo_1'], (string) $f['codigo_2']];
        $ceroMonto = (string) config('spu.cero_monto', '0');
        $opcional = fn ($valor) => (float) $valor == 0 ? $ceroMonto : self::monto($valor);
        $diasVacacion = (int) ($f['dias_vacacion'] ?? 0);

        return [
            $empleador['nit_dui'],
            $empleador['numero_patronal'],
            $empleador['periodo'],
            str_pad($empleador['centro_trabajo'], 3, '0', STR_PAD_LEFT),
            self::documento($f['numero_documento'] ?? ''),
            $f['tipo_documento'],
            self::digitos($f['afiliacion_isss'] ?? ''),
            array_intersect($codigos, self::SIN_INSTITUCION) ? '' : ($f['institucion_previsional'] ?? ''),
            trim((string) ($f['primer_nombre'] ?? '')),
            trim((string) ($f['segundo_nombre'] ?? '')),
            trim((string) ($f['primer_apellido'] ?? '')),
            trim((string) ($f['segundo_apellido'] ?? '')),
            trim((string) ($f['apellido_casada'] ?? '')),
            self::monto($f['salario']),
            $opcional($f['pago_adicional'] ?? 0),
            $opcional($f['monto_vacacion'] ?? 0),
            self::dos($f['dias']),
            self::dos($f['horas']),
            $diasVacacion === 0 ? (string) config('spu.cero_dias', '0') : self::dos($diasVacacion),
            $codigos[0],
            $codigos[1],
        ];
    }

    private static function partir(?string $texto): array
    {
        // ponytail: parte por el primer espacio; "De la Cruz" queda mal y se corrige en pantalla. Upgrade: columnas separadas de nombre en empleados.
        $partes = explode(' ', trim(preg_replace('/\s+/', ' ', (string) $texto)), 2);

        return [$partes[0] ?? '', $partes[1] ?? ''];
    }

    private static function campoCsv(string $valor, string $separador): string
    {
        return str_contains($valor, $separador) || str_contains($valor, '"') || preg_match('/[\r\n]/', $valor)
            ? '"' . str_replace('"', '""', $valor) . '"'
            : $valor;
    }

    private static function entero(mixed $valor, int $min, int $max): bool
    {
        $texto = (string) $valor;

        return ctype_digit($texto) && (int) $texto >= $min && (int) $texto <= $max;
    }

    private static function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    private static function documento(?string $valor): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $valor));
    }

    private static function dos(mixed $valor): string
    {
        return str_pad((string) (int) $valor, 2, '0', STR_PAD_LEFT);
    }

    private static function monto(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
```

- [ ] **Step 5: Correr el test y verificar que pasa**

Run: `cd Backend; & C:\Users\ingal\.config\herd\bin\php83.bat artisan test --filter=SpuPlanillaServiceTest`
Expected: PASS, 12 tests.

- [ ] **Step 6: Commit** (solo si el usuario lo autoriza)

```bash
git add Backend/config/spu.php Backend/app/Services/Planilla/SpuPlanillaService.php Backend/tests/Unit/Services/Planilla/SpuPlanillaServiceTest.php
git commit -m "feat(planilla): generador y validador CSV del SPU"
```

---

### Task 3: Controlador y rutas

**Files:**
- Create: `Backend/app/Http/Controllers/Api/Planilla/SpuPlanillaController.php`
- Modify: `Backend/routes/modulos/planilla/planillas.php` (import y grupo nuevo antes del cierre del grupo `planillas`)

**Interfaces:**
- Consumes: `SpuPlanillaService::empleador`, `filas`, `validar`, `descargar` (Task 2).
- Produces (todas con `jwt.auth`):
  - `GET /planillas/spu/empleador` → `{numero_patronal_isss, tipo_persona, regimen_especial_isss, sucursales: [{id, nombre, correlativo_centro_trabajo_isss}]}`
  - `POST /planillas/spu/empleador` (mismo body) → mismo shape
  - `GET /planillas/spu/preview?anio&mes&id_sucursal&complementaria=0|1` → `{empleador, filas, errores}`
  - `POST /planillas/spu/validar` `{anio, mes, id_sucursal, complementaria, filas}` → `{errores}`
  - `POST /planillas/spu/csv` (mismo body) → archivo, o `422 {errores}`

- [ ] **Step 1: Crear el controlador**

```php
<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Services\Planilla\SpuPlanillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SpuPlanillaController extends Controller
{
    private const CAMPOS_FILA = [
        'id_empleado', 'empleado', 'numero_documento', 'tipo_documento', 'afiliacion_isss', 'institucion_previsional',
        'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'apellido_casada',
        'salario', 'pago_adicional', 'monto_vacacion', 'dias', 'horas', 'dias_vacacion', 'codigo_1', 'codigo_2',
    ];

    public function __construct(private SpuPlanillaService $spu)
    {
    }

    public function datosEmpleador(): JsonResponse
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);

        return response()->json([
            'numero_patronal_isss' => $empresa->numero_patronal_isss,
            'tipo_persona' => $empresa->tipo_persona,
            'regimen_especial_isss' => $empresa->regimen_especial_isss,
            'sucursales' => Sucursal::where('id_empresa', $empresa->id)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'correlativo_centro_trabajo_isss']),
        ]);
    }

    public function guardarDatosEmpleador(Request $request): JsonResponse
    {
        $data = $request->validate([
            'numero_patronal_isss' => ['nullable', 'regex:/^\d{1,9}$/'],
            'tipo_persona' => ['nullable', Rule::in(['natural', 'juridica'])],
            'regimen_especial_isss' => ['nullable', Rule::in(['domestico', 'marino_mercante', 'regidores'])],
            'sucursales' => ['array'],
            'sucursales.*.id' => ['required', 'integer'],
            'sucursales.*.correlativo_centro_trabajo_isss' => ['nullable', 'regex:/^\d{1,3}$/'],
        ]);
        $idEmpresa = auth()->user()->id_empresa;

        Empresa::whereKey($idEmpresa)->update(Arr::only($data, ['numero_patronal_isss', 'tipo_persona', 'regimen_especial_isss']));
        foreach ($data['sucursales'] ?? [] as $sucursal) {
            Sucursal::where('id_empresa', $idEmpresa)
                ->whereKey($sucursal['id'])
                ->update(['correlativo_centro_trabajo_isss' => $sucursal['correlativo_centro_trabajo_isss'] ?? null]);
        }

        return $this->datosEmpleador();
    }

    public function preview(Request $request): JsonResponse
    {
        $periodo = $this->periodo($request);
        $empleador = $this->empleador($periodo);
        $filas = $this->spu->filas(auth()->user()->id_empresa, $periodo['id_sucursal'], $periodo['anio'], $periodo['mes']);

        return response()->json([
            'empleador' => $empleador,
            'filas' => $filas,
            'errores' => $this->spu->validar($filas, $empleador, $periodo['complementaria']),
        ]);
    }

    public function validar(Request $request): JsonResponse
    {
        [$periodo, $empleador, $filas] = $this->entrada($request);

        return response()->json(['errores' => $this->spu->validar($filas, $empleador, $periodo['complementaria'])]);
    }

    public function csv(Request $request)
    {
        [$periodo, $empleador, $filas] = $this->entrada($request);
        $errores = $this->spu->validar($filas, $empleador, $periodo['complementaria']);

        if ($errores) {
            return response()->json(['errores' => $errores], 422);
        }

        return $this->spu->descargar($filas, $empleador);
    }

    private function periodo(Request $request): array
    {
        $periodo = $request->validate([
            'anio' => ['required', 'integer', 'between:2006,2100'],
            'mes' => ['required', 'integer', 'between:1,12'],
            'id_sucursal' => ['required', 'integer', Rule::exists('sucursales', 'id')->where('id_empresa', auth()->user()->id_empresa)],
            'complementaria' => ['nullable', 'boolean'],
        ]);

        return [
            'anio' => (int) $periodo['anio'],
            'mes' => (int) $periodo['mes'],
            'id_sucursal' => (int) $periodo['id_sucursal'],
            'complementaria' => (bool) ($periodo['complementaria'] ?? false),
        ];
    }

    private function entrada(Request $request): array
    {
        $periodo = $this->periodo($request);
        $request->validate([
            'filas' => ['present', 'array', 'max:5000'],
            'filas.*' => ['array'],
        ]);
        $filas = array_map(fn ($fila) => Arr::only($fila, self::CAMPOS_FILA), $request->input('filas', []));

        return [$periodo, $this->empleador($periodo), $filas];
    }

    private function empleador(array $periodo): array
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);
        $sucursal = Sucursal::where('id_empresa', $empresa->id)->findOrFail($periodo['id_sucursal']);

        return $this->spu->empleador($empresa, $sucursal, $periodo['anio'], $periodo['mes']);
    }
}
```

- [ ] **Step 2: Registrar las rutas**

En `Backend/routes/modulos/planilla/planillas.php`, agregar el import:
```php
use App\Http\Controllers\Api\Planilla\SpuPlanillaController;
```
y dentro del grupo `planillas`, después del grupo de `PlanillaImportController`:
```php
    Route::controller(SpuPlanillaController::class)->prefix('spu')->group(function () {
        Route::get('empleador', 'datosEmpleador')->middleware('permission:planilla.registros.ver');
        Route::post('empleador', 'guardarDatosEmpleador')->middleware('permission:planilla.registros.editar');
        Route::get('preview', 'preview')->middleware('permission:planilla.registros.ver');
        Route::post('validar', 'validar')->middleware('permission:planilla.registros.ver');
        Route::post('csv', 'csv')->middleware('permission:planilla.registros.ver');
    });
```

- [ ] **Step 3: Verificar que las rutas existen**

Run: `cd Backend; & C:\Users\ingal\.config\herd\bin\php83.bat artisan route:list --path=planillas/spu`
Expected: 5 rutas (`GET|HEAD empleador`, `POST empleador`, `GET|HEAD preview`, `POST validar`, `POST csv`).

- [ ] **Step 4: Volver a correr el test unitario**

Run: `cd Backend; & C:\Users\ingal\.config\herd\bin\php83.bat artisan test --filter=SpuPlanillaServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit** (solo si el usuario lo autoriza)

```bash
git add Backend/app/Http/Controllers/Api/Planilla/SpuPlanillaController.php Backend/routes/modulos/planilla/planillas.php
git commit -m "feat(planilla): endpoints SPU (empleador, preview, validar, csv)"
```

---

### Task 4: Campos SPU en el formulario de empleado

**Files:**
- Modify: `Frontend/src/app/views/planillas/empleados/administrar-empleado.component.html` (después del bloque `@if (formConfig.mostrarAfp) { ... }`, línea ~261)

**Interfaces:**
- Consumes: campos `institucion_previsional`, `apellido_casada` aceptados por Store/UpdateEmpleadoRequest (Task 1).

- [ ] **Step 1: Agregar los campos (solo El Salvador)**

```html
                  @if (formConfig.mostrarOpcionesDescuentosSv) {
                    <div class="form-group col-md-3">
                      <label for="institucion_previsional">Institución previsional (SPU):</label>
                      <select
                        [(ngModel)]="empleado.institucion_previsional"
                        name="institucion_previsional"
                        id="institucion_previsional"
                        class="form-select"
                      >
                        <option [ngValue]="null">Sin definir</option>
                        <option value="COF">COF - AFP Confía</option>
                        <option value="MAX">MAX - AFP Crecer</option>
                        <option value="ISP">ISP - Instituto Salvadoreño de Pensiones</option>
                        <option value="ISS">ISS - Unidad de Pensiones del ISSS</option>
                      </select>
                    </div>
                    <div class="form-group col-md-3">
                      <label for="apellido_casada">Apellido de casada:</label>
                      <input
                        type="text"
                        [(ngModel)]="empleado.apellido_casada"
                        name="apellido_casada"
                        id="apellido_casada"
                        class="form-control"
                        maxlength="40"
                      />
                    </div>
                  }
```

- [ ] **Step 2: Verificar compilación**

Revisar la salida del `ng serve` que ya corre en la terminal del usuario: sin errores de compilación.

- [ ] **Step 3: Verificación manual**

Editar un empleado SV, elegir "MAX", guardar, recargar: el valor persiste.

- [ ] **Step 4: Commit** (solo si el usuario lo autoriza)

```bash
git add Frontend/src/app/views/planillas/empleados/administrar-empleado.component.html
git commit -m "feat(planilla): institución previsional y apellido de casada en empleado"
```

---

### Task 5: Pantalla Planilla Única SPU

**Files:**
- Create: `Frontend/src/app/views/planillas/spu/planilla-spu.component.ts`
- Create: `Frontend/src/app/views/planillas/spu/planilla-spu.component.html`
- Modify: `Frontend/src/app/views/planillas/planillas.routing.module.ts` (import + ruta junto a `prestamos`)
- Modify: `Frontend/src/app/views/planillas/planilla-nav/planilla-nav.component.ts` y `.html`

**Interfaces:**
- Consumes: endpoints de Task 3; `ApiService.getAll(url, filtros)`, `store(url, body)`, `exportPost(url, body): Observable<Blob>`, `downloadFile(blob, nombre)`; `resolveCodigoPaisFe`, `FE_PAIS_SV` de `@services/facturacion-electronica/fe-pais.util`.

- [ ] **Step 1: Componente**

```ts
import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaNavComponent } from '../planilla-nav/planilla-nav.component';

interface ErrorSpu {
  fila: number | null;
  empleado: string | null;
  columna: string;
  mensaje: string;
}

@Component({
  selector: 'app-planilla-spu',
  standalone: true,
  templateUrl: './planilla-spu.component.html',
  imports: [CommonModule, FormsModule, PlanillaNavComponent],
})
export class PlanillaSpuComponent implements OnInit {
  readonly codigos = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
  readonly instituciones = ['COF', 'MAX', 'ISP', 'ISS'];
  readonly tiposDocumento = [
    { id: '01', nombre: 'DUI' },
    { id: '02', nombre: 'Pasaporte' },
    { id: '03', nombre: 'Carné de minoridad' },
    { id: '04', nombre: 'Carné de residente' },
  ];
  readonly meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

  filtros = { anio: new Date().getFullYear(), mes: new Date().getMonth() + 1, id_sucursal: null as number | null, complementaria: false };
  empleador: any = { numero_patronal_isss: '', tipo_persona: null, regimen_especial_isss: null, sucursales: [] };
  filas: any[] = [];
  errores: ErrorSpu[] = [];
  cargado = false;
  validado = false;
  loading = false;
  guardandoEmpleador = false;

  constructor(public apiService: ApiService, private alertService: AlertService, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.apiService.getAll('planillas/spu/empleador').subscribe({
      next: (data) => {
        this.empleador = data;
        this.filtros.id_sucursal = data.sucursales?.[0]?.id ?? null;
        this.cdr.markForCheck();
      },
      error: (error) => this.alertService.error(error),
    });
  }

  get periodo(): string {
    return String(this.filtros.mes).padStart(2, '0') + this.filtros.anio;
  }

  get erroresGenerales(): ErrorSpu[] {
    return this.errores.filter((e) => e.fila === null);
  }

  erroresDeFila(indice: number): ErrorSpu[] {
    return this.errores.filter((e) => e.fila === indice + 1);
  }

  guardarEmpleador(): void {
    this.guardandoEmpleador = true;
    this.apiService.store('planillas/spu/empleador', this.empleador).subscribe({
      next: (data) => {
        this.empleador = data;
        this.guardandoEmpleador = false;
        this.validado = false;
        this.alertService.success('Datos guardados', 'Datos ISSS del empleador actualizados.');
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.guardandoEmpleador = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  cargar(): void {
    this.loading = true;
    this.apiService.getAll('planillas/spu/preview', { ...this.filtros, complementaria: this.filtros.complementaria ? 1 : 0 }).subscribe({
      next: (data) => {
        this.filas = data.filas;
        this.cargado = true;
        this.aplicarErrores(data.errores);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.loading = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  validar(): void {
    this.loading = true;
    this.apiService.store('planillas/spu/validar', this.cuerpo()).subscribe({
      next: (data) => {
        this.aplicarErrores(data.errores);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.loading = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  descargar(): void {
    this.loading = true;
    this.apiService.exportPost('planillas/spu/csv', this.cuerpo()).subscribe({
      next: (blob) => {
        this.apiService.downloadFile(blob, `planilla-unica-${this.periodo}.csv`);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.loading = false;
        this.validado = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  cambio(): void {
    this.validado = false;
  }

  duplicar(indice: number): void {
    this.filas.splice(indice + 1, 0, { ...this.filas[indice], advertencias: [] });
    this.cambio();
  }

  quitar(indice: number): void {
    this.filas.splice(indice, 1);
    this.cambio();
  }

  private cuerpo() {
    return { ...this.filtros, complementaria: this.filtros.complementaria ? 1 : 0, filas: this.filas };
  }

  private aplicarErrores(errores: ErrorSpu[] | undefined): void {
    this.errores = errores ?? [];
    this.validado = this.errores.length === 0 && this.filas.length > 0;
  }
}
```

- [ ] **Step 2: Plantilla**

```html
<section class="d-flex justify-content-between bg-white p-4 rounded mb-3">
  <h2 class="page-heading">Planilla Única SPU</h2>
</section>

<app-planilla-nav></app-planilla-nav>

<div class="card mb-3">
  <div class="card-body">
    <h6 class="fw-bold mb-3">Datos ISSS del empleador</h6>
    <div class="row g-3">
      <div class="col-md-3">
        <label for="numero_patronal_isss">Número patronal ISSS</label>
        <input id="numero_patronal_isss" name="numero_patronal_isss" class="form-control" maxlength="9" inputmode="numeric"
               [(ngModel)]="empleador.numero_patronal_isss" />
      </div>
      <div class="col-md-3">
        <label for="tipo_persona">Tipo de persona</label>
        <select id="tipo_persona" name="tipo_persona" class="form-select" [(ngModel)]="empleador.tipo_persona">
          <option [ngValue]="null">Sin definir</option>
          <option value="juridica">Jurídica</option>
          <option value="natural">Natural</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="regimen_especial_isss">Régimen especial</label>
        <select id="regimen_especial_isss" name="regimen_especial_isss" class="form-select" [(ngModel)]="empleador.regimen_especial_isss">
          <option [ngValue]="null">Ninguno</option>
          <option value="domestico">Sector doméstico (15)</option>
          <option value="marino_mercante">Marino mercante (16)</option>
          <option value="regidores">Regidores (17)</option>
        </select>
      </div>
    </div>
    <div class="row g-3 mt-1">
      @for (sucursal of empleador.sucursales; track sucursal.id) {
        <div class="col-md-3">
          <label [for]="'correlativo_' + sucursal.id">Centro de trabajo ISSS: {{ sucursal.nombre }}</label>
          <input [id]="'correlativo_' + sucursal.id" [name]="'correlativo_' + sucursal.id" class="form-control" maxlength="3"
                 inputmode="numeric" [(ngModel)]="sucursal.correlativo_centro_trabajo_isss" />
        </div>
      }
    </div>
    <button type="button" class="btn btn-outline-primary mt-3" [disabled]="guardandoEmpleador" (click)="guardarEmpleador()">
      Guardar datos del empleador
    </button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-2">
        <label for="mes">Mes</label>
        <select id="mes" name="mes" class="form-select" [(ngModel)]="filtros.mes">
          @for (nombre of meses; track $index) {
            <option [ngValue]="$index + 1">{{ nombre }}</option>
          }
        </select>
      </div>
      <div class="col-md-2">
        <label for="anio">Año</label>
        <input id="anio" name="anio" type="number" class="form-control" min="2006" [(ngModel)]="filtros.anio" />
      </div>
      <div class="col-md-3">
        <label for="id_sucursal">Sucursal</label>
        <select id="id_sucursal" name="id_sucursal" class="form-select" [(ngModel)]="filtros.id_sucursal">
          @for (sucursal of empleador.sucursales; track sucursal.id) {
            <option [ngValue]="sucursal.id">{{ sucursal.nombre }}</option>
          }
        </select>
      </div>
      <div class="col-md-2 form-check ms-2">
        <input id="complementaria" name="complementaria" type="checkbox" class="form-check-input" [(ngModel)]="filtros.complementaria" />
        <label for="complementaria" class="form-check-label">Complementaria</label>
      </div>
      <div class="col-md-auto d-flex gap-2">
        <button type="button" class="btn btn-primary" [disabled]="loading || !filtros.id_sucursal" (click)="cargar()">Cargar</button>
        <button type="button" class="btn btn-outline-primary" [disabled]="loading || !cargado" (click)="validar()">Validar</button>
        <button type="button" class="btn btn-success" [disabled]="loading || !validado" (click)="descargar()">
          <i class="fa fa-download me-1"></i> Descargar CSV
        </button>
      </div>
    </div>
    <p class="text-muted small mt-2 mb-0">
      Se suman por empleado las planillas aprobadas o pagadas del mes y sucursal. Los cambios en esta tabla no se guardan.
    </p>
  </div>
</div>

@if (erroresGenerales.length) {
  <div class="alert alert-danger">
    @for (error of erroresGenerales; track $index) {
      <div><strong>{{ error.columna }}:</strong> {{ error.mensaje }}</div>
    }
  </div>
}

@if (cargado && validado) {
  <div class="alert alert-success">Sin errores. Puede descargar el archivo.</div>
}

@if (cargado) {
  <div class="table-responsive bg-white rounded">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Empleado</th>
          <th>Tipo doc.</th>
          <th>Documento</th>
          <th>Afiliación ISSS</th>
          <th>Institución</th>
          <th>1er nombre</th>
          <th>2º nombre</th>
          <th>1er apellido</th>
          <th>2º apellido</th>
          <th>Ap. casada</th>
          <th>Salario</th>
          <th>Pago adic.</th>
          <th>Monto vac.</th>
          <th>Días</th>
          <th>Horas</th>
          <th>Días vac.</th>
          <th>Código 1</th>
          <th>Código 2</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @for (fila of filas; track $index; let i = $index) {
          <tr [class.table-danger]="erroresDeFila(i).length">
            <td class="text-nowrap">
              {{ fila.empleado }}
              @for (advertencia of fila.advertencias; track $index) {
                <div class="small text-warning">{{ advertencia }}</div>
              }
            </td>
            <td>
              <select class="form-select form-select-sm" [name]="'tipo_documento_' + i" [(ngModel)]="fila.tipo_documento" (ngModelChange)="cambio()">
                @for (tipo of tiposDocumento; track tipo.id) {
                  <option [value]="tipo.id">{{ tipo.id }} {{ tipo.nombre }}</option>
                }
              </select>
            </td>
            <td><input class="form-control form-control-sm" maxlength="20" [name]="'numero_documento_' + i" [(ngModel)]="fila.numero_documento" (ngModelChange)="cambio()" /></td>
            <td><input class="form-control form-control-sm" maxlength="9" [name]="'afiliacion_isss_' + i" [(ngModel)]="fila.afiliacion_isss" (ngModelChange)="cambio()" /></td>
            <td>
              <select class="form-select form-select-sm" [name]="'institucion_' + i" [(ngModel)]="fila.institucion_previsional" (ngModelChange)="cambio()">
                <option value="">(vacía)</option>
                @for (institucion of instituciones; track institucion) {
                  <option [value]="institucion">{{ institucion }}</option>
                }
              </select>
            </td>
            <td><input class="form-control form-control-sm" maxlength="40" [name]="'primer_nombre_' + i" [(ngModel)]="fila.primer_nombre" (ngModelChange)="cambio()" /></td>
            <td><input class="form-control form-control-sm" maxlength="40" [name]="'segundo_nombre_' + i" [(ngModel)]="fila.segundo_nombre" (ngModelChange)="cambio()" /></td>
            <td><input class="form-control form-control-sm" maxlength="40" [name]="'primer_apellido_' + i" [(ngModel)]="fila.primer_apellido" (ngModelChange)="cambio()" /></td>
            <td><input class="form-control form-control-sm" maxlength="40" [name]="'segundo_apellido_' + i" [(ngModel)]="fila.segundo_apellido" (ngModelChange)="cambio()" /></td>
            <td><input class="form-control form-control-sm" maxlength="40" [name]="'apellido_casada_' + i" [(ngModel)]="fila.apellido_casada" (ngModelChange)="cambio()" /></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" [name]="'salario_' + i" [(ngModel)]="fila.salario" (ngModelChange)="cambio()" /></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" [name]="'pago_adicional_' + i" [(ngModel)]="fila.pago_adicional" (ngModelChange)="cambio()" /></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" [name]="'monto_vacacion_' + i" [(ngModel)]="fila.monto_vacacion" (ngModelChange)="cambio()" /></td>
            <td><input type="number" min="1" max="31" class="form-control form-control-sm" [name]="'dias_' + i" [(ngModel)]="fila.dias" (ngModelChange)="cambio()" /></td>
            <td><input type="number" min="1" max="24" class="form-control form-control-sm" [name]="'horas_' + i" [(ngModel)]="fila.horas" (ngModelChange)="cambio()" /></td>
            <td><input type="number" min="0" max="99" class="form-control form-control-sm" [name]="'dias_vacacion_' + i" [(ngModel)]="fila.dias_vacacion" (ngModelChange)="cambio()" /></td>
            <td>
              <select class="form-select form-select-sm" [name]="'codigo_1_' + i" [(ngModel)]="fila.codigo_1" (ngModelChange)="cambio()">
                @for (codigo of codigos; track codigo) {
                  <option [value]="codigo">{{ codigo }}</option>
                }
              </select>
            </td>
            <td>
              <select class="form-select form-select-sm" [name]="'codigo_2_' + i" [(ngModel)]="fila.codigo_2" (ngModelChange)="cambio()">
                @for (codigo of codigos; track codigo) {
                  <option [value]="codigo">{{ codigo }}</option>
                }
              </select>
            </td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-secondary" title="Duplicar fila (par 06/12)" (click)="duplicar(i)">
                <i class="fa fa-copy"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger ms-1" title="Quitar fila" (click)="quitar(i)">
                <i class="fa fa-trash"></i>
              </button>
            </td>
          </tr>
          @if (erroresDeFila(i).length) {
            <tr class="table-danger">
              <td colspan="19" class="small">
                @for (error of erroresDeFila(i); track $index) {
                  <div><strong>{{ error.columna }}:</strong> {{ error.mensaje }}</div>
                }
              </td>
            </tr>
          }
        }
      </tbody>
    </table>
  </div>
}
```

- [ ] **Step 3: Ruta**

En `planillas.routing.module.ts`, agregar el import:
```ts
import { PlanillaSpuComponent } from './spu/planilla-spu.component';
```
y después de la ruta `prestamos` (agregar coma a la línea anterior):
```ts
      {path: 'spu', component: PlanillaSpuComponent, title: 'Planilla Única SPU', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }}
```

- [ ] **Step 4: Enlace en el menú (solo El Salvador)**

En `planilla-nav.component.ts`, agregar el import y el getter:
```ts
import { FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
```
```ts
    get esElSalvador(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_SV;
    }
```
En `planilla-nav.component.html`, después del enlace "Empleados":
```html
    @if (puedeVerRegistros() && esElSalvador) {
        <a class="btn" routerLink="/planilla/spu" routerLinkActive="btn-primary" [routerLinkActiveOptions]="{exact: true}">Planilla Única SPU</a>
    }
```

- [ ] **Step 5: Verificar compilación**

Revisar la salida del `ng serve` en la terminal del usuario: sin errores.

- [ ] **Step 6: Verificación manual en el navegador**

1. Ir a `/planilla/spu`, completar número patronal, tipo de persona y correlativo de la sucursal; guardar.
2. Elegir un mes con una planilla aprobada; "Cargar": aparecen filas con códigos derivados.
3. Borrar la institución de una fila y "Validar": aparece el error en esa fila y "Descargar CSV" queda deshabilitado.
4. Corregir, "Validar", "Descargar CSV": el archivo tiene 21 columnas por línea, sin encabezado, separador `;`.

- [ ] **Step 7: Commit** (solo si el usuario lo autoriza)

```bash
git add Frontend/src/app/views/planillas/spu Frontend/src/app/views/planillas/planillas.routing.module.ts Frontend/src/app/views/planillas/planilla-nav
git commit -m "feat(planilla): pantalla Planilla Única SPU"
```

---

### Task 6: Confirmación con el SPU (usuario)

- [ ] **Step 1:** Subir el CSV descargado en `planillaunica.ssf.gob.sv` → "Crear Planilla por Archivo" (requiere credenciales del usuario).
- [ ] **Step 2:** Si el SPU rechaza el formato, ajustar solo `Backend/config/spu.php` (o variables `SPU_*` del `.env`) y repetir: encabezado, `cero_monto` (`0.00`), `cero_dias` (`00`), `codificacion` (`Windows-1252`).
