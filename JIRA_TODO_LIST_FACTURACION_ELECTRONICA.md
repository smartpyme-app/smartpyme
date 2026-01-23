# 📋 Todo List para Jira - Facturación Electrónica Multi-País

## 🎯 EPIC 1: Refactorización Base - El Salvador
**Descripción:** Refactorizar la implementación actual de facturación electrónica de El Salvador para soportar arquitectura multi-país.

**Estimación Total:** 2-3 semanas  
**Prioridad:** Alta  
**Sprint:** 1-2

---

### 📦 STORY 1.1: Crear Estructura Base de Arquitectura Multi-País

**Descripción:** Crear la estructura base (interfaces, contratos, factory) que permitirá soportar múltiples países.

**Estimación:** 5 puntos  
**Prioridad:** Crítica  
**Dependencias:** Ninguna

#### Tareas:

- [ ] **TASK 1.1.1:** Crear interface `FacturacionElectronicaInterface`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Contracts/FacturacionElectronicaInterface.php`
  - **Criterios de Aceptación:**
    - Interface define métodos: `generarDTE()`, `firmarDTE()`, `enviarDTE()`, `anularDTE()`, `consultarDTE()`, `obtenerConfiguracion()`
    - Métodos tienen documentación PHPDoc completa
    - Interface está en namespace correcto
  - **Estimación:** 2 puntos

- [ ] **TASK 1.1.2:** Crear Factory Pattern `FacturacionElectronicaFactory`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Factories/FacturacionElectronicaFactory.php`
  - **Criterios de Aceptación:**
    - Factory puede crear instancias según código de país (SV, CR)
    - Factory puede crear instancias según tipo de documento
    - Lanza excepciones claras para países no soportados
    - Tiene tests unitarios
  - **Estimación:** 3 puntos

- [ ] **TASK 1.1.3:** Crear servicio principal `FacturacionElectronicaService`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/FacturacionElectronicaService.php`
  - **Criterios de Aceptación:**
    - Servicio usa Factory para obtener implementación correcta
    - Servicio maneja errores apropiadamente
    - Servicio tiene logging de operaciones
    - Tiene tests unitarios
  - **Estimación:** 3 puntos

- [ ] **TASK 1.1.4:** Crear archivo de configuración por país
  - **Archivo:** `Backend/config/facturacion_electronica.php`
  - **Criterios de Aceptación:**
    - Configuración incluye URLs para El Salvador (prueba y producción)
    - Configuración incluye formato de documento (JSON/XML)
    - Configuración es fácilmente extensible para nuevos países
    - Documentación de cómo agregar nuevos países
  - **Estimación:** 2 puntos

---

### 📦 STORY 1.2: Migrar Modelos de El Salvador a Nueva Estructura

**Descripción:** Mover y adaptar los modelos actuales de MH a la nueva estructura de implementación por país.

**Estimación:** 8 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 1.1

#### Tareas:

- [ ] **TASK 1.2.1:** Crear directorio de implementación El Salvador
  - **Estructura:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/`
  - **Criterios de Aceptación:**
    - Directorio creado con estructura correcta
    - Namespace actualizado
  - **Estimación:** 1 punto

- [ ] **TASK 1.2.2:** Mover y adaptar `MHFactura` a `ElSalvadorFactura`
  - **Archivo Origen:** `Backend/app/Models/MH/MHFactura.php`
  - **Archivo Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorFactura.php`
  - **Criterios de Aceptación:**
    - Clase implementa `FacturacionElectronicaInterface`
    - Método `generarDTE()` funciona igual que antes
    - Usa configuración desde archivo de config
    - Mantiene compatibilidad con código existente
    - Tests pasan
  - **Estimación:** 5 puntos

- [ ] **TASK 1.2.3:** Mover y adaptar `MHCCF` a `ElSalvadorCCF`
  - **Archivo Origen:** `Backend/app/Models/MH/MHCCF.php`
  - **Archivo Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorCCF.php`
  - **Criterios de Aceptación:**
    - Clase implementa `FacturacionElectronicaInterface`
    - Funcionalidad idéntica a la original
    - Tests pasan
  - **Estimación:** 5 puntos

