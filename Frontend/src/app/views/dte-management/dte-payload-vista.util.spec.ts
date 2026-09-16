import { tipoVistaPayload } from './dte-payload-vista.util';

describe('tipoVistaPayload', () => {
  it('en Costa Rica con XML muestra XML, no JSON', () => {
    expect(tipoVistaPayload({
      pais: 'CR',
      formato_origen: 'xml',
      xml_path: 'docs/fe.xml',
      json_path: 'docs/interno.json',
    })).toBe('xml');
  });

  it('en El Salvador con JSON muestra JSON', () => {
    expect(tipoVistaPayload({
      pais: 'SV',
      formato_origen: 'json',
      json_path: 'docs/dte.json',
    })).toBe('json');
  });

  it('sin archivo no ofrece vista', () => {
    expect(tipoVistaPayload({ pais: 'CR' })).toBeNull();
  });
});
