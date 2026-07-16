import { SumPipe } from '@pipes/sum.pipe';
import { FacturacionConsignaComponent } from './facturacion-consigna.component';

describe('FacturacionConsignaComponent', () => {
  it('mantiene impuestos especiales en total al desactivar IVA', () => {
    const component: any = Object.create(FacturacionConsignaComponent.prototype);
    component.apiService = {
      auth_user: () => ({ empresa: { iva: 13 } }),
    };
    component.sumPipe = new SumPipe();
    component.venta = {
      cobrar_impuestos: false,
      percepcion: false,
      retencion: false,
      detalles: [{
        total: 100,
        descuento: 0,
        total_costo: 0,
        gravada: 0,
        exenta: 100,
        no_sujeta: 0,
        tipo_gravado: 'exenta',
        impuestos: [
          { id: 1, porcentaje: 13, codigo_mh: '20' },
          { id: 2, porcentaje: 5, codigo_mh: 'C8' },
        ],
      }],
      impuestos: [
        { id: 1, porcentaje: 13, codigo_mh: '20', monto: 13 },
        { id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 5 },
      ],
    };

    component.sumTotal();

    expect(component.venta.iva).toBe('0.0000');
    expect(component.venta.impuestos[1].monto).toBe(5);
    expect(component.venta.total).toBe('105.0000');
  });
});