- [ ] **TASK 1.2.4:** Mover y adaptar `MHNotaCredito` a `ElSalvadorNotaCredito`
  - **Archivo Origen:** `Backend/app/Models/MH/MHNotaCredito.php`
  - **Archivo Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorNotaCredito.php`
  - **Criterios de Aceptación:**
    - Clase implementa interface
    - Funcionalidad preservada
    - Tests pasan
  - **Estimación:** 3 puntos

- [ ] **TASK 1.2.5:** Mover y adaptar `MHNotaDebito` a `ElSalvadorNotaDebito`
  - **Archivo Origen:** `Backend/app/Models/MH/MHNotaDebito.php`
  - **Archivo Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorNotaDebito.php`
  - **Criterios de Aceptación:**
    - Clase implementa interface
    - Funcionalidad preservada
    - Tests pasan
  - **Estimación:** 3 puntos

- [ ] **TASK 1.2.6:** Mover y adaptar `MHFacturaExportacion` a `ElSalvadorFacturaExportacion`
  - **Archivo Origen:** `Backend/app/Models/MH/MHFacturaExportacion.php`
  - **Archivo Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorFacturaExportacion.php`
  - **Criterios de Aceptación:**
    - Clase implementa interface
    - Funcionalidad preservada
    - Tests pasan
  - **Estimación:** 3 puntos

- [ ] **TASK 1.2.7:** Mover y adaptar `MHAnulacion` a `ElSalvadorAnulacion`
  - **Archivo Origen:** `Backend/app/Models/MH/MHAnulacion.php`
  - **Archivo Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorAnulacion.php`
  - **Criterios de Aceptación:**
    - Clase implementa interface
    - Funcionalidad preservada
    - Tests pasan
  - **Estimación:** 3 puntos

