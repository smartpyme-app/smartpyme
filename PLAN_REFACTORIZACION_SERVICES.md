# Plan de Refactorización: Extraer Lógica de Negocio a Services

## 📋 Análisis del Ticket

### Objetivo
Extraer la lógica de negocio compleja de los controladores a Services para cumplir con el principio de Single Responsibility y mejorar la testabilidad del código.

### Impacto
- **Violación actual**: Los controladores contienen cálculos, validaciones de negocio y transformaciones que deberían estar en Services
- **Riesgo**: Lógica duplicada, difícil de testear, violación del principio SRP

---

## 🔍 Análisis de Archivos Afectados

### 1. VentasController::facturacion() (Líneas 142-211)

#### Estado Actual
✅ **Bien refactorizado** - Ya utiliza Services extensivamente:
- `CotizacionService` para cotizaciones
- `VentaService` para ventas
- `InventarioService` para inventario

#### Lógica de Negocio Pendiente de Extraer
- **Validación de permisos de usuario** (líneas 144-150)
  - Validación: usuarios "Ventas Limitado" no pueden crear ventas al crédito
  - **Acción**: Crear método en `VentaService` o nuevo `VentaAuthorizationService`

#### Estructura Actual (Bien diseñada)
```php
// Controlador solo coordina
if ($request->cotizacion == 1) {
    $cotizacion = $this->cotizacionService->crearOActualizarCotizacion($data);
    $this->cotizacionService->asignarCorrelativo(...);
    $this->cotizacionService->guardarDetalles(...);
} else {
    $venta = $this->ventaService->crearOActualizarVenta($data);
    // ... más llamadas a servicios
}
```

---

### 2. ComprasController::facturacion() (Líneas 264-416)

#### Estado Actual
❌ **Requiere refactorización significativa** - Contiene mucha lógica de negocio

#### Lógica de Negocio a Extraer

##### A. Validación de Autorización (líneas 284-298)
```php
// Lógica compleja de validación de autorización
if (!$request->id && !$request->id_authorization) {
    $total = $this->calcularTotalCompra($request);
    if ($total > 3000) {
        return response()->json([...], 403);
    }
}
```
**Acción**: Extraer a `ComprasAuthorizationService::validarAutorizacionRequerida()`

##### B. Cálculo de Total (método privado calcularTotalCompra, línea 904)
```php
private function calcularTotalCompra($request)
{
    $total = $request->total ?? $request->sub_total ?? 0;
    if ($total == 0 && isset($request->detalles)) {
        $total = collect($request->detalles)->sum('total');
    }
    return $total;
}
```
**Acción**: Mover a `CompraService::calcularTotal()`

##### C. Creación/Actualización de Compra (líneas 305-312)
```php
if ($request->id)
    $compra = Compra::findOrFail($request->id);
else
    $compra = new Compra;
$compra->fill($request->merge(["id_sucursal" => Auth::user()->id_sucursal])->all());
$compra->save();
```
**Acción**: Ya existe `ComprasService` en `app/Services/Contabilidad/ComprasService.php`, verificar si tiene este método o crearlo

##### D. Procesamiento de Detalles con Lógica de Inventario (líneas 315-357)
```php
foreach ($request->detalles as $det) {
    // Crear/actualizar detalle
    // Actualizar inventario
    // Calcular costo promedio del producto
    // Actualizar producto con nuevo costo
}
```
**Acción**: Extraer a `CompraService::procesarDetallesConInventario()`

##### E. Actualización de Orden de Compra (líneas 359-381)
```php
if ($compra->num_orden_compra) {
    // Lógica compleja para actualizar orden de compra
    // Verificar si se completó la orden
    // Actualizar estado a 'Aceptada'
}
```
**Acción**: Extraer a `OrdenCompraService::actualizarDesdeCompra()`

##### F. Creación de Transacciones Bancarias (líneas 383-386)
```php
if (!$request->id && $compra->cotizacion == 0 && $compra->forma_pago != 'Efectivo' && $compra->forma_pago != 'Cheque') {
    $this->transaccionesService->crear(...);
}
```
**Acción**: Ya usa servicio, pero la lógica condicional debería estar en el Service

##### G. Creación de Cheques (líneas 388-391)
```php
if (!$request->id && $compra->cotizacion == 0 && $compra->forma_pago == 'Cheque') {
    $this->chequesService->crear(...);
}
```
**Acción**: Similar al anterior, mover lógica condicional al Service

