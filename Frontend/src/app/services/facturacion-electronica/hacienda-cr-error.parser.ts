/**
 * Convierte respuestas largas del Ministerio de Hacienda (DGT, Costa Rica) en textos
 * más claros para el usuario (similar en espíritu a {@link AlertsHaciendaComponent} para MH El Salvador).
 */

export interface HaciendaCrErrorDetalle {
  /** Título corto del problema */
  titulo: string;
  /** Qué significa y qué revisar */
  texto: string;
  /** Pasos concretos para el usuario operativo */
  pasos?: string[];
  /** Ruta interna (p. ej. configuración de empresa) */
  enlace?: string;
  enlaceLabel?: string;
  /** Fragmento original (opcional) para desplegable "detalle técnico" */
  tecnico?: string;
}

export interface HaciendaCrErrorVista {
  /** Aviso de ambiente de pruebas (sin validez tributaria), si viene en el mensaje */
  avisoPruebas: string | null;
  /** Texto introductorio antes de la lista de errores */
  resumen: string | null;
  detalles: HaciendaCrErrorDetalle[];
  /** Mensaje completo sin procesar (p. ej. para depuración) */
  raw: string;
}

const NOMBRE_ELEMENTO: Record<string, string> = {
  Impuesto: 'Impuesto (IVA u otros impuestos del comprobante)',
  ImpuestoAsumidoEmisorFabrica: 'Impuesto asumido por el emisor / fábrica',
  ImpuestoNeto: 'Impuesto neto',
  TotalDesgloseImpuesto: 'Total desglose de impuestos',
  LineaDetalle: 'Línea de detalle',
  DetalleServicio: 'Detalle de servicio',
  DetalleMercancia: 'Detalle de mercancía',
  Codigo: 'Código',
  CodigoComercial: 'Código comercial',
  Emisor: 'Emisor',
  Receptor: 'Receptor',
};

function nombreElementoAmigable(tag: string): string {
  const t = tag.trim();
  return NOMBRE_ELEMENTO[t] ?? `«${t}»`;
}

/**
 * Intenta extraer fila/columna del final de una línea tipo CSV del mensaje de Hacienda.
 */
function extraerFilaColumna(fragmento: string): { fila?: string; col?: string } {
  const m = fragmento.match(/,\s*(-?\d+)\s*,\s*(-?\d+)\s*[\]\s]*$/);
  if (m) {
    return { fila: m[1], col: m[2] };
  }

  return {};
}

/**
 * Limpia comillas duplicadas y escapes que vienen en el mensaje de Hacienda.
 */
function limpiarComillasMensaje(s: string): string {
  return s
    .replace(/^""|""$/g, '"')
    .replace(/""/g, '"')
    .trim();
}

/**
 * Quita cabeceras de tabla y sugerencias del backend (texto técnico duplicado).
 */
