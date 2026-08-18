# Diseño: Preferencias del sistema — grupos y filas limpias

**Fecha:** 2026-08-18  
**Estado:** Aprobado  
**Tipo:** Rediseño de UI (misma pestaña, mismo API)  
**Enfoque:** Menú interno por grupo + filas sin cajas. Sin componentes nuevos, sin endpoint nuevo.

---

## 1. Problema

La pestaña **Preferencias del sistema** (`Cuenta`) es un scroll único. Cada switch va en una caja azul; en Facturación hay doble recuadro. Opciones de módulos, impresión, inventario y permisos están mezcladas bajo Facturación. Cuesta escanear y el botón Guardar queda al final.

## 2. Objetivos

1. Mostrar **un grupo a la vez**, con navegación interna.
2. Reordenar opciones por tema (no por orden histórico del HTML).
3. Quitar cajas por opción: fila título + control.
4. Guardar visible sin scrollear hasta el fondo.
5. Recordar el grupo en la URL.
6. No cambiar qué se guarda ni cuándo.

## 3. Fuera de alcance

- Pestañas de Cuenta (Datos, FE, Integraciones, WooCommerce, Shopify, BoxFul).
- Buscador de preferencias.
- Extraer un componente hijo.
- Cambiar `onSubmit()`, toggles que ya guardan al cambiar, o el payload `empresa`.
- Wizard o acordeones.
- Iconos decorativos en el menú interno.

## 4. Decisiones

| Tema | Decisión |
|------|----------|
| Navegación | Lista vertical a la izquierda (desktop); pills horizontales (móvil, `< lg`) |
| Contenido | Solo el grupo activo (`*ngIf`) |
| Filas | Título + ayuda opcional a la izquierda; switch/select a la derecha; borde inferior, sin `bg-light-info` ni `empresa-pref-tile` |
| Guardar | Barra sticky al fondo del panel; F8 igual que hoy |
| Persistencia grupo | Query param `grupo` junto a `tab` |
| Default | `modulos` |
| Historial | `replaceUrl: true` al cambiar grupo (igual que las pestañas de Cuenta) |
| Archivos | `empresa.component.html`, `.ts`, `.css` |
| Permisos | Ítem de menú solo para Administrador / Super Admin |

## 5. Grupos y contenido

Slugs: `modulos` | `documentos` | `facturacion` | `inventario` | `permisos` | `cuenta`.

Visibilidad condicional de cada control **igual que hoy** (plan Pro, funcionalidades, país, etc.). Si un control estaba oculto, sigue oculto dentro de su grupo.

### Módulos (`modulos`)

- Módulo de citas
- Módulo de proyectos (plan Pro)
- Módulo de paquetes (plan Pro)
- Restaurantes y pedidos (si hay funcionalidad)
- Mostrar columna proyecto
- Habilitar módulo de bancos
- Activar fidelización de clientes (si hay funcionalidad)
- Habilitar categorías de gastos personalizadas

### Documentos e impresión (`documentos`)

- Mostrar ticket en PDF
- Descripción de producto en PDF DTE
- Mostrar sello y firma en órdenes de compra
- Mostrar sello y firma en cotizaciones
- Subida de sello y firma (si alguno de esos dos switches está activo)
- Mostrar descripción en cotizaciones
- Mostrar imágenes de productos en cotizaciones
- Imprimir en facturación
- Mostrar nota del documento al imprimir

### Facturación (`facturacion`)

- Cobrar IVA
- Vender sin stock
- Editar precio
- Agrupar detalles
- Editar descripciones
- Versión de facturación (select)
- Bloquear edición de correlativo
- Permitir editar tipo de cambio en ventas (si multimoneda)
- Mostrar campos contables
- Vendedor por detalle
- Ventas pueden cambiar vendedor al facturar
- Cambiar tipo de impuesto en venta
- Monto mínimo retención IVA (input)
- Facturación electrónica
- Venta a consigna
- Habilitar estado de cuenta en facturación

### Inventario y productos (`inventario`)

