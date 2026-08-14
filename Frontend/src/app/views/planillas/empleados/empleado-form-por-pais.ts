import {
  FE_PAIS_CR,
  FE_PAIS_SV,
  resolveCodigoPaisFe,
} from '@services/facturacion-electronica/fe-pais.util';

export type EmpleadoFormOpcion = { value: string | number; label: string };

export type EmpleadoFormConfig = {
  codPais: string;
  /** Identidad */
  mostrarIdType: boolean;
  idTypes: EmpleadoFormOpcion[];
  /** Si null, el template usa i18n country.identity.* */
  labelIdentidadFijo: string | null;
  placeholderIdentidadFijo: string | null;
  usarMascaraDuiSv: boolean;
  mostrarDuiHomologado: boolean;
  /** Fiscal / seguro */
  mostrarNit: boolean;
  usarMascaraNitSv: boolean;
  nitRequerido: boolean;
  mostrarIsss: boolean;
  labelIsss: string;
  isssRequerido: boolean;
  mostrarAfp: boolean;
  afpRequerido: boolean;
  mostrarOpcionesDescuentosSv: boolean;
  aplicarDescuentosPorDefecto: boolean;
  mostrarTiposDocumentoIsssAfp: boolean;
  /** Laboral CR-like */
  mostrarCategoriaOcupacional: boolean;
  categoriasOcupacionales: EmpleadoFormOpcion[];
  mostrarTipoSalario: boolean;
  tiposSalario: EmpleadoFormOpcion[];
  mostrarCreditosFiscales: boolean;
  mostrarHorasSiParcial: boolean;
  /** Defaults al crear empleado (merge sobre base común) */
  defaultsNuevo: Record<string, unknown>;
};

const ID_TYPES_CR: EmpleadoFormOpcion[] = [
  { value: 1, label: 'Cédula' },
  { value: 2, label: 'DIMEX' },
];

const TIPOS_SALARIO: EmpleadoFormOpcion[] = [
  { value: 1, label: 'Fijo mensual' },
  { value: 2, label: 'Por hora' },
  { value: 3, label: 'Mixto (base + comisión)' },
];

const CATEGORIAS_CNS_CR: EmpleadoFormOpcion[] = [
  { value: 'no_calificada', label: 'Trabajadores en ocupación no calificada' },
  { value: 'semi_calificada', label: 'Trabajadores semi-calificados' },
  { value: 'calificada', label: 'Trabajadores calificados' },
  { value: 'especializada', label: 'Trabajadores especializados' },
  { value: 'tecnico_medio', label: 'Técnicos medios de educación diversificada o asimilables' },
  { value: 'tecnico_superior', label: 'Técnicos de educación superior o asimilables' },
  { value: 'diplomado', label: 'Diplomados de educación superior o asimilables' },
  { value: 'bachiller', label: 'Bachilleres universitarios o asimilables' },
  { value: 'licenciado', label: 'Licenciados universitarios o asimilables' },
];

const BASE: Omit<EmpleadoFormConfig, 'codPais'> = {
  mostrarIdType: false,
  idTypes: [],
  labelIdentidadFijo: null,
  placeholderIdentidadFijo: null,
  usarMascaraDuiSv: false,
  mostrarDuiHomologado: false,
  mostrarNit: true,
  usarMascaraNitSv: false,
  nitRequerido: false,
  mostrarIsss: false,
  labelIsss: 'Número ISSS:',
  isssRequerido: false,
  mostrarAfp: false,
  afpRequerido: false,
  mostrarOpcionesDescuentosSv: false,
  aplicarDescuentosPorDefecto: false,
  mostrarTiposDocumentoIsssAfp: false,
  mostrarCategoriaOcupacional: false,
  categoriasOcupacionales: [],
  mostrarTipoSalario: false,
  tiposSalario: [],
  mostrarCreditosFiscales: false,
  mostrarHorasSiParcial: false,
  defaultsNuevo: {},
};

export const EMPLEADO_FORM_CONFIG_SV: EmpleadoFormConfig = {
  ...BASE,
  codPais: FE_PAIS_SV,
  usarMascaraDuiSv: true,
  mostrarDuiHomologado: true,
  mostrarNit: true,
  usarMascaraNitSv: true,
  nitRequerido: true,
  mostrarIsss: true,
  labelIsss: 'Número ISSS:',
  isssRequerido: true,
  mostrarAfp: true,
  afpRequerido: true,
  mostrarOpcionesDescuentosSv: true,
  aplicarDescuentosPorDefecto: true,
  mostrarTiposDocumentoIsssAfp: true,
};

export const EMPLEADO_FORM_CONFIG_CR: EmpleadoFormConfig = {
  ...BASE,
  codPais: FE_PAIS_CR,
  mostrarIdType: true,
  idTypes: ID_TYPES_CR,
  labelIdentidadFijo: 'Número de cédula:',
  placeholderIdentidadFijo: '1-2345-6789',
  mostrarNit: false,
  mostrarIsss: true,
  labelIsss: 'Nº asegurado CCSS:',
  isssRequerido: false,
  mostrarCategoriaOcupacional: true,
  categoriasOcupacionales: CATEGORIAS_CNS_CR,
  mostrarTipoSalario: true,
  tiposSalario: TIPOS_SALARIO,
  mostrarCreditosFiscales: true,
  mostrarHorasSiParcial: true,
  defaultsNuevo: {
    id_type: 1,
    tipo_salario: 1,
    tiene_conyuge_dependiente: false,
    cantidad_hijos_dependientes: 0,
    horas_jornada: null,
    categoria_ocupacional: null,
  },
};

/** Default = SV (comportamiento histórico del módulo). */
export function empleadoFormConfig(
  empresa?: { cod_pais?: string | null; pais?: string | null } | null
): EmpleadoFormConfig {
  const cod = resolveCodigoPaisFe(empresa);
  if (cod === FE_PAIS_CR) {
    return EMPLEADO_FORM_CONFIG_CR;
  }
  return EMPLEADO_FORM_CONFIG_SV;
}

export function labelIdentidadEmpleado(
  config: EmpleadoFormConfig,
  idType?: number | null
): string | null {
  if (!config.mostrarIdType) {
    return config.labelIdentidadFijo;
  }
  return idType === 2 ? 'Número DIMEX:' : 'Número de cédula:';
}

export function placeholderIdentidadEmpleado(
  config: EmpleadoFormConfig,
  idType?: number | null
): string | null {
  if (!config.mostrarIdType) {
    return config.placeholderIdentidadFijo;
  }
  return idType === 2 ? 'DIMEX' : '1-2345-6789';
}

/** NIT visible: país lo muestra y (si aplica homologado) no está homologado. */
export function mostrarCampoNit(
  config: EmpleadoFormConfig,
  duiHomologado?: boolean | null
): boolean {
  if (!config.mostrarNit) {
    return false;
  }
  if (config.mostrarDuiHomologado && duiHomologado) {
    return false;
  }
  return true;
}
