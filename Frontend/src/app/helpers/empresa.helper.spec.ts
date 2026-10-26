import {
  debeEmitirDteAlFacturar,
  debeImprimirTrasFacturar,
  esVentaTicketSinEmisionFe,
  ventaEsTicketSinEmisionFe,
  isFacturacionElectronicaActiva,
  isImpresionEnFacturacionActiva,
} from './empresa.helper';

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

describe('isFacturacionElectronicaActiva', () => {
  it('retorna false cuando FE esta apagada', () => {
    expect(isFacturacionElectronicaActiva(null)).toBeFalse();
    expect(isFacturacionElectronicaActiva({ facturacion_electronica: false })).toBeFalse();
    expect(isFacturacionElectronicaActiva({ facturacion_electronica: 0 })).toBeFalse();
  });

  it('retorna true cuando FE esta activada', () => {
    expect(isFacturacionElectronicaActiva({ facturacion_electronica: true })).toBeTrue();
    expect(isFacturacionElectronicaActiva({ facturacion_electronica: 1 })).toBeTrue();
    expect(isFacturacionElectronicaActiva({ facturacion_electronica: '1' })).toBeTrue();
  });
});

describe('debeEmitirDteAlFacturar', () => {
  it('emite DTE solo con impresion en facturacion y FE', () => {
    expect(debeEmitirDteAlFacturar({
      impresion_en_facturacion: true,
      facturacion_electronica: true,
    })).toBeTrue();
  });

  it('no emite DTE si solo hay FE', () => {
    expect(debeEmitirDteAlFacturar({
      impresion_en_facturacion: false,
      facturacion_electronica: true,
    })).toBeFalse();
  });

  it('no emite DTE si solo hay impresion', () => {
    expect(debeEmitirDteAlFacturar({
      impresion_en_facturacion: true,
      facturacion_electronica: false,
    })).toBeFalse();
  });

  it('no emite DTE si ambos estan apagados', () => {
    expect(debeEmitirDteAlFacturar({
      impresion_en_facturacion: false,
      facturacion_electronica: false,
    })).toBeFalse();
  });

  it('no emite DTE si el documento es Ticket', () => {
    expect(debeEmitirDteAlFacturar({
      impresion_en_facturacion: true,
      facturacion_electronica: true,
    }, 'Ticket')).toBeFalse();
  });
});

describe('esVentaTicketSinEmisionFe', () => {
  it('solo coincide con el nombre Ticket', () => {
    expect(esVentaTicketSinEmisionFe('Ticket')).toBeTrue();
    expect(esVentaTicketSinEmisionFe('Tiquete Electrónico')).toBeFalse();
    expect(esVentaTicketSinEmisionFe('Recibo')).toBeFalse();
    expect(esVentaTicketSinEmisionFe(null)).toBeFalse();
  });
});

describe('ventaEsTicketSinEmisionFe', () => {
  it('revisa nombre_documento y tipo_documento', () => {
    expect(ventaEsTicketSinEmisionFe({ nombre_documento: 'Factura', tipo_documento: 'Ticket' })).toBeTrue();
    expect(ventaEsTicketSinEmisionFe({ nombre_documento: 'Ticket' })).toBeTrue();
    expect(ventaEsTicketSinEmisionFe({ nombre_documento: 'Factura' })).toBeFalse();
  });
});

describe('debeImprimirTrasFacturar', () => {
  it('imprime si el checkbox de empresa y el de la venta estan activos', () => {
    expect(debeImprimirTrasFacturar({ impresion_en_facturacion: true }, true)).toBeTrue();
  });

  it('no imprime si el checkbox de empresa esta apagado', () => {
    expect(debeImprimirTrasFacturar({ impresion_en_facturacion: false }, true)).toBeFalse();
  });

  it('no imprime si el usuario desmarco Imprimir en la venta', () => {
    expect(debeImprimirTrasFacturar({ impresion_en_facturacion: true }, false)).toBeFalse();
  });
});
