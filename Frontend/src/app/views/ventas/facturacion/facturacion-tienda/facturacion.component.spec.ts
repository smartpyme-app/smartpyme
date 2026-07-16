import { SumPipe } from '@pipes/sum.pipe';
import { FacturacionComponent } from './facturacion.component';

describe('FacturacionComponent', () => {
  it('desactiva IVA al seleccionar un cliente exento sin borrar impuestos especiales', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.apiService = {
      isEstadoCuentaEnFacturacionHabilitado: () => false,
    };
    component.resetearPuntos = () => undefined;
    component.sumTotal = jasmine.createSpy('sumTotal');
    component.tieneFidelizacionHabilitada = false;
    component.venta = {
      cobrar_impuestos: true,
      detalles: [{ tipo_gravado: 'gravada' }],
      impuestos: [{ codigo_mh: 'C8', monto: 5 }],
    };

    component.setCliente({
      id: 1,
      tipo: 'Persona',
      nombre_completo: 'Cliente exento',
      tipo_fiscal: 'Exento',
    });

    expect(component.venta.cobrar_impuestos).toBeFalse();
    expect(component.venta.detalles[0].tipo_gravado).toBe('exenta');
    expect(component.venta.impuestos[0].monto).toBe(5);
    expect(component.sumTotal).toHaveBeenCalled();
  });

  it('no reactiva IVA desactivado manualmente para un cliente no exento', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.apiService = {
      isEstadoCuentaEnFacturacionHabilitado: () => false,
    };
    component.resetearPuntos = () => undefined;
    component.sumTotal = jasmine.createSpy('sumTotal');
    component.tieneFidelizacionHabilitada = false;
    component.venta = {
      cobrar_impuestos: false,
      detalles: [{ tipo_gravado: 'exenta', exenta_por_sin_iva: true }],
    };

    component.setCliente({
      id: 2,
      tipo: 'Persona',
      nombre_completo: 'Consumidor',
      tipo_fiscal: 'Consumidor Final',
    });

    expect(component.venta.cobrar_impuestos).toBeFalse();
    expect(component.venta.detalles[0].tipo_gravado).toBe('exenta');
  });

  it('mantiene impuestos especiales en total al desactivar IVA', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.apiService = {
      auth_user: () => ({
        empresa: {
          iva: 13,
          propina_porcentaje: 0,
          tipo_renta_productos: null,
          tipo_renta_servicios: null,
        },
      }),
    };
    component.sumPipe = new SumPipe();
    component.sincronizarRetencionGranContribuyente = () => undefined;
    component.actualizarCambioEfectivo = () => undefined;
    component.venta = {
      cobrar_impuestos: false,
      percepcion: false,
      retencion: false,
      renta: false,
      cobrar_propina: false,
      detalles: [{
        cantidad: 1,
        precio: 100,
        costo: 0,
        descuento: 0,
        tipo: 'Producto',
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
    expect(component.venta.total).toBe('105.00');
  });
});
