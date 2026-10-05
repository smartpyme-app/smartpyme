/** Líneas de venta a partir de los productos ya guardados en la cita. */
export function lineasVentaDesdeCita(evento: any): any[] {
  const lineas = Array.isArray(evento?.productos) ? evento.productos : [];
  return lineas.filter((linea: any) => linea?.id_producto).map((linea: any) => {
    const precio = Number.parseFloat(linea.precio_producto) || 0;
    const cantidad = Number.parseFloat(linea.cantidad) || 1;
    const total = (cantidad * precio).toFixed(4);
    return {
      id: null,
      id_cita: evento.id,
      id_producto: linea.id_producto,
      descripcion: linea.nombre_producto || 'Producto',
      cantidad,
      precio,
      costo: 0,
      total_costo: 0,
      descuento: 0,
      descuento_porcentaje: 0,
      stock: null,
      exenta: 0,
      no_sujeta: 0,
      cuenta_a_terceros: 0,
      tipo_gravado: 'gravada',
      gravada: total,
      total,
    };
  });
}

/** Copia la venta con el evento y sus líneas. No consulta el catálogo. */
export function aplicarCitaEnVenta(venta: any, evento: any): any {
  const lineas = lineasVentaDesdeCita(evento);
  return {
    ...venta,
    id_evento: evento?.id ?? venta?.id_evento,
    detalles: [...(venta?.detalles || []), ...lineas],
  };
}
