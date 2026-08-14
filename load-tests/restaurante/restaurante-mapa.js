/**
 * B. MAPA DE MESAS — GET /api/restaurante/mesas progressive load.
 */
import { loadOptions, resolveToken, getJson, think } from './lib.js';

export const options = loadOptions();

export function setup() {
  return { token: resolveToken() };
}

export default function (data) {
  getJson('/api/restaurante/mesas', data.token, { scenario: 'mapa' });
  think(0.2, 0.6);
}
