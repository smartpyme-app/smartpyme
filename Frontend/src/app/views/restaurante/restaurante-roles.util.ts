/** SP-2158: roles y mensajes para cerrar mesa. */
export function puedeCerrarMesaRestaurante(tipo: string | undefined | null): boolean {
  return ['Administrador', 'Supervisor', 'Ventas'].includes(String(tipo ?? '').trim());
}

export function puedeCerrarMesaForzadaSinCodigo(tipo: string | undefined | null): boolean {
  return ['Administrador', 'Supervisor'].includes(String(tipo ?? '').trim());
}

export function necesitaCierreForzadoMesa(sesion: any, preCuentas: any[] | null | undefined): boolean {
  const items = (sesion?.orden_detalle?.length ?? 0) > 0;
  const pcPendientes = (preCuentas ?? []).some((p) => p?.estado === 'pendiente');
  return items || pcPendientes;
}

export const MENSAJE_CONFIRMAR_CERRAR_MESA =
  '¿Cerrar esta mesa sin facturar?\n\n' +
  'Si la mesa está vacía, se liberará en el mapa. ' +
  'Si quedó una pre-cuenta en $0 tras eliminar productos, se anulará automáticamente.';

export const MENSAJE_CONFIRMAR_CERRAR_MESA_FORZADO =
  '¿Cerrar esta mesa sin facturar?\n\n' +
  'Hay consumo o pre-cuentas pendientes: se anularán y la mesa quedará libre. ' +
  'Esta acción no genera factura.';

export interface CerrarMesaOpciones {
  codigo_supervisor?: string;
}
