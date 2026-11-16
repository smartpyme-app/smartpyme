import { mapCabysApiResponseToOptions } from './cabys-hacienda.mapper';

describe('cabys-hacienda.mapper', () => {
  it('mapea la respuesta de búsqueda por código (array en la raíz)', () => {
    const r = mapCabysApiResponseToOptions([
      {
        categorias: ['Construcciones y servicios de construcción'],
        codigo: '5461100000000',
        descripcion: 'Servicios de instalación de cables y otros dispositivos eléctricos',
        impuesto: 13,
      },
    ]);
    expect(r.length).toBe(1);
    expect(r[0].codigo).toBe('5461100000000');
    expect(r[0].impuestoTarifa).toBe(13);
  });

  it('mapea la respuesta de búsqueda por texto (lista cabys)', () => {
    const r = mapCabysApiResponseToOptions({
      total: 1,
      cantidad: 1,
      cabys: [{ codigo: '8715200009900', descripcion: 'Servicios de mantenimiento', impuesto: 13 }],
    });
    expect(r.map((o) => o.codigo)).toEqual(['8715200009900']);
  });
});
