/** Permisos y patrones de URL del lazy module Compras (compras.routing.module). */

export const PERM_VER_COMPRAS = ['compras.ver', 'compras.registros.ver'] as const;
export const PERM_VER_GASTOS = ['gastos.ver', 'gastos.registros.ver'] as const;

/** Rutas del módulo compras/gastos (AdminGuard antes del lazy load). */
const RUTAS_MODULO_COMPRAS: ReadonlyArray<RegExp> = [
  /^\/compras(\/|$)/,
  /^\/compra(\/|$)/,
  /^\/ordenes-de-compras(\/|$)/,
  /^\/orden-de-compra(\/|$)/,
  /^\/proveedores(\/|$)/,
  /^\/proveedor(\/|$)/,
  /^\/retaceos(\/|$)/,
  /^\/retaceo(\/|$)/,
  /^\/devoluciones\/compras(\/|$)/,
  /^\/devolucion\/compra(\/|$)/,
  /^\/devolucion-compra(\/|$)/,
  /^\/reporte\/compras(\/|$)/,
];

const RUTAS_MODULO_GASTOS: ReadonlyArray<RegExp> = [
  /^\/gastos(\/|$)/,
  /^\/gasto(\/|$)/,
  /^\/departamentos-empresa(\/|$)/,
  /^\/areas-empresa(\/|$)/,
];

export function esRutaLazyModuloCompras(path: string): boolean {
  const p = path.split('?')[0];
  return RUTAS_MODULO_COMPRAS.some((rx) => rx.test(p)) || RUTAS_MODULO_GASTOS.some((rx) => rx.test(p));
}