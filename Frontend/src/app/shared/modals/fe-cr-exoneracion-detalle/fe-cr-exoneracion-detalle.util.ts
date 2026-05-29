export interface FeCrExoneracionDetalle {
  aplica: boolean;
  tipo_documento_ex: string;
  numero_documento: string;
  /** Código nota 23 (NombreInstitucion en XML). */
  nombre_institucion: string;
  /** Obligatorio si nombre_institucion es 99 (NombreInstitucionOtros). */
  nombre_institucion_otro: string;
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

/** Catálogo nota 23 DGT — NombreInstitucion (FE 4.4). */
export const FE_CR_INSTITUCIONES_EXONERACION: { codigo: string; nombre: string }[] = [
  { codigo: '01', nombre: 'Ministerio de Hacienda' },
  { codigo: '02', nombre: 'Ministerio de Relaciones Exteriores y Culto' },
  { codigo: '03', nombre: 'Ministerio de Agricultura y Ganadería' },
  { codigo: '04', nombre: 'Ministerio de Economía, Industria y Comercio' },
  { codigo: '05', nombre: 'Cruz Roja Costarricense' },
  { codigo: '06', nombre: 'Benemérito Cuerpo de Bomberos de Costa Rica' },
  { codigo: '07', nombre: 'Asociación Obras del Espíritu Santo' },
  { codigo: '08', nombre: 'Federación Cruzada Nacional de Protección al Anciano (Fecrunapa)' },
  { codigo: '09', nombre: 'Escuela de Agricultura de la Región Húmeda (EARTH)' },
  { codigo: '10', nombre: 'Instituto Centroamericano de Administración de Empresas (INCAE)' },
  { codigo: '11', nombre: 'Junta de Protección Social (JPS)' },
  { codigo: '12', nombre: 'Autoridad Reguladora de los Servicios Públicos (Aresep)' },
  { codigo: '99', nombre: 'Otros' },
];

function normTextoInstitucion(s: string): string {
  return s
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .trim();
}

/** Resuelve código nota 23 desde código o nombre (p. ej. respuesta Hacienda). */
export function normalizarCodigoInstitucionEx(valor: string): string {
  const v = (valor || '').trim();
  if (!v) {
    return '';
  }
  const codigo = v.padStart(2, '0').slice(-2);
  if (v.length <= 2 && FE_CR_INSTITUCIONES_EXONERACION.some((i) => i.codigo === codigo)) {
    return codigo;
  }
  const nv = normTextoInstitucion(v);
  const exacta = FE_CR_INSTITUCIONES_EXONERACION.find(
    (i) => i.codigo !== '99' && normTextoInstitucion(i.nombre) === nv
  );
  if (exacta) {
    return exacta.codigo;
  }
  for (const i of FE_CR_INSTITUCIONES_EXONERACION) {
    if (i.codigo === '99') {
      continue;
    }
    const nn = normTextoInstitucion(i.nombre);
    if (nv.includes(nn) || nn.includes(nv)) {
      return i.codigo;
    }
  }
  if (FE_CR_INSTITUCIONES_EXONERACION.some((i) => i.codigo === codigo)) {
    return codigo;
  }
  return '';
}

export function labelInstitucionEx(codigo: string): string {
  const c = (codigo || '').padStart(2, '0').slice(-2);
  return FE_CR_INSTITUCIONES_EXONERACION.find((i) => i.codigo === c)?.nombre ?? codigo;
}

export const TIPOS_GRAVADO_CON_EXONERADA = ['gravada', 'exenta', 'no_sujeta', 'exonerada'] as const;

export function baseFeCrExoneracionDetalle(): FeCrExoneracionDetalle {
  return {
    aplica: false,
    tipo_documento_ex: '',
    numero_documento: '',
    nombre_institucion: '',
    nombre_institucion_otro: '',
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
  detalle.fe_cr_exoneracion.nombre_institucion = normalizarCodigoInstitucionEx(
    detalle.fe_cr_exoneracion.nombre_institucion || ''
  );
  if (!detalle.fe_cr_exoneracion.nombre_institucion_otro) {
    detalle.fe_cr_exoneracion.nombre_institucion_otro = '';
  }
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
    nombre_institucion: normalizarCodigoInstitucionEx(legacy.nombre_institucion || ''),
    nombre_institucion_otro: legacy.nombre_institucion_otro || '',
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
  if (!ex.nombre_institucion) {
    faltan.push('institución emisora');
  } else if (!FE_CR_INSTITUCIONES_EXONERACION.some((i) => i.codigo === ex.nombre_institucion)) {
    faltan.push('institución emisora (código nota 23)');
  }
  if (ex.nombre_institucion === '99') {
    const otro = (ex.nombre_institucion_otro || '').trim();
    if (otro.length < 5) {
      faltan.push('nombre de institución «otro» (mín. 5 caracteres)');
    }
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
  const instRaw =
    data['codigoInstitucion'] ??
    data['codigo_institucion'] ??
    (data['institucion'] && typeof data['institucion'] === 'object'
      ? (data['institucion'] as Record<string, unknown>)['codigo']
      : null) ??
    data['nombreInstitucion'] ??
    data['nombre_institucion'] ??
    data['institucion'];
  if (instRaw != null && String(instRaw).trim() !== '') {
    const codigo = normalizarCodigoInstitucionEx(String(instRaw).trim());
    if (codigo) {
      out.nombre_institucion = codigo;
    } else if (String(instRaw).trim().length >= 5) {
      out.nombre_institucion = '99';
      out.nombre_institucion_otro = String(instRaw).trim();
    }
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
