import { Component, Input } from '@angular/core';

/** Document context for translating receptor/emisor (sale vs purchase/expense). */
export type ContextoHacienda = 'venta' | 'compra' | 'gasto';

export type ParsedHaciendaError = { campo: string; mensaje: string; compacto?: boolean };

@Component({
  selector: 'app-alerts-hacienda',
  templateUrl: './alerts-hacienda.component.html',
})
export class AlertsHaciendaComponent {
  @Input() errores: string[] | any = [];
  /** Compra/gasto: en el DTE, emisor = su empresa y receptor = proveedor. Venta: receptor = cliente, emisor = su empresa. */
  @Input() contexto: ContextoHacienda = 'venta';

  private readonly camposBase: Record<string, string> = {
    descActividad: 'Descripción de actividad',
    codActividad: 'Código de actividad',
    nit: 'NIT',
    nrc: 'NRC',
    direccion: 'Dirección',
    Complemento: 'Complemento de dirección',
    municipio: 'Municipio',
    departamento: 'Departamento',
    numDocumento: 'Número de documento (DUI, NIT u otro)',
    tipoDocumento: 'Tipo de documento',
    nombre: 'Nombre',
    telefono: 'Teléfono',
    correo: 'Correo electrónico',
    actividadEconomica: 'Actividad económica',
  };

  get erroresList(): string[] {
    if (!this.errores) return [];

    if (typeof this.errores === 'string') return [this.errores];

    if (Array.isArray(this.errores)) return this.errores;

    if (this.errores.descripcionMsg) return [this.errores.descripcionMsg];

    if (Array.isArray(this.errores.observaciones) && this.errores.observaciones.length > 0) {
      return this.errores.observaciones;
    }

    return [];
  }

  esErrorFormateable(msg: string): boolean {
    if (!msg || typeof msg !== 'string') return false;
    const t = msg.trim();
    return t.includes('Campo #/') || /^\[.+?\]/.test(t);
  }

  parseError(msg: string): ParsedHaciendaError {
    const bracket = msg.match(/^\[(.+?)\]\s*(.*)$/s);
    if (bracket) {
      const rawPath = bracket[1];
      const mensajeRaw = bracket[2].trim();
      const parts = rawPath.split('.').filter(Boolean);
      const compacto = this.intentarMensajeCompacto(mensajeRaw, parts);
      if (compacto) {
        return { campo: '', mensaje: compacto.mensaje, compacto: true };
      }
      const campo = parts.map(p => this.traducirSegmentoCampo(p)).join(' › ');
      const mensaje = this.ampliarMensajeMH(mensajeRaw, parts);
      return { campo, mensaje };
    }

    const match = msg.match(/Campo\s+#\/(.+?)\s+(.*)/);
    if (!match) {
      return { campo: 'Error', mensaje: msg };
    }

    const rawPath = match[1];
    const mensajeBase = match[2].trim();
    const partsSlash = rawPath.split('/').filter(Boolean);
    const compactoSlash = this.intentarMensajeCompacto(mensajeBase, partsSlash);
    if (compactoSlash) {
      return { campo: '', mensaje: compactoSlash.mensaje, compacto: true };
    }
    const campo = partsSlash.map(part => this.traducirSegmentoCampo(part)).join(' › ');

    return { campo, mensaje: mensajeBase };
  }

  /** Mensaje corto para «valor no permitido» en DUI/NIT sin repetir la ruta técnica. */
  private intentarMensajeCompacto(
    mensajeTecnico: string,
    partesRuta: string[]
  ): { mensaje: string } | null {
    const m = mensajeTecnico.toUpperCase();
    if (!m.includes('VALOR NO ES PERMITIDO')) return null;
    const refiereDocumento = partesRuta.some(
      p => p === 'numDocumento' || p.toLowerCase().includes('documento')
    );
    if (!refiereDocumento) return null;

    if (this.contexto === 'venta') {
      return {
        mensaje:
          'No se puede emitir el DTE: el número de documento (DUI, NIT, etc.) del cliente es incorrecto, no es válido o no corresponde según Hacienda.',
      };
    }
    if (partesRuta.includes('emisor')) {
      return {
        mensaje:
          'No se puede emitir el DTE: el número de documento (DUI, NIT, etc.) de su empresa (emisor) es incorrecto, no es válido o no coincide con Hacienda.',
      };
    }
    return {
      mensaje:
        'No se puede emitir el DTE con el número de documento (DUI, NIT, etc.) registrado para este sujeto excluido: está incorrecto, no es válido o está asociado a un contribuyente según Hacienda.',
    };
  }

  private traducirSegmentoCampo(part: string): string {
    if (part === 'receptor') {
      if (this.contexto === 'venta') return 'Cliente (receptor del DTE)';
      return 'Proveedor (receptor del DTE)';
    }
    if (part === 'emisor') {
      if (this.contexto === 'venta') return 'Emisor (su empresa)';
      return 'Su empresa (emisor del DTE)';
    }
    return this.camposBase[part] ?? part;
  }

  private ampliarMensajeMH(mensajeTecnico: string, partesRuta: string[]): string {
    if (!mensajeTecnico) {
      return 'Respuesta del Ministerio de Hacienda sin texto adicional.';
    }

    const m = mensajeTecnico.toUpperCase();

    if (m.includes('VALOR NO ES PERMITIDO')) {
      return `${mensajeTecnico} Revise los datos enviados y las reglas del tipo de documento.`;
    }

    return mensajeTecnico;
  }
}
