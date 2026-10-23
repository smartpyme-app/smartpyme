import {
  clienteValidoReceptorFacturaExportacionCr,
  ventaClienteListoParaExportacionCr,
} from './fe-cr-exportacion-cliente.util';

describe('fe-cr-exportacion-cliente.util', () => {
  it('rechaza venta de exportación sin cliente', () => {
    const r = ventaClienteListoParaExportacionCr({
      nombre_documento: 'Factura de exportación',
      id_cliente: null,
    });
    expect(r.ok).toBe(false);
  });

  it('acepta extranjero tipo 05 con identificación', () => {
    const r = clienteValidoReceptorFacturaExportacionCr({
      nombre: 'Acme',
      tipo_documento: '05',
      nit: 'US-998877',
    });
    expect(r.ok).toBe(true);
  });

  it('rechaza tipo 06 / sin identificación', () => {
    const r = clienteValidoReceptorFacturaExportacionCr({
      nombre: 'Cliente general',
      tipo_documento: '06',
      nit: '00000000000000',
    });
    expect(r.ok).toBe(false);
  });
});
