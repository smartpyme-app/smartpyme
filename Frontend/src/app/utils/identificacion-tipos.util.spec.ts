import {
  configIdentificacionTipos,
  defaultTipoIdentificacion,
  labelCampoIdentificacion,
  labelTipoIdentificacion,
  mascaraIdentificacion,
  plantillaIdentificacionTipos,
  tiposPorUso,
} from './identificacion-tipos.util';

describe('identificacion-tipos.util', () => {
  it('plantilla CR excluye 06 del emisor', () => {
    const cr = plantillaIdentificacionTipos('CR');
    expect(cr.default_persona).toBe('01');
    expect(tiposPorUso(cr, 'emisor').map((t) => t.codigo)).not.toContain('06');
    expect(tiposPorUso(cr, 'receptor').map((t) => t.codigo)).toContain('06');
  });

  it('defaults por tipo de cliente', () => {
    const sv = plantillaIdentificacionTipos('SV');
    expect(defaultTipoIdentificacion(sv, 'Persona')).toBe('13');
    expect(defaultTipoIdentificacion(sv, 'Empresa')).toBe('36');
    expect(defaultTipoIdentificacion(sv, 'Extranjero')).toBe('03');
    expect(defaultTipoIdentificacion(plantillaIdentificacionTipos('HN'), 'Empresa')).toBe('rtn');
  });

  it('resuelve país desde empresa', () => {
    expect(configIdentificacionTipos({ pais: 'Costa Rica' }).default_empresa).toBe('02');
    expect(configIdentificacionTipos({ pais: 'Honduras', cod_pais: 'HN' }).default_persona).toBe('dni');
  });

  it('label vacío si no hay tipo (FE infiere)', () => {
    const sv = plantillaIdentificacionTipos('SV');
    expect(labelTipoIdentificacion(sv, '')).toBe('');
    expect(labelTipoIdentificacion(sv, '13')).toBe('DUI');
  });

  it('label del campo sigue el tipo; si no hay, Identificación', () => {
    const sv = plantillaIdentificacionTipos('SV');
    expect(labelCampoIdentificacion(sv, '13')).toBe('DUI');
    expect(labelCampoIdentificacion(sv, '36')).toBe('NIT');
    expect(labelCampoIdentificacion(sv, '')).toBe('Identificación');
    expect(labelCampoIdentificacion(plantillaIdentificacionTipos('CR'), '01')).toBe('Cédula física');
  });

  it('máscara DUI/NIT según tipo; resto sin máscara', () => {
    const sv = { pais: 'El Salvador', validador_dui: '00000000-0', validador_nit: '0000000-000-000-0' };
    expect(mascaraIdentificacion('13', sv)).toBe('00000000-0');
    expect(mascaraIdentificacion('36', sv)).toBe('0000000-000-000-0');
    expect(mascaraIdentificacion('03', sv)).toBe('');
    expect(mascaraIdentificacion('02', sv)).toBe('');
    expect(mascaraIdentificacion('', sv)).toBe('');

    const cr = { pais: 'Costa Rica', validador_dui: '0-0000-0000', validador_nit: '0-0000-0000' };
    expect(mascaraIdentificacion('01', cr)).toBe('0-0000-0000');
    expect(mascaraIdentificacion('02', cr)).toBe('0-0000-0000');
    expect(mascaraIdentificacion('03', cr)).toBe('');

    const hn = { pais: 'Honduras', validador_dui: '0000-0000-00000', validador_nit: '00000000000000' };
    expect(mascaraIdentificacion('dni', hn)).toBe('0000-0000-00000');
    expect(mascaraIdentificacion('rtn', hn)).toBe('00000000000000');
    expect(mascaraIdentificacion('pasaporte', hn)).toBe('');
  });

  it('DUI SV usa máscara país si la empresa no trae validador_dui', () => {
    expect(mascaraIdentificacion('13', { pais: 'El Salvador' })).toBe('00000000-0');
    expect(mascaraIdentificacion('36', { pais: 'El Salvador' })).toBe('0000000-000-000-0');
  });
});
