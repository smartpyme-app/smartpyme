/** Alineado con Backend\app\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver */
export const FE_PAIS_SV = 'SV';
export const FE_PAIS_CR = 'CR';

export function resolveCodigoPaisFe(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): string {
  if (!empresa) {
    return FE_PAIS_SV;
  }

  const cod = empresa.cod_pais?.trim();
  if (cod) {
    return cod.toUpperCase();
  }

  const nombre = (empresa.pais ?? '').toLowerCase().trim();
  if (nombre.includes('costa rica')) {
    return FE_PAIS_CR;
  }
  if (nombre.includes('salvador')) {
    return FE_PAIS_SV;
  }

  return FE_PAIS_SV;
}
