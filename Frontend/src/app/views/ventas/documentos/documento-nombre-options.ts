import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

export interface DocumentoNombreOption {
  value: string;
  label: string;
}

/** Costa Rica: no se lista “Crédito fiscal” (la FE usa el mismo comprobante 01 que Factura). Incluye Tiquete. */
export const DOCUMENTO_NOMBRE_OPCIONES_CR: DocumentoNombreOption[] = [
  { value: 'Factura', label: 'Factura' },
  { value: 'Ticket', label: 'Ticket' },
  { value: 'Tiquete', label: 'Tiquete' },
  { value: 'Cotización', label: 'Cotización' },
  { value: 'Recibo', label: 'Recibo' },
  { value: 'Orden de compra', label: 'Orden de compra' },
  { value: 'Nota de crédito', label: 'Nota de crédito' },
  { value: 'Nota de débito', label: 'Nota de débito' },
  { value: 'Abono de Venta', label: 'Abono de Venta' },
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
