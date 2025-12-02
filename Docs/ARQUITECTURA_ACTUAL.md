# Arquitectura Actual - SmartPYME

## 📋 Índice
1. [Arquitectura General](#arquitectura-general)
2. [Backend - Laravel](#backend---laravel)
3. [Frontend - Angular](#frontend---angular)
4. [Base de Datos](#base-de-datos)
5. [Integraciones Externas](#integraciones-externas)
6. [Flujo de Autenticación](#flujo-de-autenticación)
7. [Flujo de Datos](#flujo-de-datos)

---

## Arquitectura General

```mermaid
graph TB
    subgraph "Cliente"
        Browser[🌐 Navegador Web]
        Mobile[📱 Aplicación Móvil<br/>PWA]
    end
    
    subgraph "Frontend - Angular 20"
        Angular[Angular Application<br/>Lazy Loading Modules]
        PWA[Service Worker<br/>PWA Support]
    end
    
    subgraph "Backend - Laravel 12"
        API[REST API<br/>Laravel]
        Queue[Queue System<br/>Jobs & Workers]
        Cache[Cache Layer<br/>Redis/Memcached]
    end
    
    subgraph "Base de Datos"
        MySQL[(MySQL Database)]
    end
    
    subgraph "Servicios Externos"
        MH[Ministerio de Hacienda<br/>Facturación Electrónica]
        N1CO[N1CO<br/>Pagos]
        Wompi[Wompi<br/>Gateway de Pagos]
        Shopify[Shopify<br/>E-commerce]
        WooCommerce[WooCommerce<br/>E-commerce]
        WhatsApp[WhatsApp API<br/>Mensajería]
    end
    
    Browser --> Angular
    Mobile --> Angular
    Angular --> PWA
    Angular -->|HTTPS/REST| API
    API --> Queue
    API --> Cache
    API --> MySQL
    Queue --> MySQL
    API -->|Facturación| MH
    API -->|Pagos| N1CO
    API -->|Pagos| Wompi
    API -->|Sincronización| Shopify
    API -->|Sincronización| WooCommerce
    API -->|Mensajería| WhatsApp
    
    style Angular fill:#dd0031
    style API fill:#ff2d20
    style MySQL fill:#00758f
```

---

## Backend - Laravel

### Estructura de Directorios y Módulos Principales

```mermaid
graph LR
    subgraph "Backend Laravel 12"
        subgraph "HTTP Layer"
            Controllers[Controllers<br/>API Controllers]
            Middleware[Middleware<br/>Auth, CORS, Permissions]
            Requests[Form Requests<br/>Validation]
            Resources[API Resources<br/>Transformers]
        end
        
        subgraph "Business Logic"
            Models[Models<br/>Eloquent ORM]
            Services[Services<br/>Business Logic]
            Events[Events & Listeners<br/>Event-Driven]
            Jobs[Jobs & Queues<br/>Async Processing]
        end
        
        subgraph "Data Layer"
            Migrations[Migrations<br/>Schema]
            Seeders[Seeders<br/>Initial Data]
            Exports[Exports<br/>Excel/PDF]
            Imports[Imports<br/>Excel Import]
        end
        
        subgraph "Configuration"
            Config[Config Files<br/>App, DB, JWT, etc]
            Routes[Routes<br/>API Routes]
        end
    end
    
    Controllers --> Models
    Controllers --> Services
    Controllers --> Requests
    Controllers --> Resources
    Controllers --> Middleware
    Services --> Models
    Jobs --> Models
    Events --> Models
    Models --> Migrations
    
    style Controllers fill:#ff2d20
    style Models fill:#00758f
    style Services fill:#ffa500
```

### Módulos Principales del Backend

```mermaid
mindmap
  root((Backend<br/>Módulos))
    Admin
      Empresas
      Usuarios
      Roles y Permisos
      Sucursales
      Suscripciones
      Funcionalidades
      Notificaciones
    Ventas
      Ventas
      Clientes
      Cotizaciones
      Devoluciones
      Abonos
      Facturación MH
      Métodos de Pago
    Compras
      Compras
      Proveedores
      Devoluciones
      Gastos
      Abonos
      Retaceos
    Inventario
      Productos
      Servicios
      Materias Primas
      Categorías
      Bodegas
      Traslados
      Ajustes
      Kardex
      Promociones
      Paquetes
    Contabilidad
      Catálogo de Cuentas
      Partidas Contables
      Libros IVA
      Presupuestos
      Proyectos
      Bancos
    Planilla
      Empleados
      Planillas
      Configuración
      Cargos y Departamentos
    Citas
      Eventos
      Calendario
    External API
      Ventas API
      Inventario API
      Devoluciones API
    Integraciones
      Shopify
      WooCommerce
      WhatsApp
      N1CO
      Wompi
```

### Controladores Principales

```mermaid
graph TD
    subgraph "API Controllers"
        AdminCtrl[Admin Controllers]
        VentasCtrl[Ventas Controllers]
        ComprasCtrl[Compras Controllers]
        InventarioCtrl[Inventario Controllers]
        ContabilidadCtrl[Contabilidad Controllers]
        PlanillaCtrl[Planilla Controllers]
        ExternalCtrl[External API Controllers]
    end
    
    subgraph "Admin Controllers"
        EmpresasCtrl[EmpresasController]
        UsuariosCtrl[UsuariosController]
        RolesCtrl[RolePermissionController]
        SucursalesCtrl[SucursalesController]
        SuscripcionesCtrl[SuscripcionesController]
        MHCtrl[MHController<br/>Facturación Electrónica]
    end
    
    subgraph "Ventas Controllers"
        VentasMainCtrl[VentasController]
        ClientesCtrl[ClientesController]
        CotizacionesCtrl[CotizacionesController]
        DevolucionesCtrl[DevolucionesController]
        AbonosCtrl[AbonosController]
        FacturacionCtrl[FacturacionController]
    end
    
    subgraph "Compras Controllers"
        ComprasMainCtrl[ComprasController]
        ProveedoresCtrl[ProveedoresController]
        GastosCtrl[GastosController]
        RetaceosCtrl[RetaceosController]
    end
    
    subgraph "Inventario Controllers"
        ProductosCtrl[ProductosController]
        CategoriasCtrl[CategoriasController]
        BodegasCtrl[BodegasController]
        TrasladosCtrl[TrasladosController]
        KardexCtrl[KardexController]
        PromocionesCtrl[PromocionesController]
    end
    
    AdminCtrl --> EmpresasCtrl
    AdminCtrl --> UsuariosCtrl
    AdminCtrl --> RolesCtrl
    AdminCtrl --> MHCtrl
    VentasCtrl --> VentasMainCtrl
    VentasCtrl --> ClientesCtrl
    VentasCtrl --> FacturacionCtrl
    ComprasCtrl --> ComprasMainCtrl
    InventarioCtrl --> ProductosCtrl
```

### Modelos Principales

```mermaid
erDiagram
    EMPRESA ||--o{ USUARIO : tiene
    EMPRESA ||--o{ SUCURSAL : tiene
    EMPRESA ||--o| SUSCRIPCION : tiene
    EMPRESA ||--o{ VENTA : realiza
    EMPRESA ||--o{ COMPRA : realiza
    EMPRESA ||--o{ PRODUCTO : tiene
    
    USUARIO ||--o{ VENTA : crea
    USUARIO ||--o{ ROL : tiene
    USUARIO ||--o{ PERMISO : tiene
    
    PRODUCTO ||--o{ DETALLE_VENTA : contiene
    PRODUCTO ||--o{ INVENTARIO : tiene
    PRODUCTO ||--o{ KARDEX : registra
    PRODUCTO }o--o{ CATEGORIA : pertenece
    
    VENTA ||--o{ DETALLE_VENTA : contiene
    VENTA ||--o{ ABONO : tiene
    VENTA ||--o{ DEVOLUCION : puede_tener
    VENTA }o--|| CLIENTE : pertenece
    VENTA }o--|| METODO_PAGO : usa
    
    COMPRA ||--o{ DETALLE_COMPRA : contiene
    COMPRA }o--|| PROVEEDOR : pertenece
    
    PLANILLA ||--o{ PLANILLA_DETALLE : contiene
    PLANILLA }o--|| EMPLEADO : pertenece
    
    EMPRESA {
        int id PK
        string nombre
        string nit
        boolean facturacion_electronica
    }
    
    USUARIO {
        int id PK
        string name
        string email
        int id_empresa FK
    }
    
    VENTA {
        int id PK
        int id_empresa FK
        int id_cliente FK
        decimal total
        date fecha
    }
    
    PRODUCTO {
        int id PK
        string nombre
        string codigo
        decimal precio
        int id_empresa FK
    }
```

### Middleware y Seguridad

```mermaid
graph TD
    Request[HTTP Request] --> CORS[CORS Middleware]
    CORS --> TrustProxies[TrustProxies]
    TrustProxies --> EncryptCookies[EncryptCookies]
    EncryptCookies --> JWT[JWT Auth Middleware]
    
    JWT -->|Authenticated| CompanyScope[EnsureCompanyScope]
    JWT -->|Not Authenticated| Reject[401 Unauthorized]
    
    CompanyScope --> PermissionCheck[VerificarAccesoFuncionalidad]
    PermissionCheck --> RoleCheck[Role Middleware]
    RoleCheck --> AdminCheck[Admin Middleware]
    AdminCheck --> SuperAdminCheck[SuperAdmin Middleware]
    
    PermissionCheck -->|Authorized| Controller[Controller]
    RoleCheck -->|Authorized| Controller
    AdminCheck -->|Authorized| Controller
    SuperAdminCheck -->|Authorized| Controller
    
    PermissionCheck -->|Unauthorized| Reject403[403 Forbidden]
    RoleCheck -->|Unauthorized| Reject403
    AdminCheck -->|Unauthorized| Reject403
    SuperAdminCheck -->|Unauthorized| Reject403
    
    style JWT fill:#ff2d20
    style PermissionCheck fill:#ffa500
    style Controller fill:#4caf50
```

### Jobs y Colas

```mermaid
graph LR
    subgraph "Queue Jobs"
        ShopifyJob[ExportProductsToShopify<br/>ProcessShopifyProductBatch]
        WooCommerceJob[ExportProductsToWooCommerce<br/>ProcessWooCommerceProductBatch]
        KardexJob[GenerarKardexMasivo]
        EmailJob[Email Jobs]
    end
    
    subgraph "Triggers"
        ProductUpdate[Producto Actualizado]
        VentaCreated[Venta Creada]
        PlanillaCreated[Planilla Creada]
    end
    
    ProductUpdate --> ShopifyJob
    ProductUpdate --> WooCommerceJob
    VentaCreated --> KardexJob
    PlanillaCreated --> EmailJob
    
    ShopifyJob --> ShopifyAPI[Shopify API]
    WooCommerceJob --> WooCommerceAPI[WooCommerce API]
    KardexJob --> Database[(Database)]
    EmailJob --> MailServer[SMTP Server]
    
    style ShopifyJob fill:#96bf48
    style WooCommerceJob fill:#96588a
    style KardexJob fill:#ffa500
```

---

## Frontend - Angular

### Estructura de Módulos

```mermaid
graph TD
    subgraph "Angular 20 Application"
        AppModule[App Module<br/>Root Module]
        
        subgraph "Core Modules"
            AuthModule[Auth Module<br/>Login, Register]
            LayoutModule[Layout Module<br/>Navigation, Sidebar]
            SharedModule[Shared Module<br/>Common Components]
        end
        
        subgraph "Feature Modules - Lazy Loaded"
            DashModule[Dash Module<br/>Dashboards]
            VentasModule[Ventas Module]
            ComprasModule[Compras Module]
            InventarioModule[Inventario Module]
            ContabilidadModule[Contabilidad Module]
            PlanillasModule[Planillas Module]
            CitasModule[Citas Module]
            AdminModule[Admin Module]
            SuperAdminModule[Super Admin Module]
            PaquetesModule[Paquetes Module]
            ProyectosModule[Proyectos Module]
        end
    end
    
    AppModule --> AuthModule
    AppModule --> LayoutModule
    AppModule --> SharedModule
    
    AppModule -.->|Lazy Load| DashModule
    AppModule -.->|Lazy Load| VentasModule
    AppModule -.->|Lazy Load| ComprasModule
    AppModule -.->|Lazy Load| InventarioModule
    AppModule -.->|Lazy Load| ContabilidadModule
    AppModule -.->|Lazy Load| PlanillasModule
    AppModule -.->|Lazy Load| CitasModule
    AppModule -.->|Lazy Load| AdminModule
    AppModule -.->|Lazy Load| SuperAdminModule
    
    style AppModule fill:#dd0031
    style SharedModule fill:#1976d2
```

### Módulos del Frontend - Detalle

```mermaid
mindmap
  root((Frontend<br/>Módulos))
    Dash
      Admin Dashboard
      Vendedor Dashboard
      Caja Dashboard
      Organizaciones
    Ventas
      Ventas
      Clientes
      Cotizaciones
      Devoluciones
      Abonos
      Facturación
      Documentos
      Canales
      Formas de Pago
      Impuestos
      Retenciones
    Compras
      Compras
      Proveedores
      Cotizaciones
      Devoluciones
      Gastos
      Abonos
      Retaceos
    Inventario
      Productos
      Servicios
      Materias Primas
      Categorías
      Bodegas
      Traslados
      Ajustes
      Kardex
      Promociones
      Custom Fields
    Contabilidad
      Catálogo de Cuentas
      Partidas
      Libros IVA
      Presupuestos
      Bancos
      Cierre de Mes
    Planillas
      Empleados
      Planillas
      Configuración
    Citas
      Calendario
      Eventos
    Admin
      Empresas
      Usuarios
      Roles y Permisos
      Sucursales
      Suscripciones
      Notificaciones
      WhatsApp
    Super Admin
      Empresas
      Usuarios
      Funcionalidades
      Licencias
      Planes
      Pagos
```

### Servicios Principales

```mermaid
graph TD
    subgraph "Core Services"
        ApiService[ApiService<br/>HTTP Base]
        HttpService[HttpService<br/>HTTP Wrapper]
        AuthService[AuthService<br/>Authentication]
        PermissionService[PermissionService<br/>Permissions]
    end
    
    subgraph "Feature Services"
        MHService[MHService<br/>Facturación MH]
        ConstantsService[ConstantsService<br/>App Constants]
        FileService[FileService<br/>File Operations]
        AlertService[AlertService<br/>Notifications]
        UtilityService[UtilityService<br/>Utilities]
    end
    
    subgraph "Interceptors"
        JwtInterceptor[JwtInterceptor<br/>JWT Token]
        CacheInterceptor[CacheInterceptor<br/>HTTP Cache]
        PaceInterceptor[PaceInterceptor<br/>Loading]
        AuthInterceptor[AuthorizationInterceptor<br/>Authorization Header]
    end
    
    ApiService --> HttpService
    HttpService --> JwtInterceptor
    HttpService --> CacheInterceptor
    HttpService --> PaceInterceptor
    HttpService --> AuthInterceptor
    
    AuthService --> ApiService
    PermissionService --> ApiService
    MHService --> ApiService
    
    style ApiService fill:#dd0031
    style AuthService fill:#ff2d20
```

### Guards y Routing

```mermaid
graph TD
    Route[Route Request] --> AuthGuard[AuthGuard<br/>Is Authenticated?]
    
    AuthGuard -->|Not Authenticated| Login[Redirect to Login]
    AuthGuard -->|Authenticated| SubscriptionGuard[SubscriptionGuard<br/>Has Active Subscription?]
    
    SubscriptionGuard -->|No Subscription| Paywall[Redirect to Paywall]
    SubscriptionGuard -->|Has Subscription| FeatureGuards[Feature Guards]
    
    FeatureGuards --> AdminGuard[AdminGuard<br/>Is Admin?]
    FeatureGuards --> SuperAdminGuard[SuperAdminGuard<br/>Is Super Admin?]
    FeatureGuards --> CitasGuard[CitasGuard<br/>Has Citas Module?]
    FeatureGuards --> RoleGuard[RoleGuard<br/>Has Role?]
    FeatureGuards --> PermissionGuard[PermissionGuard<br/>Has Permission?]
    
    AdminGuard -->|Authorized| Component[Load Component]
    SuperAdminGuard -->|Authorized| Component
    CitasGuard -->|Authorized| Component
    RoleGuard -->|Authorized| Component
    PermissionGuard -->|Authorized| Component
    
    AdminGuard -->|Unauthorized| Reject[403 Forbidden]
    SuperAdminGuard -->|Unauthorized| Reject
    CitasGuard -->|Unauthorized| Reject
    RoleGuard -->|Unauthorized| Reject
    PermissionGuard -->|Unauthorized| Reject
    
    style AuthGuard fill:#ff2d20
    style SubscriptionGuard fill:#ffa500
    style Component fill:#4caf50
```

---

## Base de Datos

### Principales Entidades

```mermaid
erDiagram
    EMPRESA ||--o{ SUCURSAL : tiene
    EMPRESA ||--o{ USUARIO : tiene
    EMPRESA ||--o| SUSCRIPCION : tiene
    EMPRESA ||--o{ PRODUCTO : tiene
    EMPRESA ||--o{ VENTA : realiza
    EMPRESA ||--o{ COMPRA : realiza
    EMPRESA ||--o{ CLIENTE : tiene
    EMPRESA ||--o{ PROVEEDOR : tiene
    
    USUARIO ||--o{ VENTA : crea
    USUARIO }o--o{ ROL : tiene
    ROL }o--o{ PERMISO : tiene
    
    VENTA ||--o{ DETALLE_VENTA : contiene
    VENTA }o--|| CLIENTE : pertenece
    VENTA ||--o{ ABONO : tiene
    VENTA ||--o{ DEVOLUCION : puede_tener
    VENTA }o--|| METODO_PAGO : usa
    
    PRODUCTO ||--o{ DETALLE_VENTA : aparece_en
    PRODUCTO ||--o{ INVENTARIO : tiene_stock
    PRODUCTO ||--o{ KARDEX : registra_movimiento
    PRODUCTO }o--o{ CATEGORIA : pertenece
    PRODUCTO }o--o{ BODEGA : almacenado_en
    
    COMPRA ||--o{ DETALLE_COMPRA : contiene
    COMPRA }o--|| PROVEEDOR : pertenece
    
    PLANILLA ||--o{ PLANILLA_DETALLE : contiene
    PLANILLA }o--|| EMPLEADO : pertenece
    EMPLEADO }o--|| EMPRESA : trabaja_en
    
    CONTABILIDAD_PARTIDA ||--o{ PARTIDA_DETALLE : contiene
    CONTABILIDAD_PARTIDA }o--o{ CATALOGO_CUENTA : usa
    
    EMPRESA {
        int id PK
        string nombre
        string nit
        boolean facturacion_electronica
        string mh_usuario
        string mh_contrasena
    }
    
    SUSCRIPCION {
        int id PK
        int empresa_id FK
        int plan_id FK
        string estado
        date fecha_proximo_pago
    }
```

---

## Integraciones Externas

### Servicios Externos Integrados

```mermaid
graph LR
    subgraph "Backend Laravel"
        API[API Controller]
    end
    
    subgraph "Facturación Electrónica"
        MH[Ministerio de Hacienda<br/>MH DTE]
        MHController[MHController<br/>MHDTEController]
    end
    
    subgraph "Pagos"
        N1CO[N1CO<br/>Payment Gateway]
        Wompi[Wompi<br/>Payment Gateway]
    end
    
    subgraph "E-commerce"
        Shopify[Shopify<br/>Store Sync]
        WooCommerce[WooCommerce<br/>Store Sync]
    end
    
    subgraph "Comunicación"
        WhatsApp[WhatsApp API<br/>Mensajería]
    end
    
    API --> MHController
    MHController --> MH
    
    API -->|Pagos| N1CO
    API -->|Pagos| Wompi
    
    API -->|Product Sync| Shopify
    API -->|Product Sync| WooCommerce
    
    API -->|Mensajes| WhatsApp
    
    style MH fill:#4caf50
    style N1CO fill:#ff2d20
    style Shopify fill:#96bf48
    style WooCommerce fill:#96588a
```

### Flujo de Integración con Shopify/WooCommerce

```mermaid
sequenceDiagram
    participant User as Usuario
    participant Frontend as Angular Frontend
    participant Backend as Laravel API
    participant Queue as Queue System
    participant Job as Sync Job
    participant External as Shopify/WooCommerce
    
    User->>Frontend: Actualiza Producto
    Frontend->>Backend: PUT /api/productos/{id}
    Backend->>Backend: Actualiza Producto en BD
    Backend->>Queue: Dispatch Sync Job
    Backend->>Frontend: 200 OK
    
    Queue->>Job: Process Sync Job
    Job->>Backend: Obtiene Datos Producto
    Job->>External: POST /products (API)
    External->>Job: 201 Created
    Job->>Backend: Actualiza shopify_id/woocommerce_id
    Job->>Backend: Registra Sync Status
```

---

## Flujo de Autenticación

### Autenticación JWT

```mermaid
sequenceDiagram
    participant User as Usuario
    participant Frontend as Angular
    participant Backend as Laravel API
    participant DB as Database
    
    User->>Frontend: Login (email, password)
    Frontend->>Backend: POST /api/auth/login
    Backend->>DB: Verificar Credenciales
    DB->>Backend: User Data + Roles + Permissions
    Backend->>Backend: Generar JWT Token
    Backend->>Frontend: {token, user, permissions}
    Frontend->>Frontend: Guardar Token (LocalStorage)
    Frontend->>Frontend: Guardar User Data
    
    Note over Frontend: Token incluido en headers<br/>Authorization: Bearer {token}
    
    Frontend->>Backend: API Request + JWT Token
    Backend->>Backend: Validar JWT Token
    Backend->>Backend: Verificar Permisos
    Backend->>Frontend: Response Data
```

### Autorización y Permisos

```mermaid
graph TD
    Request[API Request] --> JWT[JWT Middleware<br/>Validate Token]
    JWT -->|Valid| UserScope[Company Scope<br/>Filter by empresa_id]
    JWT -->|Invalid| Reject[401 Unauthorized]
    
    UserScope --> PermissionCheck[Permission Check<br/>VerificarAccesoFuncionalidad]
    PermissionCheck -->|Has Permission| RoleCheck[Role Check]
    PermissionCheck -->|No Permission| Reject403[403 Forbidden]
    
    RoleCheck -->|Has Role| AdminCheck[Admin Check]
    RoleCheck -->|No Role| Reject403
    
    AdminCheck -->|Is Admin| SuperAdminCheck[Super Admin Check]
    AdminCheck -->|Not Admin| Reject403
    
    SuperAdminCheck -->|Is Super Admin| Controller[Controller Access]
    SuperAdminCheck -->|Not Super Admin| Reject403
    
    Controller --> Response[200 OK + Data]
    
    style JWT fill:#ff2d20
    style PermissionCheck fill:#ffa500
    style Controller fill:#4caf50
```

---

## Flujo de Datos

### Flujo General de una Operación

```mermaid
sequenceDiagram
    participant User as Usuario
    participant Frontend as Angular Frontend
    participant Guard as Route Guard
    participant Service as Angular Service
    participant Interceptor as HTTP Interceptor
    participant Backend as Laravel API
    participant Middleware as Laravel Middleware
    participant Controller as Controller
    participant Model as Eloquent Model
    participant DB as MySQL Database
    
    User->>Frontend: Navega a Ruta
    Frontend->>Guard: Can Activate?
    Guard->>Service: Check Permission
    Service->>Frontend: Has Permission
    
    alt Has Permission
        Frontend->>Service: Load Data
        Service->>Interceptor: HTTP Request
        Interceptor->>Interceptor: Add JWT Token
        Interceptor->>Backend: API Call
        
        Backend->>Middleware: Process Request
        Middleware->>Middleware: Validate JWT
        Middleware->>Middleware: Check Permissions
        Middleware->>Controller: Authorized Request
        
        Controller->>Model: Query Data
        Model->>DB: SQL Query
        DB->>Model: Result Set
        Model->>Controller: Collection/Model
        Controller->>Controller: Transform Data
        Controller->>Backend: JSON Response
        
        Backend->>Interceptor: Response
        Interceptor->>Service: Data
        Service->>Frontend: Update Component
        Frontend->>User: Display Data
    else No Permission
        Guard->>Frontend: Redirect/403
    end
```

### Flujo de Facturación Electrónica (MH)

```mermaid
sequenceDiagram
    participant User as Usuario
    participant Frontend as Angular
    participant Backend as Laravel
    participant MHController as MH Controller
    participant MHService as MH Service
    participant MH_API as Ministerio de Hacienda
    
    User->>Frontend: Generar Factura
    Frontend->>Backend: POST /api/ventas/facturar
    Backend->>Backend: Validar Datos
    Backend->>Backend: Crear Venta en BD
    Backend->>MHController: Generar DTE
    MHController->>MHService: Preparar DTE XML
    MHService->>MHService: Firmar XML
    MHService->>MH_API: POST /api/dte/registrar
    MH_API->>MHService: Response (UUID, QR)
    MHService->>Backend: Guardar UUID y QR
    Backend->>Backend: Actualizar Venta con DTE
    Backend->>Frontend: Factura Generada
    Frontend->>User: Mostrar Factura PDF
```

---

## Tecnologías Utilizadas

### Backend Stack
- **Framework**: Laravel 12
- **PHP**: ^8.2|^8.3|^8.4
- **Base de Datos**: MySQL
- **Autenticación**: JWT (php-open-source-saver/jwt-auth)
- **Permisos**: Spatie Laravel Permission
- **PDF**: DomPDF, mPDF
- **Excel**: Maatwebsite Excel
- **Imágenes**: Intervention Image
- **Códigos de Barras**: Picqer PHP Barcode Generator
- **QR Codes**: SimpleSoftwareIO Simple-QRCode
- **HTTP Client**: Guzzle
- **AWS SDK**: Para almacenamiento en S3

### Frontend Stack
- **Framework**: Angular 20
- **TypeScript**: 5.8.3
- **UI Libraries**: 
  - Bootstrap 5
  - ngx-bootstrap
  - FontAwesome
- **Charts**: Chart.js, Chartist
- **Forms**: Angular Reactive Forms
- **HTTP**: Angular HttpClient
- **Routing**: Angular Router (Lazy Loading)
- **PWA**: Angular Service Worker
- **Masks**: ngx-mask
- **Notifications**: SweetAlert2
- **Calendar**: FullCalendar
- **PDF Viewer**: ng2-pdf-viewer

### Servicios Externos
- **Facturación**: Ministerio de Hacienda (MH DTE)
- **Pagos**: N1CO, Wompi
- **E-commerce**: Shopify, WooCommerce
- **Mensajería**: WhatsApp API

---

## Notas de Arquitectura

### Patrones Utilizados
1. **MVC (Model-View-Controller)**: Separación clara de responsabilidades
2. **Repository Pattern**: Uso de Eloquent Models como repositorios
3. **Service Layer**: Lógica de negocio en servicios
4. **Resource Transformers**: Transformación de datos para API
5. **Middleware Pipeline**: Autenticación y autorización
6. **Queue Jobs**: Procesamiento asíncrono de tareas pesadas
7. **Event-Driven**: Eventos y listeners para acciones secundarias
8. **Lazy Loading**: Módulos Angular cargados bajo demanda

### Características de Seguridad
- Autenticación JWT
- Middleware de autorización por roles y permisos
- Scope por empresa (multi-tenancy)
- CORS configurado
- Validación de datos en Form Requests
- Sanitización de inputs

### Optimizaciones
- Lazy Loading de módulos Angular
- Cache HTTP en frontend
- Queue system para tareas pesadas
- Indexación de base de datos
- Service Worker para PWA

---

## Exportación a PDF

Este documento puede ser exportado a PDF usando:

1. **Mermaid CLI**: `npm install -g @mermaid-js/mermaid-cli`
   ```bash
   mmdc -i ARQUITECTURA_ACTUAL.md -o ARQUITECTURA_ACTUAL.pdf
   ```

2. **Pandoc**: 
   ```bash
   pandoc ARQUITECTURA_ACTUAL.md -o ARQUITECTURA_ACTUAL.pdf
   ```

3. **Markdown a PDF Online**: Usar servicios como markdown-pdf, md-to-pdf, etc.

4. **VS Code Extension**: Instalar extensiones como "Markdown PDF" o "Markdown Preview Enhanced"

Los diagramas Mermaid se renderizarán correctamente en la mayoría de visualizadores de Markdown modernos (GitHub, GitLab, VS Code, etc.).

