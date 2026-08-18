import { resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

export type CampoIdentificadorFiscalCliente = 'nit' | 'ncr';

export interface ConfigIdentificadorFiscalCliente {
  campo: CampoIdentificadorFiscalCliente;
  etiqueta: string;
  mostrarRegistroSecundario: boolean;
  camposLegacy?: CampoIdentificadorFiscalCliente[];
}

const CONFIG_POR_PAIS: Record<string, ConfigIdentificadorFiscalCliente> = {
  // En Honduras el RTN se guarda en nit; ncr queda solo como respaldo histórico.
  HN: {
    campo: 'nit',
    etiqueta: 'country.tax.nit',
    mostrarRegistroSecundario: false,
    camposLegacy: ['ncr'],
  },
  // En Costa Rica la identificación fiscal también se captura en nit.
  CR: {
    campo: 'nit',
    etiqueta: 'country.tax.nit',
    mostrarRegistroSecundario: false,
  },
};

const CONFIG_POR_DEFECTO: ConfigIdentificadorFiscalCliente = {
  campo: 'ncr',
  etiqueta: 'country.tax.ncr',
  mostrarRegistroSecundario: true,
};

export function configIdentificadorFiscalCliente(
  empresa?: { cod_pais?: string | null; pais?: string | null } | null,
): ConfigIdentificadorFiscalCliente {
  const codigo = resolveCodigoPaisFe(empresa);
  return CONFIG_POR_PAIS[codigo] ?? CONFIG_POR_DEFECTO;
}

export function valorIdentificadorFiscalCliente(
  cliente: Record<string, unknown> | null | undefined,
  empresa?: { cod_pais?: string | null; pais?: string | null } | null,
): unknown {
  const config = configIdentificadorFiscalCliente(empresa);
  const valorPrincipal = cliente?.[config.campo];
  if (valorPrincipal !== null && valorPrincipal !== undefined && valorPrincipal !== '') {
    return valorPrincipal;
  }

  return config.camposLegacy
    ?.map((campo) => cliente?.[campo])
    .find((valor) => valor !== null && valor !== undefined && valor !== '') ?? '';
}
