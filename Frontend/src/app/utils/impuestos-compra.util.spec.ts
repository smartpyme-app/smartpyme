import {
  aplicarIvaCompra,
  debeUsarIvaEmpresaProducto,
  resolverPorcentajeImpuestoCompra,
  snapshotPorcentajeImpuestoProducto,
} from './impuestos-compra.util';

describe('impuestos-compra.util', () => {
  describe('resolverPorcentajeImpuestoCompra', () => {
    it('usa el IVA de la empresa si el producto no tiene tasa (null/vacío/0)', () => {
      expect(resolverPorcentajeImpuestoCompra(null, 13, true, 'El Salvador')).toBe(13);
      expect(resolverPorcentajeImpuestoCompra('', 13, true, 'El Salvador')).toBe(13);
      expect(resolverPorcentajeImpuestoCompra(0, 13, true, 'El Salvador')).toBe(13);
      expect(resolverPorcentajeImpuestoCompra('0', 13, true)).toBe(13);
    });

    it('respeta la tasa del producto cuando es mayor a 0', () => {
      expect(resolverPorcentajeImpuestoCompra(13, 13, true, 'El Salvador')).toBe(13);
      expect(resolverPorcentajeImpuestoCompra(15, 13, true, 'El Salvador')).toBe(15);
    });

    it('devuelve 0 si Con IVA está apagado, aunque el producto o la empresa tengan tasa', () => {
      expect(resolverPorcentajeImpuestoCompra(13, 13, false, 'El Salvador')).toBe(0);
      expect(resolverPorcentajeImpuestoCompra(0, 13, false, 'El Salvador')).toBe(0);
    });

    it('HN: 0 explícito se trata como exento', () => {
      expect(resolverPorcentajeImpuestoCompra(0, 15, true, 'Honduras')).toBe(0);
      expect(resolverPorcentajeImpuestoCompra(null, 15, true, 'Honduras')).toBe(15);
    });
  });

  describe('snapshotPorcentajeImpuestoProducto', () => {
    it('guarda el IVA de la empresa cuando el producto no tiene tasa', () => {
      expect(snapshotPorcentajeImpuestoProducto(0, 13, 'El Salvador')).toBe(13);
      expect(snapshotPorcentajeImpuestoProducto(null, 13, 'El Salvador')).toBe(13);
    });

    it('HN: deja 0 si el producto está explícitamente exento', () => {
      expect(snapshotPorcentajeImpuestoProducto(0, 15, 'Honduras')).toBe(0);
    });
  });

  describe('debeUsarIvaEmpresaProducto', () => {
    it('preselecciona IVA de empresa si el producto no tiene impuesto', () => {
      expect(debeUsarIvaEmpresaProducto(null, 'El Salvador')).toBe(true);
      expect(debeUsarIvaEmpresaProducto(0, 'El Salvador')).toBe(true);
      expect(debeUsarIvaEmpresaProducto(13, 'El Salvador')).toBe(false);
    });

    it('HN: no preselecciona si el producto está en 0 (exento)', () => {
      expect(debeUsarIvaEmpresaProducto(0, 'Honduras')).toBe(false);
      expect(debeUsarIvaEmpresaProducto(null, 'Honduras')).toBe(true);
    });
  });

  describe('aplicarIvaCompra', () => {
    const impuestos13 = [{ id: 1, nombre: 'IVA', porcentaje: 13, monto: 0 }];

    it('suma IVA de cabecera con Con IVA marcado aunque el producto tenga porcentaje 0', () => {
      const compra: any = {
        cobrar_impuestos: true,
        detalles: [
          { total: 100, porcentaje_impuesto: 0, iva: 0 },
        ],
        impuestos: impuestos13.map((i) => ({ ...i })),
      };

      aplicarIvaCompra(compra, 13, 'El Salvador');

      expect(compra.detalles[0].porcentaje_impuesto).toBe(13);
      expect(compra.detalles[0].iva).toBeCloseTo(13, 2);
      expect(compra.impuestos[0].monto).toBeCloseTo(13, 2);
      expect(Number(compra.iva)).toBeCloseTo(13, 2);
    });

    it('no suma IVA si Con IVA está apagado', () => {
      const compra: any = {
        cobrar_impuestos: false,
        detalles: [
          { total: 100, porcentaje_impuesto: 13, iva: 13 },
        ],
        impuestos: impuestos13.map((i) => ({ ...i })),
      };

      aplicarIvaCompra(compra, 13, 'El Salvador');

      expect(compra.detalles[0].iva).toBe(0);
      expect(compra.impuestos[0].monto).toBe(0);
      expect(Number(compra.iva)).toBe(0);
    });

    it('recalcula IVA de línea antes de acumular (no se queda con iva stale en 0)', () => {
      const compra: any = {
        cobrar_impuestos: true,
        detalles: [
          { total: 200, porcentaje_impuesto: '', iva: 0 },
        ],
        impuestos: impuestos13.map((i) => ({ ...i })),
      };

      aplicarIvaCompra(compra, 13, 'El Salvador');

      expect(compra.detalles[0].iva).toBeCloseTo(26, 2);
      expect(compra.impuestos[0].monto).toBeCloseTo(26, 2);
      expect(Number(compra.iva)).toBeCloseTo(26, 2);
    });

    it('si no hay catálogo de impuestos, el IVA de cabecera es la suma de las líneas', () => {
      const compra: any = {
        cobrar_impuestos: true,
        detalles: [
          { total: 100, porcentaje_impuesto: 0, iva: 0 },
        ],
        impuestos: [],
      };

      aplicarIvaCompra(compra, 13, 'El Salvador');

      expect(compra.detalles[0].iva).toBeCloseTo(13, 2);
      expect(Number(compra.iva)).toBeCloseTo(13, 2);
    });
  });
});
