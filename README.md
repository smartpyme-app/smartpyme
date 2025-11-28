# SmartPyme

Sistema ERP integral diseñado para la gestión completa de pequeñas y medianas empresas, incluyendo módulos de ventas, compras, inventario, contabilidad, planilla y más.

## 📋 Tabla de Contenidos

- [Descripción](#-descripción)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Tecnologías](#-tecnologías)
- [Requisitos Previos](#-requisitos-previos)
- [Instalación](#-instalación)
  - [Backend](#backend)
  - [Frontend](#frontend)
- [Configuración](#-configuración)
  - [Con Laravel Herd (Recomendado)](#con-laravel-herd-recomendado)
  - [Sin Laravel Herd](#sin-laravel-herd)
- [Uso del Script de Cambio de PHP](#-uso-del-script-de-cambio-de-php)
- [Ejecución del Proyecto](#-ejecución-del-proyecto)
- [Estructura de Directorios](#-estructura-de-directorios)
- [Documentación Adicional](#-documentación-adicional)

## 🎯 Descripción

SmartPyme es una plataforma web completa que permite a las empresas gestionar sus operaciones diarias de manera eficiente. El sistema incluye:

- **Ventas y Facturación**: Gestión completa del ciclo de ventas, facturación electrónica y control de cuentas por cobrar
- **Compras y Proveedores**: Administración de compras, proveedores y cuentas por pagar
- **Inventario**: Control de stock, categorías, productos y traslados entre sucursales
- **Contabilidad**: Sistema contable completo con partidas, catálogo de cuentas, cierres mensuales y reportes financieros
- **Planilla**: Gestión de empleados, nómina y prestaciones
- **Reportes**: Generación de reportes financieros, de ventas, inventario y más
- **Integraciones**: API externa, integración con WhatsApp y otros servicios

## 📁 Estructura del Proyecto

```
smartpyme/
├── Backend/          # API REST desarrollada en Laravel
├── Frontend/         # Aplicación web desarrollada en Angular
├── Docs/             # Diagramas y documentación del proyecto
└── switch-php.sh     # Script para cambiar versiones de PHP
```

## 🛠 Tecnologías

### Backend

- **Framework**: Laravel 8.x / 12.x (según branch)
- **Lenguaje**: PHP 7.4+ / 8.2+
- **Base de Datos**: MySQL/MariaDB
- **Autenticación**: JWT (JSON Web Tokens)
- **Librerías Principales**:
  - `spatie/laravel-permission` - Gestión de permisos y roles
  - `maatwebsite/excel` - Importación/Exportación de Excel
  - `barryvdh/laravel-dompdf` / `mpdf/mpdf` - Generación de PDFs
  - `intervention/image` - Manipulación de imágenes
  - `picqer/php-barcode-generator` - Generación de códigos de barras
  - `simplesoftwareio/simple-qrcode` - Generación de códigos QR
  - `aws/aws-sdk-php` - Integración con AWS

### Frontend

- **Framework**: Angular 20.x
- **Lenguaje**: TypeScript
- **Estilos**: Bootstrap 5, CSS3
- **Librerías Principales**:
  - `@angular/service-worker` - PWA (Progressive Web App)
  - `chart.js` / `ng2-charts` - Gráficos y visualizaciones
  - `@fullcalendar/angular` - Calendarios interactivos
  - `sweetalert2` - Alertas y notificaciones
  - `ngx-bootstrap` - Componentes Bootstrap para Angular
  - `ngx-mask` - Máscaras de entrada
  - `moment` / `dayjs` - Manejo de fechas

## 📦 Requisitos Previos

### Para Backend

- PHP 7.4+ o 8.2+ (según el branch)
- Composer 2.x
- MySQL 5.7+ o MariaDB 10.3+
- Node.js 16+ y NPM (para compilar assets)
- Extensiones PHP requeridas:
  - BCMath
  - Ctype
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PDO
  - Tokenizer
  - XML
  - GD o Imagick (para manipulación de imágenes)

### Para Frontend

- Node.js 16+ o superior
- NPM 8+ o Yarn
- Angular CLI 20.x

### Opcional (Recomendado)

- **Laravel Herd**: Para desarrollo local en macOS (simplifica la gestión de PHP y servidores)

## 🚀 Instalación

### Backend

1. **Clonar el repositorio** (si aún no lo has hecho):
   ```bash
   git clone <repository-url>
   cd smartpyme
   ```

2. **Navegar al directorio Backend**:
   ```bash
   cd Backend
   ```

3. **Instalar dependencias de Composer**:
   ```bash
   composer install
   ```

4. **Configurar el archivo de entorno**:
   ```bash
   cp .env.example .env
   ```

5. **Generar la clave de aplicación**:
   ```bash
   php artisan key:generate
   ```

6. **Configurar la base de datos**:
   Edita el archivo `.env` y configura las siguientes variables:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=smartpyme
   DB_USERNAME=tu_usuario
   DB_PASSWORD=tu_contraseña
   ```

7. **Ejecutar migraciones y seeders**:
   ```bash
   php artisan migrate --seed
   ```

8. **Crear enlace simbólico para storage**:
   ```bash
   php artisan storage:link
   ```

9. **Configurar permisos** (si es necesario):
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

### Frontend

1. **Navegar al directorio Frontend**:
   ```bash
   cd Frontend
   ```

2. **Instalar dependencias**:
   ```bash
   npm install
   ```

3. **Configurar el archivo de entorno**:
   Edita `src/environments/environment.ts` y configura la URL de la API:
   ```typescript
   export const environment = {
     production: false,
     API_URL: 'http://localhost:8000',
     APP_URL: 'http://localhost:4200',
   };
   ```

## ⚙️ Configuración

### Con Laravel Herd (Recomendado)

Laravel Herd es la forma más sencilla de desarrollar aplicaciones Laravel en macOS. Proporciona PHP, Nginx y MySQL preconfigurados.

#### Instalación de Herd

1. Descarga e instala Laravel Herd desde [herd.laravel.com](https://herd.laravel.com)

2. Verifica la instalación:
   ```bash
   herd --version
   ```

3. Instala las versiones de PHP necesarias:
   ```bash
   brew install php@7.4
   brew install php@8.4
   ```

#### Configuración del Proyecto con Herd

1. **Cambiar a la versión de PHP correcta**:
   ```bash
   # Para Laravel 8 (branch con PHP 7.4)
   herd use php@7.4
   
   # Para Laravel 12 (branch con PHP 8.4)
   herd use php@8.4
   ```

2. **Enlazar el proyecto con Herd**:
   ```bash
   cd Backend
   herd link smartpyme
   ```

3. **Acceder al proyecto**:
   El proyecto estará disponible en: `http://smartpyme.test`

4. **Configurar el archivo `.env`**:
   ```env
   APP_URL=http://smartpyme.test
   ```

### Sin Laravel Herd

Si prefieres no usar Herd, puedes configurar el proyecto manualmente:

1. **Configurar un servidor web** (Apache/Nginx) o usar el servidor de desarrollo de PHP:
   ```bash
   cd Backend/public
   php -S localhost:8000
   ```

2. **Configurar el archivo `.env`**:
   ```env
   APP_URL=http://localhost:8000
   ```

3. **Configurar CORS** (si el frontend está en otro puerto):
   Edita `config/cors.php` y agrega tu dominio frontend a los orígenes permitidos.

## 🔄 Uso del Script de Cambio de PHP

El proyecto incluye un script para cambiar fácilmente entre versiones de PHP:

### Uso Básico

```bash
# Ver versión actual
./switch-php.sh

# Cambiar a PHP 7.4
./switch-php.sh 7.4

# Cambiar a PHP 8.4
./switch-php.sh 8.4
```

### Notas Importantes

- El cambio de versión afecta globalmente a tu máquina
- Reinicia los servidores después de cambiar la versión
- Ejecuta `composer install` después del cambio para verificar compatibilidad

Para más detalles, consulta la documentación en `Docs/SWITCH_PHP.md`.

## ▶️ Ejecución del Proyecto

### Backend

**Con Herd**:
El servidor se ejecuta automáticamente. Solo asegúrate de que Herd esté corriendo.

**Sin Herd**:
```bash
cd Backend/public
php -S localhost:8000
```

O usando Artisan:
```bash
cd Backend
php artisan serve
```

El backend estará disponible en: `http://localhost:8000` o `http://smartpyme.test` (con Herd)

### Frontend

```bash
cd Frontend
npm start
# o
ng serve
```

El frontend estará disponible en: `http://localhost:4200`

### Acceso a la Aplicación

1. Abre tu navegador en `http://localhost:4200`
2. Inicia sesión con las credenciales creadas por los seeders
3. Por defecto, puedes usar:
   - Email: `admin@smartpyme.com`
   - Password: (verificar en seeders)

## 📂 Estructura de Directorios

### Backend

```
Backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # Controladores de la API
│   │   ├── Middleware/      # Middleware personalizado
│   │   └── Resources/        # Transformadores de recursos
│   ├── Models/              # Modelos Eloquent
│   ├── Services/            # Lógica de negocio
│   ├── Exports/              # Exportaciones Excel
│   ├── Imports/              # Importaciones Excel
│   └── Jobs/                 # Jobs de cola
├── config/                   # Archivos de configuración
├── database/
│   ├── migrations/           # Migraciones de BD
│   └── seeders/              # Seeders de datos iniciales
├── routes/
│   ├── api.php               # Rutas de la API
│   └── modulos/              # Rutas organizadas por módulos
└── public/                   # Punto de entrada público
```

### Frontend

```
Frontend/
├── src/
│   ├── app/
│   │   ├── views/            # Componentes de vistas
│   │   ├── services/         # Servicios Angular
│   │   ├── guards/           # Guards de autenticación
│   │   └── interceptors/     # Interceptores HTTP
│   ├── assets/               # Recursos estáticos
│   └── environments/         # Configuraciones de entorno
└── dist/                     # Build de producción
```

## 📚 Documentación Adicional

- **API Externa**: Ver `Backend/docs/API_EXTERNAL.md` para documentación de la API externa
- **Diccionario de Datos**: Ver `Backend/docs/DATA_DICTIONARY.md`
- **Diagramas**: Ver carpeta `Docs/` para diagramas de arquitectura y base de datos
- **Cambio de PHP**: Ver `Docs/SWITCH_PHP.md` para guía detallada

## 🔧 Comandos Útiles

### Backend

```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed

# Crear controlador
php artisan make:controller NombreController

# Crear modelo con migración
php artisan make:model NombreModel -m

# Ver rutas
php artisan route:list

# Ejecutar tests
php artisan test
```

### Frontend

```bash
# Compilar para desarrollo
npm start

# Compilar para producción
npm run build

# Ejecutar tests
npm test

# Generar componente
ng generate component nombre-componente

# Generar servicio
ng generate service nombre-servicio
```

## 🐛 Solución de Problemas

### Error de permisos en storage

```bash
chmod -R 775 Backend/storage Backend/bootstrap/cache
```

### Error de conexión a la base de datos

- Verifica que MySQL esté corriendo
- Revisa las credenciales en `.env`
- Asegúrate de que la base de datos existe

### Error de CORS

- Verifica `config/cors.php` en el backend
- Asegúrate de que la URL del frontend esté en los orígenes permitidos

### Problemas con Composer

```bash
composer clear-cache
composer install --no-cache
```

## 📝 Notas de Desarrollo

- El proyecto usa diferentes versiones de Laravel según el branch:
  - Branch principal: Laravel 12 con PHP 8.2+
  - Branch legacy: Laravel 8 con PHP 7.4+
- Usa el script `switch-php.sh` para cambiar entre versiones según el branch en el que trabajes
- El frontend es una PWA, por lo que puede funcionar offline después de la primera carga

## 📄 Licencia

Este proyecto es privado y de uso exclusivo para SmartPyme.

**SmartPyme** - Sistema ERP Integral para PyMEs