- [ ] **TASK 1.2.8:** Mover y adaptar `MHSujetoExcluidoCompra` y `MHSujetoExcluidoGasto`
  - **Archivos Origen:** `Backend/app/Models/MH/MHSujetoExcluido*.php`
  - **Archivos Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorSujetoExcluido*.php`
  - **Criterios de Aceptación:**
    - Clases implementan interface
    - Funcionalidad preservada
    - Tests pasan
  - **Estimación:** 5 puntos

- [ ] **TASK 1.2.9:** Mover clase base `MH` a `ElSalvadorFE`
  - **Archivo Origen:** `Backend/app/Models/MH/MH.php`
  - **Archivo Destino:** `Backend/app/Services/FacturacionElectronica/Implementations/ElSalvador/ElSalvadorFE.php`
  - **Criterios de Aceptación:**
    - Clase base refactorizada
    - Métodos comunes extraídos
    - Usa configuración desde archivo de config
    - Tests pasan
  - **Estimación:** 5 puntos

---

### 📦 STORY 1.3: Actualizar Base de Datos y Modelo Empresa

**Descripción:** Agregar nuevos campos a la tabla empresas y migrar datos existentes.

**Estimación:** 5 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 1.1

#### Tareas:

- [ ] **TASK 1.3.1:** Crear migración para nuevos campos en tabla `empresas`
  - **Archivo:** `Backend/database/migrations/YYYY_MM_DD_HHMMSS_add_fe_multi_pais_fields_to_empresas_table.php`
  - **Criterios de Aceptación:**
    - Agrega campos: `fe_pais`, `fe_usuario`, `fe_contrasena`, `fe_certificado_password`, `fe_certificado_path`, `fe_token`, `fe_token_expires_at`
    - Campos son nullable
    - Campos tienen índices apropiados
    - Migración es reversible (rollback)
  - **Estimación:** 3 puntos

- [ ] **TASK 1.3.2:** Crear migración de datos existentes
  - **Archivo:** Misma migración o migración separada
  - **Criterios de Aceptación:**
    - Migra `mh_usuario` → `fe_usuario`
    - Migra `mh_contrasena` → `fe_contrasena`
    - Migra `mh_pwd_certificado` → `fe_certificado_password`
    - Establece `fe_pais = 'SV'` para empresas con FE habilitada
    - Script es idempotente (puede ejecutarse múltiples veces)
    - Verificación de datos migrados
  - **Estimación:** 3 puntos

- [ ] **TASK 1.3.3:** Actualizar modelo `Empresa` con nuevos campos
  - **Archivo:** `Backend/app/Models/Admin/Empresa.php`
  - **Criterios de Aceptación:**
    - Nuevos campos en `$fillable`
    - Casts apropiados para nuevos campos
    - Métodos helper para obtener configuración FE
    - Documentación PHPDoc actualizada
  - **Estimación:** 2 puntos

- [ ] **TASK 1.3.4:** Crear tabla `fe_configuracion_pais` (opcional, puede estar en config)
  - **Archivo:** `Backend/database/migrations/YYYY_MM_DD_HHMMSS_create_fe_configuracion_pais_table.php`
  - **Criterios de Aceptación:**
    - Tabla con campos: `cod_pais`, `nombre`, `url_prueba`, `url_produccion`, `formato_documento`, `configuracion` (JSON)
    - Modelo creado
    - Seeder con datos de El Salvador
  - **Estimación:** 3 puntos

---

### 📦 STORY 1.4: Actualizar Controladores y Rutas

**Descripción:** Refactorizar controladores para usar el nuevo servicio de facturación electrónica.

**Estimación:** 5 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 1.2, STORY 1.3

#### Tareas:

- [ ] **TASK 1.4.1:** Refactorizar `MHDTEController` a `FacturacionElectronicaController`
  - **Archivo Origen:** `Backend/app/Http/Controllers/Api/Admin/MHDTEController.php`
  - **Archivo Destino:** `Backend/app/Http/Controllers/Api/Admin/FacturacionElectronicaController.php`
  - **Criterios de Aceptación:**
    - Controlador usa `FacturacionElectronicaService`
    - Métodos mantienen misma funcionalidad
    - Validaciones mejoradas (país, configuración)
    - Manejo de errores mejorado
    - Tests de integración pasan
  - **Estimación:** 8 puntos

- [ ] **TASK 1.4.2:** Actualizar rutas para usar nuevo controlador
  - **Archivo:** `Backend/routes/api.php` o archivo de rutas específico
  - **Criterios de Aceptación:**
    - Rutas apuntan a nuevo controlador
    - Rutas antiguas redirigen o mantienen compatibilidad
    - Documentación de rutas actualizada
  - **Estimación:** 2 puntos

- [ ] **TASK 1.4.3:** Mantener compatibilidad con rutas antiguas (deprecadas)
  - **Criterios de Aceptación:**
    - Rutas antiguas funcionan pero logean deprecación
    - Documentación indica rutas deprecadas
    - Plan de eliminación de rutas antiguas
  - **Estimación:** 2 puntos

---

### 📦 STORY 1.5: Actualizar Frontend - Servicio Genérico

**Descripción:** Refactorizar servicio frontend para soportar múltiples países.

**Estimación:** 8 puntos  
**Prioridad:** Alta  
**Dependencias:** STORY 1.4

#### Tareas:

- [ ] **TASK 1.5.1:** Crear nuevo servicio `FacturacionElectronicaService` en frontend
  - **Archivo:** `Frontend/src/app/services/facturacion-electronica.service.ts`
  - **Criterios de Aceptación:**
    - Servicio genérico que detecta país de empresa
    - Métodos principales: `emitirDTE()`, `firmarDTE()`, `enviarDTE()`, `anularDTE()`
    - Manejo de errores por país
    - Compatible con código existente
  - **Estimación:** 5 puntos

- [ ] **TASK 1.5.2:** Actualizar componentes de facturación para usar nuevo servicio
  - **Archivos:**
    - `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts`
    - `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.ts`
  - **Criterios de Aceptación:**
    - Componentes usan nuevo servicio
    - Funcionalidad idéntica a la anterior
    - Sin errores en consola
    - Tests pasan
  - **Estimación:** 5 puntos

- [ ] **TASK 1.5.3:** Mantener `MHService` como deprecated (compatibilidad)
  - **Archivo:** `Frontend/src/app/services/MH.service.ts`
  - **Criterios de Aceptación:**
    - Servicio marca métodos como deprecated
    - Servicio redirige a nuevo servicio
    - Logging de uso de métodos deprecated
    - Plan de eliminación
  - **Estimación:** 2 puntos

- [ ] **TASK 1.5.4:** Actualizar formularios de configuración de empresa
  - **Archivo:** `Frontend/src/app/views/admin/empresa/empresa.component.ts`
  - **Criterios de Aceptación:**
    - Formulario muestra campos genéricos (`fe_usuario` en lugar de `mh_usuario`)
    - Mantiene campos antiguos para compatibilidad
    - Validaciones actualizadas
    - UI/UX mejorada
  - **Estimación:** 3 puntos

---

### 📦 STORY 1.6: Testing y Validación - El Salvador

**Descripción:** Asegurar que toda la funcionalidad de El Salvador sigue funcionando después de la refactorización.

**Estimación:** 8 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 1.5

#### Tareas:

- [ ] **TASK 1.6.1:** Crear tests unitarios para Factory
  - **Archivo:** `Backend/tests/Unit/Services/FacturacionElectronica/FacturacionElectronicaFactoryTest.php`
  - **Criterios de Aceptación:**
    - Tests para creación de instancias por país
    - Tests para países no soportados
    - Tests para tipos de documento
    - Cobertura > 90%
  - **Estimación:** 3 puntos

- [ ] **TASK 1.6.2:** Crear tests unitarios para implementación El Salvador
  - **Archivos:** Tests para cada clase de documento
  - **Criterios de Aceptación:**
    - Tests para `ElSalvadorFactura`
    - Tests para `ElSalvadorCCF`
    - Tests para `ElSalvadorNotaCredito`
    - Tests para `ElSalvadorNotaDebito`
    - Cobertura > 85%
  - **Estimación:** 5 puntos

- [ ] **TASK 1.6.3:** Tests de integración end-to-end
  - **Archivo:** `Backend/tests/Feature/FacturacionElectronica/ElSalvadorTest.php`
  - **Criterios de Aceptación:**
    - Test completo de emisión de factura
    - Test completo de emisión de CCF
    - Test de anulación
    - Test de consulta
    - Tests en ambiente de prueba
  - **Estimación:** 5 puntos

- [ ] **TASK 1.6.4:** Testing manual en ambiente de desarrollo
  - **Criterios de Aceptación:**
    - Emitir factura en ambiente prueba
    - Emitir CCF en ambiente prueba
    - Emitir nota de crédito
    - Anular documento
    - Verificar PDF generado
    - Verificar envío por correo
    - Checklist completo ejecutado
  - **Estimación:** 5 puntos

- [ ] **TASK 1.6.5:** Validar compatibilidad con datos existentes
  - **Criterios de Aceptación:**
    - Empresas existentes pueden emitir documentos
    - Datos migrados correctamente
    - Sin pérdida de funcionalidad
    - Documentación de cambios
  - **Estimación:** 3 puntos

---

### 📦 STORY 1.7: Documentación y Limpieza

**Descripción:** Documentar cambios y limpiar código obsoleto.

**Estimación:** 3 puntos  
**Prioridad:** Media  
**Dependencias:** STORY 1.6

#### Tareas:

- [ ] **TASK 1.7.1:** Documentar nueva arquitectura
  - **Archivo:** `Backend/docs/FACTURACION_ELECTRONICA_ARCHITECTURE.md`
  - **Criterios de Aceptación:**
    - Diagrama de arquitectura
    - Flujo de datos
    - Cómo agregar nuevo país
    - Ejemplos de código
  - **Estimación:** 3 puntos

- [ ] **TASK 1.7.2:** Actualizar README y documentación de API
  - **Criterios de Aceptación:**
    - README actualizado
    - Documentación de endpoints actualizada
    - Changelog creado
  - **Estimación:** 2 puntos

- [ ] **TASK 1.7.3:** Marcar código obsoleto como deprecated
  - **Criterios de Aceptación:**
    - Clases antiguas marcadas con `@deprecated`
    - Comentarios explicando migración
    - Fecha de eliminación planificada
  - **Estimación:** 1 punto

---

## 🎯 EPIC 2: Implementación Costa Rica
**Descripción:** Implementar facturación electrónica para Costa Rica siguiendo la nueva arquitectura multi-país.

**Estimación Total:** 3-4 semanas  
**Prioridad:** Alta  
**Sprint:** 3-5

---

### 📦 STORY 2.1: Investigación y Documentación - Costa Rica

**Descripción:** Investigar y documentar los requisitos de la API de Hacienda de Costa Rica.

**Estimación:** 5 puntos  
**Prioridad:** Crítica  
**Dependencias:** EPIC 1 completado

#### Tareas:

- [ ] **TASK 2.1.1:** Investigar documentación oficial de Hacienda Costa Rica
  - **Criterios de Aceptación:**
    - Documentación de API obtenida
    - Endpoints identificados
    - Proceso de autenticación documentado
    - Formatos de documento identificados (XML/JSON)
    - Tipos de documentos disponibles
    - Documento de investigación creado
  - **Estimación:** 5 puntos

- [ ] **TASK 2.1.2:** Obtener credenciales de prueba
  - **Criterios de Aceptación:**
    - Credenciales de ambiente de prueba obtenidas
    - Certificado digital de prueba (si aplica)
    - Acceso a portal de pruebas verificado
    - Documentación de acceso guardada
  - **Estimación:** 3 puntos

- [ ] **TASK 2.1.3:** Documentar estructura XML/JSON requerida
  - **Criterios de Aceptación:**
    - Estructura de factura documentada
    - Estructura de nota de crédito documentada
    - Estructura de nota de débito documentada
    - Ejemplos de documentos válidos
    - Validaciones requeridas documentadas
  - **Estimación:** 5 puntos

- [ ] **TASK 2.1.4:** Documentar catálogos y códigos de Costa Rica
  - **Criterios de Aceptación:**
    - Provincias documentadas
    - Cantones documentados
    - Distritos documentados
    - Actividades económicas documentadas
    - Códigos de tipos de documento
    - Códigos de métodos de pago
  - **Estimación:** 3 puntos

---

### 📦 STORY 2.2: Implementación Backend - Clase Base Costa Rica

**Descripción:** Crear la clase base y estructura para implementación de Costa Rica.

**Estimación:** 5 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 2.1

#### Tareas:

- [ ] **TASK 2.2.1:** Crear clase base `CostaRicaFE`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Implementations/CostaRica/CostaRicaFE.php`
  - **Criterios de Aceptación:**
    - Clase implementa `FacturacionElectronicaInterface`
    - Métodos de autenticación implementados
    - Métodos de comunicación con API implementados
    - Manejo de errores específico de CR
    - Usa configuración desde archivo de config
  - **Estimación:** 8 puntos

