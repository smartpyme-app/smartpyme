import { ApiService } from '@services/api.service';
import { Observable, firstValueFrom } from 'rxjs';

export type RecurrenciaVentaConfig = {
  frecuencia: 'mensual' | 'anual';
  pausada: boolean;
};

export function payloadRecurrenciaApi(
  frecuencia: 'mensual' | 'anual',
  pausada: boolean,
): RecurrenciaVentaConfig {
  return { frecuencia, pausada };
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
  venta.recurrente = '1';
}

export function limpiarRecurrenciaEnVenta(venta: any): void {
  venta.frecuencia_recurrencia = null;
  venta.recurrencia_pausada = false;
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
