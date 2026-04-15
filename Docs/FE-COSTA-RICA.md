# Facturación electrónica — Costa Rica (Hacienda / DGT)

Documento orientado a **validar el funcionamiento** del módulo FE para empresas con país **Costa Rica** en SmartPyME. Actualizado según la implementación actual del repositorio.

---

## 1. Estado actual (resumen)

| Área | Estado |
|------|--------|
| Emisión factura / crédito fiscal (tipo **01**) | Implementado |
| Emisión tiquete electrónico (**04**) | Implementado (según nombre de documento Ticket/Tiquete) |
| Nota de crédito (**03**) vía devolución de venta | Implementado |
| Nota de débito (**02**) sobre factura ya aceptada | Implementado (desde modal en listado de ventas) |
| Autenticación ATV + certificado **.p12** | Servidor (Laravel); no hay token MH en el navegador |
| Configuración en cuenta (usuario ATV, clave .p12, subida certificado, prueba conexión) | Implementado |
| Consulta de estado en Hacienda tras emisión | Implementado (`consultarFeCrVenta`) |
| Login sin API de El Salvador para empresas CR | Implementado |
| Post-emisión sin llamar a `enviarDTE` de MH (El Salvador) | Implementado |
| Contingencia / invalidación estilo MH | No aplica a CR en el mismo flujo |
| Anulación “tributaria” automática ante Hacienda | **No** implementada; anulación en sistema es operativa, con aviso al usuario |
| PDF/XML oficiales específicos DGT en reportes | Por validar / ajustar según necesidad del negocio |
| Libros y anexos contables con criterio CR | Revisar con datos reales |

---

## 2. Requisitos previos (datos y ambiente)

1. **Empresa** con `cod_pais` **CR** (o país reconocido como Costa Rica en configuración).
2. Activar **Facturación electrónica** en la empresa y el **modo** Pruebas (`00`) o Producción (`01`).
3. **Usuario y contraseña** del ATV / portal de comprobantes electrónicos (mismos campos que se usan para MH en SV, pero en CR solo los usa el backend contra DGT).
4. **Certificado digital .p12** y su **contraseña**, subidos desde **Cuenta → Facturación electrónica** (Costa Rica).
5. **Probar conexión con Hacienda** en esa misma pestaña (guarda credenciales y valida contra el ambiente elegido).
6. **CABYS por defecto (empresa)**: en **Cuenta → Datos**, si la empresa es **Costa Rica**, el bloque **Actividad económica** es un buscador CABYS (misma API). Se reutilizan **`cod_actividad_economica`** (código 13 dígitos) y **`giro`** (descripción oficial). Si un **producto** no trae CABYS, el backend usa este valor (antes que `cabys_default` en JSON solo si este último no está definido; ver orden en mapper).
7. **Productos**: en **Inventario → producto → Detalles**, búsqueda CABYS con guardado en **`codigo_cabys`** / **`descripcion_cabys`**. Alternativas: `producto.codigo` con 13 dígitos; fallback empresa según el punto anterior.
8. **Secuenciales** por tipo de comprobante: se llevan en `custom_empresa.facturacion_fe` (factura, tiquete, NC, ND); deben alinearse con lo declarado ante Hacienda.

*(El CABYS por defecto de empresa en CR usa los campos actividad económica + giro; otros opcionales como `cabys_default` en JSON o tipo de cambio manual pueden seguir existiendo en `custom_empresa` si ya se configuraron.)*

### 2.1 Catálogos Hacienda (API propia, con caché)

