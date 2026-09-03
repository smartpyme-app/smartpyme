/**
 * Utilidades puras para convertir mensajes que contienen un SVG en un
 * fragmento HTML seguro con controles de descarga (PNG/SVG).
 *
 * Separadas de ChatService para poder testearse de forma aislada.
 */

const DEFAULT_WIDTH = 400;
const DEFAULT_HEIGHT = 300;

export interface SvgInfo {
  svg: string;
  width: number;
  height: number;
  title: string;
}

/**
 * Extrae la información de un SVG contenido en el mensaje. Devuelve `null` si
 * no hay ningún SVG.
 */
export function extractSvgInfo(message: string): SvgInfo | null {
  const svgMatch = message.match(/<svg[\s\S]*?<\/svg>/);
  if (!svgMatch) {
    return null;
  }

  const svg = svgMatch[0];

  const widthMatch = svg.match(/width="([^"]*)"/);
  const heightMatch = svg.match(/height="([^"]*)"/);
  const width = widthMatch ? parseInt(widthMatch[1], 10) : DEFAULT_WIDTH;
  const height = heightMatch ? parseInt(heightMatch[1], 10) : DEFAULT_HEIGHT;

  const titleMatch = svg.match(/<title>(.*?)<\/title>/);
  const title = titleMatch ? titleMatch[1] : 'Gráfico financiero';

  return { svg, width, height, title };
}

/**
 * Extrae el texto adicional (primer párrafo) que sigue al SVG, sin etiquetas.
 */
export function extractAdditionalText(message: string): string {
  const paragraphAfterSvg = message.match(/<\/svg>[\s\S]*?<p>([\s\S]*?)<\/p>/);
  return paragraphAfterSvg
    ? paragraphAfterSvg[1].replace(/<[^>]*>/g, '').trim()
    : '';
}

/**
 * Escapa un valor para incrustarlo de forma segura como atributo HTML.
 */
export function escapeHtmlAttr(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * Genera un ID único para un SVG.
 */
export function generateSvgId(): string {
  return 'svg-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
}

/**
 * Envuelve el SVG con los controles de descarga (PNG/SVG). No se inyecta
 * JavaScript inline; se usan data-atributos que el componente interpreta.
 */
export function wrapSvgWithDownload(
  svg: string,
  info: SvgInfo,
  additionalText: string
): string {
  const svgId = generateSvgId();
  const svgWithId = svg.replace('<svg', `<svg id="${svgId}"`);

  return `
    <div class="svg-container" style="--svg-width: ${info.width}px; --svg-height: ${info.height}px;">
      ${svgWithId}
      <div class="svg-download-container mt-2 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary svg-download-btn"
                data-svg-action="png"
                data-svg-id="${svgId}"
                data-svg-title="${escapeHtmlAttr(info.title)}"
                data-svg-text="${escapeHtmlAttr(additionalText)}"
                data-svg-width="${info.width}"
                data-svg-height="${info.height}">
          <i class="fa fa-file-image-o"></i> PNG
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary svg-download-btn"
                data-svg-action="svg"
                data-svg-id="${svgId}">
          <i class="fa fa-file-code-o"></i> SVG
        </button>
      </div>
    </div>
  `;
}

/**
 * Reemplaza el primer SVG encontrado en el mensaje por su versión envuelta con
 * controles de descarga. Si no hay SVG, devuelve el mensaje sin cambios.
 */
export function processSvgInMessage(message: string): string {
  const info = extractSvgInfo(message);
  if (!info) {
    return message;
  }

  const additionalText = extractAdditionalText(message);
  const wrapped = wrapSvgWithDownload(info.svg, info, additionalText);

  return message.replace(info.svg, wrapped);
}
