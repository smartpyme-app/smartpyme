import { FE_PAIS_CR, FE_PAIS_HN, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

export interface DocumentoNombreOption {
  value: string;
  label: string;
}

/**
 * Nombres como en comprobantes / Ministerio de Hacienda CR (p. ej. FEC 08).
 * Coinciden con `documento.nombre` al crear el registro.
 */
export const NOMBRE_DOCUMENTO_CR = {
  factura: 'Factura Electrónica',
  tiquete: 'Tiquete Electrónico',
  fecCompra: 'Factura Electrónica de Compra',
  notaCredito: 'Nota de Crédito Electrónica',
  notaDebito: 'Nota de Débito Electrónica',
  abonoVenta: 'Abono de Venta',
} as const;

/** Compra/gasto FEC (08): nombre nuevo o registros históricos «Compra electrónica». */
export function esTipoFacturaElectronicaCompraCr(tipo: string | null | undefined): boolean {
  const t = String(tipo ?? '').trim();
  return t === NOMBRE_DOCUMENTO_CR.fecCompra || t === 'Compra electrónica';
}

export function esNombreNotaCredito(nombre: string | null | undefined): boolean {
  const n = String(nombre ?? '').trim();
  return n === 'Nota de crédito' || n === NOMBRE_DOCUMENTO_CR.notaCredito;
}

export function esNombreNotaDebito(nombre: string | null | undefined): boolean {
  const n = String(nombre ?? '').trim();
  return n === 'Nota de débito' || n === NOMBRE_DOCUMENTO_CR.notaDebito;
}

export function esNombreNotaCreditoODebito(nombre: string | null | undefined): boolean {
  return esNombreNotaCredito(nombre) || esNombreNotaDebito(nombre);
}

/** Costa Rica: denominaciones alineadas con comprobantes electrónicos (DGT). No se lista “Crédito fiscal” (FE 01 = Factura). */
export const DOCUMENTO_NOMBRE_OPCIONES_CR: DocumentoNombreOption[] = [
  { value: NOMBRE_DOCUMENTO_CR.factura, label: NOMBRE_DOCUMENTO_CR.factura },
  { value: NOMBRE_DOCUMENTO_CR.tiquete, label: NOMBRE_DOCUMENTO_CR.tiquete },
  { value: 'Cotización', label: 'Cotización' },
  { value: 'Recibo', label: 'Recibo' },
  { value: 'Orden de compra', label: 'Orden de compra' },
  /** FEC 08 — compras y gastos */
  { value: NOMBRE_DOCUMENTO_CR.fecCompra, label: NOMBRE_DOCUMENTO_CR.fecCompra },
  { value: NOMBRE_DOCUMENTO_CR.notaCredito, label: NOMBRE_DOCUMENTO_CR.notaCredito },
  { value: NOMBRE_DOCUMENTO_CR.notaDebito, label: NOMBRE_DOCUMENTO_CR.notaDebito },
  { value: NOMBRE_DOCUMENTO_CR.abonoVenta, label: NOMBRE_DOCUMENTO_CR.abonoVenta },
];

export const NOMBRE_DOCUMENTO_HN = {
  facturaConRtn: 'Factura con RTN',
  facturaSinRtn: 'Factura sin RTN',
  ticket: 'Ticket',
  boletaCompra: 'Boleta de compra',
  notaCredito: 'Nota de crédito',
  notaDebito: 'Nota de débito',
  reciboHonorarios: 'Recibo por honorarios profesionales',
  guiaRemision: 'Guía de remisión',
  comprobanteRetencion: 'Comprobante de retención',
} as const;

export const DOCUMENTO_NOMBRE_OPCIONES_HN: DocumentoNombreOption[] = [
  { value: NOMBRE_DOCUMENTO_HN.facturaConRtn, label: NOMBRE_DOCUMENTO_HN.facturaConRtn },
  { value: NOMBRE_DOCUMENTO_HN.facturaSinRtn, label: NOMBRE_DOCUMENTO_HN.facturaSinRtn },
  { value: NOMBRE_DOCUMENTO_HN.ticket, label: NOMBRE_DOCUMENTO_HN.ticket },
  { value: NOMBRE_DOCUMENTO_HN.boletaCompra, label: NOMBRE_DOCUMENTO_HN.boletaCompra },
  { value: NOMBRE_DOCUMENTO_HN.notaCredito, label: NOMBRE_DOCUMENTO_HN.notaCredito },
  { value: NOMBRE_DOCUMENTO_HN.notaDebito, label: NOMBRE_DOCUMENTO_HN.notaDebito },
  { value: NOMBRE_DOCUMENTO_HN.reciboHonorarios, label: NOMBRE_DOCUMENTO_HN.reciboHonorarios },
  { value: NOMBRE_DOCUMENTO_HN.guiaRemision, label: NOMBRE_DOCUMENTO_HN.guiaRemision },
  { value: NOMBRE_DOCUMENTO_HN.comprobanteRetencion, label: NOMBRE_DOCUMENTO_HN.comprobanteRetencion },
  { value: 'Cotización', label: 'Cotización' },
  { value: 'Orden de compra', label: 'Orden de compra' },
  { value: 'Recibo', label: 'Recibo' },
  { value: 'Abono de Venta', label: 'Abono de Venta' },
];

/** Números de emisión SAR Honduras (01–20). */
export const NUMERO_EMISION_OPCIONES_HN: string[] = Array.from({ length: 20 }, (_, i) =>
  String(i + 1).padStart(2, '0')
);

const NOMBRES_FISCALES_HN: readonly string[] = [
  NOMBRE_DOCUMENTO_HN.facturaConRtn,
  NOMBRE_DOCUMENTO_HN.facturaSinRtn,
  NOMBRE_DOCUMENTO_HN.ticket,
  NOMBRE_DOCUMENTO_HN.boletaCompra,
  NOMBRE_DOCUMENTO_HN.notaCredito,
  NOMBRE_DOCUMENTO_HN.notaDebito,
  NOMBRE_DOCUMENTO_HN.reciboHonorarios,
  NOMBRE_DOCUMENTO_HN.guiaRemision,
  NOMBRE_DOCUMENTO_HN.comprobanteRetencion,
];

export function esDocumentoFiscalHn(nombre: string | null | undefined): boolean {
  return NOMBRES_FISCALES_HN.includes(String(nombre ?? '').trim());
}

/** Mismo criterio que `App\Support\Honduras\FormatoCorrelativoHn::format`. */
export function formatoCorrelativoHn(
  numeroEmision: string | null | undefined,
  correlativo: string | number | null | undefined
): string {
  const corr = String(correlativo ?? '');
  const em = String(numeroEmision ?? '').trim();
  if (em === '') {
    return corr;
  }
  const nn = (em.replace(/\D/g, '') || '0').padStart(2, '0');
  const digits = corr.replace(/\D/g, '') || '0';
  return `001-001-${nn}-${digits.padStart(8, '0')}`;
}

/** El Salvador y resto: lista completa (incluye Crédito fiscal, DTE SV, etc.). */
export const DOCUMENTO_NOMBRE_OPCIONES_DEFAULT: DocumentoNombreOption[] = [
  { value: 'Factura', label: 'Factura' },
  { value: 'Crédito fiscal', label: 'Crédito fiscal' },
  { value: 'Ticket', label: 'Ticket' },
  { value: 'Cotización', label: 'Cotización' },
  { value: 'Recibo', label: 'Recibo' },
  { value: 'Orden de compra', label: 'Orden de compra' },
  { value: 'Nota de crédito', label: 'Nota de crédito' },
  { value: 'Nota de débito', label: 'Nota de débito' },
  { value: 'Sujeto excluido', label: 'Sujeto excluido' },
  { value: 'Factura de exportación', label: 'Factura de exportación' },
  { value: 'Abono de Venta', label: 'Abono de Venta' },
  { value: 'Factura comercial', label: 'Factura comercial' },
];

export function documentoNombreOpciones(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): DocumentoNombreOption[] {
  const cod = resolveCodigoPaisFe(empresa);
  if (cod === FE_PAIS_CR) {
    return DOCUMENTO_NOMBRE_OPCIONES_CR;
  }
  if (cod === FE_PAIS_HN) {
    return DOCUMENTO_NOMBRE_OPCIONES_HN;
  }
  return DOCUMENTO_NOMBRE_OPCIONES_DEFAULT;
}

export function nombresDocumentosVentaNormales(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): string[] {
  const cod = resolveCodigoPaisFe(empresa);
  if (cod === FE_PAIS_HN) {
    return [
      NOMBRE_DOCUMENTO_HN.facturaConRtn,
      NOMBRE_DOCUMENTO_HN.facturaSinRtn,
      NOMBRE_DOCUMENTO_HN.ticket,
      'Recibo',
      NOMBRE_DOCUMENTO_HN.guiaRemision,
      'Abono de Venta',
    ];
  }
  return [
    'Factura',
    'Crédito fiscal',
    'Factura de exportación',
    'Factura comercial',
    'Ticket',
    'Recibo',
    'Sujeto excluido',
    NOMBRE_DOCUMENTO_CR.factura,
    NOMBRE_DOCUMENTO_CR.tiquete,
    'Abono de Venta',
  ];
}

export function nombresDocumentosCompraPermitidos(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): string[] {
  const cod = resolveCodigoPaisFe(empresa);
  if (cod === FE_PAIS_HN) {
    return [
      NOMBRE_DOCUMENTO_HN.facturaConRtn,
      NOMBRE_DOCUMENTO_HN.facturaSinRtn,
      NOMBRE_DOCUMENTO_HN.ticket,
      'Recibo',
      NOMBRE_DOCUMENTO_HN.boletaCompra,
      NOMBRE_DOCUMENTO_HN.reciboHonorarios,
      NOMBRE_DOCUMENTO_HN.comprobanteRetencion,
    ];
  }
  const base = [
    'Factura',
    'Crédito fiscal',
    'Ticket',
    'Recibo',
    'Sujeto excluido',
    'Factura de exportación',
    'Factura de remisión',
    'Documento contable de liquidación',
  ];
  if (cod === FE_PAIS_CR) {
    return [
      ...base,
      NOMBRE_DOCUMENTO_CR.factura,
      NOMBRE_DOCUMENTO_CR.tiquete,
      NOMBRE_DOCUMENTO_CR.fecCompra,
      'Compra electrónica',
    ];
  }
  return base;
}

/** Tipos SV que no deben ofrecerse en gastos de empresas HN. */
export function nombresDocumentoExcluidosGastoHn(): string[] {
  return [
    'Crédito fiscal',
    'Sujeto excluido',
    'Factura de exportación',
    'Factura comercial',
  ];
}
