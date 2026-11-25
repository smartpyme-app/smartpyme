# SmartPyme en Docker

Réplica local del servidor de producción: mismas versiones, mismas extensiones de PHP, mismos límites y la misma forma de correr la cola.

## Qué levanta

| Servicio | Equivale en el servidor | Puerto local |
|---|---|---|
| `app` | PHP-FPM 8.4 (pool `apiunificado`) | — |
| `web` | nginx/Apache que sirve la API | http://localhost:8080 |
| `worker` ×3 | supervisor `smartpyme-unificado-worker` (3 procesos) | — |
| `scheduler` | cron `schedule:run` cada minuto (**apagado por defecto**) | — |
| `mariadb` | MariaDB 10.11 (`smartpyme_prod` y `smartpyme_analitica`) | localhost:3307 |
| `redis` | Redis 6.2 (solo cola, vía predis) | localhost:6380 |
| `frontend` | `app.smartpyme.site`, Angular compilado (opcional, `--profile fe-build`) | http://localhost:8081 |
| `frontend-dev` | `ng serve` con recarga en vivo (opcional) | http://localhost:4200 |

### Igual que producción

- **PHP 8.4:** bcmath, ftp, gd, mysqli, pdo_mysql, pcntl, xsl, zip y OPcache, más las que trae la imagen oficial. Son las mismas 45 que da `php -m` en el servidor.
- **Límites de PHP:** `memory_limit 128M`, `upload_max_filesize 2M`, `post_max_size 8M`, `max_execution_time 30`, OPcache 512 MB y 60,000 archivos, zona horaria UTC.
- **PHP-FPM:** `ondemand` con 80 procesos y 1000 peticiones por proceso.
- **MariaDB:** `latin1` en el servidor, el mismo `sql_mode`, hora UTC y nombres de tabla sensibles a mayúsculas.
- **Cola:** `queue:work redis --tries=3 --timeout=3600 --max-jobs=1000 --max-time=3600 --memory=512`, 3 procesos.
- **Frontend:** las mismas reglas de caché del `.htaccess` (archivos con hash, 1 año; `index.html` y service worker, sin caché) y la misma regla de rutas a `index.html`.

### Diferencias a propósito

- **Servidor web:** nginx + PHP-FPM en lugar de nginx + Apache de cPanel. Las reglas son las mismas.
- **Memoria de la base:** el buffer pool es de 1 GB en lugar de 16 GB.
- **Source maps:** no se publican; el servidor hoy sí los publica.
- **Scheduler:** apagado salvo que se encienda a mano.
- **Correo:** `MAIL_MAILER=log` en el ejemplo de `.env`, para no enviar correos reales.

## Requisitos

- Docker Desktop con **al menos 8 GB de memoria** (Settings → Resources). La compilación de Angular usa unos 8 GB; con 4 GB el build del frontend se cae.
- Unos 15 GB libres en disco si se carga una copia de la base.

## Mac y Windows

Funciona en los dos con Docker Desktop. Las imágenes son multiplataforma: Apple Silicon (arm64) e Intel/AMD (amd64).

**Windows:**
- Docker Desktop con el motor **WSL 2**.
- **Clonar el repositorio dentro de WSL** (`\\wsl$\Ubuntu\home\<usuario>\smartpyme`), no en `C:\`. Desde `C:\` Laravel es entre 5 y 10 veces más lento y `ng serve` no detecta los cambios.
- Correr los comandos de esta guía desde la terminal de Ubuntu (WSL), no desde PowerShell.
- Darle a WSL al menos 8 GB de memoria en `%UserProfile%\.wslconfig`:
  ```ini
  [wsl2]
  memory=10GB
  ```
  Luego correr `wsl --shutdown` y reiniciar Docker Desktop.
- Los saltos de línea de los scripts los fija `.gitattributes`, así que no hace falta configurar `core.autocrlf`.

**Mac:** Docker Desktop → Settings → Resources → Memory, al menos 8 GB.

## Primera vez

```bash
# 1. Variables del backend (nunca claves de producción)
cp Backend/.env.docker.example Backend/.env.docker

# 2. Base de datos y Redis
docker compose up -d mariadb redis

# 3. Dependencias PHP y clave de la aplicación
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate --show
#    pegar el valor en APP_KEY= de Backend/.env.docker

