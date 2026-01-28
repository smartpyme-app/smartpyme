# Estado del Plan de Facturación Electrónica Multi-País

## EPIC 1: Refactorización El Salvador — **COMPLETADO** ✅

### Hecho

| Story / Tarea | Estado |
|---------------|--------|
| **1.1** Estructura base (interface, factory, service, config) | ✅ Completado |
| **1.2** Modelos migrados a `ElSalvador*` (Factura, CCF, NotaCredito, NotaDebito, FacturaExportacion, sujeto excluido, anulación) | ✅ Completado |
| **1.3** Migración BD, campos `fe_*` en `empresas`, modelo `Empresa` | ✅ Completado |
| **1.4** `FacturacionElectronicaController`, rutas `/fe/`, eliminación de legacy | ✅ Completado |
| **1.5** Frontend: `FacturacionElectronicaService`, componentes usando FE directamente, `MHService` eliminado | ✅ Completado |
| Funcionalidades restantes: DTE anulado, contingencia, ticket | ✅ Migradas |
| Reportes PDF/JSON en ventas | ✅ Corregidos (rutas `/fe/reporte/`) |

---

## Pendiente o por cerrar (EPIC 1)

### 1. Pruebas masivas (MHPruebasMasivasService)

- **Estado:** El servicio sigue usando lógica antigua; varios métodos lanzan excepciones porque los modelos `MH*` fueron eliminados.
- **Rutas actuales:** `mh/pruebas-masivas/estadisticas`, `mh/pruebas-masivas/ejecutar`, etc. existen y el frontend (empresa) las llama.
- **Acción:** Refactorizar `MHPruebasMasivasService` para usar `FacturacionElectronicaService` en lugar de los modelos eliminados, o documentar que las pruebas masivas están deshabilitadas hasta esa refactorización.

### 2. Tests (Story 1.6)

- Tests unitarios para `FacturacionElectronicaFactory`.
- Tests unitarios para implementaciones El Salvador (opcional).
- Tests de integración / E2E (opcional).
- Checklist manual de pruebas: existe `CHECKLIST_PRUEBAS_FACTURACION_ELECTRONICA.md`; falta ejecutarlo y validar.

### 3. Documentación (Story 1.7)

- Documentar arquitectura en algo como `Backend/docs/FACTURACION_ELECTRONICA_ARCHITECTURE.md`.
- Actualizar README y documentación de API si aplica.
- Código obsoleto ya fue eliminado (no queda “deprecated” por marcar).

---

## EPIC 2: Implementación Costa Rica — **PENDIENTE** 🔜

**Depende de:** EPIC 1 completado ✅

### Resumen de tareas

| Story | Descripción |
|-------|-------------|
| **2.1** | Investigación: documentación Hacienda CR, credenciales de prueba, estructura XML/JSON |
| **2.2** | Clase base `CostaRicaFE`, autenticación, config, firma |
| **2.3** | Implementaciones: Factura, NotaCrédito, NotaDébito, Anulación, envío a API |
| **2.4** | Modelos/seeders: Provincia, Cantón, Distrito, actividad económica CR |
| **2.5** | Factory y servicio principal para CR |
| **2.6** | Formularios empresa/cliente y componentes de facturación para CR |
| **2.7** | Tests y validación |
| **2.8** | Documentación y preparación para producción |

---

## Resumen ejecutivo

- **Fase 1 (El Salvador):** Funcionalmente terminada: arquitectura multi-país, migración de modelos y rutas, frontend usando solo el nuevo servicio, reportes PDF/JSON operativos.
- **Siguiente paso recomendado (dentro de Fase 1):**  
  1) Refactorizar `MHPruebasMasivasService` para usar `FacturacionElectronicaService`, **o**  
  2) Ejecutar el checklist manual y cerrar Story 1.6/1.7 (tests + documentación).
- **Fase 2:** Iniciar EPIC 2 (Costa Rica) cuando se decida priorizar el segundo país.
