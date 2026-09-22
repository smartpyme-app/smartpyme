import { FE_PAIS_CR, FE_PAIS_HN, FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

export interface TipoIdentificacionOpcion {
  codigo: string;
  label: string;
  uso: string[];
}

export interface ConfigIdentificacionTipos {
  default_persona: string;
  default_empresa: string;
  default_extranjero: string;
  tipos: TipoIdentificacionOpcion[];
}

const PLANTILLA: Record<string, ConfigIdentificacionTipos> = {
  CR: {
    default_persona: '01',
    default_empresa: '02',
    default_extranjero: '05',
    tipos: [
      { codigo: '01', label: 'Cédula física', uso: ['emisor', 'receptor'] },
      { codigo: '02', label: 'Cédula jurídica', uso: ['emisor', 'receptor'] },
      { codigo: '03', label: 'DIMEX', uso: ['emisor', 'receptor'] },
      { codigo: '04', label: 'NITE', uso: ['emisor', 'receptor'] },
      { codigo: '05', label: 'Extranjero no domiciliado', uso: ['emisor', 'receptor'] },
      { codigo: '06', label: 'No contribuyente', uso: ['receptor'] },
    ],
  },
  HN: {
    default_persona: 'dni',
    default_empresa: 'rtn',
    default_extranjero: 'pasaporte',
    tipos: [
      { codigo: 'dni', label: 'DNI', uso: ['receptor'] },
      { codigo: 'rtn', label: 'RTN', uso: ['receptor'] },
      { codigo: 'pasaporte', label: 'Pasaporte', uso: ['receptor'] },
    ],
  },
  SV: {
    default_persona: '13',
    default_empresa: '36',
    default_extranjero: '03',
    tipos: [
      { codigo: '13', label: 'DUI', uso: ['receptor'] },
      { codigo: '36', label: 'NIT', uso: ['receptor'] },
      { codigo: '03', label: 'Pasaporte', uso: ['receptor'] },
      { codigo: '02', label: 'Carnet de residente', uso: ['receptor'] },
      { codigo: '37', label: 'Otro', uso: ['receptor'] },
    ],
  },
};

export function plantillaIdentificacionTipos(pais: string): ConfigIdentificacionTipos {
  return PLANTILLA[pais] ?? PLANTILLA[FE_PAIS_SV];
}

export function configIdentificacionTipos(
  empresa?: { cod_pais?: string | null; pais?: string | null } | null,
): ConfigIdentificacionTipos {
  return plantillaIdentificacionTipos(resolveCodigoPaisFe(empresa));
}

export function defaultTipoIdentificacion(
  cfg: ConfigIdentificacionTipos,
  tipoCliente: string | null | undefined,
): string {
  const t = String(tipoCliente ?? '').toLowerCase();
  if (t === 'empresa') {
    return cfg.default_empresa;
  }
  if (t === 'extranjero') {
    return cfg.default_extranjero;
  }
  return cfg.default_persona;
}

export function tiposPorUso(cfg: ConfigIdentificacionTipos, uso: 'receptor' | 'emisor'): TipoIdentificacionOpcion[] {
  return cfg.tipos.filter((t) => t.uso.includes(uso));
}

export function configDesdeApi(
  res: Partial<ConfigIdentificacionTipos> | null | undefined,
  fallback: ConfigIdentificacionTipos,
): ConfigIdentificacionTipos {
  if (!res || !Array.isArray(res.tipos) || res.tipos.length === 0) {
    return fallback;
  }
  return {
    default_persona: res.default_persona || fallback.default_persona,
    default_empresa: res.default_empresa || fallback.default_empresa,
    default_extranjero: res.default_extranjero || fallback.default_extranjero,
    tipos: res.tipos,
  };
}

export function labelTipoIdentificacion(cfg: ConfigIdentificacionTipos, codigo: string | null | undefined): string {
  const c = String(codigo ?? '').trim();
  if (!c) {
    return '';
  }
  return cfg.tipos.find((t) => t.codigo === c)?.label ?? c;
}

export function labelCampoIdentificacion(cfg: ConfigIdentificacionTipos, codigo: string | null | undefined): string {
  return labelTipoIdentificacion(cfg, codigo) || 'Identificación';
}

const MASCARA_DUI_PAIS: Record<string, string> = {
  [FE_PAIS_SV]: '00000000-0',
  [FE_PAIS_CR]: '0-0000-0000',
  [FE_PAIS_HN]: '0000-0000-00000',
};

const MASCARA_NIT_PAIS: Record<string, string> = {
  [FE_PAIS_SV]: '0000000-000-000-0',
  [FE_PAIS_CR]: '0-0000-0000',
  [FE_PAIS_HN]: '00000000000000',
};

export function mascaraIdentificacion(
  codigo: string | null | undefined,
  empresa?: { validador_dui?: string | null; validador_nit?: string | null; pais?: string | null; cod_pais?: string | null } | null,
): string {
  const c = String(codigo ?? '').trim();
  if (!c) {
    return '';
  }

  const pais = resolveCodigoPaisFe(empresa);
  const dui = empresa?.validador_dui || MASCARA_DUI_PAIS[pais] || MASCARA_DUI_PAIS[FE_PAIS_SV];
  const nit = empresa?.validador_nit || MASCARA_NIT_PAIS[pais] || MASCARA_NIT_PAIS[FE_PAIS_SV];

  if (pais === FE_PAIS_CR) {
    if (c === '01') {
      return dui;
    }
    if (c === '02') {
      return nit;
    }
    return '';
  }

  if (pais === FE_PAIS_HN) {
    if (c === 'dni') {
      return dui;
    }
    if (c === 'rtn') {
      return nit;
    }
    return '';
  }

  if (c === '13') {
    return dui;
  }
  if (c === '36') {
    return nit;
  }
  return '';
}
