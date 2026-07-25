# Diseño: Bloquear edición de correlativo (ventas / compras)

**Fecha:** 2026-07-23  
**Estado:** Implementado  
**Tipo:** Nueva preferencia de sistema  
**Fuera de alcance:** Bloqueo al crear venta/compra; pantallas de recurrentes; facturación create/v2 create

---

## 1. Contexto y problema

Al editar una venta o una compra desde el listado, el usuario puede cambiar el número de documento (`correlativo` en ventas, `referencia` en compras). Algunas empresas necesitan impedir ese cambio para evitar inconsistencias de numeración.

Se requiere una preferencia de sistema que, al activarse, deje esos campos en solo lectura en los modales de edición y que el backend no acepte el cambio.

---

## 2. Objetivos

1. Agregar un switch en **Mi cuenta → Preferencias del sistema**.
2. Si está activo: campos readonly en modales **Editar venta** y **Editar compra**.
3. Si está inactivo: comportamiento actual (editables).
4. Enforzar lo mismo en el API al actualizar venta/compra.

---

## 3. Decisiones acordadas

| Tema | Decisión |
|------|----------|
| Alcance | Solo **edición** (no crear) |
| Ventas | Bloquear campo `correlativo` |
| Compras | Bloquear campo `referencia` (número de documento en el modal) |
| Almacenamiento | `custom_empresa.configuraciones` (mismo patrón que otros `bloquear_*`) |
| Clave | `bloquear_edicion_correlativo` (default `false`) |
| Enfoque | UI + backend (opción B) |
| Backend al violar | Restaurar el valor original y guardar el resto (sin tumbar el edit por un campo bloqueado) |
| Recurrentes / create | Fuera de alcance por ahora |

---

## 4. Arquitectura

### 4.1 Preferencia (persistencia)

- Clave booleana: `bloquear_edicion_correlativo`.
- Ubicación: `empresas.custom_empresa.configuraciones`.
- Defaults:
  - `EmpresaComponent.initializeCustomConfig()` / getters en frontend.
  - `Empresa::initializeCustomConfig` (o equivalente) en backend.
- Allowlist: agregar a `EmpresasController::$booleanConfigs` en `validateConfiguracionConfig`.
- UI: switch en Preferencias del sistema, sección de facturación / documentos, con `(change)="onSubmit()"` como el resto.

Texto sugerido del switch:

> **Bloquear edición de correlativo** — Impide modificar el correlativo al editar una venta y la referencia al editar una compra.

### 4.2 Lectura en frontend

- Helper en `ApiService`, p. ej. `empresaBloqueaEdicionCorrelativo(): boolean`, leyendo  
  `auth_user().empresa.custom_empresa.configuraciones.bloquear_edicion_correlativo === true`.
- Tras guardar preferencias, `SP_auth_user.empresa` ya se actualiza con el flujo actual de `onSubmit()` en `EmpresaComponent`.

### 4.3 UI de edición

| Pantalla | Archivo | Campo | Comportamiento |
|----------|---------|-------|----------------|
| Editar venta (`#mventa`) | `ventas.component.html` | `venta.correlativo` | `[readonly]="apiService.empresaBloqueaEdicionCorrelativo()"` (o `[attr.readonly]` / disabled según patrón local) |
| Editar compra (`#mcompra`) | `compras.component.html` | `compra.referencia` | Igual |

No tocar: facturación create, ventas-v2 create, compra facturación create, recurrentes.

### 4.4 Backend

Leer la preferencia con  
`$empresa->getCustomConfigValue('configuraciones', 'bloquear_edicion_correlativo', false)`.

**Ventas** — `VentasController::store` (modal de edición del listado):

- Si la preferencia está activa y el request trae `correlativo` distinto al de la venta existente, forzar `$request`/`$venta` a conservar el correlativo original antes de `fill`/`save`.

**Compras** — `ComprasController::store` (modal de edición del listado):

- Igual para `referencia`.

No aplicar en create (cuando no hay registro previo / no hay `id`).  
No cambiar en este ticket `*/facturacion` create flows.

Opcional de DX: helper en modelo `Empresa`, p. ej. `bloqueaEdicionCorrelativo(): bool`, para no repetir el string de la clave.

---

## 5. Flujo

```
Preferencias: activar bloquear_edicion_correlativo
        ↓
Se guarda en custom_empresa.configuraciones
        ↓
Listado → Editar venta/compra
        ↓
FE: correlativo/referencia readonly
        ↓
POST /venta o /compra
        ↓
BE: si flag on y valor distinto → restaurar original → save resto de campos
```

---

## 6. Pruebas manuales

1. Preferencia **off**: editar venta y compra → correlativo/referencia editables y se guardan.
2. Preferencia **on**: campos readonly en ambos modales.
3. Preferencia **on** + request manipulado (DevTools) cambiando correlativo/referencia → al guardar, el número original se conserva.
4. Crear venta/compra nueva → correlativo/referencia siguen editables (fuera de alcance del bloqueo).

---

## 7. Fuera de alcance (explícito)

- Bloqueo al crear documentos.
- Ventas/compras recurrentes.
- Catálogo de tipos de documento (`documentos.correlativo`).
- Migración de columna dedicada en `empresas`.
