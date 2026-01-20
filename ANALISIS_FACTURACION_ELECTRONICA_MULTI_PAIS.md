# Análisis: Facturación Electrónica Multi-País
## Implementación Actual (El Salvador) y Propuesta para Costa Rica

---

## 📋 Índice
1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Análisis de la Implementación Actual](#análisis-de-la-implementación-actual)
3. [Componentes Específicos por País](#componentes-específicos-por-país)
4. [Propuesta de Arquitectura Multi-País](#propuesta-de-arquitectura-multi-país)
5. [Plan de Implementación](#plan-de-implementación)
6. [Consideraciones Técnicas](#consideraciones-técnicas)

---

## 📊 Resumen Ejecutivo

### Estado Actual
- ✅ **País implementado**: El Salvador (SV)
- ✅ **Sistema**: DTE (Documento Tributario Electrónico) - Ministerio de Hacienda
- ✅ **Ambientes**: Prueba (00) y Producción (01)
- ✅ **Tipos de documentos**: Factura (01), CCF (03), Nota Crédito (05), Nota Débito (06), Factura Exportación (11), Sujeto Excluido (14)

### Objetivo
Implementar soporte multi-país para facturación electrónica, comenzando con **Costa Rica (CR)**, manteniendo compatibilidad con El Salvador y facilitando la incorporación de futuros países.

---

## 🔍 Análisis de la Implementación Actual

### 1. Estructura de Directorios y Archivos

#### Backend
```
Backend/
├── app/
│   ├── Constants/
│   │   └── FacturacionElectronica/
│   │       └── FEConstants.php          # Constantes de tipos DTE
│   ├── Http/Controllers/Api/Admin/
│   │   ├── MHDTEController.php          # Controlador principal DTE
│   │   └── MHController.php             # Controlador catálogos MH
│   ├── Models/
│   │   └── MH/                          # ⚠️ ESPECÍFICO DE EL SALVADOR
│   │       ├── MH.php                   # Clase base
│   │       ├── MHFactura.php
│   │       ├── MHCCF.php
│   │       ├── MHNotaCredito.php
│   │       ├── MHNotaDebito.php
│   │       ├── MHFacturaExportacion.php
│   │       ├── MHAnulacion.php
│   │       ├── MHContingencia.php
│   │       ├── MHSujetoExcluidoCompra.php
│   │       ├── MHSujetoExcluidoGasto.php
│   │       └── [Catálogos: ActividadEconomica, Departamento, Municipio, etc.]
│   └── Services/
│       └── MHPruebasMasivasService.php
```

#### Frontend
```
Frontend/src/app/
├── services/
│   └── MH.service.ts                    # ⚠️ ESPECÍFICO DE EL SALVADOR
└── views/ventas/facturacion/
    ├── facturacion-tienda/
    └── facturacion-tienda-v2/
```

### 2. Componentes Clave Identificados

#### A. Modelo de Empresa (Backend)
**Archivo**: `Backend/app/Models/Admin/Empresa.php`

**Campos relacionados con FE:**
```php
'facturacion_electronica',    // boolean - Habilita/deshabilita FE
'fe_ambiente',                // '00' = Prueba, '01' = Producción
'mh_usuario',                 // Usuario API MH (El Salvador)
'mh_contrasena',              // Contraseña API MH (El Salvador)
'mh_pwd_certificado',         // Clave privada certificado
'cod_estable_mh',             // Código establecimiento MH
'cod_actividad_economica',    // Código actividad económica
'cod_departamento',           // Código departamento
'cod_municipio',              // Código municipio
'cod_distrito',               // Código distrito
'tipo_establecimiento',       // Tipo establecimiento
'pais',                       // Nombre país
'cod_pais',                   // Código ISO país (SV, CR, etc.)
```

**⚠️ Problema identificado**: Los campos `mh_usuario`, `mh_contrasena` están hardcodeados para El Salvador. Costa Rica usará credenciales diferentes.

#### B. Modelos de Documentos (Backend)
**Ubicación**: `Backend/app/Models/MH/`

**Estructura actual:**
- Todos los modelos están en namespace `App\Models\MH` (específico de El Salvador)
- Cada tipo de documento tiene su propia clase que extiende de `Model`
- Métodos principales:
  - `generarDTE($venta)` - Genera el JSON del DTE
  - `identificador()` - Datos de identificación
  - `emisor()` - Datos del emisor
  - `receptor()` - Datos del receptor
  - `detalles()` - Detalles de productos/servicios

**Ejemplo - MHFactura.php:**
```php
class MHFactura extends Model {
    public function generarDTE($venta) {
        // Lógica específica de El Salvador
        $this->venta->tipo_dte = '01';
        $this->venta->numero_control = 'DTE-01-...';
        // URLs hardcodeadas de MH El Salvador
        // Formato JSON específico de El Salvador
    }
}
```

#### C. Controlador DTE (Backend)
**Archivo**: `Backend/app/Http/Controllers/Api/Admin/MHDTEController.php`

**Métodos principales:**
- `generarDTE()` - Genera DTE según tipo de documento
- `generarDTENotaCredito()` - Genera nota de crédito
- `anularDTE()` - Anula un DTE
- `enviarDTE()` - Envía DTE por correo
- `generarDTEPDF()` - Genera PDF del DTE

**⚠️ Problema**: El controlador instancia directamente clases `MH*` sin considerar el país.

#### D. Servicio Frontend (Angular)
**Archivo**: `Frontend/src/app/services/MH.service.ts`

**Características:**
- URLs hardcodeadas de MH El Salvador:
  ```typescript
  url_firmado: 'https://facturadtesv.com:8443/firmardocumento/'
  url_recepciondte: '/fesv/recepciondte'
  url_anular_dte: '/fesv/anulardte'
  ```
- Métodos:
  - `auth()` - Autenticación con MH
  - `firmarDTE()` - Firma electrónica
  - `enviarDTE()` - Envía a MH
  - `anularDTE()` - Anula DTE
  - `emitirDTE()` - Flujo completo

**⚠️ Problema**: Todo está hardcodeado para El Salvador.

#### E. URLs y Endpoints
**Backend - MH.php:**
```php
protected $url_firmado = 'https://firmador.smartpyme.site:8443/firmardocumento/';
protected $url_mh = 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte';
protected $url_anular_dte = 'https://apitest.dtes.mh.gob.sv/fesv/anulardte';
protected $url_auth = 'https://apitest.dtes.mh.gob.sv/seguridad/auth';
```

**⚠️ Problema**: URLs específicas de El Salvador.

### 3. Flujo de Emisión de DTE

```
1. Usuario emite factura en Frontend
   ↓
2. Frontend llama a MHService.emitirDTE()
   ↓
3. Backend: MHDTEController.generarDTE()
   ↓
4. Se instancia MHFactura (o MHCCF, etc.)
   ↓
5. Se genera JSON del DTE según formato El Salvador
   ↓
6. Frontend: Se firma el DTE (MHService.firmarDTE())
   ↓
7. Frontend: Se envía a MH El Salvador (MHService.enviarDTE())
   ↓
8. Se guarda sello_mh en la venta
```

---

## 🌍 Componentes Específicos por País

### El Salvador (SV) - Actual

#### Características:
- **Sistema**: DTE (Documento Tributario Electrónico)
- **Autoridad**: Ministerio de Hacienda (MH)
- **Formato**: JSON estructurado
- **Tipos de documento**: 01, 03, 05, 06, 11, 14
- **Autenticación**: Usuario/Contraseña + Certificado digital
- **Firma**: Servicio externo de firma
- **URLs**:
  - Prueba: `https://apitest.dtes.mh.gob.sv`
  - Producción: `https://api.dtes.mh.gob.sv`
- **Catálogos**: Departamentos, Municipios, Distritos, Actividades Económicas

#### Estructura JSON DTE (Ejemplo):
```json
{
  "identificacion": {
    "version": 1,
    "ambiente": "01",
    "tipoDte": "01",
    "numeroControl": "DTE-01-...",
    "codigoGeneracion": "UUID",
    "fecEmi": "2024-01-01",
    "horEmi": "10:00:00"
  },
  "emisor": {
    "nit": "12345678",
    "nrc": "123456",
    "nombre": "Empresa S.A.",
    "codActividad": "12345",
    "direccion": {
      "departamento": "01",
      "municipio": "01",
      "complemento": "Calle 123"
    }
  },
  "receptor": {
    "tipoDocumento": "36",
    "numDocumento": "87654321",
    "nombre": "Cliente"
  },
  "cuerpoDocumento": [...],
  "resumen": {...}
}
```

### Costa Rica (CR) - Por Implementar

#### Características (Basado en investigación):
- **Sistema**: Facturación Electrónica (FE) - Hacienda
- **Autoridad**: Ministerio de Hacienda de Costa Rica
- **Formato**: XML (probablemente)
- **Tipos de documento**: Factura electrónica, Nota de crédito, Nota de débito
- **Autenticación**: Token OAuth / Certificado digital
- **Firma**: Certificado digital costarricense
- **URLs**: (Por confirmar)
  - Prueba: `https://api-sandbox.hacienda.go.cr`
  - Producción: `https://api.hacienda.go.cr`
- **Catálogos**: Provincias, Cantones, Distritos, Actividades Económicas

#### Diferencias Clave:
1. **Formato**: XML vs JSON
2. **Estructura**: Diferente estructura de datos
3. **Autenticación**: Posiblemente OAuth en lugar de usuario/contraseña
4. **Catálogos**: Provincias/Cantones/Distritos vs Departamentos/Municipios/Distritos
5. **Códigos**: Diferentes códigos de tipos de documento
6. **Validaciones**: Reglas de negocio diferentes

---

## 🏗️ Propuesta de Arquitectura Multi-País

### 1. Patrón Strategy + Factory

#### Estructura Propuesta:

```
Backend/app/
├── Services/
│   └── FacturacionElectronica/
│       ├── FacturacionElectronicaService.php      # Servicio principal
│       ├── Contracts/
│       │   └── FacturacionElectronicaInterface.php
│       ├── Factories/
│       │   └── FacturacionElectronicaFactory.php
│       └── Implementations/
│           ├── ElSalvador/
│           │   ├── ElSalvadorFE.php
│           │   ├── ElSalvadorFactura.php
│           │   ├── ElSalvadorCCF.php
│           │   └── ElSalvadorNotaCredito.php
│           └── CostaRica/
│               ├── CostaRicaFE.php
│               ├── CostaRicaFactura.php
│               └── CostaRicaNotaCredito.php
├── Models/
│   └── FacturacionElectronica/
│       ├── ElSalvador/
│       │   └── [Mover modelos MH aquí]
│       └── CostaRica/
│           └── [Nuevos modelos]
└── Constants/
    └── FacturacionElectronica/
        ├── Paises.php                              # Constantes de países
        ├── ElSalvador/
        │   └── ElSalvadorConstants.php
        └── CostaRica/
            └── CostaRicaConstants.php
```

### 2. Interface Base

```php
namespace App\Services\FacturacionElectronica\Contracts;

interface FacturacionElectronicaInterface {
    public function generarDTE($venta);
    public function firmarDTE($dte);
    public function enviarDTE($dte);
    public function anularDTE($dte);
    public function consultarDTE($codigoGeneracion);
    public function obtenerConfiguracion();
}
```

### 3. Factory Pattern

```php
namespace App\Services\FacturacionElectronica\Factories;

class FacturacionElectronicaFactory {
    public static function crear($pais, $tipoDocumento) {
        $codPais = is_string($pais) ? $pais : $pais->cod_pais;
        
        switch ($codPais) {
            case 'SV':
                return self::crearElSalvador($tipoDocumento);
            case 'CR':
                return self::crearCostaRica($tipoDocumento);
            default:
                throw new \Exception("País no soportado: {$codPais}");
        }
    }
    
    private static function crearElSalvador($tipoDocumento) {
        switch ($tipoDocumento) {
            case '01': return new \App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorFactura();
            case '03': return new \App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorCCF();
            // ...
        }
    }
    
    private static function crearCostaRica($tipoDocumento) {
        switch ($tipoDocumento) {
            case '01': return new \App\Services\FacturacionElectronica\Implementations\CostaRica\CostaRicaFactura();
            // ...
        }
    }
}
```

### 4. Servicio Principal

```php
namespace App\Services\FacturacionElectronica;

class FacturacionElectronicaService {
    public function generarDTE($venta) {
        $empresa = $venta->empresa;
        $tipoDocumento = $this->obtenerTipoDocumento($venta);
        
        $fe = FacturacionElectronicaFactory::crear($empresa, $tipoDocumento);
        return $fe->generarDTE($venta);
    }
    
    private function obtenerTipoDocumento($venta) {
        // Lógica para determinar tipo de documento
        if ($venta->nombre_documento == 'Factura') return '01';
        if ($venta->nombre_documento == 'Crédito fiscal') return '03';
        // ...
    }
}
```

### 5. Configuración por País

#### Base de Datos - Nueva Tabla

```sql
CREATE TABLE fe_configuracion_pais (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cod_pais VARCHAR(2) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    url_prueba VARCHAR(255),
    url_produccion VARCHAR(255),
    url_firmado VARCHAR(255),
    formato_documento ENUM('JSON', 'XML') DEFAULT 'JSON',
    activo BOOLEAN DEFAULT TRUE,
    configuracion JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### Modelo

```php
namespace App\Models\FacturacionElectronica;

class FEConfiguracionPais extends Model {
    protected $fillable = [
        'cod_pais', 'nombre', 'url_prueba', 'url_produccion',
        'url_firmado', 'formato_documento', 'activo', 'configuracion'
    ];
    
    protected $casts = [
        'configuracion' => 'array',
        'activo' => 'boolean'
    ];
}
```

### 6. Actualización del Modelo Empresa

#### Nuevos Campos

```php
// En Empresa.php - fillable
'fe_pais',                    // Código país para FE (SV, CR)
'fe_usuario',                 // Usuario genérico (reemplaza mh_usuario)
'fe_contrasena',              // Contraseña genérica (reemplaza mh_contrasena)
'fe_certificado_password',    // Password certificado genérico
'fe_certificado_path',        // Ruta al certificado
'fe_token',                   // Token OAuth (para países que lo requieran)
'fe_token_expires_at',        // Expiración del token
```

#### Migración

```php
Schema::table('empresas', function (Blueprint $table) {
    $table->string('fe_pais', 2)->nullable()->after('cod_pais');
    $table->string('fe_usuario')->nullable()->after('mh_usuario');
    $table->string('fe_contrasena')->nullable()->after('mh_contrasena');
    $table->string('fe_certificado_password')->nullable()->after('mh_pwd_certificado');
    $table->string('fe_certificado_path')->nullable();
    $table->text('fe_token')->nullable();
    $table->timestamp('fe_token_expires_at')->nullable();
    
    // Migrar datos existentes
    DB::statement("UPDATE empresas SET fe_pais = 'SV' WHERE facturacion_electronica = 1");
    DB::statement("UPDATE empresas SET fe_usuario = mh_usuario WHERE mh_usuario IS NOT NULL");
    DB::statement("UPDATE empresas SET fe_contrasena = mh_contrasena WHERE mh_contrasena IS NOT NULL");
    DB::statement("UPDATE empresas SET fe_certificado_password = mh_pwd_certificado WHERE mh_pwd_certificado IS NOT NULL");
});
```

### 7. Actualización del Controlador

```php
namespace App\Http\Controllers\Api\Admin;

class FacturacionElectronicaController extends Controller {
    protected $feService;
    
    public function __construct(FacturacionElectronicaService $feService) {
        $this->feService = $feService;
    }
    
    public function generarDTE(Request $request) {
        $venta = Venta::where('id', $request->id)
            ->with('detalles', 'cliente', 'empresa')
            ->firstOrFail();
        
        // Validar que la empresa tenga FE configurada
        if (!$venta->empresa->facturacion_electronica) {
            return response()->json(['error' => 'Facturación electrónica no habilitada'], 400);
        }
        
        // Validar configuración del país
        if (!$venta->empresa->fe_pais) {
            return response()->json(['error' => 'País de facturación electrónica no configurado'], 400);
        }
        
        try {
            $dte = $this->feService->generarDTE($venta);
            return response()->json($dte, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

### 8. Frontend - Servicio Genérico

#### Nuevo Servicio

```typescript
// Frontend/src/app/services/facturacion-electronica.service.ts

@Injectable()
export class FacturacionElectronicaService {
    constructor(
        private http: HttpClient,
        private apiService: ApiService
    ) {}
    
    emitirDTE(venta: any): Promise<any> {
        return new Promise((resolve, reject) => {
            // Obtener país de la empresa
            const pais = this.apiService.auth_user().empresa.fe_pais || 
                        this.apiService.auth_user().empresa.cod_pais;
            
            // Llamar al backend genérico
            this.apiService.store('facturacion-electronica/generar-dte', venta)
                .subscribe(dte => {
                    venta.dte = dte;
                    
                    // Firmar según el país
                    this.firmarDTE(dte, pais).subscribe(dteFirmado => {
                        if (dteFirmado.status == 'ERROR') {
                            reject(dteFirmado.body.mensaje);
                            return;
                        }
                        
                        venta.dte.firmaElectronica = dteFirmado.body;
                        
                        // Enviar según el país
                        this.enviarDTE(venta, dteFirmado.body, pais).subscribe(result => {
                            if (result.estado == 'PROCESADO' && result.selloRecibido) {
                                venta.sello_mh = result.selloRecibido;
                                venta.tipo_dte = result.tipoDte;
                                venta.numero_control = result.numeroControl;
                                venta.codigo_generacion = result.codigoGeneracion;
                                
                                this.apiService.store('venta', venta).subscribe(data => {
                                    resolve(data);
                                });
                            }
                        }, error => reject(error));
                    }, error => reject(error));
                }, error => reject(error));
        });
    }
    
    private firmarDTE(dte: any, pais: string): Observable<any> {
        // Llamar al backend que manejará la firma según el país
        return this.apiService.store('facturacion-electronica/firmar-dte', {
            dte: dte,
            pais: pais
        });
    }
    
    private enviarDTE(venta: any, dteFirmado: any, pais: string): Observable<any> {
        // Llamar al backend que manejará el envío según el país
        return this.apiService.store('facturacion-electronica/enviar-dte', {
            venta: venta,
            dte: dteFirmado,
            pais: pais
        });
    }
}
```

### 9. Configuración de URLs por País

#### Backend - Config File

```php
// config/facturacion_electronica.php

return [
    'paises' => [
        'SV' => [
            'nombre' => 'El Salvador',
            'urls' => [
                'prueba' => [
                    'base' => 'https://apitest.dtes.mh.gob.sv',
                    'auth' => 'https://apitest.dtes.mh.gob.sv/seguridad/auth',
                    'recepcion' => 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte',
                    'anulacion' => 'https://apitest.dtes.mh.gob.sv/fesv/anulardte',
                ],
                'produccion' => [
                    'base' => 'https://api.dtes.mh.gob.sv',
                    'auth' => 'https://api.dtes.mh.gob.sv/seguridad/auth',
                    'recepcion' => 'https://api.dtes.mh.gob.sv/fesv/recepciondte',
                    'anulacion' => 'https://api.dtes.mh.gob.sv/fesv/anulardte',
                ],
            ],
            'firmador' => 'https://firmador.smartpyme.site:8443/firmardocumento/',
            'formato' => 'JSON',
        ],
        'CR' => [
            'nombre' => 'Costa Rica',
            'urls' => [
                'prueba' => [
                    'base' => 'https://api-sandbox.hacienda.go.cr',
                    'auth' => 'https://api-sandbox.hacienda.go.cr/auth/token',
                    'recepcion' => 'https://api-sandbox.hacienda.go.cr/fe/ae',
                    'anulacion' => 'https://api-sandbox.hacienda.go.cr/fe/anulacion',
                ],
                'produccion' => [
                    'base' => 'https://api.hacienda.go.cr',
                    'auth' => 'https://api.hacienda.go.cr/auth/token',
                    'recepcion' => 'https://api.hacienda.go.cr/fe/ae',
                    'anulacion' => 'https://api.hacienda.go.cr/fe/anulacion',
                ],
            ],
            'firmador' => null, // Se maneja diferente en CR
            'formato' => 'XML',
        ],
    ],
];
```

---

## 📝 Plan de Implementación

### Fase 1: Refactorización Base (2-3 semanas)

#### 1.1 Crear Estructura Base
- [ ] Crear interfaces y contratos
- [ ] Crear factory pattern
- [ ] Crear servicio principal
- [ ] Crear configuración por país

#### 1.2 Migrar El Salvador
- [ ] Mover modelos MH a `Implementations/ElSalvador/`
- [ ] Adaptar modelos a la nueva interface
- [ ] Actualizar controlador para usar factory
- [ ] Probar que todo funcione igual que antes

#### 1.3 Actualizar Base de Datos
- [ ] Crear tabla `fe_configuracion_pais`
- [ ] Agregar nuevos campos a `empresas`
- [ ] Migrar datos existentes
- [ ] Crear seeders con datos de países

### Fase 2: Implementación Costa Rica (3-4 semanas)

#### 2.1 Investigación y Documentación
- [ ] Investigar API oficial de Hacienda Costa Rica
- [ ] Documentar estructura XML requerida
- [ ] Documentar proceso de autenticación
- [ ] Documentar tipos de documentos
- [ ] Obtener credenciales de prueba

#### 2.2 Implementación Backend
- [ ] Crear clase `CostaRicaFE`
- [ ] Crear clases de documentos (Factura, Nota Crédito, etc.)
- [ ] Implementar generación de XML
- [ ] Implementar autenticación
- [ ] Implementar envío a Hacienda CR
- [ ] Implementar anulación

#### 2.3 Implementación Frontend
- [ ] Actualizar servicio para soportar CR
- [ ] Actualizar componentes de facturación
- [ ] Agregar validaciones específicas de CR
- [ ] Actualizar formularios de configuración

#### 2.4 Catálogos
- [ ] Crear modelos de Provincias
- [ ] Crear modelos de Cantones
- [ ] Crear modelos de Distritos
- [ ] Crear seeder con datos de CR
- [ ] Actualizar formularios de cliente/empresa

### Fase 3: Testing y Validación (2 semanas)

#### 3.1 Testing El Salvador
- [ ] Probar emisión de facturas
- [ ] Probar emisión de CCF
- [ ] Probar notas de crédito/débito
- [ ] Probar anulaciones
- [ ] Validar que no se rompió nada

#### 3.2 Testing Costa Rica
- [ ] Probar emisión en ambiente de prueba
- [ ] Validar estructura XML
- [ ] Probar autenticación
- [ ] Probar envío y recepción
- [ ] Probar anulaciones

### Fase 4: Documentación y Deployment (1 semana)

#### 4.1 Documentación
- [ ] Documentar API
- [ ] Documentar configuración por país
- [ ] Crear guías de usuario
- [ ] Actualizar README

#### 4.2 Deployment
- [ ] Deploy a staging
- [ ] Pruebas de integración
- [ ] Deploy a producción
- [ ] Monitoreo inicial

---

## ⚙️ Consideraciones Técnicas

### 1. Compatibilidad hacia atrás
- ✅ Mantener campos `mh_*` en la base de datos
- ✅ Crear migración que copie datos a nuevos campos
- ✅ Mantener rutas antiguas funcionando (deprecadas)
- ✅ Logging de uso de rutas antiguas

### 2. Validaciones
- Validar que la empresa tenga `fe_pais` configurado
- Validar que tenga credenciales según el país
- Validar formato de datos según país
- Validar catálogos (departamentos vs provincias)

### 3. Manejo de Errores
- Errores específicos por país
- Mensajes de error traducidos
- Logging detallado por país
- Notificaciones de errores

### 4. Performance
- Cache de configuración por país
- Cache de tokens de autenticación
- Optimización de consultas
- Lazy loading de implementaciones

### 5. Seguridad
- Encriptar credenciales en base de datos
- Validar certificados
- Rate limiting por país
- Auditoría de operaciones

### 6. Testing
- Unit tests por implementación
- Integration tests por país
- Mock de APIs externas
- Tests de regresión

---

## 📚 Referencias y Recursos

### El Salvador
- Documentación oficial MH: https://www.mh.gob.sv/dte
- API Documentation: (URL a documentación si existe)

### Costa Rica
- Documentación oficial Hacienda: https://www.hacienda.go.cr
- API Documentation: (URL a documentación cuando se investigue)

---

## ✅ Checklist de Implementación

### Preparación
- [ ] Revisar y aprobar arquitectura propuesta
- [ ] Asignar recursos de desarrollo
- [ ] Obtener credenciales de prueba para Costa Rica
- [ ] Configurar ambientes de desarrollo

### Desarrollo
- [ ] Fase 1: Refactorización base
- [ ] Fase 2: Implementación Costa Rica
- [ ] Fase 3: Testing
- [ ] Fase 4: Documentación y deployment

### Post-Implementación
- [ ] Monitoreo de errores
- [ ] Feedback de usuarios
- [ ] Optimizaciones
- [ ] Planificación de próximos países

---

**Fecha de creación**: 2024-01-XX  
**Última actualización**: 2024-01-XX  
**Autor**: Análisis técnico SmartPyme
