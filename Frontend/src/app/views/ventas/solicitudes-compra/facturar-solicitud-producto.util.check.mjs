/** ponytail: etiqueta código + nombre para alertas de solicitud OC */
function etiquetaProductoSolicitud(detalleCompra, productoOrigen) {
  const codigo = (detalleCompra.codigo ?? productoOrigen?.codigo ?? '—').toString();
  let nombre = (productoOrigen?.nombre ?? detalleCompra.nombre_producto ?? '').toString().trim();
  const variante = (productoOrigen?.nombre_variante ?? '').toString().trim();
  if (variante && nombre && !nombre.includes(variante)) {
    nombre = `${nombre} (${variante})`;
  }
  if (nombre && nombre !== codigo) {
    return `${codigo} — ${nombre}`;
  }
  return codigo;
}

const out = etiquetaProductoSolicitud(
  { codigo: '202309' },
  { nombre: 'AMPOLLETA DE CAIDA LOSS FUSION' }
);
if (out !== '202309 — AMPOLLETA DE CAIDA LOSS FUSION') {
  throw new Error(`unexpected: ${out}`);
}
console.log('facturar-solicitud-producto.util.check.mjs OK');
