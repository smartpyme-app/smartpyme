import { buildMesasPorZona } from './mesas-por-zona';

describe('buildMesasPorZona', () => {
  const mesas = [
    { id: 1, zona: 'Terraza', zona_restaurante: { nombre: 'Terraza', orden: 2 } },
    { id: 2, zona_restaurante: { nombre: 'Interior', orden: 1 } },
    { id: 3, zona: null, zona_restaurante: null },
    { id: 4, zona_restaurante: { nombre: 'Terraza', orden: 2 } },
  ];

  it('agrupa y ordena por orden de zona', () => {
    const g = buildMesasPorZona(mesas as any, '');
    expect(g.map((x) => x.nombre)).toEqual(['Interior', 'Terraza', 'Sin zona']);
    expect(g[0].mesas.map((m) => m.id)).toEqual([2]);
    expect(g[1].mesas.map((m) => m.id)).toEqual([1, 4]);
  });

  it('filtra por nombre de zona', () => {
    const g = buildMesasPorZona(mesas as any, 'terr');
    expect(g.length).toBe(1);
    expect(g[0].nombre).toBe('Terraza');
  });
});
