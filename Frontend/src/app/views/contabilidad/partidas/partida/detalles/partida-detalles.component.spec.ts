import { cuentaCoincideBusqueda } from '../../cuenta-select.util';

describe('PartidaDetallesComponent - búsqueda de cuenta', () => {
  const cuenta = { id: 1, codigo: '1101.01', nombre: 'Caja general' };

  it('encuentra por nombre y por número de cuenta', () => {
    expect(cuentaCoincideBusqueda('caja', cuenta)).toBe(true);
    expect(cuentaCoincideBusqueda('1101', cuenta)).toBe(true);
    expect(cuentaCoincideBusqueda('9999', cuenta)).toBe(false);
  });
});