- [ ] **TASK 2.2.2:** Implementar autenticación con API de Costa Rica
  - **Criterios de Aceptación:**
    - Método de autenticación funcional
    - Manejo de tokens OAuth (si aplica)
    - Refresh de tokens automático
    - Manejo de expiración de tokens
    - Tests unitarios
  - **Estimación:** 5 puntos

- [ ] **TASK 2.2.3:** Agregar configuración de Costa Rica a archivo de config
  - **Archivo:** `Backend/config/facturacion_electronica.php`
  - **Criterios de Aceptación:**
    - URLs de prueba y producción agregadas
    - Formato de documento configurado (XML/JSON)
    - Configuración de endpoints
    - Documentación en comentarios
  - **Estimación:** 2 puntos

- [ ] **TASK 2.2.4:** Implementar firma de documentos (si diferente a El Salvador)
  - **Criterios de Aceptación:**
    - Método de firma implementado
    - Compatible con certificados costarricenses
    - Tests unitarios
  - **Estimación:** 5 puntos

---

### 📦 STORY 2.3: Implementación Backend - Documentos Costa Rica

**Descripción:** Implementar generación de documentos electrónicos para Costa Rica.

**Estimación:** 13 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 2.2

#### Tareas:

- [ ] **TASK 2.3.1:** Crear `CostaRicaFactura`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Implementations/CostaRica/CostaRicaFactura.php`
  - **Criterios de Aceptación:**
    - Genera XML/JSON según formato requerido
    - Estructura válida según especificación
    - Validaciones de datos implementadas
    - Tests unitarios
    - Documentación completa
  - **Estimación:** 8 puntos

- [ ] **TASK 2.3.2:** Crear `CostaRicaNotaCredito`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Implementations/CostaRica/CostaRicaNotaCredito.php`
  - **Criterios de Aceptación:**
    - Genera documento válido
    - Relación con documento original
    - Tests unitarios
  - **Estimación:** 5 puntos

