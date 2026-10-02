import { ApiService } from '@services/api.service';
import { Observable, firstValueFrom } from 'rxjs';

export type RecurrenciaVentaConfig = {
  frecuencia: 'mensual' | 'anual';
  pausada: boolean;
  dia_generacion: number | null;
};

export function diaRecurrenciaDesdeVenta(venta: { fecha?: string; dia_generacion_recurrencia?: number | null } | null | undefined): number {
  const cfg = venta?.dia_generacion_recurrencia;
  if (cfg != null && cfg >= 1 && cfg <= 31) {
    return cfg;
  }
  const fecha = venta?.fecha;
  if (!fecha) {
    return 1;
  }
  const d = new Date(fecha.includes('T') ? fecha : fecha + 'T12:00:00');
  const day = d.getDate();
  return Number.isFinite(day) && day >= 1 && day <= 31 ? day : 1;
}

export function payloadRecurrenciaApi(
  frecuencia: 'mensual' | 'anual',
  pausada: boolean,
  diaGeneracion: number | null,
): RecurrenciaVentaConfig {
  const dia = diaGeneracion != null && diaGeneracion >= 1 && diaGeneracion <= 31 ? diaGeneracion : null;
  return { frecuencia, pausada, dia_generacion: dia };
}

export function tieneRecurrenciaProgramada(
  venta: any,
  pendiente: RecurrenciaVentaConfig | null,
): boolean {
  return !!pendiente || (venta?.frecuencia_recurrencia === 'mensual' || venta?.frecuencia_recurrencia === 'anual');
}

export function aplicarRecurrenciaEnVenta(venta: any, config: RecurrenciaVentaConfig): void {
  venta.frecuencia_recurrencia = config.frecuencia;
  venta.recurrencia_pausada = config.pausada;
  venta.dia_generacion_recurrencia = config.dia_generacion;
  venta.recurrente = '1';
}

export function limpiarRecurrenciaEnVenta(venta: any): void {
  venta.frecuencia_recurrencia = null;
  venta.recurrencia_pausada = false;
  venta.dia_generacion_recurrencia = null;
  venta.recurrente = false;
}

export function guardarRecurrenciaEnServidor(
  apiService: ApiService,
  ventaId: number,
  config: RecurrenciaVentaConfig,
): Observable<unknown> {
  return apiService.store('venta/' + ventaId + '/recurrencia', config);
}

export async function persistirRecurrenciaPendiente(
  apiService: ApiService,
  venta: any,
  pendiente: RecurrenciaVentaConfig | null,
): Promise<void> {
  if (!pendiente || !venta?.id) {
    return;
  }
  await firstValueFrom(guardarRecurrenciaEnServidor(apiService, venta.id, pendiente));
  aplicarRecurrenciaEnVenta(venta, pendiente);
}
