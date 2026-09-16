export type PayloadVista = 'xml' | 'json';

/** CR / origen XML se previsualiza como XML; SV y el resto como JSON. */
export function tipoVistaPayload(doc: {
  pais?: string | null;
  formato_origen?: string | null;
  xml_path?: string | null;
  json_path?: string | null;
} | null | undefined): PayloadVista | null {
  if (!doc) {
    return null;
  }
  const cr = String(doc.pais ?? '').toUpperCase() === 'CR';
  const origenXml = String(doc.formato_origen ?? '').toLowerCase() === 'xml';
  if ((cr || origenXml) && doc.xml_path) {
    return 'xml';
  }
  if (doc.json_path) {
    return 'json';
  }
  if (doc.xml_path) {
    return 'xml';
  }
  return null;
}
