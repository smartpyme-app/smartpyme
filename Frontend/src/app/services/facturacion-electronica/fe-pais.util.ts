/** Alineado con Backend\app\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver */
export const FE_PAIS_SV = 'SV';
export const FE_PAIS_CR = 'CR';

const FE_LOCALE_CODIGOS = new Set(['SV', 'CR', 'GT', 'HN']);

export function resolveCodigoPaisFe(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): string {
  if (!empresa) {
    return FE_PAIS_SV;
  }

  const cod = empresa.cod_pais?.trim().toUpperCase();
  if (cod && FE_LOCALE_CODIGOS.has(cod)) {
    return cod;
  }

  const nombre = (empresa.pais ?? '').toLowerCase().trim();
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

  return FE_PAIS_SV;
}
