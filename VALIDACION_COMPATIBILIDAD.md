# ✅ Validación de Compatibilidad - Facturación Electrónica Multi-País

## 📋 Objetivo

Validar que la refactorización de facturación electrónica mantiene 100% de compatibilidad con el código existente y que los datos migrados funcionan correctamente.

---

## 🔍 Validación de Datos Migrados

### 1. Verificación de Migración de Campos

**Script SQL de Verificación:**
```sql
-- Verificar que todos los campos fueron creados
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'empresas'
  AND COLUMN_NAME LIKE 'fe_%'
ORDER BY COLUMN_NAME;
```

**Resultados Esperados:**
- [ ] `fe_pais` existe (VARCHAR(3), NULLABLE)
- [ ] `fe_usuario` existe (VARCHAR, NULLABLE)
- [ ] `fe_contrasena` existe (VARCHAR, NULLABLE)
- [ ] `fe_certificado_password` existe (VARCHAR, NULLABLE)
- [ ] `fe_certificado_path` existe (VARCHAR, NULLABLE)
- [ ] `fe_token` existe (TEXT, NULLABLE)
- [ ] `fe_token_expires_at` existe (TIMESTAMP, NULLABLE)
- [ ] `fe_configuracion` existe (JSON, NULLABLE)

---

### 2. Verificación de Migración de Datos

**Script SQL de Verificación:**
```sql
-- Verificar empresas con datos migrados
SELECT 
    id,
    nombre,
    facturacion_electronica,
    fe_pais,
    CASE 
        WHEN mh_usuario IS NOT NULL AND fe_usuario IS NULL THEN 'FALTA MIGRAR'
        WHEN mh_usuario IS NOT NULL AND fe_usuario IS NOT NULL THEN 'MIGRADO'
        WHEN mh_usuario IS NULL AND fe_usuario IS NOT NULL THEN 'NUEVO'
        ELSE 'SIN DATOS'
    END as estado_usuario,
    CASE 
        WHEN mh_contrasena IS NOT NULL AND fe_contrasena IS NULL THEN 'FALTA MIGRAR'
        WHEN mh_contrasena IS NOT NULL AND fe_contrasena IS NOT NULL THEN 'MIGRADO'
        WHEN mh_contrasena IS NULL AND fe_contrasena IS NOT NULL THEN 'NUEVO'
        ELSE 'SIN DATOS'
    END as estado_contrasena,
    CASE 
        WHEN mh_pwd_certificado IS NOT NULL AND fe_certificado_password IS NULL THEN 'FALTA MIGRAR'
        WHEN mh_pwd_certificado IS NOT NULL AND fe_certificado_password IS NOT NULL THEN 'MIGRADO'
        WHEN mh_pwd_certificado IS NULL AND fe_certificado_password IS NOT NULL THEN 'NUEVO'
        ELSE 'SIN DATOS'
    END as estado_certificado
FROM empresas
WHERE facturacion_electronica = 1
ORDER BY id;
```

**Validaciones:**
- [ ] Todas las empresas con `facturacion_electronica = 1` tienen `fe_pais = 'SV'`
- [ ] Todas las empresas con `mh_usuario` tienen `fe_usuario` sincronizado
- [ ] Todas las empresas con `mh_contrasena` tienen `fe_contrasena` sincronizada
- [ ] Todas las empresas con `mh_pwd_certificado` tienen `fe_certificado_password` sincronizado
- [ ] No hay pérdida de datos

---

### 3. Verificación de Índices

**Script SQL:**
```sql
-- Verificar índices creados
SHOW INDEXES FROM empresas WHERE Key_name LIKE 'fe_%';
```

**Resultados Esperados:**
- [ ] Índice en `fe_pais` existe
- [ ] Índice en `fe_token_expires_at` existe

---

## 🔄 Validación de Compatibilidad de Código

### 1. Backend - Modelo Empresa

**Verificar métodos helper:**
- [ ] `getFePais()` retorna `fe_pais` o `cod_pais`
- [ ] `getFeUsuario()` retorna `fe_usuario` o `mh_usuario`
- [ ] `getFeContrasena()` retorna `fe_contrasena` o `mh_contrasena`
- [ ] `getFeCertificadoPassword()` retorna `fe_certificado_password` o `mh_pwd_certificado`
- [ ] `tieneFacturacionElectronica()` funciona correctamente
- [ ] `tieneTokenValido()` funciona correctamente

**Código de prueba:**
```php
$empresa = Empresa::find(1);
$pais = $empresa->getFePais(); // Debe retornar 'SV' o null
$usuario = $empresa->getFeUsuario(); // Debe retornar usuario o null
```

---

### 2. Backend - Servicios

**Verificar FacturacionElectronicaService:**
- [ ] `generarDTE()` funciona con ventas existentes
- [ ] `firmarDTE()` funciona con certificados existentes
- [ ] `enviarDTE()` funciona con autenticación existente
- [ ] `anularDTE()` funciona con documentos existentes
- [ ] `consultarDTE()` funciona correctamente

