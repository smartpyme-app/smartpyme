#!/usr/bin/env python3
"""Crea una base QA o DEMO con solo algunas empresas, a partir de smartpyme_staging
(la restauración de la copia nocturna). NUNCA lee ni escribe smartpyme_prod.

Uso:  crear-subconjunto.py smartpyme_qa 2,324,756
      crear-subconjunto.py smartpyme_demo 2,56,389 --conservar-mh 2,56,389

Pasos:
  1. Recrea la base destino con la estructura de staging (tablas, FK, vistas).
  2. Copia: empresas elegidas -> tablas con id_empresa -> catálogos globales -> tablas hijas
     (filtradas por su tabla padre ya copiada, en orden de dependencias).
  3. Neutraliza: Hacienda en pruebas (00), sin tokens de integraciones, contraseñas de QA,
     sin webhooks, sin llaves de API externa.
"""
import os
import subprocess
import sys

ORIGEN = "smartpyme_staging"
PROHIBIDAS = {"smartpyme_prod", "smartpyme_analitica", ORIGEN, "mysql", "information_schema",
              "performance_schema", "sys"}

# Catálogos sin empresa: se copian completos.
GLOBALES = {
    "actividades_economicas", "aliados", "authorization_types", "campanias", "codigos", "currencies",
    "departamentos", "distritos", "dte_tipo_mapeo", "estados_paises", "funcionalidades",
    "home_banners", "incoterms", "migrations", "modules", "module_permissions", "municipios",
    "nif_categorias", "paises", "pais_activos_categorias_plantilla", "pais_configuracion",
    "permissions", "planes", "promocionales", "recintos", "regimenes", "submodules",
    "tipos_cliente_base", "unidades",
}
# Logs, colas, temporales y datos personales sin empresa: solo estructura.
VACIAS = {
    "accesos", "failed_jobs", "jobs", "password_resets", "registros", "telescope_entries",
    "telescope_entries_tags", "telescope_monitoring", "tmp_inventario", "tmp_productos",
    "zz_inv306_bodegas", "zz_inv306_control", "whatsapp_sessions", "whatsapp_messages",
    "restaurante_idempotency_keys", "analisis_ventas_mensual_queue", "kardex_masivo_queue",
    "trabajos_pendientes",
}
# Tablas con empresa_id (en vez de id_empresa) o con otra columna de empresa.
POR_EMPRESA = {
    "empresas": "id",
    "custom_fields": "empresa_id",
    "empresa_configuracion": "empresa_id",
    "empresa_configuracion_planilla": "empresa_id",
    "empresa_cargo": "empresa_id",
    "empresa_departamento": "empresa_id",
    "suscripciones": "empresa_id",
    "suscripcion_bajas": "empresa_id",
    "contador_empresa_invitaciones": "id_empresa_cliente",
}
# Filas globales (id_empresa NULL) que sí se copian.
CON_NULOS = {"roles"}
# Tabla hija -> (columna, tabla padre).
HIJAS = {
    "aguinaldo_detalles": ("id_aguinaldo", "aguinaldos"),
    "areas_empresa": ("id_departamento", "departamentos_empresa"),
    "boxful_shipments": ("paquete_id", "paquetes"),
    "boxful_parcels": ("boxful_shipment_id", "boxful_shipments"),
    "cajas": ("sucursal_id", "sucursales"),
    "caja_cortes": ("caja_id", "cajas"),
    "cardex": ("id_producto", "productos"),
    "categoria_sucursal_cuenta": ("id_sucursal", "sucursales"),
    "cliente_actividad_reciente": ("id_cliente", "clientes"),
    "cliente_categorias_preferidas": ("id_cliente", "clientes"),
    "cliente_metricas_rfm": ("id_cliente", "clientes"),
    "cliente_notas": ("id_cliente", "clientes"),
    "cliente_notas_categorias": ("id_nota", "cliente_notas"),
    "cliente_productos_top": ("id_cliente", "clientes"),
    "cliente_ventas_mensuales": ("id_cliente", "clientes"),
    "cliente_visitas": ("id_cliente", "clientes"),
    "clinica_profesional_sucursales": ("id_sucursal", "sucursales"),
    "comanda_detalle_restaurante": ("comanda_id", "comandas_restaurante"),
    "compra_impuestos": ("id_compra", "compras"),
    "contactos_cliente": ("id_cliente", "clientes"),
    "contactos_emergencia": ("id_empleado", "empleados"),
    "credito_cuotas": ("id_contrato", "credito_contratos"),
    "custom_field_values": ("custom_field_id", "custom_fields"),
    "detalles_compra": ("id_compra", "compras"),
    "detalles_compuesto_devolucion_venta": ("id_producto", "productos"),
    "detalles_compuesto_venta": ("id_detalle", "detalles_venta"),
    "detalles_cotizacion_ventas": ("id_cotizacion_venta", "cotizacion_ventas"),
    "detalles_devolucion_compra": ("id_devolucion_compra", "devoluciones_compra"),
    "detalles_devolucion_venta": ("id_devolucion_venta", "devoluciones_venta"),
    "detalles_evento": ("id_evento", "eventos"),
    "detalles_orden_compra": ("id_orden_compra", "orden_compras"),
    "detalles_orden_produccion": ("id_orden_produccion", "ordenes_produccion"),
    "detalles_promocion": ("id_promocion", "promociones"),
    "detalles_venta": ("id_venta", "ventas"),
    "detalle_compra_lotes": ("id_detalle_compra", "detalles_compra"),
    "detalle_egresos": ("id_egreso", "egresos"),
    "detalle_salida_lotes": ("lote_id", "lotes"),
    "detalle_venta_lotes": ("id_detalle_venta", "detalles_venta"),
    "division_cuenta_restaurante": ("sesion_id", "restaurante_sesiones_mesa"),
    "documentos_empleado": ("id_empleado", "empleados"),
    "historial_bajas": ("id_empleado", "empleados"),
    "historial_contratos": ("id_empleado", "empleados"),
    "historial_orden_produccion": ("id_orden_produccion", "ordenes_produccion"),
    "ia_metricas_historial": ("usuario_id", "users"),
    "inventario": ("id_producto", "productos"),
    "inventario_entrada_detalles": ("id_entrada", "inventario_entradas"),
    "inventario_salida_detalles": ("id_salida", "inventario_salidas"),
    "kardexs": ("id_producto", "productos"),
    "messages": ("conversation_id", "conversations"),
    "metodos_pago": ("id_usuario", "users"),
    "model_has_permissions": ("model_id", "users"),
    "model_has_roles": ("model_id", "users"),
    "ordenes_pago": ("id_usuario", "users"),
    "orden_detalle_restaurante": ("sesion_id", "restaurante_sesiones_mesa"),
    "orden_produccion_documentos": ("id_orden_produccion", "ordenes_produccion"),
    "partida_detalles": ("id_partida", "partidas"),
    "pedido_detalle_lotes": ("lote_id", "lotes"),
    "permission_revocations": ("user_id", "users"),
    "planilla_detalles": ("id_planilla", "planillas"),
    "prestamo_cuotas": ("id_prestamo", "prestamos_empresa"),
    "prestamo_movimientos": ("id_prestamo", "prestamos_empleados"),
    "prestamo_pagos": ("id_prestamo", "prestamos_empresa"),
    "pre_cuentas_restaurante": ("sesion_id", "restaurante_sesiones_mesa"),
    "pre_cuenta_orden_detalle": ("pre_cuenta_id", "pre_cuentas_restaurante"),
    "productos_imagenes": ("id_producto", "productos"),
    "producto_composiciones": ("id_producto", "productos"),
    "producto_composicion_opciones": ("id_composicion", "producto_composiciones"),
    "producto_impuestos": ("id_producto", "productos"),
    "producto_precios": ("id_producto", "productos"),
    "producto_precio_usuarios": ("id_precio", "producto_precios"),
    "producto_presentaciones": ("id_producto", "productos"),
    "producto_proveedores": ("id_producto", "productos"),
    "producto_restaurante_pantalla": ("producto_id", "productos"),
    "product_custom_fields": ("custom_field_id", "custom_fields"),
    "restaurante_envio_pantalla": ("pantalla_id", "restaurante_pantallas"),
    "restaurante_pedido_detalles": ("pedido_id", "restaurante_pedidos"),
    "rest_item_eliminaciones_log": ("sesion_id", "restaurante_sesiones_mesa"),
    "retaceo_compras": ("id_retaceo", "retaceos"),
    "retaceo_distribucion": ("id_retaceo", "retaceos"),
    "retaceo_gastos": ("id_retaceo", "retaceos"),
    "role_has_permissions": ("role_id", "roles"),
    "transformaciones": ("id_bodega", "sucursal_bodegas"),
    "transformacion_detalles": ("id_transformacion", "transformaciones"),
    "traslado_lotes": ("traslado_id", "traslados"),
    "user_authorization_types": ("user_id", "users"),
    "venta_impuestos": ("id_venta", "ventas"),
    "venta_metodos_pago": ("id_venta", "ventas"),
}
FILTRO_EXTRA = {
    "model_has_permissions": "model_type LIKE '%User'",
    "model_has_roles": "model_type LIKE '%User'",
}


