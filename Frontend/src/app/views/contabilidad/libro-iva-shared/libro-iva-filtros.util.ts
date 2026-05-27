import { Params } from '@angular/router';
import * as moment from 'moment';

export function crearAniosLibroIva(cantidad = 11): number[] {
  const currentYear = new Date().getFullYear();
  const years: number[] = [];
  for (let i = 0; i < cantidad; i++) {
    years.push(currentYear - i);
  }
  return years;
}

export function crearFiltrosLibroIvaIniciales(): Record<string, unknown> {
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;
  return {
    id_sucursal: '',
    anio: currentYear,
    mes: currentMonth,
  };
}

export function aplicarRangoMesLibroIva(filtros: Record<string, unknown>): void {
  const anio = Number(filtros['anio']);
  const mes = Number(filtros['mes']);
  filtros['inicio'] = moment([anio, mes - 1]).startOf('month').format('YYYY-MM-DD');
  filtros['fin'] = moment([anio, mes - 1]).endOf('month').format('YYYY-MM-DD');
}