- [ ] **TASK 2.3.3:** Crear `CostaRicaNotaDebito`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Implementations/CostaRica/CostaRicaNotaDebito.php`
  - **Criterios de Aceptación:**
    - Genera documento válido
    - Tests unitarios
  - **Estimación:** 5 puntos

- [ ] **TASK 2.3.4:** Implementar `CostaRicaAnulacion`
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Implementations/CostaRica/CostaRicaAnulacion.php`
  - **Criterios de Aceptación:**
    - Anulación funcional
    - Validaciones de anulación
    - Tests unitarios
  - **Estimación:** 5 puntos

- [ ] **TASK 2.3.5:** Implementar envío de documentos a API
  - **Criterios de Aceptación:**
    - Envío de facturas funcional
    - Envío de notas funcional
    - Manejo de respuestas de API
    - Manejo de errores
    - Tests de integración
  - **Estimación:** 5 puntos

---

### 📦 STORY 2.4: Catálogos y Modelos de Datos - Costa Rica

**Descripción:** Crear modelos y seeders para catálogos de Costa Rica.

**Estimación:** 5 puntos  
**Prioridad:** Alta  
**Dependencias:** STORY 2.1

#### Tareas:

- [ ] **TASK 2.4.1:** Crear modelo `Provincia` (Costa Rica)
  - **Archivo:** `Backend/app/Models/FacturacionElectronica/CostaRica/Provincia.php`
  - **Criterios de Aceptación:**
    - Modelo con campos necesarios
    - Relaciones con cantones
    - Migración creada
  - **Estimación:** 2 puntos

