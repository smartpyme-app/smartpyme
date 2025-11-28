# Plan de Trabajo - Fase 1: Refactorización ComprasController

## 🎯 Objetivo
Refactorizar `ComprasController::facturacion()` extrayendo toda la lógica de negocio a Services, manteniendo la funcionalidad intacta.

---

## 📋 Flujo de Trabajo Recomendado

### Estrategia: Branch por Feature + Commits Incrementales

```
main
  └── feature/refactor-compras-controller
      ├── feat/compra-service-basic
      ├── feat/compras-authorization-service
      ├── feat/orden-compra-service
      └── feat/refactor-facturacion-method
```

**Recomendación**: Crear un branch principal `feature/refactor-compras-controller` y hacer commits incrementales por cada Service creado.

---

## 🚀 Paso a Paso - Fase 1

### Paso 1: Preparación (30 min)

#### 1.1 Crear Branch
```bash
git checkout -b feature/refactor-compras-controller
```

#### 1.2 Verificar Tests Existentes
```bash
# Verificar si hay tests para ComprasController
find tests -name "*Compra*" -type f
```

#### 1.3 Crear Estructura de Directorios
```bash
mkdir -p Backend/app/Services/Compras
mkdir -p Backend/tests/Unit/Services/Compras
mkdir -p Backend/tests/Feature/Compras
```

#### 1.4 Commit Inicial
```bash
git add .
git commit -m "chore: preparar estructura para refactorización de ComprasController"
```

---

### Paso 2: Crear CompraService - Métodos Básicos (2-3 horas)

#### 2.1 Crear Service Base
**Archivo**: `Backend/app/Services/Compras/CompraService.php`

**Métodos a crear primero** (los más simples):
1. `calcularTotal(array $data): float`
2. `crearOActualizarCompra(array $data): Compra`
3. `incrementarCorrelativo(Compra $compra, string $tipoDocumento): void`

#### 2.2 Crear Tests Unitarios
**Archivo**: `Backend/tests/Unit/Services/Compras/CompraServiceTest.php`

```php
- test_calcular_total_desde_request()
- test_calcular_total_desde_detalles()
- test_calcular_total_cuando_es_cero()
- test_crear_compra_nueva()
- test_actualizar_compra_existente()
- test_incrementar_correlativo_orden_compra()
- test_incrementar_correlativo_sujeto_excluido()
```

#### 2.3 Ejecutar Tests
```bash
php artisan test --filter CompraServiceTest
```

#### 2.4 Commit
```bash
git add Backend/app/Services/Compras/CompraService.php Backend/tests/Unit/Services/Compras/CompraServiceTest.php
git commit -m "feat(compras): crear CompraService con métodos básicos

- calcularTotal()
- crearOActualizarCompra()
- incrementarCorrelativo()
- Tests unitarios incluidos"
```

---

### Paso 3: Crear ComprasAuthorizationService (1-2 horas)

#### 3.1 Crear Service
**Archivo**: `Backend/app/Services/Compras/ComprasAuthorizationService.php`

**Método**:
- `validarAutorizacionRequerida(array $data, ?int $idCompra, ?int $idAuthorization): array`

#### 3.2 Crear Tests Unitarios
**Archivo**: `Backend/tests/Unit/Services/Compras/ComprasAuthorizationServiceTest.php`

```php
- test_no_requiere_autorizacion_compra_existente()
- test_no_requiere_autorizacion_con_authorization_id()
- test_requiere_autorizacion_monto_mayor_3000()
- test_no_requiere_autorizacion_monto_menor_3000()
- test_calcula_total_correctamente()
```

#### 3.3 Ejecutar Tests
```bash
php artisan test --filter ComprasAuthorizationServiceTest
```

#### 3.4 Commit
```bash
git add Backend/app/Services/Compras/ComprasAuthorizationService.php Backend/tests/Unit/Services/Compras/ComprasAuthorizationServiceTest.php
git commit -m "feat(compras): crear ComprasAuthorizationService

- validarAutorizacionRequerida()
- Tests unitarios incluidos"
```

---

### Paso 4: Crear OrdenCompraService (1-2 horas)

#### 4.1 Crear Service
**Archivo**: `Backend/app/Services/Compras/OrdenCompraService.php`

**Método**:
- `actualizarDesdeCompra(Compra $compra, array $detalles): void`

