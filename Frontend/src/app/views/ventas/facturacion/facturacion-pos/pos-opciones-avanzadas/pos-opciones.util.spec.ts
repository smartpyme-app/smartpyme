import { contarOpcionesAvanzadasActivas } from './pos-opciones.util';

describe('contarOpcionesAvanzadasActivas', () => {
  it('cuenta retención activa', () => {
    expect(contarOpcionesAvanzadasActivas({ retencion: true, cobrar_propina: false }, false)).toBe(1);
  });

  it('suma varios flags', () => {
    expect(
      contarOpcionesAvanzadasActivas(
        { retencion: true, recurrente: true, id_canal: 3 },
        true
      )
    ).toBe(4);
  });
});
