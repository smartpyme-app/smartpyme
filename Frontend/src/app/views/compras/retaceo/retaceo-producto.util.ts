/** El read de compra manda `nombre_producto` (append), no siempre `producto.nombre`. */
export function productoDesdeDetalleCompra(detalle: any): { nombre?: string } {
  const nombre = detalle?.producto?.nombre || detalle?.nombre_producto || detalle?.descripcion;
  return { ...(detalle?.producto || {}), nombre };
}