#### 4.2 Crear Tests Unitarios
**Archivo**: `Backend/tests/Unit/Services/Compras/OrdenCompraServiceTest.php`

```php
- test_actualizar_orden_compra_con_detalles()
- test_marcar_orden_como_aceptada_cuando_completa()
- test_no_marcar_orden_si_faltan_productos()
- test_actualizar_cantidad_procesada()
```

#### 4.3 Ejecutar Tests
```bash
php artisan test --filter OrdenCompraServiceTest
```

#### 4.4 Commit
```bash
git add Backend/app/Services/Compras/OrdenCompraService.php Backend/tests/Unit/Services/Compras/OrdenCompraServiceTest.php
git commit -m "feat(compras): crear OrdenCompraService

- actualizarDesdeCompra()
- Tests unitarios incluidos"
```

---

### Paso 5: Extender CompraService - Procesamiento de Detalles (2-3 horas)

#### 5.1 Agregar Métodos a CompraService
**Métodos**:
- `procesarDetallesConInventario(Compra $compra, array $detalles, bool $esNueva): void`
- `procesarPagos(Compra $compra, bool $esNueva): void`

#### 5.2 Actualizar Tests
Agregar tests para los nuevos métodos:
```php
- test_procesar_detalles_con_inventario()
- test_procesar_detalles_actualiza_stock()
- test_procesar_detalles_calcula_costo_promedio()
- test_procesar_pagos_crea_transaccion_bancaria()
- test_procesar_pagos_crea_cheque()
- test_procesar_pagos_no_crea_nada_si_es_efectivo()
```

#### 5.3 Ejecutar Tests
```bash
php artisan test --filter CompraServiceTest
```

#### 5.4 Commit
```bash
git add Backend/app/Services/Compras/CompraService.php Backend/tests/Unit/Services/Compras/CompraServiceTest.php
git commit -m "feat(compras): extender CompraService con procesamiento de detalles y pagos

- procesarDetallesConInventario()
- procesarPagos()
- Tests unitarios actualizados"
```

---

### Paso 6: Refactorizar ComprasController::facturacion() (2-3 horas)

#### 6.1 Actualizar ComprasController
**Archivo**: `Backend/app/Http/Controllers/Api/Compras/ComprasController.php`

**Cambios**:
1. Inyectar nuevos Services en constructor
2. Refactorizar método `facturacion()` para usar Services
3. Eliminar método privado `calcularTotalCompra()`

#### 6.2 Crear Test de Integración
**Archivo**: `Backend/tests/Feature/Compras/FacturacionTest.php`

```php
- test_facturacion_compra_nueva_exitosa()
- test_facturacion_requiere_autorizacion_monto_alto()
- test_facturacion_actualiza_inventario()
- test_facturacion_actualiza_orden_compra()
- test_facturacion_crea_transaccion_bancaria()
- test_facturacion_crea_cheque()
- test_facturacion_incrementa_correlativo()
```

#### 6.3 Ejecutar Tests
```bash
# Tests unitarios
php artisan test --filter CompraServiceTest
php artisan test --filter ComprasAuthorizationServiceTest
php artisan test --filter OrdenCompraServiceTest

# Tests de integración
php artisan test --filter FacturacionTest

# Todos los tests relacionados
php artisan test tests/Unit/Services/Compras tests/Feature/Compras
```

#### 6.4 Testing Manual
1. Crear una compra nueva desde el frontend
2. Verificar que se crea correctamente
3. Verificar que el inventario se actualiza
4. Verificar que se crean transacciones/cheques si aplica
5. Probar con compra que requiere autorización (>$3,000)
6. Probar actualización de compra existente

#### 6.5 Commit
```bash
git add Backend/app/Http/Controllers/Api/Compras/ComprasController.php Backend/tests/Feature/Compras/FacturacionTest.php
git commit -m "refactor(compras): refactorizar ComprasController::facturacion()

- Extraer lógica de negocio a Services
- Eliminar método privado calcularTotalCompra()
- Tests de integración incluidos
- Funcionalidad mantenida intacta"
```

---

### Paso 7: Refactorizar facturacionConsigna() (Opcional - 1-2 horas)

Si quieres completar todo ComprasController:

#### 7.1 Crear CompraConsignaService
**Archivo**: `Backend/app/Services/Compras/CompraConsignaService.php`