- [ ] **TASK 2.4.2:** Crear modelo `Canton`
  - **Archivo:** `Backend/app/Models/FacturacionElectronica/CostaRica/Canton.php`
  - **Criterios de Aceptación:**
    - Modelo con relación a provincia
    - Relación con distritos
    - Migración creada
  - **Estimación:** 2 puntos

- [ ] **TASK 2.4.3:** Crear modelo `Distrito`
  - **Archivo:** `Backend/app/Models/FacturacionElectronica/CostaRica/Distrito.php`
  - **Criterios de Aceptación:**
    - Modelo con relación a cantón
    - Migración creada
  - **Estimación:** 2 puntos

- [ ] **TASK 2.4.4:** Crear seeder con datos de Costa Rica
  - **Archivo:** `Backend/database/seeders/CostaRicaCatalogosSeeder.php`
  - **Criterios de Aceptación:**
    - Seeder con todas las provincias
    - Seeder con todos los cantones
    - Seeder con todos los distritos
    - Datos verificados y completos
  - **Estimación:** 5 puntos

- [ ] **TASK 2.4.5:** Crear modelo `ActividadEconomicaCR` (si diferente a SV)
  - **Archivo:** `Backend/app/Models/FacturacionElectronica/CostaRica/ActividadEconomicaCR.php`
  - **Criterios de Aceptación:**
    - Modelo creado
    - Seeder con datos
    - Migración creada
  - **Estimación:** 3 puntos