def sql(q, db=None):
    args = ["mysql", "-N", "-B"] + (["--database=" + db] if db else []) + ["-e", q]
    r = subprocess.run(args, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit(f"Error de MySQL: {r.stderr.strip()}\nConsulta: {q[:300]}")
    return r.stdout


def filas(q):
    return [l.split("\t") for l in sql(q).splitlines() if l]


def main():
    destino, empresas = sys.argv[1], sys.argv[2]
    conservar_mh = sys.argv[sys.argv.index("--conservar-mh") + 1] if "--conservar-mh" in sys.argv else "2"
    clave_qa = os.environ["CLAVE_QA_HASH"]  # bcrypt de la contraseña común, o CONSERVAR
    if destino in PROHIBIDAS or not destino.startswith("smartpyme_") or destino.endswith("_prod"):
        sys.exit(f"destino no permitido: {destino}")
    ids = ",".join(str(int(x)) for x in empresas.split(","))
    ids_mh = ",".join(str(int(x)) for x in conservar_mh.split(","))

    tablas = {t for (t,) in filas(f"SELECT table_name FROM information_schema.tables WHERE table_schema='{ORIGEN}' AND table_type='BASE TABLE'")}
    con_empresa = {t for (t,) in filas(f"SELECT table_name FROM information_schema.columns WHERE table_schema='{ORIGEN}' AND column_name='id_empresa'")} & tablas
    sin_clasificar = tablas - con_empresa - GLOBALES - VACIAS - set(POR_EMPRESA) - set(HIJAS)
    if sin_clasificar:
        sys.exit("tablas sin clasificar (agregarlas al script): " + ", ".join(sorted(sin_clasificar)))
    for h, (_, padre) in HIJAS.items():
        if padre not in tablas:
            sys.exit(f"{h}: la tabla padre {padre} no existe")

    columnas = {}
    for t, c in filas(f"SELECT table_name, column_name FROM information_schema.columns WHERE table_schema='{ORIGEN}' "
                      f"AND is_generated='NEVER' ORDER BY table_name, ordinal_position"):
        columnas.setdefault(t, []).append(f"`{c}`")

    print(f"Recreando {destino} con la estructura de {ORIGEN}", flush=True)
    sql(f"DROP DATABASE IF EXISTS `{destino}`; CREATE DATABASE `{destino}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
    subprocess.run(
        f"mysqldump --no-data --skip-triggers {ORIGEN} "
        f"| sed -E 's#/\\*!50013 DEFINER=`[^`]*`@`[^`]*` SQL SECURITY DEFINER \\*/##' "
        f"| mysql --database={destino}", shell=True, check=True)

    def copiar(t, where):
        cols = ",".join(columnas[t])
        q = (f"SET SESSION foreign_key_checks=0; SET SESSION unique_checks=0; "
             f"INSERT INTO `{destino}`.`{t}` ({cols}) SELECT {cols} FROM `{ORIGEN}`.`{t}`" + (f" WHERE {where}" if where else "") + ";"
             f"SELECT ROW_COUNT();")
        n = sql(q).strip().splitlines()[-1]
        print(f"  {t}: {n}", flush=True)

    for t, col in POR_EMPRESA.items():
        copiar(t, f"`{col}` IN ({ids})")
    for t in sorted(con_empresa):
        copiar(t, f"id_empresa IN ({ids})" + (" OR id_empresa IS NULL" if t in CON_NULOS else ""))
    for t in sorted(GLOBALES & tablas):
        copiar(t, None)

    pendientes = dict(HIJAS)
    hechas = set(con_empresa) | set(POR_EMPRESA) | GLOBALES
    while pendientes:
        listas = [h for h, (_, p) in pendientes.items() if p in hechas]
        if not listas:
            sys.exit("dependencias circulares: " + ", ".join(pendientes))
        for h in sorted(listas):
            col, padre = pendientes.pop(h)
            extra = f" AND {FILTRO_EXTRA[h]}" if h in FILTRO_EXTRA else ""
            copiar(h, f"`{col}` IN (SELECT id FROM `{destino}`.`{padre}`){extra}")
            hechas.add(h)

    print("Neutralizando", flush=True)
    neutralizar = f"""
UPDATE empresas SET fe_ambiente='00',
  wompi_id=NULL, wompi_identificador=NULL, wompi_aplicativo=NULL, wompi_secret=NULL,
  woocommerce_api_key=NULL, woocommerce_consumer_key=NULL, woocommerce_consumer_secret=NULL,
  woocommerce_status=IF(woocommerce_status IS NULL, NULL, 'disconnected'),
  shopify_client_secret=NULL, shopify_consumer_secret=NULL, shopify_access_token=NULL,
  shopify_webhook_secret=NULL, shopify_token_expires_at=NULL,
  shopify_status=IF(shopify_status IS NULL, NULL, 'disconnected'),
  webhook_paquete_venta_enabled=0, webhook_paquete_venta_url=NULL,
  webhook_paquete_venta_secret=NULL, webhook_paquete_venta_bearer_token=NULL,
  remember_token=NULL;
UPDATE empresas SET mh_usuario=NULL, mh_contrasena=NULL, mh_pwd_certificado=NULL WHERE id NOT IN ({ids_mh});
UPDATE integraciones SET credenciales=NULL, access_token=NULL, refresh_token=NULL, token_expires_at=NULL, estado='disconnected';
UPDATE user_email_accounts SET imap_password=NULL, access_token=NULL, refresh_token=NULL, token_expires_at=NULL;
UPDATE email_inboxes SET token=MD5(CONCAT(id, RAND()));
UPDATE contador_empresa_invitaciones SET token_hash=SHA2(CONCAT(id, RAND()), 256);
UPDATE users SET remember_token=NULL;
"""
    if clave_qa != "CONSERVAR":  # DEMO conserva las contraseñas para que ventas entre como siempre
        neutralizar += f"UPDATE users SET password='{clave_qa}';\n"
    sql(neutralizar, destino)
    for (t, n) in filas(f"SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='{destino}' "
                        f"AND table_name IN ('empresas','users','ventas','detalles_venta','productos','kardexs')"):
        print(f"  {t}: ~{n}")
    tam = sql(f"SELECT ROUND(SUM(data_length+index_length)/1048576) FROM information_schema.tables WHERE table_schema='{destino}'").strip()
    print(f"Listo: {destino} ({tam} MB)")


if __name__ == "__main__":
    main()
