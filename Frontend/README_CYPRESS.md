# Guía de Cypress - Pruebas E2E

Este proyecto utiliza Cypress para pruebas end-to-end (E2E).

## Instalación

Cypress ya está instalado como dependencia de desarrollo. Si necesitas reinstalarlo:

```bash
npm install --save-dev cypress
```

## Configuración

### Variables de Entorno

Crea un archivo `cypress.env.json` en la raíz del proyecto Frontend con tus credenciales de prueba:

```json
{
  "TEST_EMAIL": "tu-email@ejemplo.com",
  "TEST_PASSWORD": "tu-password",
  "API_URL": "https://api.smartpyme.bk.test"
}
```

**⚠️ IMPORTANTE:** No subas este archivo al repositorio. Está en `.gitignore`.

Puedes usar `cypress.env.example.json` como plantilla.

## Ejecutar Pruebas

### Modo Interactivo (Recomendado para desarrollo)

Abre Cypress en modo interactivo:

```bash
npm run cypress:open
# o
npm run e2e
```

Esto abre la interfaz gráfica de Cypress donde puedes:
- Ver todos los tests
- Ejecutar tests individuales
- Ver los tests ejecutándose en tiempo real

### Modo Headless (CI/CD)

Ejecuta todas las pruebas en modo headless (sin interfaz gráfica):

```bash
npm run cypress:run
# o
npm run e2e:ci
```

## Estructura de Carpetas

```
cypress/
├── e2e/              # Tests E2E
│   ├── login/        # Tests de autenticación
│   │   ├── login.cy.ts   # Test de login
│   │   └── register.cy.ts # Test de registro
│   └── productos/    # Tests de inventario
│       └── inventario-productos.cy.ts # Test de productos
├── fixtures/         # Datos de prueba
│   ├── example.json
│   └── users.json
└── support/          # Comandos personalizados y configuración
    ├── commands.ts   # Comandos personalizados
    └── e2e.ts        # Configuración global
```

## Comandos Personalizados

### Login

```typescript
cy.login('email@example.com', 'password123')
```

### Logout

```typescript
cy.logout()
```

## Tests Disponibles

### Test de Login (`cypress/e2e/login.cy.ts`)

El test de login incluye:

1. ✅ Verificación de elementos de la página
2. ✅ Validación de formulario vacío
3. ✅ Manejo de credenciales inválidas
4. ✅ Login exitoso con credenciales válidas
5. ✅ Toggle de visibilidad de contraseña
6. ✅ Navegación a "Olvidé mi contraseña"
7. ✅ Navegación a registro
8. ✅ Funcionalidad de "Recordarme"

### Test de Registro (`cypress/e2e/register.cy.ts`)

El test de registro de nueva empresa incluye:

1. ✅ Verificación de elementos de la página de registro
2. ✅ Validación de formulario vacío
3. ✅ Registro exitoso de nueva empresa
4. ✅ Navegación a página de pago después del registro
5. ✅ Selección de opción "Pagar después"
6. ✅ Validación de requisitos de contraseña
7. ✅ Selección de diferentes tipos de plan (Mensual, Trimestral, Anual)
8. ✅ Selección de diferentes planes (Estándar, Avanzado, Pro)
9. ✅ Visualización de precios al seleccionar plan
10. ✅ Navegación a login desde registro

### Test de Inventario - Productos (`cypress/e2e/inventario-productos.cy.ts`)

El test del módulo de inventario (productos) incluye:

1. ✅ Login y navegación a página de productos
2. ✅ Verificación de elementos de la página
3. ✅ Búsqueda de productos
4. ✅ Filtrado por categoría
5. ✅ Filtrado por bodega
6. ✅ Apertura y uso del modal de filtros
7. ✅ Ordenamiento por diferentes columnas
8. ✅ Cambio de estado de productos (activo/inactivo)
9. ✅ Apertura del modal de descarga
10. ✅ Navegación a página de crear producto
11. ✅ Cambio de tamaño de paginación
12. ✅ Navegación entre secciones de inventario
13. ✅ Visualización de información de stock
14. ✅ Filtrado de productos con stock bajo
15. ✅ Filtrado de productos compuestos
16. ✅ Resetear filtros con botón "Todos"

## Configuración de los Tests

### Test de Login

Para que el test de login funcione correctamente:

1. **Asegúrate de que la aplicación esté corriendo:**
   ```bash
   npm start
   ```

2. **Configura las credenciales de prueba** en `cypress.env.json`:
   ```json
   {
     "TEST_EMAIL": "tu-email@ejemplo.com",
     "TEST_PASSWORD": "tu-password"
   }
   ```

3. **Ajusta los tiempos de espera** si tu API es más lenta:
   - En `login.cy.ts`, ajusta los valores de `cy.wait()` según sea necesario

### Test de Registro

El test de registro genera datos únicos automáticamente para evitar conflictos:

- **No requiere configuración adicional** - Los datos se generan dinámicamente usando timestamps
- **Cada ejecución crea una nueva empresa** con email único
- **El test completa el flujo completo**: Registro → Pago → "Pagar después"

**Nota:** El test de registro puede crear datos en tu base de datos. Asegúrate de tener un entorno de pruebas adecuado.

### Test de Inventario

El test de inventario requiere que el usuario tenga permisos para acceder al módulo:

1. **Asegúrate de que las credenciales en `cypress.env.json` tengan permisos:**
   - `productos.ver` - Para ver la lista de productos
   - `productos.crear` - Para crear productos (opcional)
   - `productos.editar` - Para editar productos (opcional)

2. **El test verifica funcionalidades como:**
   - Búsqueda y filtrado de productos
   - Ordenamiento por columnas
   - Cambio de estado de productos
   - Navegación entre secciones
   - Modales de filtros y descarga

**Nota:** Algunos tests pueden requerir que existan productos, categorías o bodegas en el sistema. Si no existen, algunos tests pueden mostrar mensajes de "no tiene productos registrados" lo cual es válido.

## Troubleshooting

### Error: "Cannot connect to localhost:4200"

- Asegúrate de que la aplicación Angular esté corriendo en `http://localhost:4200`
- Verifica que el puerto no esté ocupado por otra aplicación

### Error: "Login fails"

- Verifica que las credenciales en `cypress.env.json` sean correctas
- Verifica que la API esté disponible y funcionando
- Revisa la consola del navegador en Cypress para ver errores de red

### Tests muy lentos

- Ajusta los `cy.wait()` según sea necesario
- Considera usar `cy.intercept()` para mockear respuestas de API en tests más rápidos

## Próximos Pasos

1. Agregar más tests E2E para otras funcionalidades
2. Configurar CI/CD para ejecutar tests automáticamente
3. Agregar más comandos personalizados según sea necesario
4. Implementar Page Object Model (POM) para mejor organización

## Recursos

- [Documentación oficial de Cypress](https://docs.cypress.io/)
- [Best Practices de Cypress](https://docs.cypress.io/guides/references/best-practices)
- [Comandos de Cypress](https://docs.cypress.io/api/commands)
