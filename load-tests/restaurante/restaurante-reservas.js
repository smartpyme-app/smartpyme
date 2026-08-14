/**
 * D. RESERVAS — GET /api/restaurante/reservas (no pagination in API).
 */
import { loadOptions, resolveToken, getJson, think } from './lib.js';

export const options = loadOptions();

export function setup() {
  return { token: resolveToken() };
}

export default function (data) {
  getJson('/api/restaurante/reservas', data.token, { scenario: 'reservas' });
  think(0.2, 0.6);
}
