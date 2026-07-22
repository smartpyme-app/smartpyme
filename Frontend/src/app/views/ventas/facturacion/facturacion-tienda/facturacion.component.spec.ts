import { SumPipe } from '@pipes/sum.pipe';
import Swal from 'sweetalert2';
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

  it('al cambiar bodega de la misma sucursal recarga documentos sin vaciarlos', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.bodegas = [
      { id: 1, id_sucursal: 10, nombre: 'A' },
      { id: 2, id_sucursal: 10, nombre: 'B' },
    ];
    component.venta = { id_bodega: '2', id_sucursal: 10, cotizacion: 0 };
    component.cargarDocumentos = jasmine.createSpy('cargarDocumentos');

    component.setBodega();

    expect(component.venta.id_bodega).toBe(2);
    expect(component.venta.id_sucursal).toBe(10);
    expect(component.cargarDocumentos).toHaveBeenCalled();
  });

  it('al cambiar bodega de otra sucursal recarga documentos por sucursal', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.bodegas = [
      { id: 1, id_sucursal: 10, nombre: 'A' },
      { id: 2, id_sucursal: 20, nombre: 'B' },
    ];
    component.venta = { id_bodega: '2', id_sucursal: 10, cotizacion: 0 };
    component.cargarDocumentos = jasmine.createSpy('cargarDocumentos');

    component.setBodega();

    expect(component.venta.id_sucursal).toBe(20);
    expect(component.cargarDocumentos).toHaveBeenCalled();
  });

  it('no falla si la bodega seleccionada no existe en la lista', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.bodegas = [{ id: 1, id_sucursal: 10, nombre: 'A' }];
    component.venta = { id_bodega: 999, id_sucursal: 10 };
    component.cargarDocumentos = jasmine.createSpy('cargarDocumentos');

    expect(() => component.setBodega()).not.toThrow();
    expect(component.venta.id_sucursal).toBe(10);
    expect(component.cargarDocumentos).not.toHaveBeenCalled();
  });

  it('filtra documentos por la sucursal de la bodega seleccionada', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.documentosLoadSeq = 0;
    component.nombresDocumentosVentaNormales = [
      'Factura',
      'Crédito fiscal',
      'Ticket',
    ];
    component.bodegas = [{ id: 5, id_sucursal: 10, nombre: 'Principal' }];
    component.venta = { id_bodega: 5, id_sucursal: 99, cotizacion: 0 };
    component.documentosSucursal = [];
    component.documentos = [];
    const docsApi = [
      { id: 1, nombre: 'Factura', id_sucursal: 10, predeterminado: 1, correlativo: 1 },
      { id: 2, nombre: 'Crédito fiscal', id_sucursal: 10, predeterminado: 0, correlativo: 2 },
      { id: 3, nombre: 'Factura', id_sucursal: 20, predeterminado: 0, correlativo: 3 },
      { id: 4, nombre: 'Cotización', id_sucursal: 10, predeterminado: 0, correlativo: 4 },
    ];
    component.apiService = {
      getAll: jasmine.createSpy('getAll').and.callFake(() => ({
        subscribe: (ok: any) => ok(docsApi),
      })),
    };
    component.alertService = { error: jasmine.createSpy('error') };

    component.cargarDocumentos();

    expect(component.documentos.map((d: any) => d.nombre)).toEqual([
      'Factura',
      'Crédito fiscal',
    ]);
    expect(component.venta.id_documento).toBe(1);
  });

  it('bloquea facturar si id_documento está vacío', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.venta = {
      id_documento: null,
      detalles: [{ cantidad: 1, total: 10, descripcion: 'Prod' }],
    };
    spyOn(Swal, 'fire');

    expect(component.tieneDetallesInvalidosParaFacturar()).toBeTrue();
    expect(Swal.fire).toHaveBeenCalled();
  });

  it('al reiniciar documento tras cargar venta base limpia y recarga documentos', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.venta = { id_documento: 99, correlativo: 5 };
    component.cargarDocumentos = jasmine.createSpy('cargarDocumentos');

    component.reiniciarDocumentoTrasCargarVentaBase();

    expect(component.venta.id_documento).toBeNull();
    expect(component.venta.correlativo).toBeNull();
    expect(component.cargarDocumentos).toHaveBeenCalled();
  });

  it('no vuelve a facturar si saving o emiting ya están activos', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.saving = true;
    component.emiting = false;
    component.mensajeErrorBanco = '';
    component.requiereBanco = () => false;
    component.tieneDetallesInvalidosParaFacturar = () => false;
    component.onSubmit = jasmine.createSpy('onSubmit');
    spyOn(window, 'confirm').and.returnValue(true);

    component.onFacturar();

    expect(component.onSubmit).not.toHaveBeenCalled();
  });

  it('no dispara un segundo store mientras saving es true', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.saving = true;
    component.emiting = false;
    component.apiService = { store: jasmine.createSpy('store') };

    component.onSubmit();

    expect(component.apiService.store).not.toHaveBeenCalled();
  });

  it('en error de red ambiguo no rehabilita saving si el usuario cancela', () => {
    const component: any = Object.create(FacturacionComponent.prototype);
    component.saving = false;
    component.emiting = false;
    component.duplicarventa = false;
    component.pedidoCanalId = null;
    component.venta = { monto_pago: 10, efectivo: 10, total: 10, detalles: [] };
    component.apiService = {
      auth_user: () => ({ tipo: 'Admin', empresa: {} }),
      store: jasmine.createSpy('store').and.callFake(() => ({
        subscribe: (_ok: any, err: any) => err({ status: 0 }),
      })),
    };
    component.alertService = { error: jasmine.createSpy('error') };
    spyOn(window, 'confirm').and.returnValue(false);

    component.onSubmit();

    expect(component.saving).toBeTrue();
  });
});
