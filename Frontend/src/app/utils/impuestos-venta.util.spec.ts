import {
  esImpuestoIva,
  acumularMontosImpuestosVenta,
  acumularImpuestosVentaConCierreResidual,
  calcularMontosLineaDetalle,
  hidratarImpuestosProductosEnDetalles,
  porcentajeIvaDetalle,
  redondearMoneda,
  sumarIvaLineasSinRedondeo,
  sumarTotalEncabezadoVenta,
  resolverIvaObjetivoEncabezadoVenta,
  calcularDescuentoDesdePrecioConIva,
  sumarDescuentoConIvaEncabezadoVenta,
} from './impuestos-venta.util';

describe('impuestos-venta.util — IVA vs especiales', () => {
  it('esImpuestoIva reconoce codigo 20, tasas regionales y empresa.iva', () => {
    expect(esImpuestoIva({ codigo_mh: '20', porcentaje: 13 })).toBe(true);
    expect(esImpuestoIva({ codigo_mh: null, porcentaje: 13 })).toBe(true);
    expect(esImpuestoIva({ codigo_mh: 'C8', porcentaje: 5 })).toBe(false);
    expect(esImpuestoIva({ porcentaje: 5 })).toBe(false);
    // Honduras
    expect(esImpuestoIva({ porcentaje: 15 }, 15)).toBe(true);
    expect(esImpuestoIva({ porcentaje: 18 }, 15)).toBe(true);
    // Código libre en HN no impide reconocer 18% como IVA (solo C8 es especial)
    expect(esImpuestoIva({ codigo_mh: '18', porcentaje: 18 }, 15)).toBe(true);
    // Costa Rica (reducidas + general)
    expect(esImpuestoIva({ porcentaje: 13 }, 13)).toBe(true);
    expect(esImpuestoIva({ porcentaje: 4 }, 13)).toBe(true);
    expect(esImpuestoIva({ porcentaje: 2 }, 13)).toBe(true);
    expect(esImpuestoIva({ porcentaje: 1 }, 13)).toBe(true);
    // Tasa custom solo vía empresa.iva
    expect(esImpuestoIva({ porcentaje: 10.5 }, 10.5)).toBe(true);
    expect(esImpuestoIva({ porcentaje: 10.5 })).toBe(false);
  });

  it('recupera línea auto-exenta cuando el IVA pasa a reconocerse (HN 18%)', () => {
    const detalle: any = {
      cantidad: 1,
      precio: 106.78,
      descuento: 0,
      tipo_gravado: 'exenta',
      exenta_por_sin_iva: true,
      impuestos: [{ id: 1, porcentaje: 18 }],
    };

    calcularMontosLineaDetalle(detalle, true, 15);

    expect(detalle.tipo_gravado).toBe('gravada');
    expect(detalle.exenta).toBe(0);
    expect(Number(detalle.gravada)).toBeCloseTo(106.78, 2);
    expect(Number(detalle.iva)).toBeCloseTo(19.2204, 2);
  });

  it('respeta exenta manual aunque el producto tenga IVA', () => {
    const detalle: any = {
      cantidad: 1,
      precio: 100,
      descuento: 0,
      tipo_gravado: 'exenta',
      exenta_manual: true,
      impuestos: [{ id: 1, porcentaje: 18 }],
    };

    calcularMontosLineaDetalle(detalle, true, 15);

    expect(detalle.tipo_gravado).toBe('exenta');
    expect(detalle.exenta).toBe(100);
    expect(detalle.iva).toBe(0);
  });

  it('porcentajeIvaDetalle reconoce IVA 15/18 HN y no deja la línea en 0', () => {
    expect(
      porcentajeIvaDetalle({ impuestos: [{ id: 1, porcentaje: 15 }] }, 15, true)
    ).toBe(15);
    expect(
      porcentajeIvaDetalle({ impuestos: [{ id: 1, porcentaje: 18 }] }, 15, true)
    ).toBe(18);
    expect(
      porcentajeIvaDetalle(
        {
          impuestos: [
            { id: 1, porcentaje: 15 },
            { id: 2, porcentaje: 5, codigo_mh: 'C8' },
          ],
        },
        15,
        true
      )
    ).toBe(15);
  });

  it('calcularMontosLineaDetalle deja gravada un producto HN 15% multi-impuesto', () => {
    const detalle: any = {
      cantidad: 1,
      precio: 100,
      descuento: 0,
      tipo_gravado: 'gravada',
      impuestos: [{ id: 1, porcentaje: 15 }],
    };

    calcularMontosLineaDetalle(detalle, true, 15);

    expect(detalle.tipo_gravado).toBe('gravada');
    expect(detalle.gravada).toBe(100);
    expect(Number(detalle.iva)).toBeCloseTo(15, 2);
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

  it('cierre residual con IVA off conserva turismo y retorna IVA 0', () => {
    const ventaImpuestos = [
      { id: 1, porcentaje: 13, codigo_mh: '20', monto: 99 },
      { id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 99 },
    ];
    const detalles: any[] = [{
      tipo_gravado: 'exenta',
      gravada: 0,
      exenta: 100,
      no_sujeta: 0,
      total_iva: '100.00',
      impuestos: [
        { id: 1, porcentaje: 13, codigo_mh: '20' },
        { id: 2, porcentaje: 5, codigo_mh: 'C8' },
      ],
    }];

    const iva = acumularImpuestosVentaConCierreResidual(
      ventaImpuestos,
      detalles,
      false,
      13
    );

    expect(iva).toBe(0);
    expect(ventaImpuestos[0].monto).toBe(0);
    expect(Number(ventaImpuestos[1].monto)).toBeCloseTo(5, 2);
  });

  it('cierre residual con IVA on ajusta solo el IVA y no el turismo', () => {
    const ventaImpuestos = [
      { id: 1, porcentaje: 13, codigo_mh: '20', monto: 0 },
      { id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 0 },
    ];
    const detalles: any[] = [{
      tipo_gravado: 'gravada',
      precio: 100,
      cantidad: 1,
      descuento: 0,
      gravada: 100,
      exenta: 0,
      no_sujeta: 0,
      total_iva: '113.00',
      impuestos: [
        { id: 1, porcentaje: 13, codigo_mh: '20' },
        { id: 2, porcentaje: 5, codigo_mh: 'C8' },
      ],
    }];

    const iva = acumularImpuestosVentaConCierreResidual(
      ventaImpuestos,
      detalles,
      true,
      13
    );

    expect(iva).toBeCloseTo(13, 2);
    expect(Number(ventaImpuestos[0].monto)).toBeCloseTo(13, 2);
    expect(Number(ventaImpuestos[1].monto)).toBeCloseTo(5, 2);
  });

  it('acumula IVA sin redondear por línea (evita desface en multi-línea)', () => {
    const lineas: any[] = [
      { cantidad: 1, precio: 4.4159, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 2, precio: 4.4159, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 3, precio: 4.4159, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 3, precio: 3.9735, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 26, precio: 4.4159, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 4, precio: 4.4159, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 1, precio: 3.9735, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 1, precio: 3.9735, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 26, precio: 3.9735, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 10, precio: 3.9735, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 2, precio: 4.4159, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
    ];
    lineas.forEach((d) => calcularMontosLineaDetalle(d, true, 13));

    const ivaRedondeadoPorLinea = redondearMoneda(
      lineas.reduce(
        (acc, d) => acc + redondearMoneda(parseFloat(String(d.gravada)) * 0.13),
        0
      )
    );
    const ivaCorrecto = redondearMoneda(sumarIvaLineasSinRedondeo(lineas, true, 13));

    expect(ivaRedondeadoPorLinea).toBe(43.01);
    expect(ivaCorrecto).toBe(42.99);

    const ventaImpuestos = [{ id: 1, porcentaje: 13, codigo_mh: '20', nombre: 'IVA', monto: 0 }];
    acumularMontosImpuestosVenta(ventaImpuestos, lineas, true, 13);

    expect(Number(ventaImpuestos[0].monto)).toBe(ivaCorrecto);
  });

  it('IVA de cabecera cuadra con subtotal × tasa (196.07 × 13% = 25.49)', () => {
    expect(redondearMoneda(196.07 * 0.13)).toBe(25.49);

    const lineas: any[] = [
      { cantidad: 1, precio: 100, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 1, precio: 96.07, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
    ];
    lineas.forEach((d) => calcularMontosLineaDetalle(d, true, 13));

    const subtotal = lineas.reduce((acc, d) => acc + Number(d.gravada), 0);
    expect(redondearMoneda(subtotal)).toBe(196.07);

    const ventaImpuestos = [{ id: 1, porcentaje: 13, codigo_mh: '20', nombre: 'IVA', monto: 0 }];
    const iva = acumularImpuestosVentaConCierreResidual(ventaImpuestos, lineas, true, 13);

    expect(iva).toBe(25.49);
    expect(Number(ventaImpuestos[0].monto)).toBe(25.49);
  });

  it('total cuadra con subtotal + IVA + cuenta a terceros (312.21)', () => {
    const lineas: any[] = [
      { cantidad: 1, precio: 100, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
      { cantidad: 1, precio: 96.07, descuento: 0, tipo_gravado: 'gravada', impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }] },
    ];
    lineas.forEach((d) => calcularMontosLineaDetalle(d, true, 13));

    const ventaImpuestos = [{ id: 1, porcentaje: 13, codigo_mh: '20', nombre: 'IVA', monto: 0 }];
    acumularImpuestosVentaConCierreResidual(ventaImpuestos, lineas, true, 13);

    const total = sumarTotalEncabezadoVenta(lineas, ventaImpuestos, {
      empresaIva: 13,
      cuentaTerceros: 90.65,
    });

    expect(total).toBe(312.21);
  });

  it('v2 precio con IVA 34.99 cierra total (30.96 + 4.03)', () => {
    const precioSinIva = 34.99 / 1.13;
    const detalle: any = {
      cantidad: 1,
      precio: precioSinIva,
      precio_iva: '34.9900',
      descuento: 0,
      tipo_gravado: 'gravada',
      impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }],
    };
    calcularMontosLineaDetalle(detalle, true, 13, { preservePrecioIva: true });

    expect(Number(detalle.gravada)).toBe(30.96);
    expect(Number(detalle.total_iva)).toBe(34.99);

    const ventaImpuestos = [{ id: 1, porcentaje: 13, codigo_mh: '20', nombre: 'IVA', monto: 0 }];
    const iva = acumularImpuestosVentaConCierreResidual(
      ventaImpuestos,
      [detalle],
      true,
      13
    );
    const total = sumarTotalEncabezadoVenta([detalle], ventaImpuestos, {
      empresaIva: 13,
    });

    expect(iva).toBe(4.03);
    expect(total).toBe(34.99);
    expect(resolverIvaObjetivoEncabezadoVenta([detalle], true, 13)).toBe(4.03);
  });

  it('v2: total incluye IVA residual aunque venta.impuestos esté vacío (pedido/race)', () => {
    const precioSinIva = 42.5 / 1.13;
    const detalle: any = {
      cantidad: 1,
      precio: precioSinIva,
      precio_iva: '42.5000',
      descuento: 0,
      tipo_gravado: 'gravada',
      porcentaje_impuesto: 13,
    };
    calcularMontosLineaDetalle(detalle, true, 13, { preservePrecioIva: true });

    const iva = acumularImpuestosVentaConCierreResidual([], [detalle], true, 13);
    const total = sumarTotalEncabezadoVenta([detalle], [], {
      empresaIva: 13,
      cobrarImpuestos: true,
    });

    expect(Number(detalle.gravada)).toBeCloseTo(37.61, 2);
    expect(iva).toBeCloseTo(4.89, 2);
    expect(total).toBe(42.5);
  });

  it('v2: descuento % se calcula sobre precio con IVA (38.50 × 10%)', () => {
    const r = calcularDescuentoDesdePrecioConIva({
      cantidad: 1,
      precioConIva: 38.5,
      pctIva: 13,
      descuentoPorcentaje: 10,
    });
    expect(r.descuentoConIva).toBeCloseTo(3.85, 2);
    expect(r.descuentoSinIva).toBeCloseTo(3.4071, 4);

    const detalle: any = {
      cantidad: 1,
      precio: 38.5 / 1.13,
      precio_iva: '38.5000',
      descuento: r.descuentoSinIva,
      descuento_con_iva: r.descuentoConIva,
      tipo_gravado: 'gravada',
      porcentaje_impuesto: 13,
    };
    calcularMontosLineaDetalle(detalle, true, 13, { preservePrecioIva: true });
    expect(Number(detalle.total_iva)).toBe(34.65);
    expect(sumarDescuentoConIvaEncabezadoVenta([detalle], 13, true)).toBeCloseTo(3.85, 2);
  });

  it('v2: descuento en dinero se calcula sobre precio con IVA ($10 de $38.50)', () => {
    const r = calcularDescuentoDesdePrecioConIva({
      cantidad: 1,
      precioConIva: 38.5,
      pctIva: 13,
      descuentoMontoConIva: 10,
    });
    expect(r.descuentoConIva).toBe(10);
    expect(r.descuentoSinIva).toBeCloseTo(8.8496, 4);

    const detalle: any = {
      cantidad: 1,
      precio: 38.5 / 1.13,
      precio_iva: '38.5000',
      descuento: r.descuentoSinIva,
      descuento_con_iva: r.descuentoConIva,
      tipo_gravado: 'gravada',
      porcentaje_impuesto: 13,
    };
    calcularMontosLineaDetalle(detalle, true, 13, { preservePrecioIva: true });
    expect(Number(detalle.total_iva)).toBe(28.5);
    expect(sumarDescuentoConIvaEncabezadoVenta([detalle], 13, true)).toBe(10);
  });

  it('v2 precio con IVA 12.99 cierra total (11.50 + 1.49)', () => {
    const precioSinIva = 12.99 / 1.13;
    const detalle: any = {
      cantidad: 1,
      precio: precioSinIva,
      precio_iva: '12.9900',
      descuento: 0,
      tipo_gravado: 'gravada',
      impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }],
    };
    calcularMontosLineaDetalle(detalle, true, 13, { preservePrecioIva: true });

    expect(Number(detalle.gravada)).toBe(11.5);
    expect(Number(detalle.total_iva)).toBe(12.99);

    const ventaImpuestos = [{ id: 1, porcentaje: 13, codigo_mh: '20', nombre: 'IVA', monto: 0 }];
    const iva = acumularImpuestosVentaConCierreResidual(
      ventaImpuestos,
      [detalle],
      true,
      13
    );
    const total = sumarTotalEncabezadoVenta([detalle], ventaImpuestos, {
      empresaIva: 13,
    });

    expect(iva).toBe(1.49);
    expect(total).toBe(12.99);
    expect(resolverIvaObjetivoEncabezadoVenta([detalle], true, 13)).toBe(1.49);
  });

  it('desglosa IVA por tasa en venta.impuestos', () => {
    const ventaImpuestos = [
      { id: 1, porcentaje: 13, codigo_mh: '20', nombre: 'IVA', monto: 0 },
      { id: 2, porcentaje: 0, codigo_mh: '20', nombre: 'IVA 0%', monto: 0 },
    ];
    const detalles: any[] = [
      {
        cantidad: 2,
        precio: 10,
        descuento: 0,
        tipo_gravado: 'gravada',
        impuestos: [{ id: 1, porcentaje: 13, codigo_mh: '20' }],
      },
      {
        cantidad: 1,
        precio: 50,
        descuento: 0,
        tipo_gravado: 'exenta',
        impuestos: [{ id: 2, porcentaje: 0, codigo_mh: '20' }],
      },
    ];
    detalles.forEach((d) => calcularMontosLineaDetalle(d, true, 13));
    acumularMontosImpuestosVenta(ventaImpuestos, detalles, true, 13);

    expect(Number(ventaImpuestos[0].monto)).toBe(2.6);
    expect(Number(ventaImpuestos[1].monto)).toBe(0);
  });
});
