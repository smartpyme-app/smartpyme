/**
 * Abre el XML (o texto) en una ventana nueva para depuración FE CR sin depender de codigo_generacion.
 */
export function abrirVentanaTextoFeCr(
  contenido: string,
  mime: string,
  tituloVentana: string
): void {
  const blob = new Blob([contenido], { type: mime });
  const url = URL.createObjectURL(blob);
  window.open(url, tituloVentana, 'width=800,height=900,scrollbars=yes');
  window.setTimeout(() => URL.revokeObjectURL(url), 120_000);
}
