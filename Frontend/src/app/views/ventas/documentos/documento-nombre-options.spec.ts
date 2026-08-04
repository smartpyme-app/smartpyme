import {
  DOCUMENTO_NOMBRE_OPCIONES_CR,
  DOCUMENTO_NOMBRE_OPCIONES_DEFAULT,
  DOCUMENTO_NOMBRE_OPCIONES_HN,
  NOMBRE_DOCUMENTO_CR,
  NOMBRE_DOCUMENTO_HN,
  NUMERO_EMISION_OPCIONES_HN,
  documentoNombreOpciones,
  esDocumentoFiscalHn,
  esNombreNotaCredito,
  esNombreNotaCreditoODebito,
  esNombreNotaDebito,
  formatoCorrelativoHn,
  nombresDocumentoExcluidosGastoHn,
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
    expect(values).toContain('Factura'); // legacy pre–split RTN
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.facturaConRtn);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.facturaSinRtn);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.boletaCompra);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.reciboHonorarios);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.guiaRemision);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.comprobanteRetencion);
  });

  it('venta HN incluye Factura con/sin RTN, legacy Factura, Ticket/Recibo/Guía/Abono y no Crédito fiscal', () => {
    const names = nombresDocumentosVentaNormales({ pais: 'Honduras' });
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.facturaConRtn);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.facturaSinRtn);
    expect(names).toContain('Factura');
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.ticket);
    expect(names).toContain('Recibo');
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.guiaRemision);
    expect(names).toContain('Abono de Venta');
    expect(names).not.toContain('Crédito fiscal');
  });

  it('compra HN incluye Factura con/sin RTN, legacy Factura, boleta/honorarios/retención y no Crédito fiscal', () => {
    const names = nombresDocumentosCompraPermitidos({ pais: 'Honduras' });
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.facturaConRtn);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.facturaSinRtn);
    expect(names).toContain('Factura');
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.boletaCompra);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.reciboHonorarios);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.comprobanteRetencion);
    expect(names).not.toContain('Crédito fiscal');
  });

  it('compra SV sigue incluyendo Crédito fiscal', () => {
    expect(nombresDocumentosCompraPermitidos({ pais: 'El Salvador' })).toContain('Crédito fiscal');
  });

  it('nombresDocumentoExcluidosGastoHn devuelve los cuatro tipos SV-only', () => {
    expect(nombresDocumentoExcluidosGastoHn()).toEqual([
      'Crédito fiscal',
      'Sujeto excluido',
      'Factura de exportación',
      'Factura comercial',
    ]);
  });
});

describe('esDocumentoFiscalHn', () => {
  it('reconoce tipos fiscales HN', () => {
    expect(esDocumentoFiscalHn(NOMBRE_DOCUMENTO_HN.facturaConRtn)).toBe(true);
    expect(esDocumentoFiscalHn(NOMBRE_DOCUMENTO_HN.facturaSinRtn)).toBe(true);
    expect(esDocumentoFiscalHn(NOMBRE_DOCUMENTO_HN.ticket)).toBe(true);
    expect(esDocumentoFiscalHn(NOMBRE_DOCUMENTO_HN.comprobanteRetencion)).toBe(true);
  });

  it('no marca operativos como fiscales', () => {
    expect(esDocumentoFiscalHn('Cotización')).toBe(false);
    expect(esDocumentoFiscalHn('Recibo')).toBe(false);
    expect(esDocumentoFiscalHn('Abono de Venta')).toBe(false);
    expect(esDocumentoFiscalHn('Factura')).toBe(false);
  });
});

describe('NUMERO_EMISION_OPCIONES_HN', () => {
  it('lista 01 a 20', () => {
    expect(NUMERO_EMISION_OPCIONES_HN).toEqual([
      '01', '02', '03', '04', '05', '06', '07', '08', '09', '10',
      '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
    ]);
  });
});

describe('formatoCorrelativoHn', () => {
  it('formatea con prefijo 001-001 y pad 8', () => {
    expect(formatoCorrelativoHn('01', 439)).toBe('001-001-01-00000439');
    expect(formatoCorrelativoHn('20', 1)).toBe('001-001-20-00000001');
  });

  it('sin numero_emision devuelve correlativo plano', () => {
    expect(formatoCorrelativoHn(null, 439)).toBe('439');
    expect(formatoCorrelativoHn('', '439')).toBe('439');
  });
});

describe('esNombreNotaCredito / esNombreNotaDebito', () => {
  it('reconoce nota de crédito SV y CR', () => {
    expect(esNombreNotaCredito('Nota de crédito')).toBe(true);
    expect(esNombreNotaCredito(NOMBRE_DOCUMENTO_CR.notaCredito)).toBe(true);
    expect(esNombreNotaCredito('Factura')).toBe(false);
  });

  it('reconoce nota de débito SV y CR', () => {
    expect(esNombreNotaDebito('Nota de débito')).toBe(true);
    expect(esNombreNotaDebito(NOMBRE_DOCUMENTO_CR.notaDebito)).toBe(true);
    expect(esNombreNotaDebito('Factura')).toBe(false);
  });

  it('esNombreNotaCreditoODebito agrupa crédito y débito', () => {
    expect(esNombreNotaCreditoODebito('Nota de crédito')).toBe(true);
    expect(esNombreNotaCreditoODebito(NOMBRE_DOCUMENTO_CR.notaDebito)).toBe(true);
    expect(esNombreNotaCreditoODebito('Factura')).toBe(false);
  });
});
