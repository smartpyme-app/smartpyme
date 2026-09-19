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

export type UbicacionGeo = {
  cod_departamento?: unknown;
  departamento?: unknown;
  municipio?: unknown;
  cod_municipio?: unknown;
  distrito?: unknown;
  cod_distrito?: unknown;
};

function codigoVacio(valor: unknown): boolean {
  return valor === undefined || valor === null || valor === '';
}

function mismoTexto(a: unknown, b: unknown): boolean {
  return String(a ?? '').trim().toLowerCase() === String(b ?? '').trim().toLowerCase();
}

export function alCambiarDepartamento(
  cliente: UbicacionGeo,
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

export function alCambiarMunicipio(
  entity: UbicacionGeo,
  municipios: Array<{ cod?: unknown; nombre?: string; cod_departamento?: unknown }>,
  cod: unknown,
): void {
  entity.cod_municipio = cod;
  const municipio = (municipios || []).find(
    (item) =>
      String(item.cod) === String(cod) && String(item.cod_departamento) === String(entity.cod_departamento),
  );
  if (municipio) {
    entity.municipio = municipio.nombre;
    entity.cod_municipio = municipio.cod;
  }
  entity.distrito = '';
  entity.cod_distrito = '';
}

export function alCambiarDistrito(
  entity: UbicacionGeo,
  distritos: Array<{
    cod?: unknown;
    nombre?: string;
    cod_departamento?: unknown;
    cod_municipio?: unknown;
  }>,
  municipios: Array<{ cod?: unknown; nombre?: string; cod_departamento?: unknown }>,
  cod: unknown,
): void {
  entity.cod_distrito = cod;
  const distrito = (distritos || []).find(
    (item) =>
      String(item.cod) === String(cod) && String(item.cod_departamento) === String(entity.cod_departamento),
  );
  if (!distrito) {
    return;
  }
  entity.cod_municipio = distrito.cod_municipio;
  const mun = (municipios || []).find(
    (m) =>
      String(m.cod) === String(distrito.cod_municipio) &&
      String(m.cod_departamento) === String(distrito.cod_departamento),
  );
  if (mun) {
    entity.municipio = mun.nombre;
  }
  entity.distrito = distrito.nombre;
  entity.cod_distrito = distrito.cod;
}

/** Completa códigos DGT/MH desde nombres ya guardados (sucursales viejas). */
export function hidratarCodigosUbicacion(
  entity: UbicacionGeo,
  catalogs: {
    departamentos: Array<{ cod?: unknown; nombre?: string }>;
    municipios: Array<{ cod?: unknown; nombre?: string; cod_departamento?: unknown }>;
    distritos: Array<{
      cod?: unknown;
      nombre?: string;
      cod_departamento?: unknown;
      cod_municipio?: unknown;
    }>;
  },
): void {
  if (codigoVacio(entity.cod_departamento) && !codigoVacio(entity.departamento)) {
    const dep = (catalogs.departamentos || []).find((d) => mismoTexto(d.nombre, entity.departamento));
    if (dep) {
      entity.cod_departamento = dep.cod;
    }
  }
  if (codigoVacio(entity.cod_municipio) && !codigoVacio(entity.municipio) && !codigoVacio(entity.cod_departamento)) {
    const mun = (catalogs.municipios || []).find(
      (m) =>
        mismoTexto(m.nombre, entity.municipio) &&
        String(m.cod_departamento) === String(entity.cod_departamento),
    );
    if (mun) {
      entity.cod_municipio = mun.cod;
    }
  }
  if (
    codigoVacio(entity.cod_distrito) &&
    !codigoVacio(entity.distrito) &&
    !codigoVacio(entity.cod_departamento) &&
    !codigoVacio(entity.cod_municipio)
  ) {
    const dis = (catalogs.distritos || []).find(
      (x) =>
        mismoTexto(x.nombre, entity.distrito) &&
        String(x.cod_departamento) === String(entity.cod_departamento) &&
        String(x.cod_municipio) === String(entity.cod_municipio),
    );
    if (dis) {
      entity.cod_distrito = dis.cod;
    }
  }
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