- Gestión de productos por vendedores
- Activar módulo de lotes + metodología + días de alerta + activación masiva (si lotes activo)
- Habilitar componente químico
- Módulo de presentaciones (si hay funcionalidad)
- Código de barras correlativo automático
- Mostrar total de stock en búsquedas
- Habilitar transformación de productos (si hay funcionalidad)
- Reporte Excel inventario vs ventas
- Valor de inventario (select)

### Permisos (`permisos`)

Solo Administrador / Super Admin. Si la URL pide este grupo y el usuario no califica, caer a `modulos` y corregir la URL.

- Bloquear a vendedores facturar y gestionar cotizaciones
- Supervisor limitado no puede generar gastos
- Supervisor limitado con acceso restringido a compras y órdenes de compra

### Cuenta (`cuenta`)

- Limpiar cache y cerrar sesión
- Enlace a eliminar datos

## 6. Layout

Dentro de la pestaña Preferencias, un `section` blanco:

```
[ Módulos                    ] [ título del grupo ]
[ Documentos e impresión     ] [ fila ]
[ Facturación            ●   ] [ fila ]
[ Inventario y productos     ] [ ... ]
[ Permisos                   ]
[ Cuenta                     ]
                               [ Guardar ]  ← sticky
```

- Menú: botones `type="button"`. Activo: fondo azul del sistema y `aria-current="page"`. Sin translate ni box-shadow (no copiar el estilo pesado de partidas).
- Móvil: el menú pasa arriba, pills en fila con scroll horizontal si no caben.
- Cada fila switch: no usar `form-switch bg-light-info rounded` como tarjeta. Título + ayuda (`small text-muted`) a la izquierda; switch a la derecha. No reescribir copy.
- Selects e inputs (versión facturación, retención, valor inventario, lotes): título + ayuda arriba, control a ancho del panel de contenido; sin tile.
- Un solo `<form>` rodea grupos + Guardar, como hoy.

## 7. URL y estado

Ya existe `?tab=preferencias`. Se agrega `grupo`.

Ejemplo: `?tab=preferencias&grupo=facturacion`

- Leer `grupo` al iniciar y en el `queryParamMap` (además de `tab`).
- Función pura `resolverGrupoPreferencias(slug, puedeVerPermisos)`:
  - slug válido y permitido → ese slug;
  - `permisos` sin permiso, vacío, o desconocido → `modulos`.
- `setPreferenciasGrupo(slug)` actualiza estado y `router.navigate` con `queryParamsHandling: 'merge'` y `replaceUrl: true`.
- Al cambiar a otra pestaña de Cuenta, `grupo` puede quedarse en la URL. Al volver a Preferencias se restaura.
- No hace falta tocar el backend.

Guardar sigue siendo `apiService.store('empresa', this.empresa)`. Los grupos no montados no afectan: el modelo vive en `this.empresa` / `customConfig`.

## 8. Archivos a tocar

- `Frontend/src/app/views/admin/empresa/empresa.component.html` — reordenar el tab Preferencias.
- `Frontend/src/app/views/admin/empresa/empresa.component.ts` — grupo activo, resolver, sync URL.
- `Frontend/src/app/views/admin/empresa/empresa.component.css` — nav, filas, sticky; retirar o dejar de usar `.empresa-pref-tile`.
- Un chequeo de `resolverGrupoPreferencias` (slug válido, inválido, permisos).

## 9. Prueba

Un assert sobre el resolver:

1. `facturacion` + admin → `facturacion`
2. `permisos` + no admin → `modulos`
3. `null` / `foo` → `modulos`

No se pide e2e de toda la pestaña.

## 10. No regresiones

- Condiciones `*ngIf` / `@if` de plan, rol y funcionalidades, iguales.
- Textos i18n (`country.tax.*`) se reubican, no se reescriben.
- Subida de sello/firma sigue dependiendo de los dos switches de sello.
- El tab Facturación electrónica sigue apareciendo según `empresa.facturacion_electronica`.
