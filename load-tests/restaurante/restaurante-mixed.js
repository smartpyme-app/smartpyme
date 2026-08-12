/**
 * Mixed read workload (Fase 12 suggested distribution):
 * 40% mesas, 25% comandas, 15% pedidos, 10% reservas, 10% sesion/precuenta|fallback mesas
 * Mutations: NOT included (NO EJECUTADO unless ENABLE_MUTATIONS=1 on isolated env).
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
  const r = Math.random();
  if (r < 0.4) {
    getJson('/api/restaurante/mesas', data.token, { scenario: 'mixed_mesas' });
  } else if (r < 0.65) {
    getJson('/api/restaurante/comandas', data.token, { scenario: 'mixed_comandas' });
  } else if (r < 0.8) {
    getJson('/api/restaurante/pedidos?paginate=10&page=1', data.token, { scenario: 'mixed_pedidos' });
  } else if (r < 0.9) {
    getJson('/api/restaurante/reservas', data.token, { scenario: 'mixed_reservas' });
  } else if (data.sesionId) {
    getJson(`/api/restaurante/sesiones-mesa/${data.sesionId}`, data.token, { scenario: 'mixed_sesion' });
    if (data.precuentaId) {
      getJson(`/api/restaurante/pre-cuentas/${data.precuentaId}`, data.token, { scenario: 'mixed_precuenta' });
    }
  } else {
    getJson('/api/restaurante/mesas', data.token, { scenario: 'mixed_fallback_mesas' });
  }
  think(0.2, 0.7);
}
