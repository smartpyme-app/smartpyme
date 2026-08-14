# Design: Importar mesas de restaurante desde Excel

**Fecha:** 2026-08-04  
**Estado:** Aprobado en conversación; pendiente revisión final del archivo  
**Alcance:** Comando Artisan one-shot para cargar mesas desde la plantilla Excel, asignándolas a zonas ya existentes en producción.

## Problema

En producción las zonas de restaurante ya están creadas. El cliente entregó un Excel (`Backend/plantilla de mesas.xlsx`) con ~120 mesas (número, capacidad, zona numérica interna, nombre de zona, orden de mapa). Hace falta importarlas sin crear zonas y con verificación previa (dry-run).

## Objetivos

1. Importar mesas desde Excel vía Artisan.
2. Resolver zona por **nombre** (`Nombre de Zona` → `restaurante_zonas.nombre`).
3. Soportar `--dry-run` que reporte crear / omitir / errores sin escribir.
4. Abortar el write completo si hay zona faltante o filas inválidas.
5. Omitir mesas que ya existan (mismo empresa + zona + número).

## Fuera de alcance

- Crear o actualizar zonas.
- UI / endpoint HTTP de importación.
- Conversión a JSON como paso requerido.
- Asignación de sucursal (`id_sucursal` siempre `null`).
- Actualizar mesas existentes (capacidad/orden).
- Match por ID de la columna `zona` del Excel.

## Contexto actual

- Modelos: `App\Models\Restaurante\Mesa`, `App\Models\Restaurante\ZonaRestaurante`.
- Tablas: `restaurante_mesas`, `restaurante_zonas`.
- Alta manual vía `MesaController::store` (sincroniza `zona` texto desde `zona_id`).
- Maatwebsite Excel ya está en el proyecto.
- Plantilla: columnas `numero de mesa`, `capacidad`, `zona` (ignorar), `Nombre de Zona`, `orden de mapa`.
- Números de mesa se repiten entre zonas distintas (válido).

## Decisiones

| Decisión | Elección |
|----------|----------|
| Enfoque | Artisan lee Excel directo (Maatwebsite) |
| Zona | Match por nombre (trim), case-sensitive |
| Empresa | Flag obligatorio `--empresa=ID` |
| Sucursal | Siempre `null` |
| Duplicados | Omitir (no actualizar) |
| Zona no encontrada | Abortar todo el import |
| Zona ambigua (mismo nombre 2+ veces) | Error / abortar |
| Zonas inactivas | No matchean (`activo=1` únicamente) |

## Comando

```bash
php artisan restaurante:importar-mesas path/al/archivo.xlsx --empresa=123 --dry-run
php artisan restaurante:importar-mesas path/al/archivo.xlsx --empresa=123
```

### Argumentos / opciones

| Nombre | Tipo | Descripción |
|--------|------|-------------|
| `archivo` | argumento | Ruta al `.xlsx` |
| `--empresa=` | required | `id` de empresa |
| `--dry-run` | flag | Solo validar y reportar |

## Flujo

1. Validar archivo existente y empresa existente.
2. Leer filas (WithHeadingRow / lectura equivalente); ignorar columna `zona` numérica.
3. Cargar zonas activas de la empresa indexadas por `trim(nombre)`.
4. Por cada fila no vacía:
   - Validar `numero` presente; `capacidad` numérica (default 4); `orden` numérico (default 0).
   - Resolver zona por nombre; error si no existe o hay ambigüedad.
   - Si ya existe mesa (`id_empresa` + `zona_id` + `numero`) → omitir.
   - Si no → marcar para crear.
5. Imprimir resumen: conteos crear / omitir / errores + lista de errores + muestra de altas.
6. Si hay errores → exit `1`. En dry-run no escribir. Sin dry-run y sin errores → `DB::transaction` insertando mesas.
7. Campos al crear: `id_empresa`, `id_sucursal=null`, `numero`, `capacidad`, `zona_id`, `zona` (nombre), `orden`, `estado=libre`, `activo=true`.

## Archivos

| Archivo | Rol |
|---------|-----|
| `Backend/app/Console/Commands/ImportarMesasRestaurante.php` | Comando (único código nuevo esperado) |
| `Backend/plantilla de mesas.xlsx` | Datos de entrada (ya presente / untracked) |

No se agregan servicios, imports Maatwebsite separados, JSON ni cambios de frontend.

## Errores y salida

- Dry-run / ejecución fallida: listar `(fila, zona, motivo)` para cada error.
- Motivos típicos: zona no encontrada, zona ambigua, número vacío, capacidad inválida, archivo o empresa inexistente.
- Éxito sin dry-run: “N mesas creadas, M omitidas”.
- Cualquier error de validación o zona → no se inserta ninguna fila (transacción o pre-check).

## Testing mínimo

Un self-check runnable o test pequeño que cubra:

1. Zona existente → mesa se crearía / se crea.
2. Zona inexistente → error y no escribe.
3. Mesa duplicada → omitir.

(Sin framework pesado; assert-based o un test PHPUnit mínimo si el módulo ya tiene suite de comandos.)

## Uso esperado en prod

```bash
# 1) Verificar
php artisan restaurante:importar-mesas "plantilla de mesas.xlsx" --empresa=ID --dry-run

# 2) Si el resumen es correcto, ejecutar
php artisan restaurante:importar-mesas "plantilla de mesas.xlsx" --empresa=ID
```

Si el dry-run reporta zonas no encontradas, corregir nombres en Excel o en BD y repetir el dry-run.
