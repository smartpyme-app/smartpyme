/**
 * Ambiente DEMO: igual que producción, pero contra su propia API (apidemo.smartpyme.site),
 * que tiene su propia base. Se compila con: ng build --configuration production,demo
 */
import { environment as produccion } from './environment.prod';

export const environment = {
  ...produccion,
  API_URL: 'https://apidemo.smartpyme.site',
  /** Sin proxy /api/ en este dominio: las peticiones van directo a la API del ambiente. */
  apiMismoOrigen: { hosts: [] as string[] },
  sentry: { ...produccion.sentry, enabled: false, environment: 'demo' },
};
