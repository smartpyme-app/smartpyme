import {
  EMPLEADO_FORM_CONFIG_CR,
  EMPLEADO_FORM_CONFIG_SV,
  empleadoFormConfig,
  labelIdentidadEmpleado,
  mostrarCampoNit,
  placeholderIdentidadEmpleado,
} from './empleado-form-por-pais';

describe('empleadoFormConfig', () => {
  it('devuelve CR para Costa Rica', () => {
    expect(empleadoFormConfig({ pais: 'Costa Rica' }).codPais).toBe('CR');
    expect(empleadoFormConfig({ pais: 'Costa Rica' }).mostrarIdType).toBe(true);
    expect(empleadoFormConfig({ pais: 'Costa Rica' }).mostrarNit).toBe(false);
    expect(empleadoFormConfig({ pais: 'Costa Rica' }).labelIsss).toContain('CCSS');
  });

  it('devuelve SV por defecto / El Salvador', () => {
    expect(empleadoFormConfig({ pais: 'El Salvador' }).codPais).toBe('SV');
    expect(empleadoFormConfig({}).codPais).toBe('SV');
    expect(empleadoFormConfig({ pais: 'El Salvador' }).usarMascaraDuiSv).toBe(true);
    expect(empleadoFormConfig({ pais: 'El Salvador' }).mostrarAfp).toBe(true);
  });

  it('HN cae en default SV hasta tener config propia', () => {
    expect(empleadoFormConfig({ pais: 'Honduras', cod_pais: 'HN' }).codPais).toBe('SV');
  });
});

describe('helpers identidad / nit', () => {
  it('label y placeholder CR según id_type', () => {
    expect(labelIdentidadEmpleado(EMPLEADO_FORM_CONFIG_CR, 1)).toBe('Número de cédula:');
    expect(labelIdentidadEmpleado(EMPLEADO_FORM_CONFIG_CR, 2)).toBe('Número DIMEX:');
    expect(placeholderIdentidadEmpleado(EMPLEADO_FORM_CONFIG_CR, 2)).toBe('DIMEX');
  });

  it('mostrarCampoNit respeta homologado en SV y oculta en CR', () => {
    expect(mostrarCampoNit(EMPLEADO_FORM_CONFIG_CR, false)).toBe(false);
    expect(mostrarCampoNit(EMPLEADO_FORM_CONFIG_SV, false)).toBe(true);
    expect(mostrarCampoNit(EMPLEADO_FORM_CONFIG_SV, true)).toBe(false);
  });
});
