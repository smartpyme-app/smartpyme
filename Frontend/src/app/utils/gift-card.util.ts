/** Mirrors Backend config/constants.php FORMAS_PAGO_GIFT_CARD */
export const FORMAS_PAGO_GIFT_CARD = [
  'Gift Card',
  'Gif card',
  'Giftcard',
  'GIFTCARD',
  'Tarjeta de regalo',
  'Tarjeta regalo',
  'Certificado de regalo',
];

export function esFormaPagoGiftCard(nombre: string | null | undefined): boolean {
  if (!nombre) {
    return false;
  }
  const normalizado = nombre.trim().toLowerCase();
  return FORMAS_PAGO_GIFT_CARD.some((sinonimo) => sinonimo.trim().toLowerCase() === normalizado);
}

export function montoPagoGiftCardVenta(venta: any, formaPagos: any[] = []): number {
  const metodos = venta?.metodos_de_pago;
  if (Array.isArray(metodos) && metodos.length > 0) {
    return metodos
      .filter((m: any) => esFormaPagoGiftCard(m?.nombre))
      .reduce((sum: number, m: any) => sum + (parseFloat(m?.total) || 0), 0);
  }

  if (venta?.forma_pago === 'Multiple' && Array.isArray(formaPagos)) {
    return formaPagos
      .filter((fp: any) => (parseFloat(fp?.total) || 0) > 0 && esFormaPagoGiftCard(fp?.nombre))
      .reduce((sum: number, fp: any) => sum + (parseFloat(fp?.total) || 0), 0);
  }

  if (esFormaPagoGiftCard(venta?.forma_pago)) {
    return parseFloat(venta?.total) || 0;
  }

  return 0;
}

export function ventaUsaGiftCard(venta: any, formaPagos: any[] = []): boolean {
  return montoPagoGiftCardVenta(venta, formaPagos) > 0;
}
