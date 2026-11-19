/**
 * Opciones para <ng-select [items] bindLabel="etiqueta" bindValue="id" [virtualScroll]="true">.
 *
 * Con listas grandes (empresas con 16k+ clientes) un <ng-option> por elemento crea miles de
 * componentes al abrir la vista o el modal y congela el navegador. Con [items] + virtualScroll
 * ng-select sólo dibuja las opciones visibles. La etiqueta reproduce el texto que mostraban
 * las plantillas: nombre de empresa o nombre completo, más "(nombre comercial)" si existe.
 */
export interface OpcionSelect {
  id: any;
  etiqueta: string;
}

/** Igual que el pipe `truncate` con sus valores por defecto (sin cortar en palabras, con '...'). */
function truncar(valor: string, limite: number): string {
  if (!valor || valor.length <= limite) {
    return valor;
  }
  return valor.substr(0, limite) + '...';
}

export function etiquetaClienteProveedor(item: any, limiteNombre?: number): string {
  const nombre = (item?.tipo == 'Empresa' ? item?.nombre_empresa : item?.nombre_completo) ?? '';
  const base = limiteNombre ? truncar(String(nombre), limiteNombre) : String(nombre);
  return item?.nombre_comercial ? `${base} (${item.nombre_comercial})` : base;
}

/**
 * @param lista       clientes o proveedores tal como llegan de la API
 * @param opcionVacia texto de la opción con valor '' ("Todos", "Cliente"...); se omite si no se pasa
 * @param limiteNombre recorte del nombre (equivalente a `| truncate:N` en la plantilla)
 */
export function opcionesSelect(lista: any[] | null | undefined, opcionVacia?: string, limiteNombre?: number): OpcionSelect[] {
  const opciones = (lista || []).map((item: any) => ({ id: item.id, etiqueta: etiquetaClienteProveedor(item, limiteNombre) }));
  return opcionVacia !== undefined ? [{ id: '', etiqueta: opcionVacia }, ...opciones] : opciones;
}
