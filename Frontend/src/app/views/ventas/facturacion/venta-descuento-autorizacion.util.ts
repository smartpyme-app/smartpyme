import Swal from 'sweetalert2';

export type PinDescuento = { usuario: string; codigo: string };

export function ventaTieneDescuentoLinea(venta: { detalles?: any[] } | null | undefined): boolean {
  const detalles = venta?.detalles;
  if (!Array.isArray(detalles)) {
    return false;
  }
  return detalles.some((det) =>
    ['descuento', 'descuento_porcentaje', 'descuento_monto'].some(
      (campo) => Number(det?.[campo] ?? 0) > 0
    )
  );
}

export function debePedirPinDescuento(
  hasAplicar: boolean,
  venta: { cotizacion?: any; detalles?: any[] }
): boolean {
  if (hasAplicar) {
    return false;
  }
  if (Number(venta?.cotizacion) === 1) {
    return false;
  }
  return ventaTieneDescuentoLinea(venta);
}

export async function pedirPinDescuentoSiAplica(
  api: { hasPermission(permission: string): boolean },
  venta: { cotizacion?: any; detalles?: any[] }
): Promise<false | PinDescuento | null> {
  if (!debePedirPinDescuento(api.hasPermission('ventas.descuentos.aplicar'), venta)) {
    return null;
  }

  const result = await Swal.fire({
    title: 'Autorización de descuento',
    html:
      '<input id="swal-desc-email" class="swal2-input" placeholder="Email del supervisor" autocomplete="off">' +
      '<input id="swal-desc-codigo" class="swal2-input" type="password" placeholder="Código de autorización" autocomplete="off">',
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Autorizar',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const usuario = (document.getElementById('swal-desc-email') as HTMLInputElement | null)?.value?.trim();
      const codigo = (document.getElementById('swal-desc-codigo') as HTMLInputElement | null)?.value ?? '';
      if (!usuario || !codigo) {
        Swal.showValidationMessage('Ingrese email y código del supervisor');
        return false;
      }
      return { usuario, codigo };
    },
  });

  if (!result.isConfirmed || !result.value) {
    return false;
  }
  return result.value as PinDescuento;
}