# 4. Todo lo demás
docker compose up -d --build
```

## Datos

Hay dos formas de tener la base, según lo que necesites.

**A. Base vacía con la estructura de producción** (rápida, sin datos):

```bash
docker compose exec app php artisan migrate
```

Laravel carga `Backend/database/schema/mysql-schema.sql`, que es la estructura exacta de producción, y después corre las migraciones nuevas. Tarda segundos y no trae datos ni usuarios.

**B. Copia de producción** (para probar con datos reales):

1. Copiar `smartpyme_prod.sql.gz` y, si aplica, `smartpyme_analitica.sql.gz` en `docker/mariadb/dumps/`. Esa carpeta nunca se sube a git.
2. Borrar el volumen y volver a levantar: `docker compose down -v && docker compose up -d`. El script `docker/mariadb/init/00-databases.sh` carga los respaldos la primera vez.
3. Correr `docker compose exec app php artisan migrate` para aplicar las migraciones que todavía no estén en esa copia.

> Son datos reales de clientes: disco cifrado, no compartir el archivo y no emitir DTE ni cobrar desde empresas reales.

### Mantener el schema al día

Cada migración nueva se agrega normalmente en `database/migrations`. Después de un pase grande a producción se regenera el schema con `php artisan schema:dump` desde una copia de la estructura de producción, quitando las líneas `DEFINER=` y `sandbox mode`. Ningún cambio de estructura se hace a mano en producción.

## Copia diaria de producción (Google Drive)

Cada noche a las 3:30 am (El Salvador) el servidor genera una copia de `smartpyme_prod` y `smartpyme_analitica`, la verifica y la sube a la Unidad compartida de Drive, en `copias-bd/AAAA-MM-DD/`. Se conservan las últimas 3. Script: `docker/db-copia/servidor-copia-drive.sh`.

Para usarla en tu máquina:

1. Pide acceso a la Unidad compartida "SmartPyme · Copias BD".
2. Instala rclone (Mac: `brew install rclone`; Ubuntu/WSL: `sudo apt install rclone`).
3. Configura el remoto una sola vez con tu cuenta de Google:
   ```bash
   rclone config create smartpyme-drive drive scope=drive.readonly team_drive=<ID de la Unidad compartida>
   ```
   Se abre el navegador para que inicies sesión.
4. Descarga y carga la copia:
   ```bash
   ./docker/db-copia/descargar-copia.sh
   ```
   El script verifica el archivo, pide confirmación, recrea tu base local y corre `migrate`.

También se puede bajar a mano desde drive.google.com y dejar los `.sql.gz` en `docker/mariadb/dumps/`.

### Importar en MySQL (Workbench) en vez de MariaDB

La copia es de MariaDB 10.11. MySQL 8/9 la rechaza con `ERROR 3105 ... generated column` (4 tablas con columnas generadas: `comision_movimientos`, `restaurante_sesiones_mesa`, `tipos_cliente_empresa`, `transacciones_puntos`) y las vistas traen `DEFINER` de usuarios de producción. Antes de importar:

```bash
gunzip -k docker/mariadb/dumps/smartpyme_prod.sql.gz
python3 docker/db-copia/adaptar-a-mysql.py docker/mariadb/dumps/smartpyme_prod.sql
```

e importa `smartpyme_prod.mysql.sql`. Solo cambia el texto del dump (lista de columnas en esos INSERT y sin DEFINER); los datos son los mismos. Con el MariaDB de Docker no hace falta.

## Memoria

- **16 GB de RAM o más:** Docker con 8 GB. Funciona todo, incluido el build de producción del frontend (`--profile fe-build`).
- **8 GB de RAM:** Docker con 4 a 5 GB para backend, base y cola. El frontend se corre fuera de Docker con `cd Frontend && npm start`, apuntando a `http://localhost:8080`.

## Uso diario

```bash
docker compose up -d                          # levantar
docker compose logs -f app worker             # ver logs
docker compose exec app php artisan tinker    # artisan
docker compose exec app php artisan test      # pruebas
docker compose restart worker                 # tomar código nuevo en la cola (= queue:restart)
docker compose --profile dev-fe up -d         # frontend con recarga en vivo en :4200
docker compose --profile scheduler up -d      # tareas programadas (solo con datos de prueba)
docker compose down                           # apagar (los datos quedan)
docker compose down -v                        # apagar y BORRAR la base local
```

## Imágenes para CI/CD

Las mismas definiciones generan las imágenes de producción:

```bash
# Backend: código y vendor dentro, sin dependencias de desarrollo, autoload optimizado
docker build -f docker/php/Dockerfile --build-context docker=docker --target prod -t smartpyme/backend:prod Backend

# Frontend: API de producción, sin source maps
docker build -f docker/frontend/Dockerfile --build-context docker=docker --build-arg NG_CONFIGURATION=production -t smartpyme/frontend:prod Frontend
```

En el pipeline de GitHub Actions, el mismo contenedor sirve para correr las pruebas (`php artisan test`, `ng test`) con el mismo PHP que producción.

## Fuera de este repositorio

- La **Go API** de dashboards y el **ETL de analítica** (`sp-analytics`).
- **Lucas** (FastAPI y Scylla), que ya corre en contenedores en el servidor.

Cuando sus repositorios entren al mismo flujo, se agregan como servicios a este `docker-compose.yml`.
