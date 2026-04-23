import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

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
  return resolveCodigoPaisFe(empresa) === FE_PAIS_CR
    ? DOCUMENTO_NOMBRE_OPCIONES_CR
    : DOCUMENTO_NOMBRE_OPCIONES_DEFAULT;
}
