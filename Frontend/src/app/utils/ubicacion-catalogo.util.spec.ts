import {
  alCambiarDepartamento,
  dedupePorCod,
  filtrarPorCodDepartamento,
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

  describe('trackUbicacionCod', () => {
    it('distingue el mismo cod en distintos departamentos', () => {
      expect(trackUbicacionCod({ cod: '01', cod_departamento: '01' })).toBe('01-01');
      expect(trackUbicacionCod({ cod: '01', cod_departamento: '05' })).toBe('05-01');
    });
  });
});