function limpiarCuerpoHacienda(cuerpo: string): string {
  return cuerpo
    .replace(/\[codigo\s*,\s*mensaje\s*,\s*fila\s*,\s*columna\]/gi, '')
    .replace(/\n*Sugerencia\s*\(\s*código\s*-?\d+\s*\):[^\n]*/gi, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

/**
 * Códigos mencionados en bloques «Sugerencia (código -XX)» del backend.
 */
function extraerCodigosSugerenciaBackend(texto: string): number[] {
  const out: number[] = [];
  const seen = new Set<number>();
  const re = /Sugerencia\s*\(\s*c[oó]digo\s*(-?\d+)\s*\)/gi;
  let m: RegExpExecArray | null;
  while ((m = re.exec(texto)) !== null) {
    const codigo = parseInt(m[1], 10);
    if (!seen.has(codigo)) {
      seen.add(codigo);
      out.push(codigo);
    }
  }
  return out;
}

/**
 * Traduce errores XSD típicos (cvc-*) a lenguaje operativo.
 */
function explicarErrorXsd(bloque: string, tecnico: string): HaciendaCrErrorDetalle {
  const t = limpiarComillasMensaje(bloque);

  const ubic = extraerFilaColumna(tecnico);

  // cvc-complex-type.2.4.a: elemento inesperado / orden incorrecto
  const m244 = t.match(
    /starting with element '(\{[^}]+\}):(\w+)'\.\s*One of '(\{[^}]+\}):(\w+)' is expected/i
  );
  if (m244) {
    const encontrado = m244[2];
    const esperado = m244[4];
    const ubi =
      ubic.fila !== undefined
        ? ` (referencia en el XML enviado: fila ${ubic.fila}, columna ${ubic.col ?? '—'})`
        : '';
    return {
      titulo: 'Estructura del comprobante no válida',
      texto:
        `En el comprobante aparece ${nombreElementoAmigable(encontrado)}, pero Hacienda espera ${nombreElementoAmigable(esperado)} en esa posición.${ubi}`,
      pasos: [
        'Revise el tipo de comprobante y los datos de la venta.',
        'Si el error persiste, contacte soporte con el detalle técnico.',
      ],
      tecnico: t,
    };
  }

  // cvc-pattern / tipo de dato
  if (/cvc-pattern/i.test(t) || /cvc-type/i.test(t)) {
    return {
      titulo: 'Dato con formato no válido',
      texto: 'Algún campo no cumple el formato que exige Hacienda.',
      pasos: [
        'Revise montos, identificaciones y códigos CABYS de los productos.',
        'Corrija los datos y vuelva a emitir.',
      ],
      tecnico: t,
    };
  }

  return {
    titulo: 'Validación del comprobante',
    texto: 'Hacienda rechazó el comprobante por una regla de validación.',
    pasos: ['Copie el detalle técnico y compártalo con soporte si necesita ayuda.'],
    tecnico: t,
  };
}

/**
 * Separa el aviso de "ambiente de pruebas" del resto del texto.
 */
function extraerAvisoPruebas(raw: string): { aviso: string | null; resto: string } {
  const re = /(Este comprobante fue recibido en el ambiente de pruebas[^.\n]*(?:\.[^\n]*)?)/i;
  const m = raw.match(re);
  if (!m) {
    return { aviso: null, resto: raw };
  }

  const aviso = m[1].trim();
  const resto = raw.replace(re, '').trim();

  return { aviso, resto };
}

/**
 * Extrae bloques que contienen cvc- (mensajes XSD en tabla o texto de Hacienda).
 */
function extraerBloquesCvc(resto: string): string[] {
  const texto = resto.replace(/\r\n/g, '\n');
  const start = texto.search(/cvc-[a-z0-9.-]+/i);
  if (start === -1) {
    return [];
  }

  let fragment = texto.slice(start);
  const cut = fragment.match(
    /^([\s\S]*?)(?=,\s*-?\d+\s*,\s*-?\d+\s*[\]\s\r\n]*$)/
  );
  if (cut) {
    fragment = cut[1].trim();
  } else {
    fragment = fragment.split(/\n/)[0].trim();
  }

  fragment = fragment.replace(/^["']+|["']+$/g, '').trim();
  fragment = limpiarComillasMensaje(fragment);

  return fragment.length > 15 ? [fragment] : [];
}

/**
 * Filas tipo: -53, "mensaje…", 0, 0 (inline o por línea).
 */
function extraerFilasCodigoMensajeHacienda(cuerpo: string): Array<{ codigo: number; mensaje: string }> {
  const text = limpiarCuerpoHacienda(cuerpo.replace(/\r\n/g, '\n'));
  const out: Array<{ codigo: number; mensaje: string }> = [];
  const seen = new Set<number>();
  const re = /(-?\d+)\s*,\s*"((?:[^"]|"")*)"\s*,\s*(-?\d+)\s*,\s*(-?\d+)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    const codigo = parseInt(m[1], 10);
    if (seen.has(codigo)) {
      continue;
    }
    seen.add(codigo);
    const mensaje = m[2].replace(/""/g, '"').trim();
    if (mensaje) {
      out.push({ codigo, mensaje });
    }
  }
  return out;
}

