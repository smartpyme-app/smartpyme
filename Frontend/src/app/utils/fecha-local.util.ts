/**
 * Fecha y hora "de hoy" en la zona horaria de la empresa, no en UTC ni en la del navegador.
 *
 * Por qué existe:
 *  - `new Date().toISOString().slice(0, 10)` da la fecha en UTC: en El Salvador (UTC-6),
 *    desde las 6 pm ya es "mañana", y ventas, pedidos, filtros y reportes se iban al día siguiente.
 *  - `new Date().getDate()` usa la zona del equipo: si la computadora del cliente está en UTC
 *    u otra zona, pasa lo mismo.
 * Aquí la zona sale del país de la empresa (todas UTC-6 salvo Panamá, UTC-5).
 */

const ZONA_POR_PAIS: Record<string, string> = {
  'el salvador': 'America/El_Salvador',
  honduras: 'America/Tegucigalpa',
  guatemala: 'America/Guatemala',
  'costa rica': 'America/Costa_Rica',
  nicaragua: 'America/Managua',
  panama: 'America/Panama',
  mexico: 'America/Mexico_City',
  belice: 'America/Belize',
  belize: 'America/Belize',
};

export const ZONA_POR_DEFECTO = 'America/El_Salvador';

function normalizar(pais: string): string {
  return pais.normalize('NFD').replace(/[̀-ͯ]/g, '').trim().toLowerCase();
}

export function zonaHorariaPorPais(pais?: string | null): string {
  return (pais && ZONA_POR_PAIS[normalizar(pais)]) || ZONA_POR_DEFECTO;
}

/** País de la empresa del usuario en sesión (SP_auth_user), sin depender de servicios. */
export function zonaHorariaEmpresa(): string {
  try {
    const usuario = JSON.parse(localStorage.getItem('SP_auth_user') || 'null');
    return zonaHorariaPorPais(usuario?.empresa?.pais);
  } catch {
    return ZONA_POR_DEFECTO;
  }
}

function partes(fecha: Date, zona: string): Record<string, string> {
  const fmt = new Intl.DateTimeFormat('en-US', {
    timeZone: zona,
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hourCycle: 'h23',
  });
  const p: Record<string, string> = {};
  for (const { type, value } of fmt.formatToParts(fecha)) {
    p[type] = value;
  }
  return p;
}

/** 'YYYY-MM-DD' de `fecha` (por defecto ahora) en la zona de la empresa. */
export function fechaLocal(fecha: Date = new Date(), zona: string = zonaHorariaEmpresa()): string {
  const p = partes(fecha, zona);
  return `${p['year']}-${p['month']}-${p['day']}`;
}

/** 'YYYY-MM-DD HH:mm:ss' de `fecha` (por defecto ahora) en la zona de la empresa. */
export function fechaHoraLocal(fecha: Date = new Date(), zona: string = zonaHorariaEmpresa()): string {
  const p = partes(fecha, zona);
  return `${p['year']}-${p['month']}-${p['day']} ${p['hour']}:${p['minute']}:${p['second']}`;
}

/** Hoy en la zona de la empresa, sumando `dias` (puede ser negativo). Aritmética de calendario, sin horas. */
export function fechaLocalMasDias(dias: number, zona: string = zonaHorariaEmpresa()): string {
  const [y, m, d] = fechaLocal(new Date(), zona).split('-').map(Number);
  return new Date(Date.UTC(y, m - 1, d + dias)).toISOString().slice(0, 10);
}
