/**
 * E. PEDIDOS — GET /api/restaurante/pedidos?paginate=10 (real pagination; detalles included).
 */
import { loadOptions, resolveToken, getJson, think } from './lib.js';

export const options = loadOptions();

export function setup() {
  return { token: resolveToken() };
}

export default function (data) {
  getJson('/api/restaurante/pedidos?paginate=10&page=1', data.token, { scenario: 'pedidos' });
  think(0.2, 0.6);
}
