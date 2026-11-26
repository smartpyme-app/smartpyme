import { fechaHoraLocal, fechaLocal, zonaHorariaPorPais } from './fecha-local.util';

describe('fecha-local.util', () => {
  // 2026-11-26 01:30 UTC = 25/11 19:30 en El Salvador: con toISOString ya era "mañana".
  const nocheSv = new Date('2026-11-26T01:30:00Z');

  it('a las 7:30 pm de El Salvador sigue siendo el mismo día', () => {
    expect(fechaLocal(nocheSv, 'America/El_Salvador')).toBe('2026-11-25');
    expect(fechaHoraLocal(nocheSv, 'America/El_Salvador')).toBe('2026-11-25 19:30:00');
  });

  it('Panamá es UTC-5', () => {
    const t = new Date('2026-11-26T05:30:00Z'); // 23:30 SV, 00:30 Panamá
    expect(fechaLocal(t, zonaHorariaPorPais('El Salvador'))).toBe('2026-11-25');
    expect(fechaLocal(t, zonaHorariaPorPais('Panamá'))).toBe('2026-11-26');
  });

  it('país desconocido o vacío usa El Salvador', () => {
    expect(zonaHorariaPorPais(null)).toBe('America/El_Salvador');
    expect(zonaHorariaPorPais('Atlántida')).toBe('America/El_Salvador');
    expect(zonaHorariaPorPais('costa rica')).toBe('America/Costa_Rica');
  });
});