##### H. Incremento de Correlativos (líneas 393-404)
```php
if (!$request->id && $request->tipo_documento == 'Orden de compra') {
    $documento = Documento::where(...)->first();
    $documento->increment('correlativo');
}
```
**Acción**: Extraer a `CompraService::incrementarCorrelativo()`

---

### 3. PlanillasController::store() (Líneas 164-306)

#### Estado Actual
⚠️ **Parcialmente refactorizado** - Usa algunos Services pero tiene lógica compleja

#### Lógica de Negocio a Extraer

##### A. Validación de Planilla Existente (líneas 179-190)
```php
$planillaExistente = Planilla::where('id_empresa', auth()->user()->id_empresa)
    ->where('id_sucursal', auth()->user()->id_sucursal)
    ->where('fecha_inicio', $fechaInicio)
    ->where('fecha_fin', $fechaFin)
    ->first();
```
**Acción**: Extraer a `PlanillaService::validarPlanillaExistente()`

##### B. Generación de Código Único (líneas 192-195)
```php
$codigo = 'PLA-' . $fechaInicio->format('Ym') .
    ($fechaInicio->day <= 15 ? '1-' : '2-') .
    auth()->user()->id_sucursal;
```
**Acción**: Extraer a `PlanillaService::generarCodigoUnico()`

##### C. Creación de Planilla Inicial (líneas 197-214)
```php
$planilla = new Planilla([
    'codigo' => $codigo,
    'fecha_inicio' => $fechaInicio,
    // ... más campos
]);
$planilla->save();
```
**Acción**: Extraer a `PlanillaService::crearPlanillaInicial()`

##### D. Creación de Detalles de Planilla (líneas 216-262)
**Método privado `crearDetallePlanilla()` (líneas 677-830)** - Contiene lógica MUY compleja:
- Cálculo de días de referencia según tipo de planilla
- Cálculo de salario base ajustado
- Cálculo de salario devengado según tipo de contrato
- Llamada a `ConfiguracionPlanillaService` para calcular conceptos
- Creación del detalle con todos los campos calculados

**Acción**: Extraer a `PlanillaService::crearDetallePlanilla()` o `PlanillaDetalleService::crearDetalle()`

##### E. Actualización de Totales (línea 273)
```php
$planilla->actualizarTotales();
```
**Acción**: Ya está en el modelo, pero podría estar en el Service para mejor testabilidad

---

## 📦 Estructura de Services Propuesta

### Services Existentes a Utilizar/Mejorar

1. **Ventas**
   - ✅ `VentaService` - Ya existe y está bien estructurado
   - ✅ `CotizacionService` - Ya existe
   - ✅ `InventarioService` - Ya existe
   - ⚠️ **Nuevo**: `VentaAuthorizationService` - Para validaciones de permisos

2. **Compras**
   - ✅ `ComprasService` (en `app/Services/Contabilidad/ComprasService.php`) - Verificar y mejorar
   - ⚠️ **Nuevo**: `CompraService` (en `app/Services/Compras/`) - Para lógica específica de compras
   - ⚠️ **Nuevo**: `ComprasAuthorizationService` - Para validaciones de autorización
   - ⚠️ **Nuevo**: `OrdenCompraService` - Para lógica de órdenes de compra

3. **Planilla**
   - ✅ `ConfiguracionPlanillaService` - Ya existe y está bien estructurado
   - ⚠️ **Nuevo**: `PlanillaService` - Para lógica principal de planillas
   - ⚠️ **Nuevo**: `PlanillaDetalleService` - Para lógica de detalles de planilla

---

## 🎯 Plan de Implementación Detallado

### Fase 1: ComprasController::facturacion()

#### Paso 1.1: Crear CompraService
**Archivo**: `app/Services/Compras/CompraService.php`

**Métodos a crear**:
1. `calcularTotal(array $data): float`
2. `crearOActualizarCompra(array $data): Compra`
3. `procesarDetallesConInventario(Compra $compra, array $detalles, bool $esNueva): void`
4. `incrementarCorrelativo(Compra $compra, string $tipoDocumento): void`
5. `procesarPagos(Compra $compra, bool $esNueva): void` - Para transacciones y cheques

#### Paso 1.2: Crear ComprasAuthorizationService
**Archivo**: `app/Services/Compras/ComprasAuthorizationService.php`

