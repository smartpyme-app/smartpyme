import {
  alCambiarDepartamento,
  alCambiarDistrito,
  alCambiarMunicipio,
  claveDistritoActual,
  dedupePorCod,
  distritosParaSelector,
  filtrarPorCodDepartamento,
  hidratarCodigosUbicacion,
  trackUbicacionCod,
} from './ubicacion-catalogo.util';

describe('ubicacion-catalogo.util', () => {
  describe('dedupePorCod', () => {
    it('elimina países con el mismo cod', () => {
      const paises = [
        { cod: '9300', nombre: 'EL SALVADOR' },
        { cod: '9330', nombre: 'ARGENTINA' },
        { cod: '9300', nombre: 'EL SALVADOR' },
      ];
      expect(dedupePorCod(paises)).toEqual([
        { cod: '9300', nombre: 'EL SALVADOR' },
        { cod: '9330', nombre: 'ARGENTINA' },
      ]);
    });
  });

  describe('filtrarPorCodDepartamento', () => {
    const municipios = [
      { cod: '01', nombre: 'Ahuachapán', cod_departamento: '01' },
      { cod: '01', nombre: 'Candelaria', cod_departamento: '05' },
      { cod: '02', nombre: 'Apaneca', cod_departamento: '01' },
    ];

    it('al cambiar de departamento solo deja municipios del nuevo código', () => {
      expect(filtrarPorCodDepartamento(municipios, '01').map((m) => m.nombre)).toEqual([
        'Ahuachapán',
        'Apaneca',
      ]);
      expect(filtrarPorCodDepartamento(municipios, '05').map((m) => m.nombre)).toEqual([
        'Candelaria',
      ]);
    });

    it('sin departamento no lista municipios viejos', () => {
      expect(filtrarPorCodDepartamento(municipios, '')).toEqual([]);
      expect(filtrarPorCodDepartamento(municipios, null)).toEqual([]);
    });
  });

  describe('alCambiarDepartamento', () => {
    it('actualiza nombre y limpia municipio/distrito', () => {
      const cliente: any = {
        cod_departamento: '01',
        departamento: 'Ahuachapán',
        municipio: 'Ahuachapán',
        cod_municipio: '01',
        distrito: 'Centro',
        cod_distrito: '01',
      };
      const deps = [
        { cod: '01', nombre: 'Ahuachapán' },
        { cod: '05', nombre: 'La Libertad' },
      ];

      alCambiarDepartamento(cliente, deps, '05');

      expect(cliente.cod_departamento).toBe('05');
      expect(cliente.departamento).toBe('La Libertad');
      expect(cliente.municipio).toBe('');
      expect(cliente.cod_municipio).toBe('');
      expect(cliente.distrito).toBe('');
      expect(cliente.cod_distrito).toBe('');
    });
  });

  describe('alCambiarMunicipio', () => {
    it('guarda el cantón y limpia distrito', () => {
      const sucursal: any = {
        cod_departamento: '1',
        departamento: 'San José',
        municipio: 'Central',
        cod_municipio: '01',
        distrito: 'Carmen',
        cod_distrito: '10101',
      };
      const municipios = [
        { cod: '01', nombre: 'Central', cod_departamento: '1' },
        { cod: '02', nombre: 'Escazú', cod_departamento: '1' },
      ];

      alCambiarMunicipio(sucursal, municipios, '02');

      expect(sucursal.cod_municipio).toBe('02');
      expect(sucursal.municipio).toBe('Escazú');
      expect(sucursal.distrito).toBe('');
      expect(sucursal.cod_distrito).toBe('');
    });
  });

  describe('alCambiarDistrito', () => {
    const distritosSv = [
      { cod: '01', nombre: 'San Salvador Centro', cod_departamento: '06', cod_municipio: '14' },
      { cod: '01', nombre: 'Soyapango', cod_departamento: '06', cod_municipio: '23' },
    ];
    const municipiosSv = [
      { cod: '14', nombre: 'San Salvador Centro', cod_departamento: '06' },
      { cod: '23', nombre: 'Soyapango', cod_departamento: '06' },
    ];

    it('rellena distrito y cantón del catálogo', () => {
      const sucursal: any = { cod_departamento: '1', cod_municipio: '01' };
      const distritos = [
        { cod: '10101', nombre: 'Carmen', cod_departamento: '1', cod_municipio: '01' },
      ];
      const municipios = [{ cod: '01', nombre: 'Central', cod_departamento: '1' }];

      alCambiarDistrito(sucursal, distritos, municipios, '10101');

      expect(sucursal.distrito).toBe('Carmen');
      expect(sucursal.cod_distrito).toBe('10101');
      expect(sucursal.municipio).toBe('Central');
    });

    it('elige el distrito del municipio correcto cuando el cod MH se repite', () => {
      const cliente: any = { cod_departamento: '06' };
      const clave = trackUbicacionCod(distritosSv[1]);

      alCambiarDistrito(cliente, distritosSv, municipiosSv, clave);

      expect(cliente.cod_distrito).toBe('01');
      expect(cliente.distrito).toBe('Soyapango');
      expect(cliente.cod_municipio).toBe('23');
      expect(cliente.municipio).toBe('Soyapango');
      expect(claveDistritoActual(cliente, distritosSv)).toBe(clave);
    });
  });

  describe('distritosParaSelector', () => {
    const distritos = [
      { cod: '01', nombre: 'Centro', cod_departamento: '06', cod_municipio: '14' },
      { cod: '01', nombre: 'Soyapango', cod_departamento: '06', cod_municipio: '23' },
      { cod: '02', nombre: 'Apaneca', cod_departamento: '01', cod_municipio: '02' },
    ];

    it('lista todos los distritos del departamento', () => {
      expect(distritosParaSelector(distritos, '06', '').map((d) => d.nombre)).toEqual([
        'Centro',
        'Soyapango',
      ]);
    });

    it('ignora un municipio que no pertenece al departamento', () => {
      expect(distritosParaSelector(distritos, '06', '02').map((d) => d.nombre)).toEqual([
        'Centro',
        'Soyapango',
      ]);
    });
  });

  describe('hidratarCodigosUbicacion', () => {
    it('completa códigos desde nombres guardados', () => {
      const sucursal: any = { departamento: 'San José', municipio: 'Escazú', distrito: 'San Rafael' };
      hidratarCodigosUbicacion(sucursal, {
        departamentos: [{ cod: '1', nombre: 'San José' }],
        municipios: [{ cod: '02', nombre: 'Escazú', cod_departamento: '1' }],
        distritos: [
          { cod: '10203', nombre: 'San Rafael', cod_departamento: '1', cod_municipio: '02' },
        ],
      });

      expect(sucursal.cod_departamento).toBe('1');
      expect(sucursal.cod_municipio).toBe('02');
      expect(sucursal.cod_distrito).toBe('10203');
    });
  });

  describe('trackUbicacionCod', () => {
    it('distingue el mismo cod en distintos departamentos', () => {
      expect(trackUbicacionCod({ cod: '01', cod_departamento: '01' })).toBe('01-01');
      expect(trackUbicacionCod({ cod: '01', cod_departamento: '05' })).toBe('05-01');
    });
  });
});
