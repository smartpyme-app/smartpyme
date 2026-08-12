/**
 * Runnable check for buildMesasPorZona (Fase 4).
 * Mirrors mesas-por-zona.ts without Angular path aliases.
 * Run: node Frontend/src/app/views/restaurante/mesas-por-zona.check.mjs
 */
function buildMesasPorZona(mesas, filtroZona) {
  const grupos = new Map();
  for (const mesa of mesas) {
    const zona = mesa.zona_restaurante?.nombre || mesa.zona || 'Sin zona';
    const orden = mesa.zona_restaurante?.orden ?? 9999;
    if (!grupos.has(zona)) {
      grupos.set(zona, { nombre: zona, orden, mesas: [] });
    }
    grupos.get(zona).mesas.push(mesa);
  }
  const q = (filtroZona || '').trim().toLowerCase();
  let lista = Array.from(grupos.values());
  if (q) {
    lista = lista.filter((g) => g.nombre.toLowerCase().includes(q));
  }
  return lista.sort((a, b) => a.orden - b.orden || a.nombre.localeCompare(b.nombre));
}

const mesas = [
  { id: 1, zona: 'Terraza', zona_restaurante: { nombre: 'Terraza', orden: 2 } },
  { id: 2, zona_restaurante: { nombre: 'Interior', orden: 1 } },
  { id: 3, zona: null, zona_restaurante: null },
  { id: 4, zona_restaurante: { nombre: 'Terraza', orden: 2 } },
];

const g = buildMesasPorZona(mesas, '');
console.assert(
  JSON.stringify(g.map((x) => x.nombre)) === JSON.stringify(['Interior', 'Terraza', 'Sin zona']),
  'orden de zonas'
);
console.assert(JSON.stringify(g[0].mesas.map((m) => m.id)) === JSON.stringify([2]), 'interior');
console.assert(JSON.stringify(g[1].mesas.map((m) => m.id)) === JSON.stringify([1, 4]), 'terraza');

const f = buildMesasPorZona(mesas, 'terr');
console.assert(f.length === 1 && f[0].nombre === 'Terraza', 'filtro');

console.log('mesas-por-zona.check.mjs OK');
