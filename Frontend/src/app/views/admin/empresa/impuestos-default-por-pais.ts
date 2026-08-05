/**
 * Defaults fiscales por país (alineado con Backend ImpuestosDefaultPorPais).
 * Fuente preferida: GET impuestos/defaults; este mapa es fallback.
 */
export type ImpuestosDefaults = {
  moneda: string;
  iva: number;
  percepcion: number;
  retencion_iva: number;
};

const POR_CODIGO: Record<string, ImpuestosDefaults> = {
  SV: { moneda: 'USD', iva: 13, percepcion: 1, retencion_iva: 1 },
  CR: { moneda: 'CRC', iva: 13, percepcion: 1, retencion_iva: 1 },
  HN: { moneda: 'HNL', iva: 15, percepcion: 1, retencion_iva: 1 },
  GT: { moneda: 'GTQ', iva: 12, percepcion: 1, retencion_iva: 1 },
  NI: { moneda: 'NIO', iva: 15, percepcion: 1, retencion_iva: 1 },
  PA: { moneda: 'PAB', iva: 7, percepcion: 1, retencion_iva: 1 },
  BZ: { moneda: 'BZD', iva: 12.5, percepcion: 1, retencion_iva: 1 },
  MX: { moneda: 'MXN', iva: 16, percepcion: 1, retencion_iva: 1 },
};

const NOMBRE_A_CODIGO: Record<string, string> = {
  'El Salvador': 'SV',
  Belice: 'BZ',
  Guatemala: 'GT',
  Honduras: 'HN',
  Nicaragua: 'NI',
  'Costa Rica': 'CR',
  Panamá: 'PA',
  México: 'MX',
};

export function codigoPaisDesdeNombre(nombre: string | null | undefined): string | null {
  if (!nombre) return null;
  return NOMBRE_A_CODIGO[nombre] ?? null;
}

export function impuestosDefaultsPorCodigo(cod: string | null | undefined): ImpuestosDefaults {
  const key = String(cod || 'SV').toUpperCase();
  return POR_CODIGO[key] ?? POR_CODIGO['SV'];
}

export function impuestosDefaultsPorNombrePais(nombre: string | null | undefined): ImpuestosDefaults {
  return impuestosDefaultsPorCodigo(codigoPaisDesdeNombre(nombre) || 'SV');
}

/** Aplica moneda + iva al objeto empresa (mutación in-place). */
export function aplicarImpuestosDefaultsAEmpresa(
  empresa: { moneda?: any; iva?: any; cod_pais?: any; pais?: any },
  opts?: { nombrePais?: string; codPais?: string }
): void {
  const cod =
    opts?.codPais ||
    empresa.cod_pais ||
    codigoPaisDesdeNombre(opts?.nombrePais ?? empresa.pais) ||
    'SV';
  const d = impuestosDefaultsPorCodigo(cod);
  empresa.moneda = d.moneda;
  empresa.iva = d.iva;
  if (!empresa.cod_pais) {
    empresa.cod_pais = String(cod).toUpperCase();
  }
}

export function fraccionPercepcion(codPais?: string | null): number {
  return impuestosDefaultsPorCodigo(codPais).percepcion / 100;
}

export function fraccionRetencionIva(codPais?: string | null): number {
  return impuestosDefaultsPorCodigo(codPais).retencion_iva / 100;
}
