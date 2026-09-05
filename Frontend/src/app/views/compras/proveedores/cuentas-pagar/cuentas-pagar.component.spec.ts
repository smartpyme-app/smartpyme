import { of, throwError } from 'rxjs';
import { CuentasPagarComponent } from './cuentas-pagar.component';

function invocar(metodo: keyof CuentasPagarComponent, ctx: any): void {
  (CuentasPagarComponent.prototype[metodo] as any).call(ctx);
}

describe('CuentasPagarComponent - Banco Agrícola', () => {
  it('no pide el archivo si falta la fecha de pago', () => {
    const ctx = {
      fechaPagoAgricola: '',
      downloading: false,
      apiService: { getAll: jasmine.createSpy('getAll') },
    };

    invocar('descargarArchivoBancoAgricola', ctx);

    expect(ctx.apiService.getAll).not.toHaveBeenCalled();
    expect(ctx.downloading).toBe(false);
  });

  it('descarga el archivo y avisa cuando hay omitidos', () => {
    const ctx: any = {
      fechaPagoAgricola: '2026-09-05',
      formatoAgricola: 'csv',
      downloading: false,
      filtros: {},
      modalRef: { hide: jasmine.createSpy('hide') },
      descargarTexto: jasmine.createSpy('descargarTexto'),
      apiService: {
        getAll: jasmine.createSpy('getAll').and.returnValue(of({
          incluidos: 1,
          omitidos: [{ proveedor: 'Davivienda SA', referencia: 'P002', motivo: 'El banco no es Banco Agrícola' }],
          contenido: '001300995009,Proveedor 1,,2477.25,P001-1,Pago de proveedor 05-09-2026,jperez@gmail.com\r\n',
          filename: 'pagos-banco-agricola-2026-09-05.csv',
          mime: 'text/csv; charset=UTF-8',
        })),
      },
      alertService: {
        warning: jasmine.createSpy('warning'),
        success: jasmine.createSpy('success'),
        error: jasmine.createSpy('error'),
      },
    };

    invocar('descargarArchivoBancoAgricola', ctx);

    expect(ctx.apiService.getAll).toHaveBeenCalledWith('cuentas-pagar/banco-agricola', {
      fecha_pago: '2026-09-05',
      formato: 'csv',
    });
    expect(ctx.descargarTexto).toHaveBeenCalled();
    expect(ctx.alertService.warning).toHaveBeenCalled();
    expect(ctx.alertService.success).not.toHaveBeenCalled();
    expect(ctx.modalRef.hide).toHaveBeenCalled();
    expect(ctx.downloading).toBe(false);
  });

  it('muestra el error si no hay filas válidas', () => {
    const ctx: any = {
      fechaPagoAgricola: '2026-09-05',
      formatoAgricola: 'txt',
      downloading: false,
      filtros: { id_proveedor: 9 },
      apiService: {
        getAll: jasmine.createSpy('getAll').and.returnValue(throwError(() => ({ status: 422, error: { error: 'No hay pagos válidos' } }))),
      },
      alertService: { error: jasmine.createSpy('error') },
    };

    invocar('descargarArchivoBancoAgricola', ctx);

    expect(ctx.alertService.error).toHaveBeenCalled();
    expect(ctx.downloading).toBe(false);
  });
});