**Métodos a crear**:
1. `validarAutorizacionRequerida(array $data, ?int $idCompra, ?int $idAuthorization): array`
   - Retorna: `['requiere_autorizacion' => bool, 'mensaje' => string, 'total' => float]`

#### Paso 1.3: Crear OrdenCompraService
**Archivo**: `app/Services/Compras/OrdenCompraService.php`

**Métodos a crear**:
1. `actualizarDesdeCompra(Compra $compra, array $detalles): void`

#### Paso 1.4: Refactorizar ComprasController::facturacion()
```php
public function facturacion(Request $request)
{
    $request->validate([...]);
    
    // Validar autorización
    $validacion = $this->comprasAuthorizationService->validarAutorizacionRequerida(
        $request->all(),
        $request->id,
        $request->id_authorization
    );
    
    if ($validacion['requiere_autorizacion']) {
        return response()->json([
            'ok' => false,
            'requires_authorization' => true,
            'authorization_type' => 'compras_altas',
            'message' => $validacion['mensaje']
        ], 403);
    }
    
    DB::beginTransaction();
    try {
        // Crear/actualizar compra
        $compra = $this->compraService->crearOActualizarCompra($request->all());
        
        // Procesar detalles con inventario
        $this->compraService->procesarDetallesConInventario(
            $compra,
            $request->detalles,
            !$request->id
        );
        
        // Actualizar orden de compra si aplica
        if ($compra->num_orden_compra) {
            $this->ordenCompraService->actualizarDesdeCompra($compra, $request->detalles);
        }
        
        // Procesar pagos
        $this->compraService->procesarPagos($compra, !$request->id);
        
        // Incrementar correlativo
        $this->compraService->incrementarCorrelativo($compra, $request->tipo_documento);
        
        DB::commit();
        return Response()->json($compra, 200);
    } catch (\Exception $e) {
        DB::rollback();
        return Response()->json(['error' => $e->getMessage()], 400);
    }
}
```

---

### Fase 2: PlanillasController::store()

#### Paso 2.1: Crear PlanillaService
**Archivo**: `app/Services/Planilla/PlanillaService.php`

**Métodos a crear**:
1. `validarPlanillaExistente(Carbon $fechaInicio, Carbon $fechaFin, int $idEmpresa, int $idSucursal): ?Planilla`
2. `generarCodigoUnico(Carbon $fechaInicio, int $idSucursal): string`
3. `crearPlanillaInicial(array $data): Planilla`
4. `crearDetallesDesdeTemplate(Planilla $planilla, int $templateId, string $tipoPlanilla): array`
5. `crearDetallesDesdeEmpleados(Planilla $planilla, string $tipoPlanilla): array`
6. `actualizarTotales(Planilla $planilla): void`

#### Paso 2.2: Crear PlanillaDetalleService
**Archivo**: `app/Services/Planilla/PlanillaDetalleService.php`

**Métodos a crear**:
1. `crearDetalle(Empleado $empleado, int $planillaId, string $tipoPlanilla): ?PlanillaDetalle`
2. `calcularSalarioDevengado(Empleado $empleado, string $tipoPlanilla, int $diasReferencia): array`
   - Retorna: `['salario_base' => float, 'salario_devengado' => float, 'dias_laborados' => int]`
3. `crearDetalleFallback(Empleado $empleado, int $planillaId, string $tipoPlanilla): PlanillaDetalle`

