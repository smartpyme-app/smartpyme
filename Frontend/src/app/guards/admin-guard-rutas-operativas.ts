import {
  mostrarMenuCompras,
  mostrarMenuGastos,
} from '../layout/sidebar/sidebar-operativos-nav.shared';
import { esRutaLazyModuloCompras } from '../views/compras/compras-routing-access';

type PermisoFn = (p: string) => boolean;

function puedeAccederLazyComprasGastos(
  hasPermission: PermisoFn,
  hasAnyPermission: (ps: string[]) => boolean,
  canAccederOperacionesInventario: () => boolean,
): boolean {
  return (
    mostrarMenuCompras(hasPermission, hasAnyPermission, canAccederOperacionesInventario) ||
    mostrarMenuGastos(hasPermission) ||
    hasPermission('compras.crear') ||
    hasPermission('compras.editar') ||
    hasPermission('compras.devoluciones.ver') ||
    hasPermission('gastos.crear') ||
    hasPermission('gastos.editar')
  );
}

/** AdminGuard: usuarios con rol acotado (Ventas, etc.) pueden cargar lazy modules si tienen permiso Spatie para la URL. */
export function adminGuardPermiteRutaOperativa(
  url: string,
  hasPermission: PermisoFn,
  hasAnyPermission: (ps: string[]) => boolean,
  canAccederOperacionesInventario: () => boolean,
): boolean {
  const path = url.split('?')[0];

  if (esRutaLazyModuloCompras(path)) {
    return puedeAccederLazyComprasGastos(
      hasPermission,
      hasAnyPermission,
      canAccederOperacionesInventario,
    );
  }

  const seg = path.split('/').filter(Boolean)[0] ?? '';

  if (seg === 'consignas' && !path.startsWith('/consignas/ventas')) {
    return (
      mostrarMenuCompras(hasPermission, hasAnyPermission, canAccederOperacionesInventario) ||
      hasPermission('compras.consignas.ver')
    );
  }

  const finanzas = new Set([
    'finanzas',
    'presupuestos',
    'bancos',
    'cierre-de-caja',
    'impuestos',
    'documentos',
    'formas-de-pago',
    'dte-management',
  ]);
  if (finanzas.has(seg)) {
    return (
      hasPermission('finanzas.ver') ||
      hasAnyPermission([
        'finanzas.libro_iva.ver',
        'finanzas.cierre_caja.ver',
        'finanzas.documentos.ver',
        'finanzas.bancos.ver',
        'finanzas.prestamos.ver',
        'finanzas.reporteria.ver',
      ])
    );
  }

  if (seg === 'planilla' || seg === 'comisiones' || seg === 'bonos' || seg === 'incentivos') {
    return hasAnyPermission([
      'planilla.ver',
      'planilla.registros.ver',
      'planilla.empleados.ver',
      'planilla.configuracion.ver',
      'planilla.comisiones.ver',
      'planilla.bonos.ver',
      'planilla.incentivos.ver',
    ]);
  }

  if (seg === 'contabilidad' || seg === 'catalogo') {
    return hasPermission('contabilidad.ver') || hasAnyPermission([
      'contabilidad.partidas.ver',
      'contabilidad.catalogo_cuentas.ver',
      'contabilidad.activos.ver',
    ]);
  }

  const inventario = new Set(['productos', 'bodegas', 'kardex', 'categorias', 'lotes', 'consigna-kardex']);
  if (inventario.has(seg)) {
    return hasPermission('productos.ver') || hasAnyPermission([
      'productos.bodegas.ver',
      'productos.kardex.ver',
      'productos.categorias.ver',
    ]);
  }

  if (seg === 'reportes' && hasPermission('inteligencia_negocios.ver')) {
    return true;
  }

  if (seg === 'fidelizacion' && hasPermission('fidelizacion.ver')) {
    return true;
  }

  if (seg === 'organizacion') {
    return hasAnyPermission(['organizacion.empresas.ver', 'organizacion.usuarios.ver']);
  }

  if (seg === 'restaurante' || seg === 'pedidos') {
    return hasPermission('restaurante.ver') || hasPermission('pedidos.ver');
  }

  if (seg === 'despacho' && hasPermission('contador.portal.ver')) {
    return true;
  }

  return false;
}