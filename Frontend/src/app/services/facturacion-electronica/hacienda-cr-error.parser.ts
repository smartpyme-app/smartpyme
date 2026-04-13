/**
 * Convierte respuestas largas del Ministerio de Hacienda (DGT, Costa Rica) en textos
 * más claros para el usuario (similar en espíritu a {@link AlertsHaciendaComponent} para MH El Salvador).
 */

export interface HaciendaCrErrorDetalle {
  /** Título corto del problema */
  titulo: string;
  /** Qué significa y qué revisar */
  texto: string;
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
      titulo: 'Estructura del XML no coincide con el esquema de Hacienda',
      texto:
        `En el comprobante aparece el elemento ${nombreElementoAmigable(encontrado)}, pero en esa posición el Ministerio espera ${nombreElementoAmigable(esperado)}. ` +
        `Suele deberse al orden de los nodos (p. ej. impuestos en líneas o totales) o a un tipo de documento que no corresponde.${ubi} ` +
        `Revise con su asesor o la documentación del catálogo de comprobantes (versión del esquema).`,
      tecnico: t,
    };
  }

  // cvc-pattern / tipo de dato
  if (/cvc-pattern/i.test(t) || /cvc-type/i.test(t)) {
    return {
      titulo: 'Dato con formato no válido',
      texto:
        'Algún campo no cumple el formato que exige Hacienda (longitud, decimales o caracteres permitidos). Revise montos, identificaciones y códigos CABYS.',
      tecnico: t,
    };
  }

  return {
    titulo: 'Validación del comprobante',
    texto:
      'Hacienda rechazó el XML por una regla de validación. Si no entiende el detalle técnico, comparta el mensaje con soporte o con quien mantenga la integración.',
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
 * El texto puede incluir comillas internas; se toma desde cvc- hasta antes de fila/columna (…, 60, 37).
 */
function extraerBloquesCvc(resto: string): string[] {
  const texto = resto.replace(/\r\n/g, '\n');
  const start = texto.search(/cvc-[a-z0-9.-]+/i);
  if (start === -1) {
    return [];
  }

  let fragment = texto.slice(start);
  // Cortar antes del patrón ", número, número" (fila/columna en tabla Hacienda), con o sin `]` final
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
 * Filas tipo: -53, "mensaje…", 0, 0 (códigos numéricos DGT distintos de XSD cvc-*).
 */
function extraerFilasCodigoMensajeHacienda(cuerpo: string): Array<{ codigo: number; mensaje: string }> {
  const text = cuerpo.replace(/\r\n/g, '\n');
  const out: Array<{ codigo: number; mensaje: string }> = [];
  const re = /^\s*(-?\d+)\s*,\s*"((?:[^"]|"")*)"\s*,\s*(-?\d+)\s*,\s*(-?\d+)\s*$/gm;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    const codigo = parseInt(m[1], 10);
    const mensaje = m[2].replace(/""/g, '"').trim();
    out.push({ codigo, mensaje });
  }
  return out;
}

/**
 * Explica códigos conocidos del validador de Hacienda (DGT).
 */
function detallePorCodigoNumericoHacienda(codigo: number, mensajeLimpio: string): HaciendaCrErrorDetalle {
  const tecnico = mensajeLimpio;
  switch (codigo) {
    case -53:
      return {
        titulo: 'Fecha y hora de emisión del XML',
        texto:
          'La marca de tiempo del comprobante no coincide con la hora oficial de referencia de Hacienda. Al emitir desde SmartPyme se usa la hora actual en Costa Rica; si persiste, sincronice el reloj del servidor (NTP) o emita de nuevo en unos segundos.',
        tecnico,
      };
    case -37:
      return {
        titulo: 'Ubicación fiscal del emisor',
        texto:
          'Provincia, cantón y distrito del emisor no coinciden con los registrados en la Dirección General de Tributación. Actualice la dirección en Hacienda (datos de contribuyente) y los mismos códigos en la empresa en SmartPyme (facturación FE / distrito 5 dígitos).',
        tecnico,
      };
    case -111:
      if (/servicios gravados/i.test(mensajeLimpio)) {
        return {
          titulo: 'Totales de servicios gravados (resumen vs detalle)',
          texto:
            'El total de servicios gravados del resumen debe coincidir con la suma de las líneas del detalle marcadas como servicio (tipo de transacción servicio). Revise el tipo de producto (bien vs servicio) y los montos por línea.',
          tecnico,
        };
      }
      if (/mercanc/i.test(mensajeLimpio)) {
        return {
          titulo: 'Totales de mercancías gravadas (resumen vs detalle)',
          texto:
            'El total de mercancías gravadas del resumen debe coincidir con la suma de las líneas clasificadas como bien/mercancía. Revise el tipo de producto y los montos.',
          tecnico,
        };
      }
      return {
        titulo: 'Coherencia de totales en el resumen',
        texto:
          'Algún total del bloque de resumen no coincide con la suma de las líneas del detalle. Revise montos y que bienes y servicios estén en los campos correctos.',
        tecnico,
      };
    default:
      return {
        titulo: `Validación Hacienda (código ${codigo})`,
        texto: 'Revise el mensaje técnico o consulte con soporte si no está claro.',
        tecnico,
      };
  }
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

  let intro: string | null = null;
  const introMatch = resto.match(
    /El comprobante electrónico tiene los siguientes errores:\s*/i
  );
  let cuerpo = resto;
  if (introMatch) {
    intro = introMatch[0].replace(/:\s*$/, '').trim();
    cuerpo = resto.slice(introMatch.index! + introMatch[0].length).trim();
  }

  const detalles: HaciendaCrErrorDetalle[] = [];

  const filasCodigo = extraerFilasCodigoMensajeHacienda(cuerpo);
  filasCodigo.forEach((f) => {
    detalles.push(detallePorCodigoNumericoHacienda(f.codigo, f.mensaje));
  });

  const bloques = extraerBloquesCvc(cuerpo);
  bloques.forEach((bloque) => {
    const lineaCompleta = cuerpo.includes(bloque) ? cuerpo : `${cuerpo}\n${bloque}`;
    detalles.push(explicarErrorXsd(bloque, lineaCompleta));
  });

  if (detalles.length === 0 && /error|rechaz|inválid|invalid/i.test(original)) {
    detalles.push({
      titulo: 'Respuesta de Hacienda',
      texto:
        'No se pudieron extraer líneas técnicas automáticamente. Revise el detalle técnico o copie el mensaje para soporte.',
      tecnico: original.length > 2000 ? `${original.slice(0, 2000)}…` : original,
    });
  }

  return {
    avisoPruebas: aviso,
    resumen: intro,
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
    /hora oficial|Dirección General de Tributación|no coincide con la suma/i.test(t)
  );
}
