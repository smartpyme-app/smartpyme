/** Reglas de menú operativo compartidas entre app-sidebar y sidebars por rol (ventas, etc.). */

export const PERMISOS_MENU_COMPRAS = [
  'compras.ver',
  'compras.registros.ver',
  'compras.ordenes_compra.ver',
  'compras.proveedores.ver',
  'compras.retaceo.ver',
  'compras.consignas.ver',
] as const;

export function mostrarMenuCompras(
  hasPermission: (p: string) => boolean,
  hasAnyPermission: (ps: string[]) => boolean,
  canAccederOperacionesInventario: () => boolean,
): boolean {
  return (
    hasAnyPermission([...PERMISOS_MENU_COMPRAS]) ||
    (canAccederOperacionesInventario() && hasPermission('compras.consignas.ver'))
  );
}

export function mostrarMenuGastos(hasPermission: (p: string) => boolean): boolean {
  return (
    hasPermission('gastos.ver') ||
    hasPermission('gastos.registros.ver') ||
    hasPermission('gastos.categorias.ver')
  );
}

export function puedeVerRegistrosCompras(hasPermission: (p: string) => boolean): boolean {
  return hasPermission('compras.ver') || hasPermission('compras.registros.ver');
}

export function puedeVerRegistrosGastos(hasPermission: (p: string) => boolean): boolean {
  return hasPermission('gastos.ver') || hasPermission('gastos.registros.ver');
}

export function mostrarSeccionProductosExpandida(
  hasPermission: (p: string) => boolean,
  enSidebarVentas: boolean,
): boolean {
  if (!hasPermission('productos.ver')) {
    return false;
  }
  if (!enSidebarVentas) {
    return true;
  }
  return (
    hasPermission('productos.bodegas.ver') ||
    hasPermission('productos.kardex.ver') ||
    hasPermission('productos.categorias.ver') ||
    hasPermission('productos.paquetes.ver')
  );
}