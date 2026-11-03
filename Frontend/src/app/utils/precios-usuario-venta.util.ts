/** Tarifa sin usuarios asignados = visible para todos; con lista = solo esos id_usuario. */
type PrecioConUsuarios = { usuarios?: Array<{ id_usuario?: number; id?: number }> };

export function precioVisibleParaUsuario(
  precio: PrecioConUsuarios | null | undefined,
  userId: number | string | null | undefined,
): boolean {
  const usuarios = precio?.usuarios;
  if (!Array.isArray(usuarios) || usuarios.length === 0) {
    return true;
  }
  if (userId == null || userId === '') {
    return false;
  }
  const id = Number(userId);
  return usuarios.some((u) => Number(u?.id_usuario ?? u?.id) === id);
}

export function filtrarPreciosPorUsuario<T extends PrecioConUsuarios>(
  precios: T[] | null | undefined,
  userId: number | string | null | undefined,
): T[] {
  if (!Array.isArray(precios)) {
    return [];
  }
  return precios.filter((p) => precioVisibleParaUsuario(p, userId));
}
