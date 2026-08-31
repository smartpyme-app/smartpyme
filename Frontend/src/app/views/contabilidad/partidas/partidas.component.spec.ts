import { armarOpcionesTipoCuentaReporte } from './cuenta-select.util';

describe('PartidasComponent - opciones de cuenta en reportes', () => {
  it('incluye Todas y etiqueta código — nombre', () => {
    const opts = armarOpcionesTipoCuentaReporte([{ id: 7, codigo: '1101', nombre: 'Caja' }]);
    expect(opts[0]).toEqual({ value: 'all', label: 'Todas las cuentas' });
    expect(opts[1]).toEqual({ value: 7, label: '1101 — Caja' });
  });
});
