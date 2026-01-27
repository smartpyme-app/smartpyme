# 📋 Instrucciones de Migración - Facturación Electrónica Multi-País

## 🎯 Migración Requerida

Solo necesitas ejecutar **una migración** para la funcionalidad de facturación electrónica multi-país:

### Migración: `2026_01_26_180007_add_fe_multi_pais_fields_to_empresas_table.php`

Esta migración:
- ✅ Agrega campos genéricos para facturación electrónica multi-país
- ✅ Migra automáticamente datos existentes (idempotente)
- ✅ Crea índices para mejorar rendimiento
- ✅ Es reversible (rollback disponible)

---

## 📝 Pasos para Ejecutar la Migración

### 1. Verificar Estado Actual

Antes de ejecutar, verifica qué migraciones ya se han ejecutado:

```bash
cd Backend
php artisan migrate:status
```

Busca la migración `2026_01_26_180007_add_fe_multi_pais_fields_to_empresas_table` en la lista.

---

### 2. Ejecutar la Migración

**Opción A: Ejecutar todas las migraciones pendientes**
```bash
cd Backend
php artisan migrate
```

**Opción B: Ejecutar solo esta migración específica**
```bash
cd Backend
php artisan migrate --path=database/migrations/2026_01_26_180007_add_fe_multi_pais_fields_to_empresas_table.php
```

---

### 3. Verificar que la Migración se Ejecutó Correctamente

**Verificar campos creados:**
```sql
-- Verificar que los campos fueron creados
SHOW COLUMNS FROM empresas LIKE 'fe_%';
```

**Verificar migración de datos:**
```sql
-- Verificar que los datos se migraron correctamente
SELECT 
    id,
    nombre,
    facturacion_electronica,
    fe_pais,
    CASE 
        WHEN mh_usuario IS NOT NULL AND fe_usuario IS NULL THEN 'FALTA MIGRAR'
        WHEN mh_usuario IS NOT NULL AND fe_usuario IS NOT NULL THEN 'MIGRADO'
        ELSE 'OK'
    END as estado_migracion
FROM empresas 
WHERE facturacion_electronica = 1 
LIMIT 10;
```

---

## ⚠️ Importante

### Antes de Ejecutar en Producción

1. **Backup de Base de Datos:**
   ```bash
   # Ejemplo para MySQL
   mysqldump -u usuario -p nombre_base_datos > backup_antes_fe_multi_pais.sql
   ```

2. **Ejecutar en Ambiente de Pruebas Primero:**
   - Ejecuta la migración en desarrollo/pruebas
   - Verifica que todo funciona correctamente
   - Luego ejecuta en producción

3. **La Migración es Idempotente:**
   - Puedes ejecutarla múltiples veces sin problemas
   - No duplicará datos
   - Solo migrará datos que aún no se han migrado

---

## 🔄 Rollback (Si es Necesario)

Si necesitas revertir la migración:

```bash
cd Backend
php artisan migrate:rollback --step=1
```

**⚠️ ADVERTENCIA:** El rollback eliminará los campos `fe_*` pero **NO** eliminará los datos de los campos antiguos (`mh_*`), ya que esos campos ya existían antes.

---

## 📊 Campos que se Agregan

La migración agrega los siguientes campos a la tabla `empresas`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `fe_pais` | VARCHAR(3) | Código de país (SV, CR, etc.) |
| `fe_usuario` | VARCHAR | Usuario genérico para FE |
| `fe_contrasena` | VARCHAR | Contraseña genérica para FE |
| `fe_certificado_password` | VARCHAR | Contraseña del certificado digital |
| `fe_certificado_path` | VARCHAR | Ruta al archivo del certificado |
| `fe_token` | TEXT | Token de autenticación FE |
| `fe_token_expires_at` | TIMESTAMP | Fecha de expiración del token |
| `fe_configuracion` | JSON | Configuración específica por país |

**Índices creados:**
- Índice en `fe_pais`
- Índice en `fe_token_expires_at`

---

## 🔍 Verificación Post-Migración

Después de ejecutar la migración, verifica:

1. **Campos creados:**
   ```sql
   SELECT COUNT(*) as total_campos_fe
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_NAME = 'empresas'
     AND COLUMN_NAME LIKE 'fe_%';
   ```
   Debe retornar: **8 campos**

2. **Datos migrados:**
   ```sql
   SELECT 
       COUNT(*) as total_empresas_fe,
       SUM(CASE WHEN fe_pais = 'SV' THEN 1 ELSE 0 END) as empresas_sv,
       SUM(CASE WHEN fe_usuario IS NOT NULL THEN 1 ELSE 0 END) as empresas_con_usuario
   FROM empresas
   WHERE facturacion_electronica = 1;
   ```

3. **Índices creados:**
   ```sql
   SHOW INDEXES FROM empresas WHERE Key_name LIKE 'fe_%';
   ```
   Debe mostrar 2 índices.

---

## ✅ Checklist de Ejecución

- [ ] Backup de base de datos realizado
- [ ] Migración ejecutada en ambiente de pruebas
- [ ] Verificación de campos realizada
- [ ] Verificación de datos migrados realizada
- [ ] Pruebas funcionales realizadas
- [ ] Migración ejecutada en producción (si aplica)

---

## 🆘 Solución de Problemas

### Error: "Column already exists"
Si obtienes este error, significa que la migración ya se ejecutó. Verifica con:
```bash
php artisan migrate:status
```

### Error: "Table 'empresas' doesn't exist"
Asegúrate de que la tabla `empresas` existe. Si no existe, ejecuta primero las migraciones base.

### Error: "Syntax error" en SQL
Verifica que estás usando MySQL 5.7+ o MariaDB 10.2+ (soporte para JSON).

---

## 📞 Soporte

Si encuentras algún problema durante la migración:
1. Revisa los logs: `storage/logs/laravel.log`
2. Verifica el estado de las migraciones: `php artisan migrate:status`
3. Consulta el archivo `VALIDACION_COMPATIBILIDAD.md` para más detalles
