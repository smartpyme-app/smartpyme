# Diseño: Permisos para Planillas, Consignas, Restaurante y Pedidos

**Fecha:** 2026-08-05  
**Estado:** Aprobado — pendiente de plan de implementación  
**Tipo:** Seguridad, roles y navegación

---

## 1. Contexto y problema

El sistema combina permisos Spatie por rol/usuario, funcionalidades activadas por empresa y comprobaciones legacy de `users.tipo`. Esto produce reglas distintas entre sidebar, rutas Angular y API:

1. Planillas ya tiene permisos, pero el sidebar usa condiciones negadas (`!hasPermission`) y muestra el módulo a quien no tiene acceso.
2. Consignas no tiene permisos propios y está disponible para casi cualquier usuario autenticado.
3. Restaurante depende de la funcionalidad empresarial `modulo-restaurante`, pero no tiene permisos por rol.
4. Pedidos comparte activación empresarial con Restaurante, pero debe poder autorizarse por separado.
5. Las API de Planillas y Consignas no validan permisos Spatie.
6. Configuraciones del sidebar muestra opciones que el menú del header sí filtra.

## 2. Decisiones acordadas

- Planillas y Consignas se consideran siempre activos; su acceso depende de permisos.
- Restaurante y Pedidos requieren dos condiciones: funcionalidad empresarial activa y permiso efectivo del usuario.
- Restaurante y Pedidos tendrán permisos independientes.
- Administrador (`admin`), Supervisor normal (`usuario_supervisor`) y Contador superior (`contador_superior`) recibirán acceso predeterminado.
- El rol técnico de plataforma `super_admin` conservará acceso total.
- Supervisor limitado y Contador auxiliar no recibirán acceso predeterminado.
- Los demás roles solo accederán si se les asigna el permiso desde Roles y permisos.
- La autorización se aplicará en sidebar, rutas Angular y API; ocultar el menú no será el control de seguridad.
- Configuraciones del sidebar replicará las reglas del header.

## 3. Modelo de permisos

### 3.1 Planillas

Se reutilizan los 16 permisos existentes:

- `planilla.ver|crear|editar|eliminar`
- `planilla.empleados.ver|crear|editar|eliminar`
- `planilla.registros.ver|crear|editar|eliminar`
- `planilla.configuracion.ver|crear|editar|eliminar`

El permiso `planilla.ver` controla el acceso general. Los permisos de submódulo controlan sus páginas y operaciones. Aguinaldos y préstamos se protegerán con `planilla.registros.*`, porque forman parte del registro operativo de planilla y no necesitan permisos nuevos.

### 3.2 Consignas

Nuevo módulo de permisos:

- `consignas.ver`
- `consignas.crear`
- `consignas.editar`
- `consignas.eliminar`

### 3.3 Restaurante

Nuevo módulo de permisos:

- `restaurante.ver`
- `restaurante.crear`
- `restaurante.editar`
- `restaurante.eliminar`

### 3.4 Pedidos

Nuevo módulo independiente:

- `pedidos.ver`
- `pedidos.crear`
- `pedidos.editar`
- `pedidos.eliminar`

Los cuatro módulos aparecerán en la interfaz de Roles y permisos. Planillas conservará sus submódulos actuales.

## 4. Asignación predeterminada

Para conservar la capacidad operativa que hoy tienen los roles autorizados, los permisos nuevos y los permisos de Planillas se asignarán inicialmente completos a:

- `admin`
- `super_admin` (rol técnico de plataforma)
- `usuario_supervisor`
- `contador_superior`

No se asignarán automáticamente a:

- `supervisor_limitado`
- `contador_auxiliar`
- otros roles operativos

Después del despliegue, las asignaciones podrán modificarse normalmente desde Roles y permisos. Las revocaciones directas existentes seguirán teniendo prioridad sobre los permisos heredados del rol.

## 5. Backend

### Catálogo e instalaciones nuevas

- Agregar Consignas, Restaurante y Pedidos a `Backend/config/permissions.php`.
- Actualizar `RoleSeeder` para que instalaciones nuevas reciban los defaults acordados.

