import { armarOpcionesTipoCuentaReporte, cuentaCoincideBusqueda } from './cuenta-select.util';

describe('cuentaCoincideBusqueda', () => {
  const cuenta = { id: 1, codigo: '1101.01', nombre: 'Caja general' };

  it('encuentra por nombre', () => {
    expect(cuentaCoincideBusqueda('caja', cuenta)).toBe(true);
    expect(cuentaCoincideBusqueda('banco', cuenta)).toBe(false);
  });

  it('encuentra por número de cuenta (código)', () => {
    expect(cuentaCoincideBusqueda('1101', cuenta)).toBe(true);
    expect(cuentaCoincideBusqueda('1101.01', cuenta)).toBe(true);
    expect(cuentaCoincideBusqueda('9999', cuenta)).toBe(false);
  });
});

describe('armarOpcionesTipoCuentaReporte', () => {
  it('incluye Todas y etiqueta código — nombre', () => {
    const opts = armarOpcionesTipoCuentaReporte([{ id: 7, codigo: '1101', nombre: 'Caja' }]);
    expect(opts[0]).toEqual({ value: 'all', label: 'Todas las cuentas' });
    expect(opts[1]).toEqual({ value: 7, label: '1101 — Caja' });
  });

  it('la etiqueta permite buscar por código', () => {
    const opts = armarOpcionesTipoCuentaReporte([{ id: 3, codigo: '2102', nombre: 'Proveedores' }]);
    expect(opts[1].label.toLowerCase().includes('2102')).toBe(true);
  });
});
