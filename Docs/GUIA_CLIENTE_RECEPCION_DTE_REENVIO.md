# Guía: recibir facturas electrónicas por reenvío

SmartPyme puede importar DTE de dos maneras:

1. **Conectar Gmail** (SmartPyme lee su bandeja, con su permiso).
2. **Reenvío automático** (usted reenvía los correos a una dirección de SmartPyme; no damos acceso a su bandeja).

Esta guía es el método 2. Puede usar ambos a la vez. Los documentos llegan a la misma bandeja de DTEs.

**Pantalla:** menú DTEs → **Cuentas de correo**.

---

## 1. Activar en SmartPyme

1. Entre a **Cuentas de correo**.
2. Pulse **Activar reenvío automático**.
3. Copie la dirección que aparece (termina en `@ingest.smartpyme.site`).
4. Deje esa dirección **solo** para el reenvío. No la use como correo de la empresa.

Estado **Activo** = SmartPyme acepta correos. **Pausado** = los rechaza. **Desactivar** apaga el método. **Regenerar dirección** cambia el correo: hay que actualizar el filtro en Gmail u Outlook.

En la tarjeta verá *último correo* y *último DTE*. Sirve para saber si el reenvío está llegando.

---

## 2. Gmail (recomendado: filtro, no toda la bandeja)

Fuente: [Reenviar mensajes de Gmail automáticamente](https://support.google.com/mail/answer/10957?hl=es).

Gmail exige **verificar el destino** antes de reenviar. Hasta entonces no reenvía nada.

### Paso A — Añadir y verificar la dirección

En el **ordenador** (esta pantalla no está en el celular):

1. Abra Gmail e inicie sesión en la cuenta que recibe las facturas.
2. Arriba a la derecha: **Ajustes** → **Ver todos los ajustes**.
3. Pestaña **Reenvío y correo POP/IMAP** (a veces se llama **Reenvío**).
4. En “Reenvío”, pulse **Añadir una dirección de reenvío**.
5. Pegue la dirección de SmartPyme → **Siguiente** → **Continuar** → **Aceptar**.
6. Gmail envía un correo de verificación a SmartPyme.
7. Vuelva a SmartPyme → **Cuentas de correo**. En unos minutos debe aparecer un aviso con el **código** y/o un **enlace**.
8. Confirme en Gmail (código o enlace). Recargue Ajustes de Gmail.

SmartPyme **no** confirma por usted. Si el aviso no aparece, espere un minuto y recargue la página de Cuentas.

### Paso B — Reenviar solo facturas (filtro)

El reenvío general manda **todos** los correos nuevos, **excepto el spam**. No lo deje encendido para toda la bandeja.

Según Google: para reenviar solo algunos mensajes, **inhabilite el reenvío automático** y use un filtro.

1. En Gmail, en el cuadro de búsqueda, pulse **Mostrar opciones de búsqueda**.
2. Criterio útil: correos **con adjunto**. Si sus facturas vienen siempre del mismo remitente (p. ej. Hacienda o su proveedor), fíjelo también.
3. **Crear filtro** → marque **Reenviar** → elija la dirección de SmartPyme → **Crear filtro**.
4. La dirección solo aparece en esa lista **después** de verificarla (paso A).

Gmail puede mostrar una semana el aviso “Estás reenviando tu correo a…”. Es normal.

**Google Workspace:** un administrador puede prohibir el reenvío. En ese caso use **Conectar Gmail**, **Conectar Otros (IMAP)**, o pida a TI que lo permita.

**Spam:** Gmail **no reenvía** lo que marca como spam. Si una factura no llega a SmartPyme, mire Spam en Gmail y márquela como “No es spam”.

---

## 3. Outlook.com (correo personal)

Fuente: [Activar o desactivar el reenvío automático en Outlook.com](https://support.microsoft.com/en-us/outlook/turn-on-or-off-automatic-forwarding-in-outlook-com).

Microsoft pide **verificación en dos pasos** de *su* cuenta, no un código a SmartPyme.

1. En Outlook.com: **Configuración** → **Correo** → **Reenvío**.
2. **Activar reenvío**, pegue la dirección de SmartPyme.
3. Marque **Conservar una copia de los mensajes reenviados** si quiere quedarse el original.
4. **Guardar**.

Eso reenvía **todo** el correo nuevo. Si solo quiere facturas, use una **regla** “Reenviar a” / “Redirigir a” en lugar del reenvío general (Microsoft: las respuestas a un *forward* vuelven a usted; un *redirect* las manda al remitente original). Para SmartPyme da igual: importa el adjunto, no quién responde.

---

## 4. Microsoft 365 / correo de empresa

Puede crear una regla **Reenviar a** o **Redirigir a** la dirección de SmartPyme.

Si aparece un error como **5.7.520** (*organization does not allow external forwarding*), TI bloqueó el reenvío a fuera de la empresa. Pida que permitan reenvío externo a `ingest.smartpyme.site`, o use **Conectar Gmail** / **IMAP**.

Eso no lo puede desbloquear SmartPyme.

---

## 5. Qué debe llevar el correo

- Un adjunto **JSON** (El Salvador) o **XML** (Costa Rica) de la factura electrónica.
- El PDF ayuda a revisarla, pero **un PDF solo no se importa**.

Los documentos aparecen en **Bandeja de DTEs** para revisión. No se contabilizan solos.

Si reenvía a mano un correo viejo, también funciona: el token está en el sobre de llegada, no en el “Para:” original.

---

## 6. Pausar, regenerar, desactivar

| Acción | Efecto |
|---|---|
| **Pausar** | Deja de importar. La dirección sigue existiendo. |
| **Reanudar** | Vuelve a aceptar. |
| **Regenerar dirección** | Invalida la anterior. Actualice el filtro o el reenvío. |
| **Desactivar** | Apaga el método. Puede volver a activarlo después (nueva dirección). |

---

## 7. Si no llega nada

1. ¿La tarjeta está **Activa** y la dirección coincide con la del filtro?
2. ¿Confirmó la verificación de Gmail? Sin eso Gmail no reenvía.
3. ¿El correo está en spam de Gmail? No se reenvía.
4. ¿Es solo PDF? No se importa.
5. En Outlook de empresa, ¿TI bloquea el reenvío externo?
6. ¿Regeneró la dirección y el filtro sigue apuntando a la vieja?

*Último correo* con fecha y *último DTE* vacío suele significar: el reenvío funciona, pero ese mensaje no traía JSON/XML.
