/**
 * G. MUTATIONS — DISABLED BY DEFAULT.
 * Set ENABLE_MUTATIONS=1 only on an isolated test DB / staging.
 * This script refuses to run otherwise (exits setup with error).
 *
 * Even when enabled, it only opens ONE mesa (not mass mutations) —
 * still not Peak. Prefer Feature suite for integrity.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { resolveToken, baseUrl, authHeaders } from './lib.js';

export const options = {
  vus: 1,
  iterations: 1,
};

export function setup() {
  if (__ENV.ENABLE_MUTATIONS !== '1') {
    throw new Error('Mutations disabled. Set ENABLE_MUTATIONS=1 only on isolated/staging. (Fase 12 default: NO EJECUTADO)');
  }
  if (!__ENV.MESA_ID) {
    throw new Error('MESA_ID required for mutation smoke');
  }
  return { token: resolveToken(), mesaId: __ENV.MESA_ID };
}

export default function (data) {
  const res = http.post(
    `${baseUrl()}/api/restaurante/sesiones-mesa`,
    JSON.stringify({ mesa_id: Number(data.mesaId), num_comensales: 2 }),
    { headers: authHeaders(data.token) },
  );
  check(res, {
    'open mesa 201 or 422': (r) => r.status === 201 || r.status === 422,
  });
  sleep(1);
}
