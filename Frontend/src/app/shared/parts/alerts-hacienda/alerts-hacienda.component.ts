import { Component, Input } from '@angular/core';
import { normalizarErroresHacienda } from '../../utils/mh-recepcion-errores';

@Component({
  selector: 'app-alerts-hacienda',
  templateUrl: './alerts-hacienda.component.html',
})
export class AlertsHaciendaComponent {
  @Input() errores: string[] | any = [];

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

  /**
   * Todo el mapeo de errores MH (recepción, firma, anulación, HttpClient) vive en normalizarErroresHacienda.
   * Aquí solo se pasa el valor crudo que guardó el flujo (error completo).
   */
  get erroresList(): string[] {
    return normalizarErroresHacienda(this.errores);
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