---

### 📦 STORY 2.5: Actualizar Factory y Servicio Principal

**Descripción:** Integrar Costa Rica en el Factory y servicio principal.

**Estimación:** 3 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 2.3

#### Tareas:

- [ ] **TASK 2.5.1:** Actualizar Factory para incluir Costa Rica
  - **Archivo:** `Backend/app/Services/FacturacionElectronica/Factories/FacturacionElectronicaFactory.php`
  - **Criterios de Aceptación:**
    - Factory puede crear instancias de CR
    - Tests actualizados
    - Documentación actualizada
  - **Estimación:** 2 puntos

- [ ] **TASK 2.5.2:** Verificar que servicio principal funciona con CR
  - **Criterios de Aceptación:**
    - Servicio detecta país correctamente
    - Crea instancias correctas
    - Tests de integración pasan
  - **Estimación:** 2 puntos

---

### 📦 STORY 2.6: Actualizar Frontend - Soporte Costa Rica

**Descripción:** Actualizar frontend para soportar configuración y uso de Costa Rica.

**Estimación:** 5 puntos  
**Prioridad:** Alta  
**Dependencias:** STORY 2.5

#### Tareas:

- [ ] **TASK 2.6.1:** Actualizar formularios de empresa para CR
  - **Archivo:** `Frontend/src/app/views/admin/empresa/empresa.component.ts`
  - **Criterios de Aceptación:**
    - Formulario detecta país y muestra campos apropiados
    - Selectores de provincia/cantón/distrito para CR
    - Validaciones específicas de CR
    - UI/UX consistente
  - **Estimación:** 5 puntos

- [ ] **TASK 2.6.2:** Actualizar formularios de cliente para CR
  - **Archivo:** `Frontend/src/app/views/ventas/clientes/cliente/informacion/cliente-informacion.component.ts`
  - **Criterios de Aceptación:**
    - Selectores de ubicación para CR
    - Validaciones de documentos de CR
    - UI consistente
  - **Estimación:** 3 puntos

- [ ] **TASK 2.6.3:** Actualizar componentes de facturación
  - **Criterios de Aceptación:**
    - Componentes funcionan con CR
    - Validaciones específicas de CR
    - Mensajes de error apropiados
    - Tests pasan
  - **Estimación:** 3 puntos

- [ ] **TASK 2.6.4:** Actualizar servicio frontend para CR
  - **Archivo:** `Frontend/src/app/services/facturacion-electronica.service.ts`
  - **Criterios de Aceptación:**
    - Servicio detecta CR correctamente
    - Manejo de errores específico de CR
    - Tests actualizados
  - **Estimación:** 2 puntos

---

### 📦 STORY 2.7: Testing y Validación - Costa Rica

**Descripción:** Testing completo de la implementación de Costa Rica.

**Estimación:** 8 puntos  
**Prioridad:** Crítica  
**Dependencias:** STORY 2.6

#### Tareas:

