## 📝 Resumen del Merge Request
- **Tipo de cambio**:
- [x] **Corrección de errores**
- [ ] **Nueva funcionalidad**
- [x] **Mejora**
- [x] **Refactorización**

- **Ticket de Jira**: [SP-388](https://smartpyme.atlassian.net/browse/SP-388)

---

## 🔍 Descripción detallada
- **¿Qué hace este MR?**
  - Corrige la visualización del identificador fiscal de clientes empresa para evitar mostrar dos veces RTN en Honduras.
  - Usa `cliente.nit` como RTN para Honduras y mantiene `cliente.ncr` como respaldo para registros históricos.
  - Usa `cliente.nit` como identificación fiscal para Costa Rica.
  - Mantiene el uso de `cliente.ncr` para El Salvador y países no configurados.
  - Centraliza la configuración del campo, etiqueta y compatibilidad histórica en `identificador-fiscal-cliente.util.ts`.
  - Aplica la misma lógica en el listado de clientes, el formulario de información y el modal de creación.

---

## ✅ Lista de verificación
- [x] Código probado localmente.
- [ ] Se actualizó la documentación relacionada (si aplica).
- [ ] La funcionalidad fue revisada por otro desarrollador.

---

## 🚀 Instrucciones para el revisor
1. **Pasos para probar los cambios**:
   - Iniciar sesión con una empresa de Honduras.
   - Abrir el listado y el formulario de clientes empresa.
   - Confirmar que se muestra un único campo RTN y que utiliza el valor de `cliente.nit`.
   - Repetir la prueba con empresas de El Salvador y Costa Rica.
   - Confirmar que El Salvador muestra NCR y Costa Rica muestra Identificación fiscal.
2. **Puntos clave a revisar**:
   - La resolución del campo fiscal mediante el código de país.
   - El fallback histórico de Honduras desde `cliente.ncr` hacia `cliente.nit`.
   - La consistencia entre listado, creación y edición de clientes.

---

## 🤝 Consideraciones adicionales
- El fallback `cliente.ncr` para Honduras es temporal y permite visualizar clientes antiguos sin migrar datos inmediatamente.
- La configuración puede extenderse agregando nuevos códigos de país en la utilidad centralizada.

---

## 📸 Capturas de pantalla (si aplica)
- No incluidas.
