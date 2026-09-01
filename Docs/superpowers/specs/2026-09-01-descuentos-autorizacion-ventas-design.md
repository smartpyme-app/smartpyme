# Diseño: Permisos y PIN de descuentos en facturación

**Fecha:** 2026-09-01  
**Estado:** Aprobado en conversación; pendiente revisión final del archivo  
**Tipo:** Seguridad, roles, facturación

---

## 1. Problema

Hoy cualquier usuario que puede facturar aplica descuentos de línea sin control. El catálogo de Roles y Permisos de Ventas solo tiene CRUD por submódulo; no hay acción de descuentos. Existe un módulo de autorizaciones con `ventas_descuento_alto`, pero está apagado (`requiresAuthorization` siempre retorna false) y no se usa en caja.

Se necesita: (1) que el permiso salga en Roles y Permisos de Ventas, y (2) que un cajero sin permiso libre pida PIN de un supervisor en la misma caja al facturar.

## 2. Objetivos

1. Dos permisos nuevos visibles en Ventas → Descuentos: `aplicar` y `autorizar`.
2. Al desplegar, el flujo de caja **no cambia**. El PIN solo aparece cuando un admin quita `aplicar` al cajero y alguien tiene `autorizar`.
3. Sin `aplicar`, cualquier descuento de línea (> 0) exige PIN al facturar.
4. El API rechaza la venta si hay descuento y no hay autorización válida. El front no es el control de seguridad.
5. Un PIN por venta, no por línea.

## 3. Fuera de alcance

- Cotizaciones.
- Canje de puntos y promociones automáticas.
- Facturación consigna.
- Reactivar el módulo de autorizaciones (solicitudes pendientes, correos, Tipos de autorización).
- Copiar el PIN de “vender sin stock” (hoy se compara en el navegador contra el código del cajero).
- Umbral de porcentaje o monto: cualquier descuento de línea cuenta.

## 4. Decisiones

| Decisión | Elección |
|----------|----------|
| Enfoque | Permisos Spatie + PIN al facturar |
| Permisos | `ventas.descuentos.aplicar` y `ventas.descuentos.autorizar` |
| Sin `aplicar` | Cualquier descuento de línea pide PIN |
| Quién aprueba | Usuario de la misma empresa con `autorizar` + `codigo_autorizacion` |
| Dónde | Facturación tienda, tienda v2 y POS (POS hereda de v2) |
| Cuándo | Al cobrar (`POST venta`), un PIN para todos los descuentos de esa venta |
| Default al instalar | Quien hoy puede vender recibe `aplicar`. Caja igual que ahora |
| Módulo autorizaciones | No se enciende |

## 5. Permisos y roles

### 5.1 Catálogo

En `Backend/config/permissions.php`, dentro de `PERMISSION_VENTAS`, submódulo `descuentos`:

- `ventas.descuentos.aplicar`
- `ventas.descuentos.autorizar`

En Roles y Permisos se muestran como submódulo **Descuentos**, labels `aplicar` y `autorizar` (último segmento del nombre, igual que el resto).

### 5.2 Significado

- **`aplicar`:** el usuario cobra ventas con descuento de línea sin modal ni credenciales extra.
- **`autorizar`:** el usuario puede ingresar su usuario + código en el modal de otro (o el suyo si él factura sin `aplicar`).
- Tener `autorizar` y no `aplicar` no salta el PIN: debe escribir su propio usuario y código.

### 5.3 Default (no romper caja)

Al crear los permisos (instalación nueva o seeder aditivo):

- `aplicar` se asigna a todo rol que ya tenga `ventas.registros.crear` (incluye `usuario_ventas`). Así el cajero sigue descontando solo.
- `autorizar` se asigna a `admin`, `super_admin`, `usuario_supervisor` y `gerente_ventas`.
- `usuario_ventas` **no** recibe `autorizar`.

`RoleSeeder` hoy recorre todo `PERMISSION_VENTAS` y se lo da a `usuario_ventas`. Hay que **excluir** `ventas.descuentos.autorizar` de ese recorido. `aplicar` sí entra, para no cambiar el flujo.

`admin` y `super_admin` reciben ambos porque ya toman todos los permisos.

Para activar el control en un local: en Roles y Permisos, quitar `aplicar` al rol del cajero y dejar `autorizar` en el del supervisor.

### 5.4 Seeder de instalaciones existentes

Seeder aditivo e idempotente (`firstOrCreate`), mismo patrón que `ModulosOperativosPermissionSeeder`. No truncar ni ejecutar el `PermissionSeeder` destructivo. Crear módulo/submódulo Ventas → Descuentos, los dos permisos Spatie, `module_permissions`, asignar defaults de 5.3 y limpiar caché Spatie.

