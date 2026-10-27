/** Contexto para filtrar módulos de roles/permisos según empresa (alineado al sidebar). */
export interface PermisosModulosEmpresaContext {
  slugsActivos: ReadonlySet<string>;
  empresa: Record<string, unknown> | null | undefined;
  fidelizacionActiva?: boolean;
}

function tieneSlug(ctx: PermisosModulosEmpresaContext, ...slugs: string[]): boolean {
  return slugs.some((s) => ctx.slugsActivos.has(s));
}

function norm(value: string | undefined | null): string {
  return (value ?? '').toLowerCase();
}

/** Flags booleanos en `empresa` (pueden venir como 1/0 desde API). */
function empresaFlag(ctx: PermisosModulosEmpresaContext, key: string): boolean {
  const v = ctx.empresa?.[key];
  return v === true || v === 1 || v === '1';
}

function configuracionesEmpresa(ctx: PermisosModulosEmpresaContext): Record<string, unknown> | undefined {
  const raw = ctx.empresa?.['custom_empresa'];
  if (!raw) {
    return undefined;
  }
  try {
    const custom = typeof raw === 'string' ? JSON.parse(raw) : raw;
    return custom?.configuraciones as Record<string, unknown> | undefined;
  } catch {
    return undefined;
  }
}

function configFlag(ctx: PermisosModulosEmpresaContext, key: string): boolean {
  const cfg = configuracionesEmpresa(ctx);
  const v = cfg?.[key];
  return v === true || v === 1 || v === '1';
}

function contabilidadHabilitada(ctx: PermisosModulosEmpresaContext): boolean {
  return tieneSlug(ctx, 'contabilidad');
}

/** Misma lógica que ApiService.mostrarMenuConfigGastos */
function mostrarConfigGastos(ctx: PermisosModulosEmpresaContext): boolean {
  return contabilidadHabilitada(ctx) || configFlag(ctx, 'gastos_categorias_personalizadas');
}

/** Permisos finanzas.bancos: con contabilidad o módulo bancos sin contabilidad */
function permisosBancosVisibles(ctx: PermisosModulosEmpresaContext): boolean {
  return contabilidadHabilitada(ctx) || configFlag(ctx, 'modulo_bancos');
}

const SLUGS_CLINICA_PACIENTES = ['clinica-pacientes', 'clinica'] as const;

type ReglaSubmodulo = (ctx: PermisosModulosEmpresaContext) => boolean;

/**
 * Reglas parent.submodule → visibilidad (misma fuente que el sidebar).
 * Mantener alineado con permisos-modulos-empresa.check.mjs
 */
export const REGLAS_SUBMODULO_KEYS: readonly string[] = [
  'productos.paquetes',
  'productos.campos_personalizados',
  'ventas.proyectos',
  'ventas.ordenes_produccion',
  'gastos.categorias',
  'finanzas.bancos',
  'planilla.comisiones',
  'planilla.bonos',
  'planilla.incentivos',
  'finanzas.prestamos',
  'contabilidad.activos',
  'compras.retaceo',
  'clinica.pacientes',
  'clinica.expediente',
  'clinica.consultas',
  'clinica.tratamientos',
  'clinica.diagnosticos',
  'clinica.profesionales',
];

const REGLAS_SUBMODULO: Record<string, ReglaSubmodulo> = {
  'productos.paquetes': (ctx) => empresaFlag(ctx, 'modulo_paquetes'),
  'productos.campos_personalizados': (ctx) => contabilidadHabilitada(ctx),
  'ventas.proyectos': (ctx) => empresaFlag(ctx, 'modulo_proyectos'),
  'ventas.ordenes_produccion': (ctx) => contabilidadHabilitada(ctx),
  'gastos.categorias': (ctx) => mostrarConfigGastos(ctx),
  'finanzas.bancos': (ctx) => permisosBancosVisibles(ctx),
  'planilla.comisiones': (ctx) => tieneSlug(ctx, 'comisiones-vendedores'),
  'planilla.bonos': (ctx) => tieneSlug(ctx, 'bonos-vendedores'),
  'planilla.incentivos': (ctx) =>
    tieneSlug(ctx, 'comisiones-vendedores', 'bonos-vendedores', 'gift-cards'),
  'finanzas.prestamos': (ctx) =>
    tieneSlug(ctx, 'prestamos-empresa') && contabilidadHabilitada(ctx),
  'contabilidad.activos': (ctx) => contabilidadHabilitada(ctx),
  'compras.retaceo': (ctx) => contabilidadHabilitada(ctx),
  'clinica.profesionales': (ctx) => tieneSlug(ctx, 'clinica-profesionales'),
  'clinica.pacientes': (ctx) => tieneSlug(ctx, ...SLUGS_CLINICA_PACIENTES),
  'clinica.expediente': (ctx) => tieneSlug(ctx, ...SLUGS_CLINICA_PACIENTES),
  'clinica.consultas': (ctx) => tieneSlug(ctx, ...SLUGS_CLINICA_PACIENTES),
  'clinica.tratamientos': (ctx) => tieneSlug(ctx, ...SLUGS_CLINICA_PACIENTES),
  'clinica.diagnosticos': (ctx) => tieneSlug(ctx, ...SLUGS_CLINICA_PACIENTES),
};

/** ¿Mostrar el módulo principal en pestañas de permisos? */
export function moduloPermisosVisible(
  moduleName: string,
  ctx: PermisosModulosEmpresaContext,
): boolean {
  const name = norm(moduleName);
  switch (name) {
    case 'contabilidad':
      return contabilidadHabilitada(ctx);
    case 'restaurante':
    case 'pedidos':
      return tieneSlug(ctx, 'modulo-restaurante');
    case 'clinica':
      return tieneSlug(ctx, 'clinica', 'clinica-pacientes', 'clinica-profesionales');
    case 'citas':
      return empresaFlag(ctx, 'modulo_citas');
    case 'inteligencia_negocios':
      return tieneSlug(ctx, 'inteligencia-negocios-v2');
    case 'fidelizacion':
    case 'lealtad':
      return tieneSlug(ctx, 'fidelizacion-clientes') && ctx.fidelizacionActiva === true;
    case 'consignas':
      return true;
    default:
      return true;
  }
}

/** ¿Mostrar submódulo dentro de un módulo de permisos? */
export function submoduloPermisosVisible(
  parentModuleName: string,
  submoduleName: string,
  ctx: PermisosModulosEmpresaContext,
): boolean {
  const key = `${norm(parentModuleName)}.${norm(submoduleName)}`;
  const regla = REGLAS_SUBMODULO[key];
  if (regla) {
    return regla(ctx);
  }
  return true;
}

export function filtrarModulosPermisos(
  modules: readonly any[],
  ctx: PermisosModulosEmpresaContext,
): any[] {
  return modules
    .map((m) => {
      const submodulesOriginales = m.submodules ?? [];
      const submodules = submodulesOriginales
        .filter((s: { name?: string }) => submoduloPermisosVisible(m.name, s?.name ?? '', ctx))
        .map((s: object) => ({ ...s }));
      return { ...m, submodules, _hadSubmodules: submodulesOriginales.length > 0 };
    })
    .filter((m) => {
      if (!moduloPermisosVisible(m?.name, ctx)) {
        return false;
      }
      if (m._hadSubmodules && (m.submodules?.length ?? 0) === 0) {
        return false;
      }
      return true;
    })
    .map(({ _hadSubmodules, ...m }) => m);
}