El backend expone un **proxy** a [api.hacienda.go.cr](https://api.hacienda.go.cr/docs/) con caché para respetar límites de uso. Requiere el mismo **JWT** que el resto de la API (`Authorization: Bearer …`).

| Método | Ruta | Parámetros (query) |
|--------|------|--------------------|
| GET | `/api/fe-cr/cabys` | `codigo` (13 dígitos) **o** `q` (texto, mín. 3 caracteres); opcional `top` (1–50, default 15) con `q`. |
| GET | `/api/fe-cr/contribuyente` | `identificacion` (9–12 dígitos). Equivale a `/fe/ae`. |
| GET | `/api/fe-cr/exoneracion` | `autorizacion` formato `AL-XXXXXXXX-XX`. Equivale a `/fe/ex`. |
| GET | `/api/fe-cr/tipo-cambio-dolar` | Sin parámetros. Equivale a `/indicadores/tc/dolar`. |

La respuesta HTTP y el cuerpo JSON son los que devuelve Hacienda cuando la consulta es correcta. Errores **429** u otros no se cachean. TTLs configurables en `config/services.php` → `hacienda_cr.cache` (y variables `HACIENDA_CR_*` en `.env`).

---

## 3. Pasos de uso para validación

### 3.1 Configuración inicial (administrador)

1. Iniciar sesión con una empresa **Costa Rica**.
2. Ir a **Cuenta → Facturación electrónica**.
3. Completar usuario/contraseña ATV, contraseña del .p12, modo Pruebas o Producción.
4. Subir el archivo **.p12**.
5. **Guardar** (pestaña Datos) o usar **Comprobar conexión con Hacienda**.
6. Confirmar mensaje de conexión exitosa.

### 3.2 Factura o crédito fiscal (01)

1. Crear venta con documento tipo **Factura** o **Crédito fiscal** (según catálogo de documentos de la sucursal).
2. Completar cliente y líneas con **CABYS** válido en el producto.
3. Guardar / finalizar venta según flujo habitual.
4. Desde **Ventas** (menú acciones) o **Caja**, abrir **Emitir DTE / comprobante**.
5. **Emitir comprobante**. Si Hacienda no acepta de inmediato, usar **Consultar estado en Hacienda** más tarde.
6. Revisar en **detalle de venta** la clave y el estado (aceptado / pendiente).

### 3.3 Tiquete (04)

1. Usar documento de venta cuyo nombre incluya **Ticket** o **Tiquete**.
2. Mismo flujo de emisión que la factura; el backend elige endpoint de tiquete.

### 3.4 Nota de crédito (03)

1. Registrar **devolución** ligada a una venta que **ya tenga** comprobante electrónico **aceptado** en CR.
2. En **Devoluciones de ventas**, emitir el comprobante electrónico (nota de crédito).

### 3.5 Nota de débito (02)

1. Partir de una **factura / crédito fiscal** con comprobante **aceptado** por Hacienda.
2. En **Ventas → acciones → Ver / Emitir comprobante**, en el modal, sección **Nota de débito electrónica**.
3. Indicar **motivo** (opcional) y **monto total en colones con IVA incluido** (lógica actual asume tarifa general 13 %).
4. Debe existir **`custom_empresa.facturacion_fe.cabys_default`** (13 dígitos) o equivalente que use el backend para la línea de la ND (la pantalla de cuenta ya no expone ese campo por ahora).
5. **Emitir nota de débito** y, si hace falta, consultar estado otra vez.

### 3.6 Anulación de venta (operativa)

- Con comprobante aceptado en CR, **Anular venta** anula el registro en el sistema y advierte que lo **tributario** debe resolverse según Hacienda; no es el flujo de invalidación de DTE de El Salvador.

---

## 4. Qué falta o conviene revisar

| Tema | Comentario |
|------|------------|
| **Rectificaciones / anulación ante Hacienda** | No automatizado; depende de procesos y APIs/reglas DGT que el negocio quiera integrar después. |
| **Reportes PDF impresos** | Validar que el formato comercial muestre clave, consecutivo y leyendas que el cliente final espere en CR. |
| **Correo electrónico del comprobante** | El envío por correo del flujo MH no se usa en CR; definir si se desea otro canal/plantilla. |
| **Nota de débito desde otros puntos** | Hoy principalmente desde el modal del listado de ventas; unificar si hace falta desde “devolución tipo ND”. |
| **Concurrencia** | Varios usuarios emitiendo a la vez: validar secuenciales y transacciones en escenario real. |
| **Casos exóticos de IVA / exoneración** | El ND de ejemplo usa tarifa general; líneas mixtas pueden requerir más reglas en el mapper. |

---

## 5. Checklist rápido para el validador (Costa Rica)

- [ ] Empresa CR, FE activada, modo **Pruebas** para las primeras pruebas.
- [ ] Certificado .p12 válido para ese ambiente y credenciales ATV correctas.
- [ ] Prueba de conexión exitosa en cuenta.
- [ ] Al menos un producto con **CABYS** correcto (13 dígitos).
- [ ] Emisión **01** aceptada (o clave generada + consulta hasta aceptado).
- [ ] Emisión **04** si el negocio usa tiquetes.
- [ ] **03** desde devolución sobre venta ya aceptada.
- [ ] **02** sobre factura aceptada, con monto y CABYS/respaldo que exija el backend para la ND.
- [ ] Consulta de estado tras una emisión “pendiente”.
- [ ] Revisar detalle de venta: clave y estado coherente con Hacienda.
- [ ] (Opcional) Impresión / PDF desde el sistema según formato usado por la empresa.

---

## 6. Solución de problemas

### 6.1. `OpenSSL command failed` / `"openssl" no se reconoce como un comando...` (Windows)

La librería **dgt-cr-signer** usa el ejecutable **`openssl`** (CLI) para abrir el `.p12`, no solo la extensión PHP `openssl`. En Windows hace falta tener **OpenSSL instalado y en el PATH**.

**Opciones habituales:**

1. **Git for Windows** suele traer `openssl.exe` en  
   `C:\Program Files\Git\usr\bin`  
   Añada esa carpeta a la variable de entorno **PATH** del sistema (o del usuario que ejecuta PHP) y **reinicie** el terminal / servicio PHP (Laravel Valet, `php artisan serve`, IIS, etc.).

2. Instale **Win64 OpenSSL** desde el instalador oficial (p. ej. Shining Light) y añada a PATH la carpeta `bin` donde quede `openssl.exe`.

3. **WSL2 o Docker** con Linux: allí `openssl` suele estar instalado por defecto; ejecute el backend en ese entorno.

Compruebe en una consola nueva (como el mismo usuario que corre PHP):

```bat
where openssl
openssl version
```

Si no aparece la ruta, FE CR seguirá fallando al probar conexión o al firmar.

---

### 6.2. `Class "DazzaDev\DgtCr\Client" not found`

Eso significa que **Composer no cargó** la librería `dazza-dev/dgt-cr` en ese entorno.

En la carpeta **Backend** del servidor (donde corre la API), ejecute:

```bash
composer install
# o, si ya había vendor desactualizado:
composer update dazza-dev/dgt-cr dazza-dev/laravel-dgt-cr
composer dump-autoload -o
```

Compruebe que exista `Backend/vendor/dazza-dev/dgt-cr/src/Client.php`. Si el despliegue **no sube** la carpeta `vendor` (recomendado), **siempre** debe ejecutarse `composer install` en el servidor tras cada deploy. Si usa PHP-FPM/Octane, reinicie el worker tras instalar.

---

### 6.3. Búsqueda CABYS devuelve HTML / “Estimado usuario… no ha podido ser procesada”

Hacienda suele **bloquear la IP del servidor** (hosting/VPS) aunque el catálogo sea público. La API expone **`Access-Control-Allow-Origin: *`**, por lo que el **navegador del usuario** puede llamar a `https://api.hacienda.go.cr/fe/cabys` directamente.

En el front, el buscador CABYS (empresa y producto) **prioriza esa llamada directa** (HTTP sin pasar por el JWT del backend) y solo si falla usa el proxy `/api/fe-cr/cabys`. Si aun así falla:

1. Reducir frecuencia de búsquedas (debounce, caché en servidor del proxy).
2. Probar otra red.
3. Opcional `.env` backend: `HACIENDA_CR_USER_AGENT` (ver `config/services.php`).
4. Contactar `facturati@hacienda.go.cr` / `seguridaddigital@hacienda.go.cr` si el bloqueo es sistemático incluso desde el navegador.

---

## 7. Referencias técnicas (código)

- **Backend:** `Backend/app/Services/FacturacionElectronica/CostaRica/`, rutas en `routes/modulos/admin/cr-fe.php`.
- **Frontend:** `Frontend/src/app/services/facturacion-electronica/`, orquestador `facturacion-electronica.service.ts`, pantalla **Cuenta → Facturación electrónica**, **Ventas**, **Devoluciones**, **Caja → ventas**.
- **Paquete DGT:** `dazza-dev/dgt-cr` (vendor).

---

*Este archivo sirve como guía de validación funcional; no sustituye asesoría fiscal ni la documentación oficial del Ministerio de Hacienda de Costa Rica.*
