export type OrdenDir = 'asc' | 'desc';

export interface EstadoOrden<TCol extends string = string> {
  col: TCol | null;
  dir: OrdenDir;
}

export function alternarOrden<TCol extends string>(
  actual: EstadoOrden<TCol>,
  col: TCol,
  conReset = false,
): EstadoOrden<TCol> {
  if (actual.col !== col) {
    return { col, dir: 'asc' };
  }
  if (actual.dir === 'asc') {
    return { col, dir: 'desc' };
  }
  return conReset ? { col: null, dir: 'asc' } : { col, dir: 'asc' };
}

export function ordenarFilas<T>(
  filas: readonly T[],
  dir: OrdenDir,
  getValue: (fila: T) => string | number | null | undefined,
): T[] {
  const factor = dir === 'asc' ? 1 : -1;
  return [...filas].sort((a, b) => {
    const va = getValue(a);
    const vb = getValue(b);
    if (va == null && vb == null) {
      return 0;
    }
    if (va == null) {
      return 1;
    }
    if (vb == null) {
      return -1;
    }
    if (typeof va === 'string' && typeof vb === 'string') {
      return va.localeCompare(vb, 'es', { sensitivity: 'base' }) * factor;
    }
    return (Number(va) - Number(vb)) * factor;
  });
}
