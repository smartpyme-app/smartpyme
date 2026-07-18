/** Catálogos MH/DGT: códigos de municipio/distrito se repiten entre departamentos. */

export function dedupePorCod<T extends { cod?: unknown }>(items: T[] | null | undefined): T[] {
  const seen = new Set<string>();
  return (items || []).filter((item) => {
    const k = String(item?.cod ?? '');
    if (k === '' || seen.has(k)) {
      return false;
    }
    seen.add(k);
    return true;
  });
}

export function filtrarPorCodDepartamento<T extends { cod_departamento?: unknown }>(
  items: T[] | null | undefined,
  codDepartamento: unknown,
): T[] {
  if (codDepartamento === undefined || codDepartamento === null || codDepartamento === '') {
    return [];
  }
  const needle = String(codDepartamento);
  return (items || []).filter((item) => String(item?.cod_departamento) === needle);
}

export function alCambiarDepartamento(
  cliente: {
    cod_departamento?: unknown;
    departamento?: unknown;
    municipio?: unknown;
    cod_municipio?: unknown;
    distrito?: unknown;
    cod_distrito?: unknown;
  },
  departamentos: Array<{ cod?: unknown; nombre?: string }>,
  cod: unknown,
): void {
  cliente.cod_departamento = cod;
  const departamento = (departamentos || []).find((item) => String(item.cod) === String(cod));
  if (departamento) {
    cliente.departamento = departamento.nombre;
    cliente.cod_departamento = departamento.cod;
  }
  cliente.municipio = '';
  cliente.cod_municipio = '';
  cliente.distrito = '';
  cliente.cod_distrito = '';
}

/** Clave estable para @for / ng-option (evita reutilizar opciones con el mismo cod MH). */
export function trackUbicacionCod(item: {
  cod?: unknown;
  cod_departamento?: unknown;
  cod_municipio?: unknown;
}): string {
  const dep = item?.cod_departamento != null ? String(item.cod_departamento) : '';
  const mun = item?.cod_municipio != null ? String(item.cod_municipio) : '';
  const cod = item?.cod != null ? String(item.cod) : '';
  return mun ? `${dep}-${mun}-${cod}` : `${dep}-${cod}`;
}
