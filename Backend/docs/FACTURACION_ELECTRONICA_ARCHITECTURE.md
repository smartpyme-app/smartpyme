# Arquitectura de Facturación Electrónica Multi-País

## 1. Resumen

La facturación electrónica está implementada con una arquitectura multi-país que permite:

- **El Salvador (SV):** Implementación completa (Factura, CCF, Nota Crédito/Débito, Factura Exportación, Sujeto Excluido, Anulación, Contingencia, Ticket).
- **Costa Rica (CR):** Pendiente de implementación (Fase 2).

El sistema utiliza **Strategy** (implementaciones por país) y **Factory** (creación de la implementación correcta según país y tipo de documento).

---

## 2. Estructura de directorios

```
Backend/app/
├── Services/FacturacionElectronica/
│   ├── Contracts/
│   │   └── FacturacionElectronicaInterface.php   # Contrato común
│   ├── Factories/
│   │   └── FacturacionElectronicaFactory.php     # Factory por país/tipo
│   ├── FacturacionElectronicaService.php         # Servicio principal (orquestador)
│   └── Implementations/
│       └── ElSalvador/
│           ├── ElSalvadorFE.php                  # Clase base abstracta SV
│           ├── ElSalvadorFactura.php             # 01 Factura
│           ├── ElSalvadorCCF.php                 # 03 CCF
│           ├── ElSalvadorNotaCredito.php         # 05 Nota Crédito
│           ├── ElSalvadorNotaDebito.php          # 06 Nota Débito
│           ├── ElSalvadorFacturaExportacion.php   # 11 Exportación
│           ├── ElSalvadorSujetoExcluidoCompra.php # 14 Sujeto excluido compra
│           └── ElSalvadorSujetoExcluidoGasto.php  # 14 Sujeto excluido gasto
├── Http/Controllers/Api/Admin/
│   ├── FacturacionElectronicaController.php      # Controlador FE (rutas /fe/)
│   └── MHController.php                          # Catálogos MH (paises, municipios, etc.)
├── Models/Admin/
│   └── Empresa.php                               # Campos fe_* y helpers
└── config/
    └── facturacion_electronica.php               # URLs y config por país
```

---

## 3. Flujo de datos

### 3.1 Generar DTE

1. El cliente (frontend o otro servicio) llama al API `POST /api/fe/generarDTE` (o `generarDTENotaCredito`, `generarDTESujetoExcluidoGasto`, etc.).
2. `FacturacionElectronicaController` obtiene el documento (Venta, Devolución, Gasto, Compra) y llama a `FacturacionElectronicaService::generarDTE($documento)`.
3. El servicio obtiene la empresa del documento, valida FE y tipo de documento, y usa `FacturacionElectronicaFactory::crear($empresa, $tipoDocumento)` para obtener la implementación correcta (ej. `ElSalvadorFactura`).
4. La implementación genera el JSON del DTE según las reglas del país.
5. El controlador devuelve el DTE al cliente (que luego lo firma y envía con el mismo servicio o desde el frontend).

### 3.2 Firmar y enviar DTE

- **Backend:** El servicio expone `firmarDTE($dte, $documento)` y `enviarDTE($dteFirmado, $documento)`. La implementación por país usa las URLs de firmador y de recepción definidas en `config/facturacion_electronica.php`.
- **Frontend:** `FacturacionElectronicaService` (Angular) llama a los endpoints `/fe/...` y, para firma, puede usar el firmador externo según configuración.

### 3.3 Anulación

- El flujo de anulación usa `FacturacionElectronicaService::generarDTEAnulado($dte, $documento)` para generar el documento de anulación y luego `anularDTE` (firmar y enviar a la URL de anulación).

---

## 4. Configuración por país

Archivo: `config/facturacion_electronica.php`.

- **paises.SV:** URLs de prueba y producción (auth, recepción, anulación, consulta), firmador, consulta pública.
- **paises.CR:** Reservado para Costa Rica (Fase 2).

La empresa usa los campos `fe_pais`, `fe_usuario`, `fe_contrasena`, `fe_certificado_password`, `fe_ambiente`, etc. El modelo `Empresa` tiene helpers como `tieneFacturacionElectronica()` y `getFePais()`.

---

## 5. Cómo agregar un nuevo país (ej. Costa Rica)

1. **Config:** Agregar entrada en `config/facturacion_electronica.php` para el país (URLs, firmador, etc.).
2. **Implementación:** Crear `Implementations/CostaRica/CostaRicaFE.php` (base) y las clases por tipo de documento (Factura, Nota Crédito, etc.) que implementen `FacturacionElectronicaInterface`.
3. **Factory:** En `FacturacionElectronicaFactory::crear()` ya existe el caso `CR` que delega a `crearCostaRica()`; solo hay que implementar las clases referenciadas (o ajustar el mapeo si los nombres cambian).
4. **Empresa:** Asegurar que las empresas que usen el nuevo país tengan `fe_pais = 'CR'` y los campos de credenciales/certificado necesarios.
5. **Frontend:** Si el nuevo país requiere pasos o pantallas distintas (ej. otro flujo de firma), extender el servicio Angular y los componentes según sea necesario.

---

## 6. Rutas API (prefijo `/api/`, grupo admin)

Bajo el prefijo configurado para el módulo admin (ej. `api/...`):

- **Catálogos (MH):** `GET paises`, `municipios`, `distritos`, `departamentos`, `actividades_economicas`, `unidades`, `recintos`, `regimenes`, `incoterms`.
- **FE (Facturación Electrónica):** Todas bajo el prefijo `fe/`:
  - Generar: `POST fe/generarDTE`, `fe/generarDTENotaCredito`, `fe/generarDTESujetoExcluidoGasto`, `fe/generarDTESujetoExcluidoCompra`, `fe/generarDTEAnulado`, `fe/generarDTEAnuladoSujetoExcluidoGasto`, `fe/generarDTEAnuladoSujetoExcluidoCompra`, `fe/generarContingencia`.
  - Otros: `POST fe/anularDTE`, `fe/consultarDTE`, `fe/enviarDTE`.
  - Reportes: `GET fe/reporte/dte/{id}/{tipo}`, `fe/reporte/dte-json/{id}/{tipo}`, `fe/reporte/ticket/{id}`.

---

## 7. Pruebas masivas

El servicio `MHPruebasMasivasService` utiliza `FacturacionElectronicaService` para:

- Generar DTEs (ventas, notas de crédito/débito, sujeto excluido).
- Firmar y enviar mediante `feService->firmarDTE()` y `feService->enviarDTE()`.

Las rutas de pruebas masivas están en `routes/modulos/admin/pruebas-masivas-mh.php` (prefijo `mh/pruebas-masivas`).

---

## 8. Tests

- **Unit:** `tests/Unit/Services/FacturacionElectronica/FacturacionElectronicaFactoryTest.php` — pruebas del Factory (crear por país y tipo, mensajes de error, `obtenerTipoDocumento`).
- **Manual:** Ver `CHECKLIST_PRUEBAS_FACTURACION_ELECTRONICA.md` en la raíz del proyecto.

---

*Documento actualizado al cierre de Fase 1 (refactorización El Salvador).*
