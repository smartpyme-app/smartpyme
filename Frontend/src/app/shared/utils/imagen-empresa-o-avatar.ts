/** Ruta de logo usable; ignora vacío y default del backend. */
export function pathLogoEmpresa(
  logo?: string | null,
  logoContexto?: string | null,
): string | null {
  const raw = (logo ?? logoContexto)?.trim();
  if (!raw || raw.includes('default.jpg')) {
    return null;
  }
  return raw;
}

/** URL pública: logo de empresa si existe; si no, avatar del usuario. */
export function urlImagenEmpresaOAvatar(
  baseUrl: string,
  logoPath: string | null,
  avatar?: string | null,
): string {
  if (logoPath) {
    if (logoPath.startsWith('http://') || logoPath.startsWith('https://')) {
      return logoPath;
    }
    return `${baseUrl}/img/${logoPath.replace(/^\/+/, '')}`;
  }
  if (avatar) {
    return `${baseUrl}/img/${String(avatar).replace(/^\/+/, '')}`;
  }
  return '';
}