## 6. Flujo al facturar

El cajero arma la venta y edita descuentos en las líneas. El PIN no sale al teclear.

Al cobrar (`POST venta`):

1. Si el usuario tiene `ventas.descuentos.aplicar` → guardar como hoy. No hace falta payload de autorización.
2. Si no hay descuento de línea > 0 → guardar como hoy, aunque no tenga `aplicar`.
3. Si hay descuento de línea y no tiene `aplicar` → modal: email del supervisor (el mismo del login) + `codigo_autorizacion`. Esas credenciales van en el mismo `POST venta`.
4. El API valida. Si falla → **403**, no se crea la venta.
5. Si pasa → se guarda `id_usuario_autorizo_descuento`. El código no se persiste.

**Descuento de línea:** en el payload, alguna línea con `descuento`, `descuento_porcentaje` o `descuento_monto` > 0. No cuenta `descuento_puntos` ni promociones automáticas.

**Clientes:** el modal solo en facturación tienda, tienda v2 y POS. POS extiende v2: interceptar el `store('venta')` de v2 cubre POS. El API aplica la regla a **cualquier** `POST venta` (recurrentes, MH, caja, etc.): sin `aplicar` y con descuento de línea, 403 si no vienen credenciales válidas.

## 7. API

Validación en el store de venta (el que usa `POST venta`). Misma regla si ese flujo también actualiza una venta abierta con descuentos nuevos.

Credenciales (nombres exactos del payload):

```json
"descuento_autorizacion": {
  "usuario": "supervisor@empresa.com",
  "codigo": "1234"
}
```

`usuario` es el **email** de login del supervisor (`users.email`).

Reglas cuando el cajero **no** tiene `aplicar` y hay descuento de línea:

1. `descuento_autorizacion.usuario` y `codigo` son obligatorios.
2. El usuario existe (lookup por email), pertenece a la misma empresa que el cajero, y tiene permiso efectivo `ventas.descuentos.autorizar` (rol o permiso directo; las revocaciones Spatie existentes siguen valiendo).
3. `codigo` coincide con `users.codigo_autorizacion`. Comparación en servidor. Respuesta genérica si falla (no distinguir “usuario no existe” vs “código malo”).
4. Si el supervisor no tiene código configurado → 403 con mensaje de que falta código de autorización.

Si el cajero **tiene** `aplicar`, se ignora `descuento_autorizacion` (no se guarda autorizador).

Columna nullable `ventas.id_usuario_autorizo_descuento` (FK a `users`, `nullOnDelete`).

No se usa `AuthorizationService`, `HasAutoAuthorization` ni tipos `ventas_descuento_alto`.

## 8. Frontend

Modal al facturar, no al editar la línea. Campos: email del supervisor y código (password). Cancelar o cerrar: no se llama a `POST venta`.

Error de PIN o 403: alerta, la venta no se guardó; puede reintentar o quitar descuentos y cobrar.

No comparar el código en el cliente. No reutilizar el SweetAlert de stock insuficiente.

## 9. Errores

| Caso | Resultado |
|------|-----------|
| Tiene `aplicar` + descuento | 200, sin modal |
| Sin `aplicar`, sin descuento de línea | 200, sin modal |
| Sin `aplicar` + descuento, sin payload | 403, no hay venta |
| PIN o usuario inválido, otra empresa, sin `autorizar` | 403 genérico, no hay venta |
| Supervisor sin `codigo_autorizacion` | 403, mensaje de código no configurado |
| Cancelar modal | No se envía la venta |

## 10. Pruebas

- Cajero con `aplicar` + descuento → 200, `id_usuario_autorizo_descuento` null.
- Cajero sin `aplicar` + descuento, sin PIN → 403.
- Cajero sin `aplicar` + PIN de alguien con `autorizar` de la misma empresa → 200 y queda el id del autorizador.
- PIN malo, usuario de otra empresa, o sin `autorizar` → 403.
- Sin descuento de línea, sin `aplicar` → 200.
- Seeder aditivo: permisos y defaults idempotentes; `usuario_ventas` tiene `aplicar` y no `autorizar`.

Una prueba de API que falle si la regla se rompe. No hace falta suite de UI.

## 11. Cómo se activa en un local

1. Desplegar código + correr seeder aditivo. Caja sigue igual.
2. En Roles y Permisos, rol del cajero: desmarcar Descuentos → `aplicar`.
3. Confirmar que el supervisor tiene Descuentos → `autorizar` y un código de autorización en su usuario.
4. A partir de ahí, al facturar con descuento, el cajero pide usuario + código del supervisor.
