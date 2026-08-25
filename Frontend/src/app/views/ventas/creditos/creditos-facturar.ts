export type PrefillCreditoCuota = {
  id_cuota: number;
  id_cliente: number;
  cliente: any;
  monto: number;
  fecha: string;
  descripcion: string;
  id_documento: number | null;
  documento_bloqueado: boolean;
};

export function queryFacturarCuota(idCuota: number): { credito_cuota: number } {
  return { credito_cuota: idCuota };
}

export function aplicarPrefillCredito(venta: any, prefill: PrefillCreditoCuota): any {
  const monto = Number(prefill.monto) || 0;
  venta.id_cliente = prefill.id_cliente;
  venta.cliente = prefill.cliente || {};
  venta.fecha = prefill.fecha;
  venta.fecha_pago = prefill.fecha;
  venta.estado = 'Pendiente';
  venta.condicion = 'Crédito';
  venta.credito = true;
  venta.id_credito_cuota = prefill.id_cuota;
  venta.referencia = prefill.descripcion;
  venta.documento_bloqueado = !!prefill.documento_bloqueado;
  if (prefill.id_documento) {
    venta.id_documento = prefill.id_documento;
  }
  venta.detalles = [
    {
      descripcion: prefill.descripcion,
      cantidad: 1,
      precio: monto,
      descuento: 0,
      sub_total: monto,
      total: monto,
      iva: 0,
      costo: 0,
    },
  ];
  venta.sub_total = monto;
  venta.total = monto;
  return venta;
}
