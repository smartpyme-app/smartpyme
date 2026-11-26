#!/usr/bin/env python3
"""Genera el .env de QA o DEMO a partir del .env de producción, cambiando lo necesario para que
el ambiente NO pueda tocar producción ni a clientes reales.

Uso: crear-env.py qa|demo <archivo_salida> <db_password>
Lee /home/smartpyme/repositories/unificado/Backend/.env (solo lectura).
"""
import base64
import os
import re
import secrets
import sys

PROD_ENV = "/home/smartpyme/repositories/unificado/Backend/.env"

AMBIENTES = {
    "qa": {
        "api": "https://apitest.smartpyme.site/",
        "front": "https://test.smartpyme.site",
        "db": "smartpyme_qa",
    },
    "demo": {
        "api": "https://apidemo.smartpyme.site/",
        "front": "https://demo.smartpyme.site",
        "db": "smartpyme_demo",
    },
}

# Se dejan vacías: con ellas el ambiente podría escribir en servicios reales o cobrar.
VACIAS = [
    "MAIL_HOST", "MAIL_USERNAME", "MAIL_PASSWORD",
    "AWS_ACCESS_KEY_ID", "AWS_SECRET_ACCESS_KEY",
    "NICO_CLIENT_ID", "NICO_CLIENT_SECRET", "NICO_WEBHOOK_SECRET", "NICO_API_KEY",
    "WHATSAPP_ACCESS_TOKEN", "WHATSAPP_VERIFY_TOKEN",
    "PUSHER_APP_ID", "PUSHER_APP_KEY", "PUSHER_APP_SECRET", "MIX_PUSHER_APP_KEY",
    "GOOGLE_GMAIL_CLIENT_ID", "GOOGLE_GMAIL_CLIENT_SECRET",
    "LUCAS_API_KEY", "LUCAS_API_URL",
    "BEDROCK_INFERENCE_PROFILE_ARN",
    "EC2_HOST", "EC2_USER", "EC2_KEY_PATH", "EC2_UPLOADS_PATH",
    "SENTRY_LARAVEL_DSN",
    "STRIPE_KEY", "STRIPE_SECRET",
]


def main():
    amb, salida, db_pass = sys.argv[1], sys.argv[2], sys.argv[3]
    a = AMBIENTES[amb]
    fijas = {
        "APP_ENV": amb,
        "APP_DEBUG": "false",
        "APP_KEY": "base64:" + base64.b64encode(secrets.token_bytes(32)).decode(),
        "JWT_SECRET": secrets.token_urlsafe(48),
        "APP_URL": a["api"],
        "FRONTEND_URL": a["front"],
        "DTE_FRONTEND_URL": a["front"],
        "CORS_ALLOWED_ORIGINS": f"{a['front']},http://localhost:4200,http://127.0.0.1:4200",
        "LOG_LEVEL": "debug" if amb == "qa" else "warning",
        "DB_HOST": "127.0.0.1",
        "DB_DATABASE": a["db"],
        "DB_USERNAME": a["db"],
        "DB_PASSWORD": db_pass,
        # Cola en línea: si fuera a Redis, los workers de producción procesarían estos trabajos.
        "QUEUE_CONNECTION": "sync",
        "CACHE_DRIVER": "file",
        "SESSION_DRIVER": "file",
        "BROADCAST_DRIVER": "log",
        "REDIS_PREFIX": f"smartpyme_{amb}_",
        "REDIS_DB": "5" if amb == "qa" else "7",
        "REDIS_CACHE_DB": "6" if amb == "qa" else "8",
        "REDIS_QUEUE": amb,
        "MAIL_MAILER": "log",
        "MAIL_TO_ADDRESS": "",
        "PAYMENT_SANDBOX_MODE": "true",
        "NICO_BASE_URL": "https://api-sandbox.n1co.shop/api/v2",
        "WHATSAPP_DEV_MODE": "true",
        "BOXFUL_ENV": "sandbox",
        "DTE_S3_SCHEDULE_ENABLED": "false",
        "SENTRY_ENVIRONMENT": amb,
        "SHOPIFY_ENABLED": "false",
        "WOOCOMMERCE_ENABLED": "false",
    }
    for k in VACIAS:
        fijas.setdefault(k, "")

    lineas, vistas = [], set()
    for linea in open(PROD_ENV, encoding="utf-8"):
        m = re.match(r"^([A-Z0-9_]+)=", linea)
        if m and m.group(1) in fijas:
            k = m.group(1)
            lineas.append(f"{k}={fijas[k]}\n")
            vistas.add(k)
        else:
            lineas.append(linea)
    faltan = [f"{k}={v}\n" for k, v in fijas.items() if k not in vistas]
    cabecera = (f"# Ambiente {amb.upper()} generado por /root/ambientes/crear-env.py a partir de producción.\n"
                f"# Base propia ({a['db']}), sin correo real, sin cola compartida, sin llaves de servicios.\n")
    old = os.umask(0o077)
    with open(salida, "w", encoding="utf-8") as f:
        f.write(cabecera + "".join(lineas) + "\n# Agregadas para el ambiente\n" + "".join(faltan))
    os.umask(old)
    print(f"{salida} listo ({len(vistas)} variables cambiadas, {len(faltan)} agregadas)")


if __name__ == "__main__":
    main()
