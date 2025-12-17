# Auditoría de Sistema Contable Multi-País: El Salvador y Honduras

## Documento de Análisis y Recomendaciones para Smartpyme

**Fecha:** Enero 2025  
**Objetivo:** Analizar y documentar las adaptaciones necesarias para que el sistema Smartpyme funcione correctamente tanto en El Salvador como en Honduras, específicamente en módulos contables, fiscales y de reportes.

---

## Índice

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Diferencias Normativas Clave](#diferencias-normativas-clave)
3. [Análisis de Módulos Existentes](#análisis-de-módulos-existentes)
4. [Módulos que Requieren Modificaciones](#módulos-que-requieren-modificaciones)
5. [Módulos que Requieren Creación](#módulos-que-requieren-creación)
6. [Reportes que Requieren Actualización](#reportes-que-requieren-actualización)
7. [Plan de Implementación Recomendado](#plan-de-implementación-recomendado)
8. [Consideraciones Técnicas](#consideraciones-técnicas)

---

## Resumen Ejecutivo

### Estado Actual del Sistema

El sistema Smartpyme actualmente está diseñado principalmente para El Salvador, con algunas referencias a Honduras en el código. El sistema cuenta con:

- ✅ Infraestructura básica multi-país (campo `cod_pais` en tabla `empresas`)
- ✅ Módulos contables básicos implementados
- ✅ Sistema de libros de IVA funcional para El Salvador
- ✅ Reportes contables básicos (Balance General, Estado de Resultados, Libro Diario)
- ⚠️ Configuración de país parcialmente implementada
- ❌ Formatos específicos de Honduras no implementados
- ❌ Diferenciación de normativas fiscales por país limitada

### Impacto de las Adaptaciones

**Módulos Críticos a Modificar:** 8  
**Nuevos Módulos Requeridos:** 3  
**Reportes a Actualizar:** 12  
**Nuevos Reportes Requeridos:** 5  
**Tiempo Estimado de Implementación:** 8-12 semanas

---

## Diferencias Normativas Clave

### 1. Impuestos y Tasas

| Concepto | El Salvador | Honduras | Impacto en Sistema |
|----------|-------------|----------|---------------------|
| **IVA General** | 13% | 15% | ⚠️ CRÍTICO - Requiere configuración por país |
| **ISR Corporativo** | 30% | 25% | ⚠️ CRÍTICO - Requiere configuración por país |
| **ISR Retención** | Variable | Variable | ⚠️ ALTO - Diferentes tasas y conceptos |
| **Percepción IVA** | 1% | No aplica | ⚠️ ALTO - Solo El Salvador |
| **Retención IVA** | 1% | Variable | ⚠️ ALTO - Diferentes reglas |

### 2. Libros Contables Obligatorios

#### El Salvador
- ✅ Libro Diario
- ✅ Libro Mayor
- ✅ Libro de Inventarios y Balances
- ✅ Libro de Ventas a Consumidores Finales
- ✅ Libro de Ventas a Contribuyentes
- ✅ Libro de Compras
- ✅ Libro de Anulados
- ✅ Libro de Sujetos Excluidos

#### Honduras
- ✅ Libro Diario
- ✅ Libro Mayor
- ✅ Libro de Inventarios y Balances
- ✅ Libro de Actas de Asamblea (❌ NO IMPLEMENTADO)
- ✅ Libro de Registro de Accionistas (❌ NO IMPLEMENTADO)
- ✅ Libro de Ventas (ISV)
- ✅ Libro de Compras (ISV)
- ⚠️ Formatos de anexos diferentes a El Salvador

### 3. Facturación Electrónica

| Aspecto | El Salvador | Honduras |
|---------|-------------|----------|
| **Sistema** | Ministerio de Hacienda (MH) | Servicio de Administración de Rentas (SAR) |
| **Formato DTE** | JSON específico MH | Formato diferente SAR |
| **Validación** | Sello MH | Sello SAR |
| **Códigos** | Códigos específicos MH | Códigos específicos SAR |

### 4. Nomenclatura y Terminología

| Concepto | El Salvador | Honduras |
|----------|-------------|----------|
| **Impuesto sobre Ventas** | IVA | ISV (Impuesto sobre Ventas) |
| **Número de Registro** | NRC | RTN (Registro Tributario Nacional) |
| **Autoridad Fiscal** | MH (Ministerio de Hacienda) | SAR (Servicio de Administración de Rentas) |
| **Contribuyente** | Contribuyente | Contribuyente (mismo término) |

---

## Análisis de Módulos Existentes

### ✅ Módulos que Funcionan Correctamente (Sin Modificaciones)

Estos módulos son genéricos y funcionan para ambos países:

1. **Catálogo de Cuentas Contables**
   - Ubicación: `Backend/app/Models/Contabilidad/Catalogo/Cuenta.php`
   - Estado: ✅ Funcional
   - Nota: La estructura de cuentas es estándar y aplica para ambos países

2. **Partidas Contables**
   - Ubicación: `Backend/app/Models/Contabilidad/Partidas/Partida.php`
   - Estado: ✅ Funcional
   - Nota: El sistema de partidas es universal

3. **Balance General**
   - Ubicación: `Backend/app/Http/Controllers/Api/Contabilidad/Reportes/GenerarReportesController.php`
   - Estado: ✅ Funcional
   - Nota: Formato estándar aplicable a ambos países

4. **Estado de Resultados**
   - Ubicación: `Backend/app/Http/Controllers/Api/Contabilidad/Reportes/GenerarReportesController.php`
   - Estado: ✅ Funcional
   - Nota: Formato estándar aplicable a ambos países

5. **Libro Diario**
   - Ubicación: `Backend/app/Http/Controllers/Api/Contabilidad/Reportes/GenerarReportesController.php`
   - Estado: ✅ Funcional
   - Nota: Formato estándar aplicable a ambos países

6. **Libro Mayor**
   - Ubicación: `Backend/app/Http/Controllers/Api/Contabilidad/Reportes/GenerarReportesController.php`
   - Estado: ✅ Funcional
   - Nota: Formato estándar aplicable a ambos países

---

## Módulos que Requieren Modificaciones

### 🔧 1. Configuración de Impuestos

**Ubicación Actual:**
- `Backend/app/Models/Admin/Empresa.php` (campo `iva`)
- `Backend/app/Services/ImpuestosService.php`

**Problema Identificado:**
- El campo `iva` en la tabla `empresas` almacena un porcentaje único (13% para El Salvador)
- No hay diferenciación por país
- No se considera el ISV de Honduras (15%)

**Modificaciones Requeridas:**

1. **Crear tabla `configuracion_impuestos_pais`**
```sql
CREATE TABLE configuracion_impuestos_pais (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cod_pais VARCHAR(3) NOT NULL,
    tipo_impuesto VARCHAR(50) NOT NULL, -- 'IVA', 'ISV', 'ISR'
    porcentaje DECIMAL(5,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_vigencia_desde DATE,
    fecha_vigencia_hasta DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_impuesto_pais (cod_pais, tipo_impuesto, fecha_vigencia_desde)
);
```

2. **Modificar `ImpuestosService.php`**
   - Agregar método `obtenerPorcentajeImpuestoPorPais($empresaId, $tipoImpuesto)`
   - Considerar el país de la empresa al obtener porcentajes
   - Implementar lógica para IVA (El Salvador) vs ISV (Honduras)

3. **Actualizar modelo `Empresa.php`**
   - Agregar relación con configuración de impuestos por país
   - Método helper `obtenerPorcentajeImpuesto($tipoImpuesto)`

**Archivos a Modificar:**
- `Backend/app/Services/ImpuestosService.php`
- `Backend/app/Models/Admin/Empresa.php`
- `Backend/database/migrations/` (nueva migración)

**Prioridad:** 🔴 CRÍTICA

---

### 🔧 2. Libros de IVA/ISV

**Ubicación Actual:**
- `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php`
- `Backend/app/Exports/Contabilidad/LibroConsumidoresExport.php`
- `Backend/app/Exports/Contabilidad/LibroContribuyentesExport.php`
- `Backend/app/Exports/Contabilidad/AnexoConsumidoresExport.php`
- `Backend/app/Exports/Contabilidad/AnexoContribuyentesExport.php`

**Problema Identificado:**
- Los formatos están hardcodeados para El Salvador
- Los anexos CSV tienen estructura específica de El Salvador
- No hay diferenciación entre IVA (El Salvador) e ISV (Honduras)
- Los campos y columnas son específicos de la normativa salvadoreña

**Modificaciones Requeridas:**

1. **Crear Service para Formateo por País**
   - `Backend/app/Services/Contabilidad/LibroIvaFormatoService.php`
   - Métodos para obtener formato según país
   - Métodos para mapear campos según país

2. **Modificar Exports para ser Multi-País**
   - Agregar lógica condicional basada en `cod_pais`
   - Crear métodos separados para formato El Salvador vs Honduras
   - Ajustar columnas y campos según país

3. **Diferencias Específicas a Implementar:**

   **El Salvador:**
   - Campo "NRC" (Número de Registro de Contribuyente)
   - Clase documento: 1 (Impreso) o 4 (DTE)
   - Formato anexo con campos específicos MH

   **Honduras:**
   - Campo "RTN" (Registro Tributario Nacional)
   - Formato anexo con estructura diferente SAR
   - Campos adicionales requeridos por SAR

**Archivos a Modificar:**
- `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php`
- `Backend/app/Exports/Contabilidad/LibroConsumidoresExport.php`
- `Backend/app/Exports/Contabilidad/LibroContribuyentesExport.php`
- `Backend/app/Exports/Contabilidad/AnexoConsumidoresExport.php`
- `Backend/app/Exports/Contabilidad/AnexoContribuyentesExport.php`
- `Backend/app/Exports/Contabilidad/LibroComprasExport.php`
- `Backend/app/Exports/Contabilidad/AnexoComprasExport.php`

**Archivos Nuevos a Crear:**
- `Backend/app/Services/Contabilidad/LibroIvaFormatoService.php`
- `Backend/app/Constants/ContabilidadConstants.php` (si no existe)

**Prioridad:** 🔴 CRÍTICA

---

### 🔧 3. Libro de Compras

**Ubicación Actual:**
- `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php` (método `compras`)
- `Backend/app/Exports/Contabilidad/LibroComprasExport.php`
- `Backend/app/Exports/Contabilidad/AnexoComprasExport.php`

**Problema Identificado:**
- El formato está diseñado para El Salvador
- Los campos y estructura no coinciden con requerimientos de Honduras
- No diferencia entre crédito fiscal (El Salvador) y crédito ISV (Honduras)

**Modificaciones Requeridas:**

1. **Actualizar método `compras()` en `LibrosIVAController.php`**
   - Agregar lógica condicional por país
   - Ajustar campos según normativa del país

2. **Modificar Exports**
   - Crear variantes de formato por país
   - Ajustar columnas según requerimientos

**Archivos a Modificar:**
- `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php`
- `Backend/app/Exports/Contabilidad/LibroComprasExport.php`
- `Backend/app/Exports/Contabilidad/AnexoComprasExport.php`

**Prioridad:** 🔴 CRÍTICA

---

### 🔧 4. Libro de Sujetos Excluidos

**Ubicación Actual:**
- `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php` (método `comprasSujetosExcluidos`)
- `Backend/app/Exports/Contabilidad/LibroSujetosExcluidosExport.php`
- `Backend/app/Exports/Contabilidad/AnexoSujetosExcluidosExport.php`

**Problema Identificado:**
- El formato actual está específico para El Salvador
- Honduras tiene requisitos diferentes para sujetos excluidos
- Los campos y clasificaciones pueden variar

**Modificaciones Requeridas:**

1. **Investigar normativa específica de Honduras**
2. **Ajustar campos y estructura según país**
3. **Crear lógica condicional en exports**

**Archivos a Modificar:**
- `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php`
- `Backend/app/Exports/Contabilidad/LibroSujetosExcluidosExport.php`
- `Backend/app/Exports/Contabilidad/AnexoSujetosExcluidosExport.php`

**Prioridad:** 🟡 MEDIA

---

### 🔧 5. Retenciones y Percepciones

**Ubicación Actual:**
- `Backend/app/Exports/Contabilidad/LibroRetencion1Export.php`
- `Backend/app/Exports/Contabilidad/LibroPercepcion1Export.php`
- `Backend/app/Exports/Contabilidad/AnexoRetencion1Export.php`
- `Backend/app/Exports/Contabilidad/AnexoPercepcion1Export.php`

**Problema Identificado:**
- Las retenciones y percepciones tienen reglas diferentes en cada país
- El Salvador tiene percepción del 1% que Honduras no aplica
- Las tasas de retención varían entre países

**Modificaciones Requeridas:**

1. **Crear configuración de retenciones por país**
2. **Ajustar cálculos según país**
3. **Modificar formatos de reportes**

**Archivos a Modificar:**
- `Backend/app/Exports/Contabilidad/LibroRetencion1Export.php`
- `Backend/app/Exports/Contabilidad/LibroPercepcion1Export.php`
- `Backend/app/Exports/Contabilidad/AnexoRetencion1Export.php`
- `Backend/app/Exports/Contabilidad/AnexoPercepcion1Export.php`

**Archivos Nuevos:**
- `Backend/app/Services/Contabilidad/RetencionesService.php`

**Prioridad:** 🟡 MEDIA

---

### 🔧 6. Configuración Contable

**Ubicación Actual:**
- `Backend/app/Models/Contabilidad/Configuracion.php`
- `Backend/app/Http/Controllers/Api/Contabilidad/ConfiguracionController.php`

**Problema Identificado:**
- La configuración contable es genérica
- No considera diferencias en cuentas contables por país
- No hay configuración específica de normativas por país

**Modificaciones Requeridas:**

1. **Agregar campo `cod_pais` a configuración contable**
2. **Permitir configuraciones diferentes por país**
3. **Crear plantillas de configuración por país**

**Archivos a Modificar:**
- `Backend/app/Models/Contabilidad/Configuracion.php`
- `Backend/app/Http/Controllers/Api/Contabilidad/ConfiguracionController.php`
- `Backend/database/migrations/` (migración para agregar cod_pais)

**Prioridad:** 🟡 MEDIA

---

### 🔧 7. Facturación Electrónica

**Ubicación Actual:**
- Múltiples archivos relacionados con DTE y facturación electrónica
- Referencias a `sello_mh` (Ministerio de Hacienda - El Salvador)

**Problema Identificado:**
- El sistema está diseñado para facturación electrónica de El Salvador (MH)
- Honduras usa SAR (Servicio de Administración de Rentas)
- Los formatos DTE son diferentes
- Los códigos y validaciones son específicos de cada país

**Modificaciones Requeridas:**

1. **Crear abstracción para facturación electrónica multi-país**
2. **Implementar adaptadores para MH (El Salvador) y SAR (Honduras)**
3. **Ajustar validaciones y formatos según país**

**Archivos a Modificar:**
- Todos los archivos relacionados con facturación electrónica
- Servicios de generación de DTE

**Prioridad:** 🔴 CRÍTICA (si se usa facturación electrónica en Honduras)

---

### 🔧 8. Cálculo de Impuestos en Ventas y Compras

**Ubicación Actual:**
- `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts`
- `Frontend/src/app/views/compras/facturacion/facturacion-compra.component.ts`
- `Backend/app/Services/ImpuestosService.php`

**Problema Identificado:**
- Los cálculos de IVA están hardcodeados con porcentaje de empresa
- No diferencia entre IVA (El Salvador) e ISV (Honduras)
- No considera diferentes reglas de cálculo por país

**Modificaciones Requeridas:**

1. **Actualizar servicios de cálculo de impuestos**
2. **Modificar componentes frontend para usar servicio actualizado**
3. **Agregar validaciones según país**

**Archivos a Modificar:**
- `Backend/app/Services/ImpuestosService.php`
- `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts`
- `Frontend/src/app/views/compras/facturacion/facturacion-compra.component.ts`

**Prioridad:** 🔴 CRÍTICA

---

## Módulos que Requieren Creación

### 🆕 1. Libro de Actas de Asamblea (Honduras)

**Descripción:**
Honduras requiere llevar un Libro de Actas de Asamblea para empresas que tienen estructura societaria.

**Funcionalidades Requeridas:**

1. **Registro de Actas**
   - Fecha de la asamblea
   - Tipo de asamblea (Ordinaria, Extraordinaria)
   - Asistentes y sus participaciones
   - Acuerdos tomados
   - Firmas de aprobación

2. **Reportes**
   - Listado de actas
   - Exportación a PDF
   - Exportación a Excel

**Archivos a Crear:**
- `Backend/app/Models/Contabilidad/LibroActasAsamblea.php`
- `Backend/app/Http/Controllers/Api/Contabilidad/LibroActasAsambleaController.php`
- `Backend/app/Exports/Contabilidad/LibroActasAsambleaExport.php`
- `Backend/database/migrations/` (migración para tabla)
- `Frontend/src/app/views/contabilidad/libro-actas-asamblea/` (componentes Angular)

**Prioridad:** 🟡 MEDIA (solo para Honduras)

---

### 🆕 2. Libro de Registro de Accionistas (Honduras)

**Descripción:**
Honduras requiere llevar un Libro de Registro de Accionistas para empresas con estructura societaria.

**Funcionalidades Requeridas:**

1. **Registro de Accionistas**
   - Información del accionista
   - Número de acciones
   - Porcentaje de participación
   - Fecha de registro
   - Transferencias de acciones

2. **Reportes**
   - Listado de accionistas
   - Historial de transferencias
   - Exportación a PDF/Excel

**Archivos a Crear:**
- `Backend/app/Models/Contabilidad/LibroRegistroAccionistas.php`
- `Backend/app/Http/Controllers/Api/Contabilidad/LibroRegistroAccionistasController.php`
- `Backend/app/Exports/Contabilidad/LibroRegistroAccionistasExport.php`
- `Backend/database/migrations/` (migración para tabla)
- `Frontend/src/app/views/contabilidad/libro-registro-accionistas/` (componentes Angular)

**Prioridad:** 🟡 MEDIA (solo para Honduras)

---

### 🆕 3. Configuración de Normativas por País

**Descripción:**
Sistema centralizado para gestionar configuraciones específicas de normativas contables y fiscales por país.

**Funcionalidades Requeridas:**

1. **Gestión de Configuraciones**
   - Tasas de impuestos por país
   - Formatos de reportes por país
   - Campos requeridos por país
   - Validaciones específicas por país

2. **API de Configuración**
   - Endpoints para obtener configuraciones
   - Endpoints para actualizar configuraciones
   - Validación de configuraciones

**Archivos a Crear:**
- `Backend/app/Models/Contabilidad/NormativaPais.php`
- `Backend/app/Http/Controllers/Api/Contabilidad/NormativaPaisController.php`
- `Backend/app/Services/Contabilidad/NormativaPaisService.php`
- `Backend/database/migrations/` (migración para tabla)
- `Backend/database/seeders/NormativasPaisSeeder.php` (datos iniciales)

**Prioridad:** 🔴 CRÍTICA

---

## Reportes que Requieren Actualización

### 📊 1. Libro de Consumidores Finales

**Ubicación:**
- `Backend/app/Exports/Contabilidad/LibroConsumidoresExport.php`
- `Backend/app/Exports/Contabilidad/AnexoConsumidoresExport.php`
- `Backend/resources/views/reportes/contabilidad/libro-consumidores.blade.php`

**Cambios Requeridos:**

1. **Diferenciar nomenclatura**
   - El Salvador: "Libro de Ventas a Consumidores Finales"
   - Honduras: "Libro de Ventas a Consumidores Finales" (ISV)

2. **Ajustar campos del anexo**
   - El Salvador: Campos específicos MH
   - Honduras: Campos específicos SAR

3. **Formato CSV**
   - Delimitadores y estructura pueden variar
   - Codificación de caracteres

**Prioridad:** 🔴 CRÍTICA

---

### 📊 2. Libro de Contribuyentes

**Ubicación:**
- `Backend/app/Exports/Contabilidad/LibroContribuyentesExport.php`
- `Backend/app/Exports/Contabilidad/AnexoContribuyentesExport.php`
- `Backend/resources/views/reportes/contabilidad/libro-contribuyentes.blade.php`

**Cambios Requeridos:**

1. **Ajustar campos según país**
2. **Modificar formato de anexo**
3. **Actualizar validaciones**

**Prioridad:** 🔴 CRÍTICA

---

### 📊 3. Libro de Compras

**Ubicación:**
- `Backend/app/Exports/Contabilidad/LibroComprasExport.php`
- `Backend/app/Exports/Contabilidad/AnexoComprasExport.php`
- `Backend/resources/views/reportes/contabilidad/libro-compras.blade.php`

**Cambios Requeridos:**

1. **Ajustar estructura según país**
2. **Modificar campos de crédito fiscal/ISV**
3. **Actualizar formato de anexo**

**Prioridad:** 🔴 CRÍTICA

---

### 📊 4. Libro de Anulados

**Ubicación:**
- `Backend/app/Exports/Contabilidad/LibroAnuladosExport.php`
- `Backend/app/Exports/Contabilidad/AnexoAnuladosExport.php`

**Cambios Requeridos:**

1. **Ajustar formato según país**
2. **Modificar campos requeridos**

**Prioridad:** 🟡 MEDIA

---

### 📊 5. Balance de Comprobación

**Ubicación:**
- `Backend/app/Exports/Contabilidad/BalanceComprobacionExport.php`
- `Backend/resources/views/reportes/contabilidad/balance_comprobacion.blade.php`

**Cambios Requeridos:**

1. **Ajustar formato según normativa del país**
2. **Verificar requerimientos específicos**

**Prioridad:** 🟢 BAJA

---

### 📊 6. Balance General

**Ubicación:**
- `Backend/app/Exports/Contabilidad/BalanceGeneralExport.php`
- `Backend/resources/views/reportes/contabilidad/balance_general.blade.php`

**Cambios Requeridos:**

1. **Verificar cumplimiento con NIIF para PYMES**
2. **Ajustar formato si hay diferencias**

**Prioridad:** 🟢 BAJA

---

### 📊 7. Estado de Resultados

**Ubicación:**
- `Backend/app/Exports/Contabilidad/EstadoResultadosExport.php`
- `Backend/resources/views/reportes/contabilidad/estado_resultados.blade.php`

**Cambios Requeridos:**

1. **Verificar cumplimiento con NIIF para PYMES**
2. **Ajustar formato si hay diferencias**

**Prioridad:** 🟢 BAJA

---

### 📊 8. Libro Diario

**Ubicación:**
- `Backend/app/Exports/Contabilidad/DiarioAuxiliarExport.php`
- `Backend/resources/views/reportes/contabilidad/libro_diario.blade.php`

**Cambios Requeridos:**

1. **Verificar formato según país**
2. **Ajustar si hay diferencias**

**Prioridad:** 🟢 BAJA

---

### 📊 9. Libro Mayor

**Ubicación:**
- `Backend/app/Exports/Contabilidad/DiarioMayorExport.php`
- `Backend/resources/views/reportes/contabilidad/libro_mayor.blade.php`

**Cambios Requeridos:**

1. **Verificar formato según país**
2. **Ajustar si hay diferencias**

**Prioridad:** 🟢 BAJA

---

### 📊 10. Movimiento de Cuenta

**Ubicación:**
- `Backend/resources/views/reportes/contabilidad/movimiento_cuenta.blade.php`

**Cambios Requeridos:**

1. **Verificar formato según país**
2. **Ajustar si hay diferencias**

**Prioridad:** 🟢 BAJA

---

### 📊 11. Retenciones

**Ubicación:**
- `Backend/app/Exports/Contabilidad/LibroRetencion1Export.php`
- `Backend/app/Exports/Contabilidad/AnexoRetencion1Export.php`

**Cambios Requeridos:**

1. **Ajustar tasas y conceptos según país**
2. **Modificar formato de reporte**

**Prioridad:** 🟡 MEDIA

---

### 📊 12. Percepciones

**Ubicación:**
- `Backend/app/Exports/Contabilidad/LibroPercepcion1Export.php`
- `Backend/app/Exports/Contabilidad/AnexoPercepcion1Export.php`

**Cambios Requeridos:**

1. **El Salvador: Mantener percepción 1%**
2. **Honduras: Verificar si aplica**
3. **Ajustar formato según país**

**Prioridad:** 🟡 MEDIA

---

## Plan de Implementación Recomendado

### Fase 1: Infraestructura Base (2 semanas)

**Objetivo:** Establecer la base para soporte multi-país

1. **Semana 1:**
   - ✅ Crear tabla `configuracion_impuestos_pais`
   - ✅ Crear tabla `normativas_pais`
   - ✅ Crear migraciones y seeders
   - ✅ Crear modelos y relaciones

2. **Semana 2:**
   - ✅ Crear `NormativaPaisService`
   - ✅ Crear `LibroIvaFormatoService`
   - ✅ Actualizar `ImpuestosService` para multi-país
   - ✅ Crear constantes por país

**Entregables:**
- Base de datos actualizada
- Servicios base implementados
- Configuraciones iniciales cargadas

---

### Fase 2: Módulos Críticos (3 semanas)

**Objetivo:** Implementar cambios en módulos críticos

1. **Semana 3:**
   - ✅ Actualizar cálculo de impuestos (IVA/ISV)
   - ✅ Modificar componentes de facturación
   - ✅ Actualizar servicios de impuestos

2. **Semana 4:**
   - ✅ Modificar Libro de Consumidores
   - ✅ Modificar Libro de Contribuyentes
   - ✅ Actualizar anexos CSV

3. **Semana 5:**
   - ✅ Modificar Libro de Compras
   - ✅ Actualizar formatos de anexos
   - ✅ Implementar lógica condicional por país

**Entregables:**
- Módulos críticos funcionando para ambos países
- Formatos de reportes ajustados

---

### Fase 3: Reportes y Anexos (2 semanas)

**Objetivo:** Completar actualización de reportes

1. **Semana 6:**
   - ✅ Actualizar todos los exports de libros IVA/ISV
   - ✅ Ajustar formatos CSV según país
   - ✅ Actualizar vistas PDF

2. **Semana 7:**
   - ✅ Actualizar reportes de retenciones y percepciones
   - ✅ Verificar y ajustar reportes contables básicos
   - ✅ Pruebas de formatos

**Entregables:**
- Todos los reportes actualizados
- Formatos validados

---

### Fase 4: Módulos Adicionales (2 semanas)

**Objetivo:** Implementar módulos específicos de Honduras

1. **Semana 8:**
   - ✅ Crear Libro de Actas de Asamblea
   - ✅ Crear Libro de Registro de Accionistas
   - ✅ Implementar funcionalidades básicas

2. **Semana 9:**
   - ✅ Completar reportes de nuevos módulos
   - ✅ Integrar con sistema existente
   - ✅ Pruebas completas

**Entregables:**
- Módulos específicos de Honduras implementados
- Sistema completo funcional

---

### Fase 5: Pruebas y Ajustes (1-2 semanas)

**Objetivo:** Validar funcionamiento completo

1. **Semana 10-11:**
   - ✅ Pruebas unitarias
   - ✅ Pruebas de integración
   - ✅ Pruebas con datos reales
   - ✅ Ajustes y correcciones

**Entregables:**
- Sistema probado y validado
- Documentación actualizada

---

## Consideraciones Técnicas

### 1. Estructura de Datos

**Recomendación:** Usar el campo `cod_pais` existente en la tabla `empresas` como referencia principal.

```php
// Ejemplo de uso
$empresa = Empresa::find($id);
$codPais = $empresa->cod_pais; // 'SV' o 'HN'

// Obtener configuración según país
$configuracion = NormativaPaisService::obtenerConfiguracion($codPais);
```

### 2. Patrón de Diseño

**Recomendación:** Usar Strategy Pattern para manejar diferentes formatos por país.

```php
// Ejemplo
interface LibroIvaFormatoInterface {
    public function generarAnexo($datos);
    public function generarLibro($datos);
}

class LibroIvaFormatoElSalvador implements LibroIvaFormatoInterface {
    // Implementación específica El Salvador
}

class LibroIvaFormatoHonduras implements LibroIvaFormatoInterface {
    // Implementación específica Honduras
}
```

### 3. Configuración

**Recomendación:** Usar archivos de configuración o base de datos para almacenar diferencias por país.

```php
// config/contabilidad.php
return [
    'SV' => [
        'iva_porcentaje' => 13,
        'isr_porcentaje' => 30,
        'percepcion_porcentaje' => 1,
        'retencion_porcentaje' => 1,
        'formato_anexo' => 'el_salvador',
    ],
    'HN' => [
        'isv_porcentaje' => 15,
        'isr_porcentaje' => 25,
        'percepcion_porcentaje' => 0,
        'retencion_porcentaje' => 0,
        'formato_anexo' => 'honduras',
    ],
];
```

### 4. Validaciones

**Recomendación:** Implementar validaciones específicas por país.

```php
// Ejemplo
class ValidacionFiscalService {
    public function validarDocumento($documento, $codPais) {
        if ($codPais === 'SV') {
            return $this->validarDocumentoSV($documento);
        } elseif ($codPais === 'HN') {
            return $this->validarDocumentoHN($documento);
        }
    }
}
```

### 5. Testing

**Recomendación:** Crear tests específicos por país.

```php
// Ejemplo
class LibroIvaTest extends TestCase {
    public function test_libro_consumidores_el_salvador() {
        // Test específico El Salvador
    }
    
    public function test_libro_consumidores_honduras() {
        // Test específico Honduras
    }
}
```

---

## Checklist de Implementación

### Infraestructura
- [ ] Crear tablas de configuración por país
- [ ] Crear modelos y relaciones
- [ ] Crear servicios base
- [ ] Crear seeders con datos iniciales

### Módulos Críticos
- [ ] Actualizar cálculo de impuestos
- [ ] Modificar libros de IVA/ISV
- [ ] Actualizar anexos CSV
- [ ] Modificar libro de compras

### Reportes
- [ ] Actualizar todos los exports
- [ ] Ajustar formatos PDF
- [ ] Validar formatos CSV
- [ ] Verificar cumplimiento normativo

### Módulos Nuevos
- [ ] Libro de Actas de Asamblea
- [ ] Libro de Registro de Accionistas
- [ ] Configuración de normativas

### Pruebas
- [ ] Pruebas unitarias
- [ ] Pruebas de integración
- [ ] Pruebas con datos reales
- [ ] Validación con autoridades fiscales

### Documentación
- [ ] Actualizar documentación técnica
- [ ] Crear guías de usuario por país
- [ ] Documentar configuraciones
- [ ] Crear manual de migración

---

## Riesgos y Mitigaciones

### Riesgo 1: Cambios en Normativas
**Probabilidad:** Media  
**Impacto:** Alto  
**Mitigación:** 
- Diseñar sistema flexible
- Mantener configuración en base de datos
- Crear proceso de actualización de normativas

### Riesgo 2: Complejidad de Implementación
**Probabilidad:** Alta  
**Impacto:** Medio  
**Mitigación:**
- Implementar por fases
- Priorizar módulos críticos
- Realizar pruebas continuas

### Riesgo 3: Validación con Autoridades Fiscales
**Probabilidad:** Media  
**Impacto:** Alto  
**Mitigación:**
- Consultar con contadores locales
- Validar formatos antes de producción
- Mantener comunicación con autoridades

---

## Conclusiones

El sistema Smartpyme requiere adaptaciones significativas para funcionar correctamente tanto en El Salvador como en Honduras. Las principales áreas de trabajo son:

1. **Configuración de impuestos por país** (CRÍTICO)
2. **Formatos de libros IVA/ISV** (CRÍTICO)
3. **Anexos y reportes** (CRÍTICO)
4. **Módulos específicos de Honduras** (MEDIO)
5. **Facturación electrónica** (si aplica)

Con una implementación planificada y por fases, es posible lograr un sistema robusto y multi-país que cumpla con las normativas de ambos países.

---

## Referencias y Recursos

### Normativas
- El Salvador: Ministerio de Hacienda (MH)
- Honduras: Servicio de Administración de Rentas (SAR)

### Documentación Técnica
- NIIF para PYMES
- Formatos de anexos oficiales
- Guías de facturación electrónica

### Contactos Recomendados
- Contadores públicos certificados en ambos países
- Representantes de autoridades fiscales
- Consultores especializados en normativas fiscales

---

**Documento preparado por:** Sistema de Auditoría Smartpyme  
**Última actualización:** Enero 2025  
**Versión:** 1.0

