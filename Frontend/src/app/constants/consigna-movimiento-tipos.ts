/** Etiquetas alineadas con ConsignaMovimientoTipoConstants (backend). */
/** Solo pool consigna compra (proveedor); el resto vive en kardex físico / custodia clientes. */
export const CONSIGNA_MOVIMIENTO_TIPOS: { value: string; label: string; ayuda?: string }[] = [
  { value: 'entrada_proveedor', label: 'Entrada consigna (proveedor)', ayuda: 'Recepción en consigna del proveedor.' },
  { value: 'venta_regular_consigna_proveedor', label: 'Venta (stock consigna compra)', ayuda: 'Descontó del pool del proveedor.' },
  { value: 'liquidacion_proveedor', label: 'Liquidación al proveedor', ayuda: 'Pago al proveedor; no mueve el pool vendible.' },
  { value: 'devolucion_proveedor', label: 'Devolución al proveedor' },
  { value: 'traslado_consigna', label: 'Traslado pool consigna' },
  { value: 'ajuste_consigna', label: 'Ajuste / conteo consigna' },
];
