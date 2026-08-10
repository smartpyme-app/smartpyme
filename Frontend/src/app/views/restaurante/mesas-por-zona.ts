import { Mesa } from '@services/restaurante.service';

/** Agrupa mesas por zona para el mapa (sin getter en cada CD). */
export interface MesaZonaGrupo {
  nombre: string;
  orden: number;
  mesas: Mesa[];
}

export function buildMesasPorZona(mesas: Mesa[], filtroZona: string): MesaZonaGrupo[] {
  const grupos = new Map<string, MesaZonaGrupo>();
  for (const mesa of mesas) {
    const zona = mesa.zona_restaurante?.nombre || mesa.zona || 'Sin zona';
    const orden = mesa.zona_restaurante?.orden ?? 9999;
    if (!grupos.has(zona)) {
      grupos.set(zona, { nombre: zona, orden, mesas: [] });
    }
    grupos.get(zona)!.mesas.push(mesa);
  }
  const q = (filtroZona || '').trim().toLowerCase();
  let lista = Array.from(grupos.values());
  if (q) {
    lista = lista.filter((g) => g.nombre.toLowerCase().includes(q));
  }
  return lista.sort((a, b) => a.orden - b.orden || a.nombre.localeCompare(b.nombre));
}
