import { GastoComponent } from './gasto.component';

// ponytail: se invocan los métodos sobre un objeto plano con `call` en vez de montar el
// componente con TestBed. Techo: solo sirve para lógica que no toca la vista ni inyecta
// servicios en el constructor; si hiciera falta probar el template, montar TestBed.
function invocar(metodo: keyof GastoComponent, ctx: any): void {
  (GastoComponent.prototype[metodo] as any).call(ctx);
}

function leerGetter(nombre: string, ctx: any): any {
  const descriptor = Object.getOwnPropertyDescriptor(GastoComponent.prototype, nombre)!;
  return descriptor.get!.call(ctx);
}

describe('GastoComponent - campos condicionales', () => {
  describe('setCredito', () => {
    it('deja el gasto pendiente y conserva la fecha de pago al activar crédito', () => {
      const ctx = { gasto: { credito: true, estado: 'Confirmado', fecha_pago: '2026-09-01' } };

      invocar('setCredito', ctx);

      expect(ctx.gasto.estado).toBe('Pendiente');
      expect(ctx.gasto.fecha_pago).toBe('2026-09-01');
    });

    it('limpia la fecha de pago al desactivar crédito', () => {
      const ctx = { gasto: { credito: false, estado: 'Pendiente', fecha_pago: '2026-09-01' } };

      invocar('setCredito', ctx);

      expect(ctx.gasto.estado).toBe('Confirmado');
      expect(ctx.gasto.fecha_pago).toBeNull();
    });
  });

  describe('requiereBanco', () => {
    it('no pide banco en Efectivo ni Wompi', () => {
      expect(leerGetter('requiereBanco', { gasto: { forma_pago: 'Efectivo' } })).toBe(false);
      expect(leerGetter('requiereBanco', { gasto: { forma_pago: 'Wompi' } })).toBe(false);
    });

    it('pide banco en transferencia, tarjeta y cheque', () => {
      expect(leerGetter('requiereBanco', { gasto: { forma_pago: 'Transferencia' } })).toBe(true);
      expect(leerGetter('requiereBanco', { gasto: { forma_pago: 'Tarjeta de Crédito' } })).toBe(true);
      expect(leerGetter('requiereBanco', { gasto: { forma_pago: 'Cheque' } })).toBe(true);
    });
  });

  describe('cambioMetodoDePago', () => {
    it('limpia el banco al volver a Efectivo', () => {
      const ctx = {
        gasto: { forma_pago: 'Efectivo', detalle_banco: 'Banco Agrícola' },
        formaspago: [],
        apiService: { isModuloBancos: () => true },
      };

      invocar('cambioMetodoDePago', ctx);

      expect(ctx.gasto.detalle_banco).toBe('');
    });

    it('precarga el banco asociado a la forma de pago seleccionada', () => {
      const ctx = {
        gasto: { forma_pago: 'Transferencia', detalle_banco: '' },
        formaspago: [{ nombre: 'Transferencia', banco: { nombre_banco: 'Banco Agrícola' } }],
        apiService: { isModuloBancos: () => true },
      };

      invocar('cambioMetodoDePago', ctx);

      expect(ctx.gasto.detalle_banco).toBe('Banco Agrícola');
    });
  });

  describe('abrirAvanzadasSiHayDatos', () => {
    it('mantiene el acordeón cerrado en un gasto sin campos avanzados', () => {
      const ctx: any = { gasto: { concepto: 'Compra', total: 100 }, mostrar_otros_impuestos: false, opAvanzadas: false };

      invocar('abrirAvanzadasSiHayDatos', ctx);

      expect(ctx.opAvanzadas).toBe(false);
    });

    it('abre el acordeón si el gasto ya usa un campo avanzado', () => {
      const ctx: any = { gasto: { nota: 'Pago parcial' }, mostrar_otros_impuestos: false, opAvanzadas: false };

      invocar('abrirAvanzadasSiHayDatos', ctx);

      expect(ctx.opAvanzadas).toBe(true);
    });

    it('ignora el IVA por defecto para no abrir el acordeón en todos los gastos', () => {
      const ctx: any = { gasto: { impuesto: true }, mostrar_otros_impuestos: false, opAvanzadas: false };

      invocar('abrirAvanzadasSiHayDatos', ctx);

      expect(ctx.opAvanzadas).toBe(false);
    });
  });

  describe('Multimoneda por país', () => {
    it('resuelve moneda funcional y símbolos para Honduras (HNL)', () => {
      const ctx: any = {
        monedaConfig: { moneda_funcional: 'HNL', monedas_documento: ['HNL', 'USD'], fuente: 'api', permitir_editar: true },
        apiService: { auth_user: () => ({ empresa: { pais: 'Honduras', cod_pais: 'HN', moneda: 'HNL' } }) },
      };

      expect(leerGetter('monedaFuncional', ctx)).toBe('HNL');
      expect(leerGetter('monedasDocumento', ctx)).toEqual(['HNL', 'USD']);
      expect(leerGetter('simboloMonedaFuncional', ctx)).toBe('L ');
    });

    it('formatea las etiquetas de moneda correctamente para HNL, CRC y USD', () => {
      const ctx: any = {
        monedaConfig: { moneda_funcional: 'HNL', monedas_documento: ['HNL', 'USD'], fuente: 'api', permitir_editar: true },
        apiService: { auth_user: () => ({ empresa: { pais: 'Honduras', cod_pais: 'HN', moneda: 'HNL' } }) },
        gasto: { exchange_rate: 26.9455 },
        tcPreview: { rate: 26.9455 },
      };

      expect((GastoComponent.prototype.etiquetaMoneda as any).call(ctx, 'HNL')).toBe('Lempiras (HNL)');
      expect((GastoComponent.prototype.etiquetaMoneda as any).call(ctx, 'CRC')).toBe('Colones (CRC)');
      expect((GastoComponent.prototype.etiquetaMoneda as any).call(ctx, 'USD')).toBe('USD (L 26.95)');
    });

    it('al seleccionar la moneda funcional fija exchange_rate en 1', () => {
      const ctx: any = {
        monedaConfig: { moneda_funcional: 'HNL', monedas_documento: ['HNL', 'USD'], fuente: 'api', permitir_editar: true },
        gasto: { currency_code: 'HNL', fecha: '2026-08-22', exchange_rate: null },
        tcPreview: { rate: null, date: null, loading: false, error: null },
      };

      invocar('onCurrencyCodeChange', ctx);

      expect(ctx.gasto.exchange_rate).toBe(1);
      expect(ctx.tcPreview.rate).toBe(1);
      expect(ctx.tcPreview.error).toBeNull();
    });
  });
});
