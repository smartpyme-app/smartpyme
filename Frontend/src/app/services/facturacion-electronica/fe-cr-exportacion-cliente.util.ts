/** Validación de receptor para FEE (09), alineada con CostaRicaInvoiceFromVentaMapper::assertIdentificacionExportacion. */

export interface ClienteFeCrExportacionLike {
  nombre?: string | null;
  apellido?: string | null;
  nombre_empresa?: string | null;
  nit?: string | null;
  dui?: string | null;
  tipo_documento?: string | null;
}

const CODIGOS_CR = new Set(['01', '02', '03', '04', '05', '06']);

export function esNombreDocumentoExportacionCr(nombreDocumento: string | null | undefined): boolean {
  const n = String(nombreDocumento ?? '').toLowerCase();
  return n.includes('exportación') || n.includes('exportacion');
}

function soloDigitos(s: string): string {
  return String(s ?? '').replace(/\D/g, '');
}

function cedulaJuridicaConLetra(raw: string): boolean {
  const n = String(raw ?? '').replace(/[\s-]/g, '');
  return n.length === 10 && /^[0-9A-Za-z]{10}$/.test(n) && /[A-Za-z]/.test(n);
}

function tipoIdentificacionCostaRica(
  tipoDocumento: string | null | undefined,
  nitRaw: string,
  duiRaw: string,
): string | null {
  const guardado = String(tipoDocumento ?? '').trim();
  if (guardado && CODIGOS_CR.has(guardado)) {
    return guardado;
  }
  if (cedulaJuridicaConLetra(nitRaw) || cedulaJuridicaConLetra(duiRaw)) {
    return '02';
  }
  const nit = soloDigitos(nitRaw);
  const dui = soloDigitos(duiRaw);
  if (nit.length >= 9) {
    return '02';
  }
  if (dui.length >= 9) {
    return '01';
  }
  return null;
}

function normalizarNumeroCr(tipo: string, raw: string): string {
  if (tipo === '02') {
    return String(raw ?? '').replace(/[^0-9A-Za-z]/g, '');
  }
  return soloDigitos(raw);
}

function estructuraValidaFacturaCr(tipo: string, numero: string): boolean {
  const n = normalizarNumeroCr(tipo, numero);
  if (!n || /^0+$/.test(n)) {
    return false;
  }
  switch (tipo) {
    case '01':
      return n.length === 9;
    case '02':
      return n.length === 10;
    case '03':
      return n.length === 11 || n.length === 12;
    case '04':
      return n.length === 10;
    default:
      return false;
  }
}

function numeroExtranjeroNoDomiciliado(nitRaw: string, duiRaw: string): string | null {
  const raw = String(nitRaw ?? '').trim() || String(duiRaw ?? '').trim();
  const n = raw.replace(/[\s-]/g, '');
  if (!n || n.length > 20 || /^0+$/.test(n)) {
    return null;
  }
  return n;
}

function nombreCliente(cliente: ClienteFeCrExportacionLike): string {
  const persona = `${cliente.nombre ?? ''} ${cliente.apellido ?? ''}`.trim();
  return persona || String(cliente.nombre_empresa ?? '').trim() || 'Cliente';
}

export function clienteValidoReceptorFacturaExportacionCr(
  cliente: ClienteFeCrExportacionLike | null | undefined,
): { ok: boolean; message: string } {
  if (!cliente) {
    return {
      ok: false,
      message:
        'La factura de exportación requiere un cliente con identificación válida. Seleccione el cliente en la venta; no use consumidor final.',
    };
  }

  const nitRaw = String(cliente.nit ?? '');
  const duiRaw = String(cliente.dui ?? '');
  const tipo = tipoIdentificacionCostaRica(cliente.tipo_documento, nitRaw, duiRaw);
  const etiqueta = nombreCliente(cliente);

  if (tipo && ['01', '02', '03', '04'].includes(tipo)) {
    const num =
      tipo === '02'
        ? normalizarNumeroCr('02', nitRaw || duiRaw)
        : soloDigitos(tipo === '01' && duiRaw ? duiRaw : nitRaw || duiRaw);
    if (estructuraValidaFacturaCr(tipo, num)) {
      return { ok: true, message: '' };
    }
  }

  if (tipo === '05') {
    const num = numeroExtranjeroNoDomiciliado(nitRaw, duiRaw);
    if (num) {
      return { ok: true, message: '' };
    }
  }

  return {
    ok: false,
    message:
      `La factura de exportación requiere un receptor extranjero (tipo 05, identificación de 1 a 20 caracteres) ` +
      `o una cédula válida de Hacienda. Complete la identificación del cliente «${etiqueta}».`,
  };
}

export function ventaClienteListoParaExportacionCr(venta: {
  nombre_documento?: string | null;
  id_cliente?: number | string | null;
  cliente?: ClienteFeCrExportacionLike | null;
}): { ok: boolean; message: string } {
  if (!esNombreDocumentoExportacionCr(venta?.nombre_documento)) {
    return { ok: true, message: '' };
  }
  if (!venta?.id_cliente) {
    return {
      ok: false,
      message:
        'La factura de exportación requiere seleccionar un cliente con identificación válida. No use consumidor final.',
    };
  }
  if (venta.cliente) {
    return clienteValidoReceptorFacturaExportacionCr(venta.cliente);
  }
  return { ok: true, message: '' };
}
