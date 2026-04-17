/**
 * Stock mostrado en facturación / tienda: va por bodega. Filtrar solo por `id_sucursal`
 * en filas de inventario suele dejar el array vacío (campo no poblado) y el stock en 0.
 */
export function inventariosParaStockVenta(
  inventarios: any[] | undefined | null,
  venta: { id_bodega?: unknown; id_sucursal?: unknown }
): any[] {
  const rows = Array.isArray(inventarios) ? inventarios : [];
  const idBodega = venta?.id_bodega;
  if (idBodega != null && idBodega !== '') {
    return rows.filter((item) => Number(item?.id_bodega) === Number(idBodega));
  }
  const idSucursal = venta?.id_sucursal;
  if (idSucursal != null && idSucursal !== '') {
    const porSucursal = rows.filter(
      (item) => Number(item?.id_sucursal) === Number(idSucursal)
    );
    if (porSucursal.length > 0) {
      return porSucursal;
    }
  }
  return rows;
}
