/** Alineado con Backend\app\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver */
export const FE_PAIS_SV = 'SV';
export const FE_PAIS_CR = 'CR';

const FE_LOCALE_CODIGOS = new Set(['SV', 'CR', 'GT', 'HN']);

/** Resuelve código ISO desde el nombre de país (campo `empresas.pais`). */
function codigoFromNombrePais(pais: string | null | undefined): string | null {
  const nombre = (pais ?? '').toLowerCase().trim();
  if (!nombre) {
    return null;
  }
  if (nombre.includes('costa rica')) {
    return FE_PAIS_CR;
  }
  if (nombre.includes('salvador')) {
    return FE_PAIS_SV;
  }
  if (nombre.includes('guatemala')) {
    return 'GT';
  }
  if (nombre.includes('honduras')) {
    return 'HN';
  }
  const upper = nombre.toUpperCase();
  if (FE_LOCALE_CODIGOS.has(upper)) {
    return upper;
  }
  return null;
}

export function resolveCodigoPaisFe(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): string {
  if (!empresa) {
    return FE_PAIS_SV;
  }

  // ponytail: `pais` manda sobre `cod_pais`; muchas empresas CR tienen cod_pais null o SV legacy
  const fromNombre = codigoFromNombrePais(empresa.pais);
  if (fromNombre) {
    return fromNombre;
  }

  const cod = empresa.cod_pais?.trim().toUpperCase();
  if (cod && FE_LOCALE_CODIGOS.has(cod)) {
    return cod;
  }

  return FE_PAIS_SV;
}

export function esElSalvadorFe(
  empresa?: { cod_pais?: string | null; pais?: string | null } | null | undefined
): boolean {
  return resolveCodigoPaisFe(empresa) === FE_PAIS_SV;
}
