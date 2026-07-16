import {
  esImpuestoIva,
  acumularMontosImpuestosVenta,
  calcularMontosLineaDetalle,
  hidratarImpuestosProductosEnDetalles,
  porcentajeIvaDetalle,
} from './impuestos-venta.util';

describe('impuestos-venta.util — IVA vs especiales', () => {
  it('esImpuestoIva reconoce codigo 20 y 13% sin código', () => {
    expect(esImpuestoIva({ codigo_mh: '20', porcentaje: 13 })).toBe(true);
    expect(esImpuestoIva({ codigo_mh: null, porcentaje: 13 })).toBe(true);
    expect(esImpuestoIva({ codigo_mh: 'C8', porcentaje: 5 })).toBe(false);
    expect(esImpuestoIva({ porcentaje: 5 })).toBe(false);
  });

  it('con cobrarIva=false acumula turismo sobre línea exenta si el detalle tiene el impuesto', () => {
    const ventaImpuestos = [
      { id: 101, id_impuesto: 1, porcentaje: 13, codigo_mh: '20', monto: 0 },
      { id: 102, id_impuesto: 2, porcentaje: 5, codigo_mh: 'C8', monto: 0 },
    ];
    const detalles: any[] = [{
      tipo_gravado: 'exenta',
      gravada: 0,
      exenta: 100,
      no_sujeta: 0,
      impuestos: [
        { id: 1, porcentaje: 13, codigo_mh: '20' },
        { id: 2, porcentaje: 5, codigo_mh: 'C8' },
      ],
    }];
    acumularMontosImpuestosVenta(ventaImpuestos, detalles, false, 13);
    expect(ventaImpuestos[0].monto).toBe(0);
    expect(Number(ventaImpuestos[1].monto)).toBeCloseTo(5, 2);
  });

  it('hidrata IVA y turismo del producto antes de acumular una venta editada', () => {
    const ventaImpuestos = [
      { id: 1, porcentaje: 13, codigo_mh: '20', monto: 0 },
      { id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 0 },
    ];
    const detalles: any[] = [{
      tipo_gravado: 'exenta',
      gravada: 0,
      exenta: 100,
      no_sujeta: 0,
      porcentaje_impuesto: 18,
      producto: {
        impuestos: [
          { id: 1, nombre: 'IVA', porcentaje: 13, codigo_mh: '20' },
          { id: 2, nombre: 'Turismo', porcentaje: 5, codigo_mh: 'C8' },
        ],
      },
    }];

    hidratarImpuestosProductosEnDetalles(detalles, 13);
    acumularMontosImpuestosVenta(ventaImpuestos, detalles, false, 13);

    expect(detalles[0].impuestos.length).toBe(2);
    expect(ventaImpuestos[0].monto).toBe(0);
    expect(Number(ventaImpuestos[1].monto)).toBeCloseTo(5, 2);
  });

  it('no acumula especial en línea no_sujeta', () => {
    const ventaImpuestos = [{ id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 0 }];
    const detalles = [{
      tipo_gravado: 'no_sujeta',
      gravada: 0,
      exenta: 0,
      no_sujeta: 100,
      impuestos: [{ id: 2, porcentaje: 5, codigo_mh: 'C8' }],
    }];
    acumularMontosImpuestosVenta(ventaImpuestos, detalles, false, 13);
    expect(ventaImpuestos[0].monto).toBe(0);
  });

  it('porcentajeIvaDetalle usa solo IVA configurado y respeta cobrarIva', () => {
    const detalle = {
      impuestos: [
        { id: 1, porcentaje: 13, codigo_mh: '20' },
        { id: 2, porcentaje: 5, codigo_mh: 'C8' },
      ],
    };

    expect(porcentajeIvaDetalle(detalle, 13, true)).toBe(13);
    expect(porcentajeIvaDetalle(detalle, 13, false)).toBe(0);
    expect(
      porcentajeIvaDetalle(
        { impuestos: [{ id: 2, porcentaje: 5, codigo_mh: 'C8' }] },
        13,
        true
      )
    ).toBe(0);
  });

  it('calcularMontosLineaDetalle deja exento un producto multi-impuesto sin cobrar IVA', () => {
    const detalle: any = {
      cantidad: 1,
      precio: 100,
      descuento: 0,
      tipo_gravado: 'gravada',
      impuestos: [
        { id: 1, porcentaje: 13, codigo_mh: '20' },
        { id: 2, porcentaje: 5, codigo_mh: 'C8' },
      ],
    };

    calcularMontosLineaDetalle(detalle, false, 13);

    expect(detalle.iva).toBe(0);
    expect(detalle.tipo_gravado).toBe('exenta');
    expect(detalle.exenta).toBe(100);
  });

  it('no acumula especial legacy cuando impuestos está vacío', () => {
    const ventaImpuestos = [
      { id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 0 },
    ];
    const detalles = [{
      tipo_gravado: 'exenta',
      gravada: 0,
      exenta: 100,
      no_sujeta: 0,
      porcentaje_impuesto: 5,
      impuestos: [],
    }];

    acumularMontosImpuestosVenta(ventaImpuestos, detalles, false, 13);

    expect(ventaImpuestos[0].monto).toBe(0);
  });

  it('cuando el detalle tiene id no hace fallback por porcentaje', () => {
    const ventaImpuestos = [
      { id: 99, porcentaje: 5, codigo_mh: 'C8', monto: 0 },
    ];
    const detalles = [{
      tipo_gravado: 'exenta',
      gravada: 0,
      exenta: 100,
      no_sujeta: 0,
      impuestos: [{ id: 2, porcentaje: 5, codigo_mh: 'C8' }],
    }];

    acumularMontosImpuestosVenta(ventaImpuestos, detalles, false, 13);

    expect(ventaImpuestos[0].monto).toBe(0);
  });

  it('cuando IVA tiene id no reasigna por porcentaje si el id no coincide', () => {
    const ventaImpuestos = [
      { id: 99, porcentaje: 13, codigo_mh: '20', monto: 0 },
    ];
    const detalles = [{
      tipo_gravado: 'gravada',
      gravada: 100,
      exenta: 0,
      no_sujeta: 0,
      impuestos: [{ id: 2, porcentaje: 13, codigo_mh: '20' }],
    }];

    acumularMontosImpuestosVenta(ventaImpuestos, detalles, true, 13);

    expect(ventaImpuestos[0].monto).toBe(0);
  });
});
