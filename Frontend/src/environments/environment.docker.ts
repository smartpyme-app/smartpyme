// Entorno para Docker local: misma configuración que producción, pero contra la API
// del docker-compose. No duplicar aquí claves; se heredan de environment.prod.ts.
import { environment as prod } from './environment.prod';

export const environment = {
  ...prod,
  production: false,
  API_URL: 'http://localhost:8080',
  APP_URL: typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8081',
  sentry: { ...prod.sentry, enabled: false },
};
