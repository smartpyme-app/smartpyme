import {
  codigoGeneracionDesdeDte,
  codigoGeneracionYaUsadoEnLote,
  esErrorCodigoGeneracionDuplicado,
  mensajeErrorDocumentoImport,
  normalizarCodigoGeneracion,
} from './codigo-generacion.util';

describe('codigo-generacion.util', () => {
  it('normaliza recortando y pasando a mayúsculas', () => {
    expect(normalizarCodigoGeneracion('  abc-123  ')).toBe('ABC-123');
  });

  it('lee codigoGeneracion o clave del DTE', () => {
    expect(codigoGeneracionDesdeDte({ identificacion: { codigoGeneracion: 'u-1' } })).toBe('U-1');
    expect(codigoGeneracionDesdeDte({ identificacion: { clave: '506123' } })).toBe('506123');
  });

  it('detecta el error de duplicado del backend', () => {
    const err = { error: { error: 'Ya está cargada una compra con ese código de generación.' } };
    expect(esErrorCodigoGeneracionDuplicado(err)).toBe(true);
    expect(mensajeErrorDocumentoImport(err)).toContain('compra');
  });

  it('detecta código repetido en el mismo lote', () => {
    expect(codigoGeneracionYaUsadoEnLote('abc', ['ABC'])).toBe(true);
    expect(codigoGeneracionYaUsadoEnLote('abc', ['xyz'])).toBe(false);
    expect(codigoGeneracionYaUsadoEnLote('', ['ABC'])).toBe(false);
  });
});