**Verificar Factory:**
- [ ] Crea instancias correctas para El Salvador
- [ ] Detecta país desde `fe_pais` o `cod_pais`
- [ ] Lanza excepciones claras para países no soportados

---

### 3. Backend - Controladores

**Verificar rutas nuevas:**
- [ ] `POST /api/fe/generarDTE` funciona
- [ ] `POST /api/fe/anularDTE` funciona
- [ ] `POST /api/fe/consultarDTE` funciona
- [ ] `GET /api/fe/reporte/dte/{id}/{tipo}` funciona

**Verificar rutas antiguas (deprecated):**
- [ ] `POST /api/generarDTE` redirige correctamente
- [ ] `POST /api/anularDTE` redirige correctamente
- [ ] Logs de deprecación se generan
- [ ] Funcionalidad idéntica a rutas nuevas

---

### 4. Frontend - Servicios

**Verificar FacturacionElectronicaService:**
- [ ] `auth()` funciona
- [ ] `firmarDTE()` funciona
- [ ] `enviarDTE()` funciona
- [ ] `emitirDTE()` funciona (flujo completo)
- [ ] Detecta país automáticamente

**Verificar MHService (deprecated):**
- [ ] Métodos redirigen a `FacturacionElectronicaService`
- [ ] Advertencias en consola aparecen
- [ ] Funcionalidad idéntica a antes

---

### 5. Frontend - Componentes

**Verificar componentes de facturación:**
- [ ] `facturacion.component.ts` funciona
- [ ] `facturacion-v2.component.ts` funciona
- [ ] Emisión de DTE funciona
- [ ] Sin errores en consola

**Verificar componente de empresa:**
- [ ] Formulario muestra campos genéricos
- [ ] Sincronización automática funciona
- [ ] Guardado funciona correctamente

---

## 📊 Validación de Funcionalidad Existente

### 1. Emisión de Documentos

**Documentos a probar:**
- [ ] Factura (tipo 01) - Funciona igual que antes
- [ ] CCF (tipo 03) - Funciona igual que antes
- [ ] Nota de Crédito (tipo 05) - Funciona igual que antes
- [ ] Nota de Débito (tipo 06) - Funciona igual que antes
- [ ] Factura de Exportación (tipo 11) - Funciona igual que antes

**Criterios:**
- Misma estructura de DTE
- Mismos campos requeridos
- Misma validación
- Mismo formato de respuesta

---

### 2. Firma y Envío

- [ ] Firma funciona con certificados existentes
- [ ] Envío funciona con credenciales existentes
- [ ] Mismo formato de respuesta
- [ ] Mismos códigos de estado

---

### 3. Anulación

- [ ] Anulación funciona igual que antes
- [ ] Mismo formato de documento de anulación
- [ ] Misma validación
- [ ] Mismo proceso

---

### 4. Consultas y Reportes

- [ ] Consulta de DTE funciona igual
- [ ] Generación de PDF funciona igual
- [ ] Envío por correo funciona igual
- [ ] URLs de consulta pública correctas

---

## 🐛 Validación de Errores

### 1. Manejo de Errores

- [ ] Errores de autenticación manejados correctamente
- [ ] Errores de firma manejados correctamente
- [ ] Errores de envío manejados correctamente
- [ ] Mensajes de error claros y útiles
- [ ] Logs de errores generados

---

### 2. Validaciones

- [ ] Validación de empresa sin FE configurada
- [ ] Validación de campos faltantes
- [ ] Validación de país no soportado
- [ ] Validación de tipo de documento inválido

---

## ⚡ Validación de Rendimiento

### 1. Tiempos de Respuesta

- [ ] Generación de DTE: < 2 segundos
- [ ] Firma de DTE: < 5 segundos
- [ ] Envío de DTE: < 10 segundos
- [ ] Consulta de DTE: < 3 segundos

---

### 2. Uso de Recursos

- [ ] Sin memory leaks
- [ ] Consultas SQL optimizadas
- [ ] Sin queries N+1
- [ ] Uso de memoria estable

---

## 🔐 Validación de Seguridad

- [ ] Contraseñas no se exponen en logs
- [ ] Tokens se guardan de forma segura
- [ ] Certificados no se exponen
- [ ] Validación de permisos funciona
- [ ] SQL injection prevenido
- [ ] XSS prevenido

---

## 📝 Checklist Final

### Pre-Producción

- [ ] Todas las pruebas manuales pasadas
- [ ] Todos los tests unitarios pasan
- [ ] Migración de datos verificada
- [ ] Compatibilidad verificada
- [ ] Documentación actualizada
- [ ] Rollback planificado y probado

### Post-Implementación

- [ ] Monitoreo de errores activo
- [ ] Logs revisados diariamente
- [ ] Performance monitoreado
- [ ] Feedback de usuarios recopilado

---

## 📌 Notas

**Fecha de Validación:** _______________  
**Validado por:** _______________  
**Ambiente:** _______________  

**Problemas Encontrados:**
1. _______________
2. _______________

**Acciones Correctivas:**
1. _______________
2. _______________

**Estado:** [ ] ✅ Aprobado [ ] ⚠️ Requiere Correcciones [ ] ❌ Rechazado
