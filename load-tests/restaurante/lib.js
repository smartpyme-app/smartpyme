/**
 * Shared helpers for Smartpyme Restaurante k6 load tests.
 * Secrets ONLY via env: BASE_URL, AUTH_TOKEN (or LOGIN_EMAIL + LOGIN_PASSWORD).
 * Never log Authorization / tokens / passwords.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';

export const endpointDuration = new Trend('rest_endpoint_duration', true);
export const endpointBytes = new Trend('rest_endpoint_bytes', true);
export const httpFails = new Rate('rest_http_fail');
export const checkFails = new Counter('rest_check_fail');

export function baseUrl() {
  const u = __ENV.BASE_URL || 'http://127.0.0.1:8000';
  return u.replace(/\/$/, '');
}

export function authHeaders(token) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
}

/**
 * Resolve JWT: AUTH_TOKEN preferred; else POST /api/login with LOGIN_EMAIL/LOGIN_PASSWORD.
 * Does not print secrets.
 */
export function resolveToken() {
  if (__ENV.AUTH_TOKEN) {
    return __ENV.AUTH_TOKEN;
  }
  const email = __ENV.LOGIN_EMAIL;
  const password = __ENV.LOGIN_PASSWORD;
  if (!email || !password) {
    throw new Error('Set AUTH_TOKEN or LOGIN_EMAIL+LOGIN_PASSWORD');
  }
  const res = http.post(
    `${baseUrl()}/api/login`,
    JSON.stringify({ email, password }),
    { headers: { Accept: 'application/json', 'Content-Type': 'application/json' } },
  );
  if (res.status !== 200) {
    throw new Error(`login failed status=${res.status}`);
  }
  let body;
  try {
    body = res.json();
  } catch (e) {
    throw new Error('login response not JSON');
  }
  if (!body || !body.token) {
    throw new Error('login response missing token');
  }
  return body.token;
}

export function getJson(path, token, tags = {}) {
  const url = `${baseUrl()}${path}`;
  const res = http.get(url, {
    headers: authHeaders(token),
    tags: { name: path, ...tags },
  });
  endpointDuration.add(res.timings.duration, { endpoint: path });
  endpointBytes.add(res.body ? res.body.length : 0, { endpoint: path });
  const okStatus = res.status === 200;
  httpFails.add(!okStatus);
  const ok = check(res, {
    'status 200': (r) => r.status === 200,
    'body non-empty': (r) => r.body && r.body.length > 0,
  });
  if (!ok) {
    checkFails.add(1);
  }
  return res;
}

export function think(min = 0.2, max = 0.8) {
  sleep(min + Math.random() * (max - min));
}

/** Trend percentiles exported in summary (Fase 13: include real p99). */
export const SUMMARY_TREND_STATS = ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'];

/**
 * Progressive stages.
 * STAGE_PROFILE=light|full|peak (default full for backward compat).
 * peak = Fase 13 capacity ramp to 50 VUs with cool-down.
 */
export function loadStages() {
  const profile = __ENV.STAGE_PROFILE || 'full';
  if (profile === 'light') {
    return [
      { duration: '20s', target: 1 },
      { duration: '40s', target: 5 },
      { duration: '40s', target: 10 },
      { duration: '30s', target: 5 },
      { duration: '20s', target: 0 },
    ];
  }
  if (profile === 'peak') {
    return [
      { duration: '30s', target: 1 },
      { duration: '30s', target: 5 },
      { duration: '60s', target: 10 },
      { duration: '60s', target: 20 },
      { duration: '60s', target: 30 },
      { duration: '60s', target: 40 },
      { duration: '60s', target: 50 },
      { duration: '60s', target: 50 }, // stabilize
      { duration: '30s', target: 30 },
      { duration: '30s', target: 10 },
      { duration: '30s', target: 0 },
    ];
  }
  return [
    { duration: '30s', target: 1 },
    { duration: '1m', target: 5 },
    { duration: '1m', target: 10 },
    { duration: '2m', target: 20 },
    { duration: '2m', target: 30 },
    { duration: '1m', target: 20 },
    { duration: '1m', target: 5 },
    { duration: '30s', target: 0 },
  ];
}

export function loadOptions(extra = {}) {
  // STAGE_PROFILE=fixed → no stages; use CLI --vus/--duration (steady probes)
  const opts = {
    summaryTrendStats: SUMMARY_TREND_STATS,
    thresholds: {
      // Observational — do not use as capacity PASS/FAIL
      checks: ['rate>0.85'],
    },
    ...extra,
  };
  if (__ENV.STAGE_PROFILE !== 'fixed') {
    opts.stages = loadStages();
  }
  return opts;
}

export function baselineOptions() {
  return {
    summaryTrendStats: SUMMARY_TREND_STATS,
    vus: 1,
    duration: '30s',
    thresholds: {
      // Observational only — plan §14 mapa p95 <500ms referenced as PLAN_REF not hard fail
      checks: ['rate>0.9'],
    },
  };
}
