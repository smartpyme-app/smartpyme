/** Roles gerente (Spatie); alineado con Backend/config/constants.php */
export const GERENTE_ROLE_SLUGS = [
  'gerente_ventas',
  'gerente_compras',
  'gerente_operaciones',
] as const;

export type GerenteRoleSlug = (typeof GERENTE_ROLE_SLUGS)[number];

const GERENTE_SLUG_SET = new Set<string>(GERENTE_ROLE_SLUGS);

/** slug Spatie y/o tipo legacy ("Gerente ventas", etc.) */
export function esRolGerente(
  roleSlug: string | null | undefined,
  tipo?: string | null,
): boolean {
  if (roleSlug && GERENTE_SLUG_SET.has(roleSlug)) {
    return true;
  }
  const t = (tipo ?? '').trim().toLowerCase();
  return t.startsWith('gerente');
}