/**
 * Explica códigos conocidos del validador de Hacienda (DGT).
 */
function detallePorCodigoNumericoHacienda(codigo: number, mensajeLimpio: string): HaciendaCrErrorDetalle {
  const tecnico = mensajeLimpio || undefined;
  switch (codigo) {
    case -99:
      return {
        titulo: 'Consecutivo duplicado',
        texto:
          'Hacienda ya tiene registrado ese número de comprobante para este establecimiento y punto de venta.',
        pasos: [
          'Verifique si este documento ya fue emitido antes (consulte ventas o el estado en Hacienda).',
          'Si no fue emitido, espere unos minutos y reintente; si persiste, contacte soporte.',
        ],
        tecnico,
      };
    case -53:
      return {
        titulo: 'Fecha y hora de emisión',
        texto: 'La hora del comprobante no coincide con la hora oficial de referencia de Hacienda.',
        pasos: [
          'Espere unos segundos y vuelva a emitir.',
          'Si el error se repite, avise a soporte (puede requerir sincronizar el reloj del servidor).',
        ],
        tecnico,
      };
    case -37:
      return {
        titulo: 'Dirección fiscal del emisor',
        texto:
          'Provincia, cantón o distrito no coinciden con el domicilio fiscal registrado en Hacienda.',
        pasos: [
          'Confirme su ubicación en el portal ATV de Hacienda.',
          'Actualice la misma dirección en la configuración de su empresa en SmartPyme.',
        ],
        enlace: '/admin/empresa',
        enlaceLabel: 'Ir a configuración de empresa',
        tecnico,
      };
    case -111:
      if (/servicios gravados/i.test(mensajeLimpio)) {
        return {
          titulo: 'Totales de servicios incorrectos',
          texto: 'El total de servicios gravados no coincide con las líneas del detalle.',
          pasos: [
            'Revise que los productos tipo servicio estén marcados correctamente.',
            'Verifique montos e impuestos de cada línea.',
          ],
          tecnico,
        };
      }
      if (/mercanc/i.test(mensajeLimpio)) {
        return {
          titulo: 'Totales de mercancías incorrectos',
          texto: 'El total de mercancías gravadas no coincide con las líneas del detalle.',
          pasos: [
            'Revise que los productos tipo bien/mercancía estén clasificados correctamente.',
            'Verifique montos e impuestos de cada línea.',
          ],
          tecnico,
        };
      }
      return {
        titulo: 'Totales del resumen incorrectos',
        texto: 'Algún total del resumen no coincide con la suma de las líneas del detalle.',
        pasos: [
          'Revise montos por línea y la clasificación bien vs servicio.',
          'Corrija los productos afectados y vuelva a emitir.',
        ],
        tecnico,
      };
    default:
      return {
        titulo: 'Validación rechazada por Hacienda',
        texto: mensajeLimpio
          ? resumirMensajeHacienda(mensajeLimpio)
          : 'Hacienda reportó un error de validación en el comprobante.',
        pasos: ['Revise los datos del comprobante o contacte soporte con el detalle técnico.'],
        tecnico: mensajeLimpio || undefined,
      };
  }
}

/** Recorta mensajes largos de Hacienda para el nivel usuario. */
function resumirMensajeHacienda(mensaje: string): string {
  const limpio = mensaje.replace(/\s+/g, ' ').trim();
  if (limpio.length <= 180) {
    return limpio;
  }
  const corte = limpio.slice(0, 177).replace(/\s+\S*$/, '');
  return `${corte}…`;
}

/**
 * Parsea el texto completo devuelto por el backend (mensaje de Hacienda / DGT).
 */
