import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import {
  HaciendaCrErrorVista,
  parsearRespuestaErrorHaciendaCr,
  pareceErrorHaciendaCr,
} from '@services/facturacion-electronica/hacienda-cr-error.parser';

@Component({
    selector: 'app-alerts-hacienda',
    templateUrl: './alerts-hacienda.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class AlertsHaciendaComponent {
  @Input() errores: string[] | any = [];

  /** Vista parseada para mensajes DGT Costa Rica (XSD / ambiente de pruebas). */
  get vistaCostaRica(): HaciendaCrErrorVista | null {
    const raw = this.textoErrorCostaRicaRaw;
    if (!raw || !pareceErrorHaciendaCr(raw)) {
      return null;
    }
    const vista = parsearRespuestaErrorHaciendaCr(raw);
    const mostrarCr =
      vista.detalles.length > 0 || !!vista.avisoPruebas || !!(vista.resumen && vista.resumen.trim());
    return mostrarCr ? vista : null;
  }

  /** Texto bruto del mensaje Hacienda CR (string o envuelto en objeto). */
  private get textoErrorCostaRicaRaw(): string {
    const e = this.errores;
    if (typeof e === 'string') {
      return e;
    }
    if (e && typeof e === 'object' && typeof e.haciendaCrRaw === 'string') {
      return e.haciendaCrRaw;
    }
    return '';
  }

  // Diccionario para traducir campos técnicos a nombres amigables
  private nombresAmigables: Record<string, string> = {
    receptor: 'Cliente',
    descActividad: 'Descripción de Actividad',
    codActividad: 'Código de Actividad',
    nit: 'NIT',
    nrc: 'NRC',
    direccion: 'Dirección',
    Complemento: 'Complemento de Dirección',
    municipio: 'Municipio',
    departamento: 'Departamento',
  };

  // Normaliza los errores (Ministerio de Hacienda El Salvador y otros formatos simples)
  get erroresList(): string[] {
    if (!this.errores) return [];

    if (this.vistaCostaRica) {
      return [];
    }

    if (typeof this.errores === 'string') return [this.errores];

    if (Array.isArray(this.errores)) return this.errores;

    if (this.errores.descripcionMsg) return [this.errores.descripcionMsg];

    return [];
  }

  // Parsea el error para obtener el campo y el mensaje
  parseError(msg: string): { campo: string; mensaje: string } {
    const match = msg.match(/Campo\s+#\/(.+?)\s+(.*)/);
    if (!match) return { campo: 'Error', mensaje: msg };

    const rawPath = match[1];
    const mensaje = match[2];

    // Traduce cada parte del path si está en el diccionario
    const campo = rawPath
      .split('/')
      .map(part => this.nombresAmigables[part] || part)
      .join(' > ');

    return { campo, mensaje };
  }
}