### Instalaciones existentes

Crear un seeder aditivo e idempotente que:

1. Use `firstOrCreate` para permisos, módulos y asociaciones del catálogo.
2. No trunque tablas ni ejecute el `PermissionSeeder` destructivo.
3. Asigne los defaults a los tres roles empresariales acordados y a `super_admin` cuando existan.
4. Limpie la caché de Spatie al finalizar.

### Protección API

- Aplicar `permission:` a Planillas y Consignas.
- Mantener `verificar.funcionalidad:modulo-restaurante` para Restaurante/Pedidos y añadir sus respectivos `permission:`.
- Usar `.ver` para consultas, `.crear` para altas, `.editar` para cambios y `.eliminar` para eliminaciones.
- Una respuesta sin autorización será HTTP 403.

## 6. Frontend

### Sidebar

- Corregir las condiciones negadas de Planillas.
- Mostrar cada módulo solo con su permiso `.ver`.
- Restaurante y Pedidos también requieren que la funcionalidad y la modalidad configurada para la empresa permitan mostrar cada vista.
- Las acciones internas deben respetar permisos de crear, editar y eliminar cuando ya exista un control visible para ellas.

### Rutas Angular

- Añadir `PermissionGuard` a Planillas, Consignas, Restaurante y Pedidos.
- Mantener los guards existentes; Restaurante/Pedidos conservarán además la validación de funcionalidad.
- Un acceso directo sin permiso redirigirá fuera del módulo y mostrará el aviso estándar.

### Configuraciones

El grupo del sidebar se mostrará si al menos una opción hija está permitida:

- Usuarios: `organizacion.usuarios.ver`
- Sucursales: `administracion.sucursales.ver`
- Mi suscripción: solo rol `admin`
- Mi cuenta: cualquier usuario autenticado

Esto replica el comportamiento del header y elimina el filtro general por `users.tipo`.

## 7. Flujo de autorización

1. Al iniciar sesión se cargan permisos efectivos: rol + permisos directos − revocaciones.
2. El sidebar usa esos permisos para visibilidad.
3. `PermissionGuard` evita navegación directa no autorizada.
4. La API vuelve a validar el permiso, que es la barrera definitiva.
5. Para Restaurante/Pedidos, la empresa debe tener además la funcionalidad activa y la modalidad correspondiente habilitada.

## 8. Pruebas

### Backend

- Seeder aditivo idempotente: dos ejecuciones no duplican registros ni eliminan asignaciones.
- Roles predeterminados reciben permisos; roles excluidos no.
- Usuario sin permiso recibe 403 en Planillas, Consignas, Restaurante y Pedidos.
- Usuario con permiso accede.
- Restaurante/Pedidos devuelven 403 si la funcionalidad empresarial no está activa aunque exista permiso.

### Frontend

- Sidebar muestra u oculta cada módulo según permiso.
- Planillas deja de usar condiciones invertidas.
- Restaurante y Pedidos se controlan de forma independiente.
- Guards bloquean URL directa sin permiso.
- Configuraciones muestra exactamente las opciones autorizadas como el header.

## 9. Despliegue

1. Desplegar backend y frontend.
2. Ejecutar únicamente el nuevo seeder aditivo.
3. Limpiar caché de permisos/configuración.
4. Reiniciar workers persistentes.
5. Verificar con un usuario de cada rol predeterminado y uno operativo sin permisos.

No se debe ejecutar `PermissionSeeder` en producción porque actualmente trunca y reconstruye las tablas de permisos.

## 10. Criterios de aceptación

1. Planillas, Consignas, Restaurante y Pedidos aparecen en Roles y permisos.
2. Admin, Supervisor normal y Contador superior tienen acceso predeterminado.
3. Otros usuarios no ven ni pueden abrir o invocar esos módulos hasta recibir permiso.
4. Restaurante y Pedidos se autorizan por separado y siguen respetando su activación empresarial.
5. El sidebar, las rutas Angular y las API aplican reglas equivalentes.
6. Configuraciones del sidebar coincide con el header.
7. El despliegue no elimina roles, permisos, revocaciones ni asignaciones existentes.
