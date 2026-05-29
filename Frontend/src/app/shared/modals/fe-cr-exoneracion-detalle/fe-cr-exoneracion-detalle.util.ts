export interface FeCrExoneracionDetalle {
  aplica: boolean;
  tipo_documento_ex: string;
  numero_documento: string;
  nombre_institucion: string;
  fecha_emision: string;
  tarifa_exonerada: number;
  numero_articulo: string;
  numero_inciso: string;
  documento_otro: string;
}

/** Catálogo nota 10.1 DGT — TipoDocumentoEX (FE 4.4). */
export const FE_CR_TIPOS_DOCUMENTO_EXONERACION: { codigo: string; nombre: string }[] = [
  { codigo: '01', nombre: 'Compras autorizadas por la Dirección General de Tributación (solo NC/ND)' },
  { codigo: '02', nombre: 'Ventas exentas a diplomáticos' },
  { codigo: '03', nombre: 'Autorizado por Ley especial' },
  { codigo: '04', nombre: 'Exenciones DGT — autorización local genérica' },
  { codigo: '05', nombre: 'Exenciones DGT — transitorio V (ingeniería, arquitectura, topografía, obra civil)' },
  { codigo: '06', nombre: 'Servicios turísticos inscritos ante el ICT' },
  { codigo: '07', nombre: 'Transitorio XVII (reciclaje y reutilizable)' },
  { codigo: '08', nombre: 'Exoneración a Zona Franca' },
  { codigo: '09', nombre: 'Exoneración servicios complementarios exportación (art. 11 RLIVA)' },
  { codigo: '10', nombre: 'Órgano de las corporaciones municipales' },
  { codigo: '11', nombre: 'Exenciones DGT — autorización de impuesto local concreta' },
  { codigo: '99', nombre: 'Otros' },
];

export const TIPOS_GRAVADO_CON_EXONERADA = ['gravada', 'exenta', 'no_sujeta', 'exonerada'] as const;

export function baseFeCrExoneracionDetalle(): FeCrExoneracionDetalle {
  return {
    aplica: false,
    tipo_documento_ex: '',
    numero_documento: '',
    nombre_institucion: '',
    fecha_emision: '',
    tarifa_exonerada: 13,
    numero_articulo: '',
    numero_inciso: '',
    documento_otro: '',
  };
}

export function initFeCrExoneracionDetalle(detalle: any): void {
  const base = baseFeCrExoneracionDetalle();
  const cur = detalle?.fe_cr_exoneracion;
  if (!cur || typeof cur !== 'object') {
    detalle.fe_cr_exoneracion = { ...base };
    return;
  }
  detalle.fe_cr_exoneracion = { ...base, ...cur };
}

export function detalleTieneExoneracionCr(detalle: any): boolean {
  const ex = detalle?.fe_cr_exoneracion;
  return !!(ex && ex.aplica) || (detalle?.tipo_gravado || '').toLowerCase() === 'exonerada';
}

export function migrarExoneracionCrLegacyADetalles(venta: any): void {
  if (!venta?.detalles?.length) {
    return;
  }
  const legacy = venta.fe_cr_exoneracion;
  const hayLegacy = legacy && typeof legacy === 'object' && legacy.aplica;
  if (!hayLegacy) {
    return;
  }
  const base = {
    aplica: true,
    tipo_documento_ex: legacy.tipo_documento_ex || '',
    numero_documento: legacy.numero_documento || '',
    nombre_institucion: legacy.nombre_institucion || '',
    fecha_emision: legacy.fecha_emision || '',
    tarifa_exonerada: legacy.tarifa_exonerada ?? 13,
    numero_articulo: legacy.numero_articulo || '',
    numero_inciso: legacy.numero_inciso || '',
    documento_otro: legacy.documento_otro || '',
  };
  for (const d of venta.detalles) {
    const ex = d.fe_cr_exoneracion;
    if (ex && typeof ex === 'object' && ex.aplica) {
      continue;
    }
    d.fe_cr_exoneracion = { ...base };
    d.tipo_gravado = 'exonerada';
  }
}

export function validarExoneracionForm(ex: FeCrExoneracionDetalle): string[] {
  const faltan: string[] = [];
  if (!ex.aplica) {
    return faltan;
  }
  if (!ex.tipo_documento_ex) {
    faltan.push('tipo de documento EX');
  }
  if (!(ex.numero_documento || '').trim()) {
    faltan.push('número de autorización');
  }
  if (!(ex.nombre_institucion || '').trim()) {
    faltan.push('institución emisora');
  }
  if (!(ex.fecha_emision || '').trim()) {
    faltan.push('fecha de emisión del documento');
  }
  if (!(Number(ex.tarifa_exonerada) > 0)) {
    faltan.push('tarifa exonerada (%)');
  }
  if (ex.tipo_documento_ex === '99' && !(ex.documento_otro || '').trim()) {
    faltan.push('descripción del documento «otro» (tipo 99)');
  }
  return faltan;
}

/** Aplica la respuesta de GET /fe-cr/exoneracion sobre el formulario del modal. */
export function aplicarRespuestaExoneracionHacienda(
  form: FeCrExoneracionDetalle,
  data: Record<string, unknown>
): FeCrExoneracionDetalle {
  const out = { ...form };
  const tipoDoc = data['tipoDocumento'];
  if (tipoDoc && typeof tipoDoc === 'object') {
    const codigo = String((tipoDoc as Record<string, unknown>)['codigo'] ?? '').trim();
    if (codigo) {
      out.tipo_documento_ex = codigo.padStart(2, '0').slice(-2);
    }
  }
  const numDoc = data['numeroDocumento'] ?? data['numero_documento'];
  if (numDoc != null && String(numDoc).trim() !== '') {
    out.numero_documento = String(numDoc).trim();
  }
  const inst =
    data['nombreInstitucion'] ?? data['nombre_institucion'] ?? data['institucion'];
  if (inst != null && String(inst).trim() !== '') {
    out.nombre_institucion = String(inst).trim();
  }
  const tarifa =
    data['porcentajeExoneracion'] ?? data['tarifaExoneracion'] ?? data['tarifa'];
  if (tarifa != null && tarifa !== '') {
    out.tarifa_exonerada = Number(tarifa);
  }
  const fechaRaw = data['fechaEmision'] ?? data['fecha_emision'];
  if (fechaRaw != null && String(fechaRaw).trim() !== '') {
    const s = String(fechaRaw).slice(0, 10);
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
      out.fecha_emision = s;
    }
  }
  return out;
}

export function aplicarExoneracionGuardadaEnDetalle(detalle: any, ex: FeCrExoneracionDetalle): void {
  if (ex.aplica) {
    detalle.tipo_gravado = 'exonerada';
  } else if (detalle.tipo_gravado === 'exonerada') {
    detalle.tipo_gravado = 'gravada';
  }
  detalle.fe_cr_exoneracion = { ...ex };
}
