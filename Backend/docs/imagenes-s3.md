# Imágenes de productos en S3 — Comandos y referencia

Las imágenes de productos se almacenan en el bucket **`sp-imagenes-productos`** (lectura pública). Los DTE siguen en su bucket privado (`AWS_BUCKET`); no reutilizar esa variable para imágenes.

---

## 1. Variables de entorno (`.env`)

| Variable | Uso |
|----------|-----|
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | Mismas credenciales IAM que para DTE (con policy adicional en el bucket de imágenes). |
| `AWS_DEFAULT_REGION` | Ej. `us-east-2`. |
| `AWS_PRODUCT_IMAGES_BUCKET` | `sp-imagenes-productos` |
| `AWS_PRODUCT_IMAGES_URL` | URL base pública, ej. `https://sp-imagenes-productos.s3.us-east-2.amazonaws.com` |
| `AWS_PRODUCT_IMAGES_DISK` | Disco Laravel (defecto: `s3_productos`) |
| `PRODUCT_IMAGES_LOCAL_ROOT` | Carpeta **`img`** en el VPS (dentro va `productos/`). No es `Backend/public/img` si el sitio sirve desde `api/img`. |

**No** usar `AWS_BUCKET` para imágenes de productos.

En producción, si las fotos están en `api/img/productos/`, configura la ruta **hasta `img`** (sin `productos`):

```env
PRODUCT_IMAGES_LOCAL_ROOT=/ruta/completa/al/api/img
```

Prueba rápida en el VPS (debe existir el archivo):

```bash
ls /ruta/completa/al/api/img/productos/e3c52001f8b367e2408c4419cae187b9.jpg
```

Tras cambiar `.env`:

```bash
php artisan config:clear
php artisan config:cache
```

---

## 2. Configuración AWS (checklist)

### Block Public Access (solo bucket `sp-imagenes-productos`)

| Ajuste | Valor |
|--------|--------|
| Block public ACLs | ON |
| Block public **bucket policies** | **OFF** |
| Ignore public ACLs | ON |
| Restrict public **buckets** | **OFF** |

**Object Ownership:** Bucket owner enforced (sin ACLs por objeto). Laravel sube con `ContentType` solamente; la lectura pública es vía bucket policy.

### Bucket policy (lectura pública)

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Sid": "PublicReadProductImages",
    "Effect": "Allow",
    "Principal": "*",
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::sp-imagenes-productos/*"
  }]
}
```

### IAM (usuario de la app) — policy adicional

Adjuntar al mismo usuario que usa S3 para DTE (no modificar permisos del bucket de DTE):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListProductImagesBucket",
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": "arn:aws:s3:::sp-imagenes-productos"
    },
    {
      "Sid": "WriteProductImages",
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::sp-imagenes-productos/*"
    }
  ]
}
```

### CORS (consola S3 → Permissions → CORS)

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedOrigins": [
      "https://*.smartpyme.site",
      "http://localhost:4200"
    ],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

Ajustar `AllowedOrigins` según dominios reales del frontend.

### Recomendado

- Cifrado por defecto: SSE-S3.
- Versioning: opcional.

---

## 3. `php artisan imagenes:migrate-to-s3`

**Qué hace:** Para cada fila en `productos_imagenes` con archivo local en `public/img/productos/`, sube el objeto a S3 (clave `productos/{hash}.jpg`), y si la subida fue exitosa **elimina** el archivo del VPS. La columna `img` no cambia (`/productos/{hash}.jpg`).

**Requisitos:** `AWS_PRODUCT_IMAGES_*` configurado (excepto en `--dry-run` para simulación sin bucket).

| Opción | Descripción |
|--------|-------------|
| `--dry-run` | Solo imprime acciones; no sube ni borra local. |
| `--limit=` | Máximo de filas a procesar. |
| `--empresa=` | Solo imágenes de productos con `id_empresa` dado. |
| `--local-root=` | Ruta a la carpeta `img` (override puntual de `PRODUCT_IMAGES_LOCAL_ROOT`). |

Al iniciar, el comando imprime `Carpeta local de imágenes: ...` para verificar la ruta.

**Idempotencia:** Si el objeto ya existe en S3, no re-sube; si el archivo local sigue presente, lo borra.

### Ejemplos

```bash
php artisan imagenes:migrate-to-s3 --dry-run
php artisan imagenes:migrate-to-s3 --limit=100
php artisan imagenes:migrate-to-s3 --empresa=42
php artisan imagenes:migrate-to-s3
```

---

## 4. Convención de claves S3

- `productos/{md5-del-jpg}.jpg` (mismo hash que en disco local histórico).

---

## 5. Orden de deploy sugerido

1. AWS: bucket policy, IAM, CORS, Block Public Access.
2. `.env` en el VPS con `AWS_PRODUCT_IMAGES_*`.
3. Deploy backend (nuevas imágenes → S3).
4. `imagenes:migrate-to-s3 --dry-run` y luego migración real.
5. Deploy frontend con `productImagesUrl`.

---

## 6. Resumen rápido

| Objetivo | Comando / acción |
|----------|------------------|
| Probar migración | `php artisan imagenes:migrate-to-s3 --dry-run` |
| Migrar tanda | `--limit=N` |
| Una empresa | `--empresa=ID` |
| Ver opciones | `php artisan imagenes:migrate-to-s3 --help` |
