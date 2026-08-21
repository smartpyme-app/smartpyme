import { isImpresionEnFacturacionActiva } from './empresa.helper';

describe('isImpresionEnFacturacionActiva', () => {
  it('retorna false cuando empresa es null o undefined', () => {
    expect(isImpresionEnFacturacionActiva(null)).toBeFalse();
    expect(isImpresionEnFacturacionActiva(undefined)).toBeFalse();
    expect(isImpresionEnFacturacionActiva({})).toBeFalse();
  });

  it('retorna false cuando impresion_en_facturacion esta desactivada en cualquier formato', () => {
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: false })).toBeFalse();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: 0 })).toBeFalse();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: '0' })).toBeFalse();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: 'false' })).toBeFalse();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: null })).toBeFalse();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: undefined })).toBeFalse();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: '' })).toBeFalse();
  });

  it('retorna true cuando impresion_en_facturacion esta activada', () => {
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: true })).toBeTrue();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: 1 })).toBeTrue();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: '1' })).toBeTrue();
    expect(isImpresionEnFacturacionActiva({ impresion_en_facturacion: 'true' })).toBeTrue();
  });
});