#### Paso 2.3: Refactorizar PlanillasController::store()
```php
public function store(Request $request)
{
    $request->validate([...]);
    
    DB::beginTransaction();
    try {
        $fechaInicio = Carbon::parse($request->fecha_inicio);
        $fechaFin = Carbon::parse($request->fecha_fin);
        
        // Validar planilla existente
        $planillaExistente = $this->planillaService->validarPlanillaExistente(
            $fechaInicio,
            $fechaFin,
            auth()->user()->id_empresa,
            auth()->user()->id_sucursal
        );
        
        if ($planillaExistente) {
            return response()->json([
                'error' => 'Ya existe una planilla para este período'
            ], 422);
        }
        
        // Generar código único
        $codigo = $this->planillaService->generarCodigoUnico(
            $fechaInicio,
            auth()->user()->id_sucursal
        );
        
        // Crear planilla inicial
        $planilla = $this->planillaService->crearPlanillaInicial([
            'codigo' => $codigo,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'tipo_planilla' => $request->tipo_planilla,
            'id_empresa' => auth()->user()->id_empresa,
            'id_sucursal' => auth()->user()->id_sucursal,
        ]);
        
        // Crear detalles
        if ($request->planillaTemplate) {
            $resultado = $this->planillaService->crearDetallesDesdeTemplate(
                $planilla,
                $request->planillaTemplate,
                $request->tipo_planilla
            );
        } else {
            $resultado = $this->planillaService->crearDetallesDesdeEmpleados(
                $planilla,
                $request->tipo_planilla
            );
        }
        
        if ($resultado['empleados_incluidos'] === 0) {
            DB::rollback();
            return response()->json([
                'error' => 'No se pudo generar la planilla porque no hay empleados activos para el período indicado'
            ], 422);
        }
        
        // Actualizar totales
        $this->planillaService->actualizarTotales($planilla);
        
        DB::commit();
        
        return response()->json([
            'message' => 'Planilla generada exitosamente',
            'planilla' => $planilla->fresh(['detalles']),
            'estadisticas' => [
                'empleados_incluidos' => $resultado['empleados_incluidos'],
                'empleados_omitidos' => $resultado['empleados_omitidos']
            ]
        ]);
    } catch (\Exception $e) {
        DB::rollback();
        Log::error('Error generando planilla: ' . $e->getMessage());
        return response()->json([
            'error' => 'Error al generar la planilla: ' . $e->getMessage()
        ], 500);
    }
}
```

---

### Fase 3: VentasController::facturacion()

#### Paso 3.1: Crear VentaAuthorizationService
**Archivo**: `app/Services/Ventas/VentaAuthorizationService.php`

**Métodos a crear**:
1. `validarPermisoCredito(User $user, bool $esCredito): void`
   - Lanza excepción si no tiene permiso

#### Paso 3.2: Refactorizar VentasController::facturacion()
```php
public function facturacion(FacturacionRequest $request)
{
    // Validar permisos
    try {
        $this->ventaAuthorizationService->validarPermisoCredito(
            auth()->user(),
            $request->credito == 1
        );
    } catch (UnauthorizedException $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 403);
    }
    
    DB::beginTransaction();
    try {
        // Resto del código ya está bien refactorizado
        // ...
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error en facturacion: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 400);
    }
}
```

---

## 🧪 Plan de Testing

### Tests Unitarios para Services

#### CompraService Tests
```php
tests/Unit/Services/Compras/CompraServiceTest.php
- test_calcular_total_desde_request()
- test_calcular_total_desde_detalles()
- test_crear_compra_nueva()
- test_actualizar_compra_existente()
- test_procesar_detalles_con_inventario()
- test_incrementar_correlativo()
- test_procesar_pagos_transaccion_bancaria()
- test_procesar_pagos_cheque()
```

#### ComprasAuthorizationService Tests
```php
tests/Unit/Services/Compras/ComprasAuthorizationServiceTest.php
- test_no_requiere_autorizacion_compra_existente()
- test_no_requiere_autorizacion_con_authorization_id()
- test_requiere_autorizacion_monto_mayor_3000()
- test_no_requiere_autorizacion_monto_menor_3000()
```

#### PlanillaService Tests
```php
tests/Unit/Services/Planilla/PlanillaServiceTest.php
- test_validar_planilla_existente()
- test_generar_codigo_unico_quincenal()
- test_generar_codigo_unico_mensual()
- test_crear_planilla_inicial()
- test_crear_detalles_desde_template()
- test_crear_detalles_desde_empleados()
```

#### PlanillaDetalleService Tests
```php
tests/Unit/Services/Planilla/PlanillaDetalleServiceTest.php
- test_calcular_salario_devengado_mensual()
- test_calcular_salario_devengado_quincenal()
- test_calcular_salario_devengado_semanal()
- test_crear_detalle_empleado_permanente()
- test_crear_detalle_empleado_por_obra()
- test_crear_detalle_empleado_servicios_profesionales()
```

### Tests de Integración

#### Feature Tests
```php
tests/Feature/Compras/FacturacionTest.php
- test_facturacion_compra_nueva_exitosa()
- test_facturacion_requiere_autorizacion()
- test_facturacion_actualiza_inventario()
- test_facturacion_actualiza_orden_compra()
- test_facturacion_crea_transaccion_bancaria()
- test_facturacion_crea_cheque()
```

