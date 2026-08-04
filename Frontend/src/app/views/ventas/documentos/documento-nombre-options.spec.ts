import {
  DOCUMENTO_NOMBRE_OPCIONES_CR,
  DOCUMENTO_NOMBRE_OPCIONES_DEFAULT,
  DOCUMENTO_NOMBRE_OPCIONES_HN,
  NOMBRE_DOCUMENTO_HN,
  documentoNombreOpciones,
  nombresDocumentosCompraPermitidos,
  nombresDocumentosVentaNormales,
} from './documento-nombre-options';

describe('documentoNombreOpciones', () => {
  it('devuelve CR para Costa Rica', () => {
    expect(documentoNombreOpciones({ pais: 'Costa Rica' })).toEqual(DOCUMENTO_NOMBRE_OPCIONES_CR);
  });

  it('devuelve HN para Honduras', () => {
    expect(documentoNombreOpciones({ pais: 'Honduras', cod_pais: 'HN' })).toEqual(
      DOCUMENTO_NOMBRE_OPCIONES_HN
    );
  });

  it('devuelve default SV para El Salvador', () => {
    expect(documentoNombreOpciones({ pais: 'El Salvador' })).toEqual(DOCUMENTO_NOMBRE_OPCIONES_DEFAULT);
  });

  it('HN no incluye Crédito fiscal ni Sujeto excluido', () => {
    const values = DOCUMENTO_NOMBRE_OPCIONES_HN.map((o) => o.value);
    expect(values).not.toContain('Crédito fiscal');
    expect(values).not.toContain('Sujeto excluido');
    expect(values).not.toContain('Factura de exportación');
    expect(values).not.toContain('Factura comercial');
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.boletaCompra);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.reciboHonorarios);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.guiaRemision);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.comprobanteRetencion);
  });

  it('venta HN incluye Factura/Ticket/Recibo/Guía/Abono y no Crédito fiscal', () => {
    const names = nombresDocumentosVentaNormales({ pais: 'Honduras' });
    expect(names).toContain('Factura');
    expect(names).toContain('Ticket');
    expect(names).toContain('Recibo');
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.guiaRemision);
    expect(names).toContain('Abono de Venta');
    expect(names).not.toContain('Crédito fiscal');
  });

  it('compra HN incluye boleta/honorarios/retención y no Crédito fiscal', () => {
    const names = nombresDocumentosCompraPermitidos({ pais: 'Honduras' });
    expect(names).toContain('Factura');
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.boletaCompra);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.reciboHonorarios);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.comprobanteRetencion);
    expect(names).not.toContain('Crédito fiscal');
  });

  it('compra SV sigue incluyendo Crédito fiscal', () => {
    expect(nombresDocumentosCompraPermitidos({ pais: 'El Salvador' })).toContain('Crédito fiscal');
  });
});
