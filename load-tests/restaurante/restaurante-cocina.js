/**
 * C. COCINA / COMANDAS — GET /api/restaurante/comandas (no LIMIT in API; measure as-is).
 */
import { loadOptions, resolveToken, getJson, think } from './lib.js';

export const options = loadOptions();

export function setup() {
  return { token: resolveToken() };
}

export default function (data) {
  getJson('/api/restaurante/comandas', data.token, { scenario: 'cocina' });
  think(0.2, 0.6);
}