```php
tests/Feature/Planilla/PlanillaStoreTest.php
- test_store_planilla_exitosa()
- test_store_planilla_duplicada_falla()
- test_store_planilla_desde_template()
- test_store_planilla_desde_empleados()
- test_store_planilla_sin_empleados_falla()
```

---

## ✅ Criterios de Aceptación

### Checklist de Implementación

#### ComprasController
- [ ] `CompraService` creado con todos los métodos necesarios
- [ ] `ComprasAuthorizationService` creado
- [ ] `OrdenCompraService` creado
- [ ] `ComprasController::facturacion()` refactorizado
- [ ] Controlador solo coordina (validar, llamar service, retornar)
- [ ] Tests unitarios para `CompraService`
- [ ] Tests unitarios para `ComprasAuthorizationService`
- [ ] Tests de integración para facturación de compras
- [ ] Funcionalidad se mantiene intacta

#### PlanillasController
- [ ] `PlanillaService` creado con todos los métodos necesarios
- [ ] `PlanillaDetalleService` creado
- [ ] `PlanillasController::store()` refactorizado
- [ ] Método privado `crearDetallePlanilla()` movido a Service
- [ ] Controlador solo coordina (validar, llamar service, retornar)
- [ ] Tests unitarios para `PlanillaService`
- [ ] Tests unitarios para `PlanillaDetalleService`
- [ ] Tests de integración para creación de planillas
- [ ] Funcionalidad se mantiene intacta

#### VentasController
- [ ] `VentaAuthorizationService` creado
- [ ] `VentasController::facturacion()` refactorizado (solo validación de permisos)
- [ ] Tests unitarios para `VentaAuthorizationService`
- [ ] Funcionalidad se mantiene intacta

---

## 📝 Notas de Implementación

### Principios a Seguir

1. **Single Responsibility**: Cada Service tiene una responsabilidad clara
2. **Dependency Injection**: Todos los Services se inyectan en los controladores
3. **Testabilidad**: Cada método debe ser testeable de forma independiente
4. **Mantenibilidad**: Código claro y bien documentado

### Convenciones de Nombres

- Services: `{Entidad}Service` (ej: `CompraService`, `PlanillaService`)
- Métodos: Verbos en infinitivo (ej: `crear`, `actualizar`, `validar`)
- Tests: `{Service}Test` en `tests/Unit/Services/{Modulo}/`

### Manejo de Errores

- Los Services deben lanzar excepciones específicas
- Los controladores capturan y transforman en respuestas HTTP apropiadas
- Logging de errores en los Services, no en los controladores

### Transacciones

- Las transacciones DB se mantienen en los controladores
- Los Services no deben manejar transacciones directamente
- Cada Service puede tener métodos que acepten transacciones como parámetro opcional

---

## 🚀 Orden de Implementación Recomendado

1. **Fase 1**: ComprasController (mayor impacto)
   - Crear Services
   - Refactorizar controlador
   - Crear tests
   - Verificar funcionalidad

2. **Fase 2**: PlanillasController (lógica compleja)
   - Crear Services
   - Refactorizar controlador
   - Crear tests
   - Verificar funcionalidad

3. **Fase 3**: VentasController (menor impacto)
   - Crear Service de autorización
   - Refactorizar validación
   - Crear tests
   - Verificar funcionalidad

---

## 📊 Métricas de Éxito

- ✅ 0 lógica de negocio compleja en controladores
- ✅ 100% de métodos de negocio con tests unitarios
- ✅ Controladores con menos de 50 líneas por método
- ✅ Services con responsabilidades claras y únicas
- ✅ Funcionalidad existente se mantiene intacta

---

## ⚠️ Riesgos y Consideraciones

### Riesgos
1. **Regresiones**: Cambios pueden afectar funcionalidad existente
   - **Mitigación**: Tests exhaustivos antes y después

2. **Dependencias circulares**: Services pueden depender entre sí
   - **Mitigación**: Diseño cuidadoso de dependencias

3. **Performance**: Múltiples llamadas a Services pueden afectar rendimiento
   - **Mitigación**: Optimizar queries, usar eager loading

### Consideraciones
- Mantener compatibilidad con código existente
- Documentar cambios en Services
- Revisar código en PR antes de merge
- Testing manual después de cada fase

---

## 📚 Referencias

- Laravel Service Layer Pattern: https://laravel.com/docs/controllers
- Single Responsibility Principle: https://en.wikipedia.org/wiki/Single-responsibility_principle
- Estructura actual de Services en: `app/Services/`

