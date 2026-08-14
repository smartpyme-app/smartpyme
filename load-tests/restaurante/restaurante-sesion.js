/**
 * F. SESIÓN / PRECUENTA — optional IDs via SESION_ID / PRECUENTA_ID env.
 * Skips inventing data; falls back to GET /mesas if SESION_ID unset.
 */
import { loadOptions, resolveToken, getJson, think } from './lib.js';

export const options = loadOptions();

export function setup() {
  return {
    token: resolveToken(),
    sesionId: __ENV.SESION_ID || '',
    precuentaId: __ENV.PRECUENTA_ID || '',
  };
}

export default function (data) {
  if (data.sesionId) {
    getJson(`/api/restaurante/sesiones-mesa/${data.sesionId}`, data.token, { scenario: 'sesion' });
  } else {
    getJson('/api/restaurante/mesas', data.token, { scenario: 'sesion_fallback_mesas' });
  }
  if (data.precuentaId) {
    getJson(`/api/restaurante/pre-cuentas/${data.precuentaId}`, data.token, { scenario: 'precuenta' });
  }
  think(0.2, 0.6);
}
