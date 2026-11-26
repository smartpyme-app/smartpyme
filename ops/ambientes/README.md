# Ambientes QA y DEMO

| Ambiente | API | Frontend | Código | Base | Para |
|---|---|---|---|---|---|
| Producción | apiunificado / api | app, app-unificado, abaco, onvo, sivareconomics, contadores | `repositories/unificado` (main) | `smartpyme_prod` | clientes |
| QA | apitest.smartpyme.site | test.smartpyme.site | `repositories/qa` (develop) | `smartpyme_qa` | desarrollo y pruebas |
| DEMO | apidemo.smartpyme.site | demo.smartpyme.site | **el de producción** (`repositories/unificado`) | `smartpyme_demo` | ventas |

QA y DEMO **no pueden tocar producción ni a clientes**:

- Base propia, con un usuario de MySQL que solo tiene permisos sobre esa base.
- `arranque.php` fija `.env`, `storage`, caché y carpeta pública del ambiente **antes** de arrancar Laravel,
  y se detiene si la base cargada no es la del ambiente o si la cola no es `sync`.
- Cola `sync`: si usara Redis, los workers de producción procesarían sus trabajos contra la base real.
- Correo en `log`, sin llaves de AWS, N1co, WhatsApp, Pusher, Gmail, Lucas ni EC2; `APP_KEY` y `JWT_SECRET` propios
  (un token de QA no sirve en producción).
- En la base: todas las empresas en Hacienda `00` (pruebas), sin tokens de Shopify, WooCommerce, Wompi ni Boxful,
  sin webhooks y sin llaves de API externa. Credenciales de Hacienda solo para las empresas indicadas.

## En el servidor

```
/root/ambientes/                 scripts (solo root) y contraseñas generadas
/home/smartpyme/ambientes/qa     .env, arranque.php, artisan, cachear-config.php, storage, cache
/home/smartpyme/ambientes/demo   ídem
public_html/apitest/index.php    -> ambientes/qa/arranque.php
public_html/apidemo/index.php    -> ambientes/demo/arranque.php
```

Los archivos de esta carpeta tienen marcadores (`__AMBIENTE__`, `__CODIGO__`, `__PUBLICO__`) que se reemplazan al instalarlos.

**No usar `artisan config:cache` ni `artisan optimize` en los ambientes**: arman la config con una instancia nueva
que lee el `.env` del repositorio (en DEMO, el de producción). El `artisan` del ambiente los rechaza.
Para cachear: `cd /home/smartpyme/ambientes/<amb> && php cachear-config.php`.

## Refrescar los datos

```bash
/root/ambientes/restaurar-staging.sh            # copia nocturna -> smartpyme_staging (~12 min, sin tocar prod)
CLAVE_QA_HASH="$(cat /root/ambientes/clave-qa.hash)" \
  python3 /root/ambientes/crear-subconjunto.py smartpyme_qa 2,324,756,212,529,367,700,853,13,8,142,437,380
CLAVE_QA_HASH=CONSERVAR python3 /root/ambientes/crear-subconjunto.py smartpyme_demo \
  2,13,56,389,487,528,553,590,650,660,695,786,835,867 --conservar-mh 2,13,56,389,487,528,553,590,650,660,695,786,835,867
```

`crear-subconjunto.py` copia las empresas indicadas con todo lo que cuelga de ellas (tablas con `id_empresa`
y tablas hijas por su tabla padre) y luego neutraliza. Si aparece una tabla nueva sin clasificar, se detiene
y pide agregarla a `GLOBALES`, `VACIAS`, `POR_EMPRESA` o `HIJAS`.

### Empresas

- **QA** (contraseña común para todos los usuarios, en `/root/ambientes/clave-qa.txt`):
  2 SmartPyme Technologies (super admin), 324 Octavia Mundo Gourmet (contabilidad + restaurante),
  756 Spoties (restaurante + contabilidad), 212 Fotopro (planillas), 529 Ambienta Hogar (Shopify, API externa),
  367 Cristiam Frank (WooCommerce), 700 Vilorio Ohle (Honduras), 853 Grupo Tec (Costa Rica),
  13 Demo SmartPyme (inventario grande), 8/142/437/380 empresas de pruebas.
- **DEMO** (conserva las contraseñas, ventas entra como siempre): 2 y las empresas demo activas
  13, 56, 389, 487, 528, 553, 590, 650, 660, 695, 786, 835, 867.
