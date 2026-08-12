/**
 * A. HEALTH / BASELINE — 1 VU, short. Validates auth + GET /mesas.
 * LOCAL — NO REPRESENTATIVO DE PROD unless BASE_URL points to staging.
 */
import { baselineOptions, resolveToken, getJson, think } from './lib.js';

export const options = baselineOptions();

export function setup() {
  return { token: resolveToken() };
}

export default function (data) {
  getJson('/api/restaurante/mesas', data.token, { scenario: 'baseline' });
  think(0.3, 0.7);
}
