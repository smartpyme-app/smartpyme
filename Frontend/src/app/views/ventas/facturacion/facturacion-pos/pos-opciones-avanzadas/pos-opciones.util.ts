/** Cuenta switches/campos avanzados activos en POS (badge del modal). */
export function contarOpcionesAvanzadasActivas(venta: any, habilitarCuentaTerceros: boolean): number {
  let n = 0;
  if (venta?.consigna) n++;
  if (venta?.recurrente) n++;
  if (venta?.retencion) n++;
  if (venta?.renta) n++;
  if (venta?.cobrar_propina) n++;
  if (habilitarCuentaTerceros) n++;
  if (venta?.id_canal) n++;
  if (venta?.id_proyecto) n++;
  if (venta?.descripcion_personalizada) n++;
  if (venta?.cobrar_impuestos === false) n++;
  if (venta?.tipo_operacion) n++;
  if (venta?.tipo_renta) n++;
  if (venta?.nombre_documento === 'Factura de exportación' && (venta?.cod_incoterm || venta?.recinto_fiscal)) n++;
  return n;
}
