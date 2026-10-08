/**
 * Empresa dueña de licencias (organización padre) usa la plataforma.
 * Licencias queda como opción del menú, no como un shell aparte.
 * Una empresa sin licencia también entra normal.
 * ponytail: solo mira los dos flags que ya manda el login; si aparece un tercer tipo de empresa, ampliar acá.
 */
export function empresaPuedeUsarPlataforma(
  empresa: { licencia?: unknown; es_empresa_padre?: boolean } | null | undefined
): boolean {
  return !empresa?.licencia || !!empresa.es_empresa_padre;
}