- [ ] **TASK 2.7.1:** Tests unitarios para implementación CR
  - **Criterios de Aceptación:**
    - Tests para `CostaRicaFactura`
    - Tests para `CostaRicaNotaCredito`
    - Tests para `CostaRicaNotaDebito`
    - Tests para `CostaRicaAnulacion`
    - Cobertura > 85%
  - **Estimación:** 5 puntos

- [ ] **TASK 2.7.2:** Tests de integración con API de prueba
  - **Criterios de Aceptación:**
    - Test de emisión de factura en ambiente prueba
    - Test de emisión de nota de crédito
    - Test de anulación
    - Test de consulta
    - Validación de respuestas de API
  - **Estimación:** 5 puntos

- [ ] **TASK 2.7.3:** Testing manual en ambiente de desarrollo
  - **Criterios de Aceptación:**
    - Emitir factura en ambiente prueba CR
    - Verificar estructura XML/JSON
    - Verificar validaciones
    - Verificar PDF generado
    - Verificar envío por correo
    - Checklist completo ejecutado
  - **Estimación:** 5 puntos

- [ ] **TASK 2.7.4:** Validar catálogos y datos
  - **Criterios de Aceptación:**
    - Provincias cargadas correctamente
    - Cantones cargados correctamente
    - Distritos cargados correctamente
    - Validación de selección en formularios
  - **Estimación:** 2 puntos

---

### 📦 STORY 2.8: Documentación y Deployment

**Descripción:** Documentar implementación de Costa Rica y preparar para producción.

**Estimación:** 3 puntos  
**Prioridad:** Media  
**Dependencias:** STORY 2.7

#### Tareas:

- [ ] **TASK 2.8.1:** Documentar implementación de Costa Rica
  - **Archivo:** `Backend/docs/FACTURACION_ELECTRONICA_COSTA_RICA.md`
  - **Criterios de Aceptación:**
    - Documentación de API usada
    - Flujo de emisión documentado
    - Ejemplos de uso
    - Troubleshooting
  - **Estimación:** 3 puntos

- [ ] **TASK 2.8.2:** Actualizar documentación general
  - **Criterios de Aceptación:**
    - README actualizado con CR
    - Changelog actualizado
    - Guía de configuración actualizada
  - **Estimación:** 2 puntos

- [ ] **TASK 2.8.3:** Preparar deployment a producción
  - **Criterios de Aceptación:**
    - Checklist de deployment
    - Plan de rollback
    - Monitoreo configurado
    - Documentación de operaciones
  - **Estimación:** 2 puntos

---

## 📊 Resumen de Estimaciones

### Fase 1: Refactorización El Salvador
- **Total Story Points:** ~50 puntos
- **Sprints estimados:** 2-3 sprints
- **Tiempo estimado:** 2-3 semanas

### Fase 2: Implementación Costa Rica
- **Total Story Points:** ~47 puntos
- **Sprints estimados:** 3-4 sprints
- **Tiempo estimado:** 3-4 semanas

### **Total General**
- **Total Story Points:** ~97 puntos
- **Sprints estimados:** 5-7 sprints
- **Tiempo estimado:** 5-7 semanas

---

## 🏷️ Labels Sugeridos para Jira

- `facturacion-electronica`
- `multi-pais`
- `el-salvador`
- `costa-rica`
- `backend`
- `frontend`
- `refactoring`
- `nueva-funcionalidad`
- `breaking-change`

---

## 📝 Notas Importantes

1. **Dependencias:** Las tareas deben ejecutarse en el orden indicado por las dependencias
2. **Testing:** Cada story debe incluir testing antes de considerarse completa
3. **Code Review:** Todas las tareas requieren code review antes de merge
4. **Documentación:** Mantener documentación actualizada en cada paso
5. **Compatibilidad:** Asegurar que cambios no rompan funcionalidad existente

---

**Fecha de creación:** 2024-01-XX  
**Última actualización:** 2024-01-XX