#### 7.2 Refactorizar método
**Archivo**: `Backend/app/Http/Controllers/Api/Compras/ComprasController.php`

#### 7.3 Tests
```bash
php artisan test --filter CompraConsignaServiceTest
```

#### 7.4 Commit
```bash
git commit -m "refactor(compras): refactorizar facturacionConsigna()"
```

---

### Paso 8: Limpieza y Documentación (1 hora)

#### 8.1 Revisar Código
- Eliminar código comentado si existe
- Verificar que no hay métodos privados con lógica de negocio
- Verificar que los controladores solo coordinan

#### 8.2 Documentar Services
Agregar PHPDoc a los métodos de los Services:
```php
/**
 * Calcula el total de una compra desde los datos del request
 *
 * @param array $data Datos de la compra
 * @return float Total calculado
 */
```

#### 8.3 Actualizar README o Documentación
Si existe documentación del proyecto, actualizarla.

#### 8.4 Commit Final
```bash
git add .
git commit -m "docs(compras): agregar documentación a Services

- PHPDoc en todos los métodos
- Limpieza de código comentado"
```

---

### Paso 9: Merge a Main (30 min)

#### 9.1 Revisión Final
```bash
# Ver todos los cambios
git diff main..feature/refactor-compras-controller

# Ver commits
git log main..feature/refactor-compras-controller --oneline
```

#### 9.2 Ejecutar Todos los Tests
```bash
php artisan test
```

#### 9.3 Merge
```bash
git checkout main
git merge feature/refactor-compras-controller
# O crear Pull Request si usas GitHub/GitLab
```

#### 9.4 Tag (Opcional)
```bash
git tag -a v1.0.0-refactor-compras -m "Refactorización ComprasController completada"
```

---

## ✅ Checklist de Completitud

Antes de considerar la Fase 1 completa:

### Código
- [ ] Todos los Services creados y funcionando
- [ ] ComprasController refactorizado
- [ ] Métodos privados con lógica eliminados
- [ ] Código comentado eliminado
- [ ] PHPDoc agregado

### Tests
- [ ] Tests unitarios para todos los Services (cobertura > 80%)
- [ ] Tests de integración para facturacion()
- [ ] Todos los tests pasando
- [ ] Tests manuales realizados

### Funcionalidad
- [ ] Crear compra nueva funciona
- [ ] Actualizar compra existente funciona
- [ ] Autorización para compras >$3,000 funciona
- [ ] Inventario se actualiza correctamente
- [ ] Transacciones/cheques se crean correctamente
- [ ] Orden de compra se actualiza correctamente
- [ ] Correlativos se incrementan correctamente

### Documentación
- [ ] Services documentados con PHPDoc
- [ ] README actualizado (si aplica)
- [ ] Comentarios explicativos donde sea necesario

---

## 📊 Métricas de Éxito

- ✅ 0 métodos privados con lógica de negocio en ComprasController
- ✅ 100% de métodos de Service con tests unitarios
- ✅ ComprasController::facturacion() < 50 líneas
- ✅ Funcionalidad se mantiene intacta
- ✅ Todos los tests pasando

---

## 🐛 Troubleshooting

### Si los tests fallan:
1. Verificar que los Services están registrados en el contenedor de Laravel
2. Verificar que las dependencias están inyectadas correctamente
3. Revisar logs: `storage/logs/laravel.log`

### Si hay errores de dependencias:
```bash
composer dump-autoload
php artisan clear-compiled
php artisan config:clear
```

### Si hay problemas con transacciones DB:
- Verificar que `DB::beginTransaction()` está en el controlador
- Verificar que los Services no manejan transacciones directamente

---

## 📝 Notas Importantes

1. **No hacer cambios funcionales**: Solo refactorizar, no agregar nuevas features
2. **Un commit por Service**: Facilita el review y el rollback si es necesario
3. **Tests primero**: Crear tests antes o junto con el código
4. **Testing manual**: Siempre probar manualmente después de cada cambio importante
5. **Comunicar cambios**: Si trabajas en equipo, comunicar los cambios

---

## 🎯 Siguiente Fase

Una vez completada la Fase 1:
1. Revisar resultados
2. Aplicar aprendizajes a Fase 2 (PlanillasController)
3. Ajustar proceso si es necesario

---

**Tiempo Estimado Total**: 12-16 horas  
**Duración Recomendada**: 1-2 semanas (trabajando de forma incremental)

