import {
  FE_PAIS_HN,
  resolveCodigoPaisFe,
} from '@services/facturacion-electronica/fe-pais.util';

type EmpresaPais = { cod_pais?: string | null; pais?: string | null } | null | undefined;

const ETIQUETA_POR_CODIGO: Record<string, string> = {
  HN: 'ISV',
  SV: 'IVA',
  CR: 'IVA',
  GT: 'IVA',
};

/** Etiqueta genérica del impuesto al consumo según país de la empresa. */
export function etiquetaImpuestoPais(empresa?: EmpresaPais): string {
  return ETIQUETA_POR_CODIGO[resolveCodigoPaisFe(empresa)] ?? 'IVA';
}

/** Nombre de catálogo/listado con terminología del país (p. ej. IVA → ISV en Honduras). */
export function displayNombreImpuesto(
  nombre: string | null | undefined,
  empresa?: EmpresaPais
): string {
  const raw = (nombre ?? '').trim();
  if (resolveCodigoPaisFe(empresa) === FE_PAIS_HN) {
    if (!raw) {
      return etiquetaImpuestoPais(empresa);
    }
    return raw.replace(/\bIVA\b/gi, 'ISV');
  }
  return raw || etiquetaImpuestoPais(empresa);
}
