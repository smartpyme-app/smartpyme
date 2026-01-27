# ✅ Checklist de Pruebas - Facturación Electrónica Multi-País

## 📋 Información General

**Fecha de Pruebas:** _______________  
**Ambiente:** [ ] Desarrollo [ ] Pruebas [ ] Producción  
**País:** [ ] El Salvador [ ] Costa Rica  
**Tester:** _______________

---

## 🔧 Pre-requisitos

- [ ] Migración de base de datos ejecutada (`php artisan migrate`)
- [ ] Datos de empresas existentes migrados correctamente
- [ ] Configuración de facturación electrónica en empresa de prueba
- [ ] Certificado digital configurado
- [ ] Ambiente configurado (Pruebas/Producción)

---

## 📝 Pruebas de Base de Datos

### Migración de Campos
- [ ] Campos genéricos creados en tabla `empresas`:
  - [ ] `fe_pais` existe
  - [ ] `fe_usuario` existe
  - [ ] `fe_contrasena` existe
  - [ ] `fe_certificado_password` existe
  - [ ] `fe_certificado_path` existe
  - [ ] `fe_token` existe
  - [ ] `fe_token_expires_at` existe
  - [ ] `fe_configuracion` (JSON) existe

### Migración de Datos
- [ ] Empresas con `mh_usuario` tienen `fe_usuario` sincronizado
- [ ] Empresas con `mh_contrasena` tienen `fe_contrasena` sincronizado
- [ ] Empresas con `mh_pwd_certificado` tienen `fe_certificado_password` sincronizado
- [ ] Empresas con `facturacion_electronica = 1` tienen `fe_pais = 'SV'`

### Verificación SQL
```sql
-- Verificar migración de datos
SELECT 
    id, 
    nombre,
    mh_usuario, 
    fe_usuario,
    mh_contrasena, 
    fe_contrasena,
    mh_pwd_certificado,
    fe_certificado_password,
    facturacion_electronica,
    fe_pais
FROM empresas 
WHERE facturacion_electronica = 1 
LIMIT 10;
```

---

## 🎯 Pruebas de Backend - API

### 1. Generar DTE - Factura (Tipo 01)

**Endpoint:** `POST /api/fe/generarDTE`  
**Body:**
```json
{
  "id": <id_venta>
}
```

**Resultados Esperados:**
- [ ] DTE generado correctamente
- [ ] Estructura JSON válida
- [ ] Campos de identificación presentes
- [ ] Emisor y receptor correctos
- [ ] Detalles de productos incluidos
- [ ] Resumen de totales correcto

**Notas:** _______________

---

### 2. Generar DTE - CCF (Tipo 03)

**Endpoint:** `POST /api/fe/generarDTE`  
**Body:**
```json
{
  "id": <id_venta_ccf>
}
```

**Resultados Esperados:**
- [ ] DTE generado correctamente
- [ ] Tipo de documento = '03'
- [ ] Receptor con estructura de CCF
- [ ] Sin errores en validación

**Notas:** _______________

---

### 3. Generar DTE - Nota de Crédito (Tipo 05)

**Endpoint:** `POST /api/fe/generarDTENotaCredito`  
**Body:**
```json
{
  "id": <id_devolucion>
}
```

**Resultados Esperados:**
- [ ] DTE generado correctamente
- [ ] Tipo de documento = '05'
- [ ] Documento relacionado presente
- [ ] Estructura de nota de crédito válida

**Notas:** _______________

---

### 4. Generar DTE - Nota de Débito (Tipo 06)

**Endpoint:** `POST /api/fe/generarDTENotaCredito`  
**Body:**
```json
{
  "id": <id_devolucion>
}
```

**Resultados Esperados:**
- [ ] DTE generado correctamente
- [ ] Tipo de documento = '06'
- [ ] Documento relacionado presente

**Notas:** _______________

---

### 5. Generar DTE - Factura de Exportación (Tipo 11)

**Endpoint:** `POST /api/fe/generarDTE`  
**Body:**
```json
{
  "id": <id_venta_exportacion>
}
```

**Resultados Esperados:**
- [ ] DTE generado correctamente
- [ ] Tipo de documento = '11'
- [ ] Emisor con campos de exportación
- [ ] Receptor con información de país extranjero
- [ ] Incoterms presentes si aplica

**Notas:** _______________

---

### 6. Firmar DTE

**Flujo:** Generar DTE → Firmar → Enviar

**Resultados Esperados:**
- [ ] Firma electrónica exitosa
- [ ] `firmaElectronica` presente en respuesta
- [ ] Sin errores de certificado
- [ ] Token válido

**Notas:** _______________

---

### 7. Enviar DTE a MH

**Flujo:** Generar → Firmar → Enviar

**Resultados Esperados:**
- [ ] DTE enviado exitosamente
- [ ] Estado = 'PROCESADO' o 'RECIBIDO'
- [ ] `selloRecibido` presente
- [ ] `codigoGeneracion` guardado en venta
- [ ] `sello_mh` guardado en venta

**Notas:** _______________

---

### 8. Anular DTE

**Endpoint:** `POST /api/fe/anularDTE`  
**Body:**
```json
{
  "id": <id_venta>
}
```

