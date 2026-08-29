/** SP-2158: roles que pueden cerrar mesa sin factura. */
export function puedeCerrarMesaRestaurante(tipo: string | undefined | null): boolean {
  return ['Administrador', 'Supervisor', 'Ventas'].includes(String(tipo ?? '').trim());
}

export const MENSAJE_CONFIRMAR_CERRAR_MESA =
  '¿Cerrar esta mesa sin facturar?\n\n' +
  'La mesa debe estar vacía: sin ítems en la orden, sin pre-cuentas pendientes ' +
  'y sin comandas activas en cocina/barra.';
