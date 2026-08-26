import { DteDetailComponent } from './dte-detail.component';

// ponytail: métodos sobre objeto plano con `call` (mismo techo que gasto.component.spec.ts)
function invocar(metodo: string, ctx: any, ...args: any[]): any {
  return (DteDetailComponent.prototype as any)[metodo].call(ctx, ...args);
}

function leerGetter(nombre: string, ctx: any): any {
  const descriptor = Object.getOwnPropertyDescriptor(DteDetailComponent.prototype, nombre)!;
  return descriptor.get!.call(ctx);
}

describe('DteDetailComponent - proceso compra/gasto', () => {
  describe('requiereBanco', () => {
    it('no pide banco en Efectivo ni Wompi', () => {
      expect(leerGetter('requiereBanco', { formaPago: 'Efectivo' })).toBe(false);
      expect(leerGetter('requiereBanco', { formaPago: 'Wompi' })).toBe(false);
    });

    it('pide banco en transferencia', () => {
      expect(leerGetter('requiereBanco', { formaPago: 'Transferencia' })).toBe(true);
    });
  });

  describe('setCredito', () => {
    it('asigna fecha de pago al activar crédito', () => {
      const ctx: any = {
        credito: true,
        fechaPago: null,
        apiService: { date: () => '2026-08-26' },
      };
      invocar('setCredito', ctx);
      expect(ctx.fechaPago).toBe('2026-08-26');
    });

    it('limpia fecha de pago al desactivar crédito', () => {
      const ctx = {
        credito: false,
        fechaPago: '2026-09-01',
        apiService: { date: () => '2026-08-26' },
      };
      invocar('setCredito', ctx);
      expect(ctx.fechaPago).toBeNull();
    });
  });

  describe('compraListaParaProcesar', () => {
    it('en gasto no exige productos', () => {
      expect(leerGetter('compraListaParaProcesar', {
        destinoSeleccionado: 'gasto',
        lineItems: [{ id_producto: null, cantidad: 1 }],
      })).toBe(true);
    });

    it('en compra exige producto y cantidad en cada línea', () => {
      expect(leerGetter('compraListaParaProcesar', {
        destinoSeleccionado: 'compra',
        lineItems: [{ id_producto: 1, cantidad: 2 }],
      })).toBe(true);
      expect(leerGetter('compraListaParaProcesar', {
        destinoSeleccionado: 'compra',
        lineItems: [{ id_producto: null, cantidad: 2 }],
      })).toBe(false);
    });
  });

  describe('buildProcesarPayload', () => {
    it('incluye pago, retaceo y mappings de compra', () => {
      const ctx = {
        destinoSeleccionado: 'compra',
        idProyecto: null,
        idCategoria: null,
        tipoGasto: '',
        tipoCostoGasto: '',
        formaPago: 'Transferencia',
        credito: true,
        fechaPago: '2026-09-15',
        detalleBanco: 'Banco Agrícola',
        esRetaceo: true,
        lineItems: [{ id_producto: 12, cantidad: 3 }],
      };

      const payload = invocar('buildProcesarPayload', ctx);

      expect(payload.forma_pago).toBe('Transferencia');
      expect(payload.credito).toBe(true);
      expect(payload.fecha_pago).toBe('2026-09-15');
      expect(payload.detalle_banco).toBe('Banco Agrícola');
      expect(payload.es_retaceo).toBe(true);
      expect(payload.line_mappings).toEqual([{ index: 0, id_producto: 12, cantidad: 3 }]);
    });

    it('en gasto no envía mappings', () => {
      const ctx = {
        destinoSeleccionado: 'gasto',
        idProyecto: null,
        idCategoria: 4,
        tipoGasto: 'Combustible',
        tipoCostoGasto: '',
        formaPago: 'Efectivo',
        credito: false,
        fechaPago: null,
        detalleBanco: null,
        esRetaceo: false,
        lineItems: [{ id_producto: null, cantidad: 1 }],
      };

      const payload = invocar('buildProcesarPayload', ctx);
      expect(payload.line_mappings).toEqual([]);
      expect(payload.id_categoria).toBe(4);
    });
  });
});