**Resultados Esperados:**
- [ ] Documento de anulación generado
- [ ] DTE anulado exitosamente
- [ ] Estado de venta = 'Anulada'
- [ ] `dte_invalidacion` guardado

**Notas:** _______________

---

### 9. Consultar DTE

**Endpoint:** `POST /api/fe/consultarDTE`  
**Body:**
```json
{
  "codigoGeneracion": "...",
  "tipoDte": "01",
  "ambiente": "00"
}
```

**Resultados Esperados:**
- [ ] Consulta exitosa
- [ ] Estado del documento retornado
- [ ] Información completa del DTE

**Notas:** _______________

---

### 10. Generar PDF DTE

**Endpoint:** `GET /api/fe/reporte/dte/{id}/{tipo}`

**Resultados Esperados:**
- [ ] PDF generado correctamente
- [ ] QR code presente y funcional
- [ ] URL de consulta pública correcta
- [ ] Formato correcto según tipo de documento

**Notas:** _______________

---

### 11. Enviar DTE por Correo

**Endpoint:** `POST /api/fe/enviarDTE`  
**Body:**
```json
{
  "id": <id_venta>,
  "tipo_dte": "01"
}
```

**Resultados Esperados:**
- [ ] Correo enviado exitosamente
- [ ] PDF adjunto en correo
- [ ] JSON adjunto en correo
- [ ] Correo recibido en bandeja de entrada

**Notas:** _______________

---

## 🌐 Pruebas de Frontend

### 1. Configuración de Empresa

**Ruta:** `/admin/empresa`

**Pruebas:**
- [ ] Campo `fe_pais` visible y funcional
- [ ] Campo `fe_usuario` visible y funcional
- [ ] Campo `fe_contrasena` visible y funcional
- [ ] Campo `fe_certificado_password` visible y funcional
- [ ] Sincronización automática con campos antiguos
- [ ] Botón "Comprobar datos" funciona
- [ ] Validaciones funcionan correctamente

**Notas:** _______________

---

### 2. Emisión de Factura

**Ruta:** `/venta/crear` o `/ventas-v2/crear`

**Pruebas:**
- [ ] Botón "Emitir DTE" visible
- [ ] Emisión de factura funciona
- [ ] Mensajes de éxito/error correctos
- [ ] PDF se abre automáticamente
- [ ] Ventana de impresión aparece
- [ ] Sin errores en consola del navegador

**Notas:** _______________

---

### 3. Emisión de Nota de Crédito

**Ruta:** `/devoluciones`

**Pruebas:**
- [ ] Emisión de nota de crédito funciona
- [ ] Validación de venta relacionada
- [ ] Mensajes correctos
- [ ] Sin errores en consola

**Notas:** _______________

---

### 4. Compatibilidad con Código Antiguo

**Pruebas:**
- [ ] Rutas antiguas (`/generarDTE`) funcionan
- [ ] Advertencias de deprecación en consola
- [ ] `MHService` funciona (redirige a nuevo servicio)
- [ ] Sin errores en componentes que usan `MHService`

**Notas:** _______________

---

## 🔄 Pruebas de Compatibilidad

### 1. Datos Existentes

- [ ] Empresas existentes pueden emitir documentos
- [ ] Documentos antiguos siguen siendo válidos
- [ ] PDFs antiguos se generan correctamente
- [ ] Consultas de documentos antiguos funcionan

**Notas:** _______________

---

### 2. Migración de Datos

- [ ] Ejecutar migración múltiples veces (idempotencia)
- [ ] No se duplican datos
- [ ] Datos antiguos preservados
- [ ] Rollback funciona correctamente

**Notas:** _______________

---

## ⚠️ Pruebas de Errores

### 1. Validaciones

- [ ] Error si empresa no tiene FE configurada
- [ ] Error si falta usuario/contraseña
- [ ] Error si falta certificado
- [ ] Error si país no soportado
- [ ] Mensajes de error claros y útiles

**Notas:** _______________

---

### 2. Manejo de Excepciones

- [ ] Errores de autenticación manejados
- [ ] Errores de firma manejados
- [ ] Errores de envío manejados
- [ ] Logs de errores generados
- [ ] No se rompe la aplicación

**Notas:** _______________

---

## 📊 Pruebas de Rendimiento

- [ ] Tiempo de generación de DTE < 2 segundos
- [ ] Tiempo de firma < 5 segundos
- [ ] Tiempo de envío < 10 segundos
- [ ] Sin memory leaks
- [ ] Consultas SQL optimizadas

**Notas:** _______________

---

## 🔐 Pruebas de Seguridad

- [ ] Contraseñas no se exponen en logs
- [ ] Tokens se guardan de forma segura
- [ ] Certificados no se exponen
- [ ] Validación de permisos funciona
- [ ] SQL injection prevenido

**Notas:** _______________

---

## 📝 Notas Generales

**Problemas Encontrados:**
1. _______________
2. _______________
3. _______________

**Mejoras Sugeridas:**
1. _______________
2. _______________
3. _______________

**Estado Final:** [ ] ✅ Aprobado [ ] ⚠️ Requiere Correcciones [ ] ❌ Rechazado

**Firma del Tester:** _______________  
**Fecha:** _______________
