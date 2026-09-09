import {
  processSvgInMessage,
  extractSvgInfo,
  extractAdditionalText,
  escapeHtmlAttr,
} from './svg-message.util';

describe('svg-message.util', () => {
  it('devuelve el mensaje sin cambios si no hay SVG', () => {
    const input = '<p>Hola mundo</p>';
    expect(processSvgInMessage(input)).toBe(input);
  });

  it('envuelve un SVG con controles de descarga', () => {
    const input =
      '<p>Resumen</p><svg width="500" height="400"><title>Ventas</title><rect/></svg>';
    const result = processSvgInMessage(input);

    expect(result).toContain('svg-container');
    expect(result).toContain('data-svg-action="png"');
    expect(result).toContain('data-svg-action="svg"');
    expect(result).toContain('data-svg-width="500"');
    expect(result).toContain('data-svg-height="400"');
    expect(result).toContain('data-svg-title="Ventas"');
    // No debe inyectar JavaScript inline
    expect(result).not.toContain('onclick');
  });

  it('escapa valores en atributos', () => {
    expect(escapeHtmlAttr('a<b"c&d')).toBe(
      'a&lt;b&quot;c&amp;d'
    );
  });

  it('extrae el título del SVG', () => {
    const info = extractSvgInfo('<svg><title>Mi gráfico</title></svg>');
    expect(info?.title).toBe('Mi gráfico');
  });

  it('extrae texto adicional tras el SVG', () => {
    const input = '<svg></svg><p>Texto <b>adicional</b></p>';
    expect(extractAdditionalText(input)).toBe('Texto adicional');
  });

  it('usa valores por defecto cuando no hay dimensiones', () => {
    const info = extractSvgInfo('<svg></svg>');
    expect(info?.width).toBe(400);
    expect(info?.height).toBe(300);
  });
});