export function parsearRespuestaErrorHaciendaCr(raw: string): HaciendaCrErrorVista {
  const original = (raw ?? '').trim();
  if (!original) {
    return {
      avisoPruebas: null,
      resumen: null,
      detalles: [],
      raw: original,
    };
  }

  const { aviso, resto } = extraerAvisoPruebas(original);

  let cuerpo = resto;
  const introMatch = resto.match(
    /El comprobante electrónico tiene los siguientes errores:\s*/i
  );
  if (introMatch) {
    cuerpo = resto.slice(introMatch.index! + introMatch[0].length).trim();
  }

  const detalles: HaciendaCrErrorDetalle[] = [];
  const codigosVistos = new Set<number>();

  const filasCodigo = extraerFilasCodigoMensajeHacienda(cuerpo);
  filasCodigo.forEach((f) => {
    codigosVistos.add(f.codigo);
    detalles.push(detallePorCodigoNumericoHacienda(f.codigo, f.mensaje));
  });

  const bloques = extraerBloquesCvc(cuerpo);
  bloques.forEach((bloque) => {
    const lineaCompleta = cuerpo.includes(bloque) ? cuerpo : `${cuerpo}\n${bloque}`;
    detalles.push(explicarErrorXsd(bloque, lineaCompleta));
  });

  // Fallback: códigos en sugerencias del backend aunque no se parsearon filas CSV
  extraerCodigosSugerenciaBackend(original).forEach((codigo) => {
    if (codigosVistos.has(codigo)) {
      return;
    }
    codigosVistos.add(codigo);
    detalles.push(detallePorCodigoNumericoHacienda(codigo, ''));
  });

  if (detalles.length === 0 && /error|rechaz|inválid|invalid/i.test(original)) {
    detalles.push({
      titulo: 'Hacienda rechazó el comprobante',
      texto: 'No pudimos interpretar el detalle del error automáticamente.',
      pasos: [
        'Despliegue «Detalle técnico completo» abajo y copie el texto para soporte.',
      ],
    });
  }

  return {
    avisoPruebas: aviso,
    resumen: null,
    detalles,
    raw: original,
  };
}

/**
 * Indica si el texto parece un mensaje de validación DGT (Costa Rica).
 */
export function pareceErrorHaciendaCr(texto: string | null | undefined): boolean {
  if (!texto || typeof texto !== 'string') {
    return false;
  }
  const t = texto;
  return (
    /cvc-complex-type|cvc-pattern|cvc-type|comprobanteselectronicos\.go\.cr/i.test(t) ||
    /ambiente de pruebas[^\n]*validez/i.test(t) ||
    /tiene los siguientes errores/i.test(t) ||
    /codigo\s*,\s*mensaje\s*,\s*fila\s*,\s*columna/i.test(t) ||
    /-\d{1,4}\s*,\s*"/.test(t) ||
    /Sugerencia\s*\(\s*c[oó]digo\s*-?\d+\s*\)/i.test(t) ||
    /hora oficial|Dirección General de Tributación|no coincide con la suma/i.test(t)
  );
}

// ponytail: smoke check — ejecutar en consola: import('./hacienda-cr-error.parser').then(m => m.smokeTestParserHaciendaCr())
export function smokeTestParserHaciendaCr(): boolean {
  const muestra =
    'El comprobante electrónico tiene los siguientes errores:\n' +
    '[codigo, mensaje, fila, columna]\n' +
    '-99, "El consecutivo enviado 00100001030000000001 ya existe en la base de datos.", 1, 0\n' +
    '-37, "En la Dirección General de Tributación los datos de la provincia, cantón y distrito del \'emisor\' no coinciden.", 1, 0\n\n' +
    'Sugerencia (código -99): el consecutivo enviado ya existe.\n' +
    'Sugerencia (código -37): facturacion_fe.emisor_distrito.';
  const v = parsearRespuestaErrorHaciendaCr(muestra);
  return (
    v.detalles.length === 2 &&
    v.detalles.some((d) => /duplicado/i.test(d.titulo)) &&
    v.detalles.some((d) => d.enlace === '/admin/empresa')
  );
}
