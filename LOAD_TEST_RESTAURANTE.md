# Load tests — Smartpyme Restaurante 1.0 (Fase 12–13)

**Purpose:** controlled load measurement. Fase 12 = light baseline; Fase 13 = Peak/Capacity (`STAGE_PROFILE=peak`).

**Do not** run against production without explicit authorization.

## Requirements

- [k6](https://k6.io/) (`brew install k6` or Docker `grafana/k6`)
- Reachable API (`BASE_URL`)
- JWT via `AUTH_TOKEN` **or** `LOGIN_EMAIL` + `LOGIN_PASSWORD` (test user only)
- Empresa with `modulo-restaurante` + permission `restaurante.ver` (and `pedidos.ver` for pedidos)

## Environment variables

| Variable | Required | Description |
|----------|----------|-------------|
| `BASE_URL` | yes | e.g. `http://127.0.0.1:8000` |
| `AUTH_TOKEN` | one of | JWT bearer (preferred) |
| `LOGIN_EMAIL` / `LOGIN_PASSWORD` | one of | login to `/api/login` |
| `SESION_ID` | optional | for sesión scenario |
| `PRECUENTA_ID` | optional | for precuenta GET |
| `STAGE_PROFILE` | optional | `light` (F12), `full`, `peak` (F13 ramp), or `fixed` (CLI `--vus`/`--duration` only) |
| `ENABLE_MUTATIONS` | optional | must be `1` to run mutations script |
| `MESA_ID` | mutations | mesa libre for single open-mesa smoke |

Never commit tokens/passwords. Prefer a local `.env.load` that is gitignored.

## Scripts

| File | Scenario |
|------|----------|
| `restaurante-baseline.js` | 1 VU health/auth |
| `restaurante-mapa.js` | GET `/api/restaurante/mesas` |
| `restaurante-cocina.js` | GET `/api/restaurante/comandas` |
| `restaurante-reservas.js` | GET `/api/restaurante/reservas` |
| `restaurante-pedidos.js` | GET `/api/restaurante/pedidos?paginate=10` |
| `restaurante-sesion.js` | GET sesión/precuenta (IDs optional) |
| `restaurante-mixed.js` | weighted read mix |
| `restaurante-mutations.js` | **disabled** unless `ENABLE_MUTATIONS=1` |

## Example (local)

```bash
export BASE_URL=http://127.0.0.1:8000
export AUTH_TOKEN='…'          # do not commit
export STAGE_PROFILE=light
export SESION_ID=…             # optional
export PRECUENTA_ID=…          # optional

cd load-tests/restaurante
k6 run --summary-export=results/baseline.json restaurante-baseline.js
k6 run --summary-export=results/mapa.json restaurante-mapa.js
k6 run --summary-export=results/cocina.json restaurante-cocina.js
k6 run --summary-export=results/reservas.json restaurante-reservas.js
k6 run --summary-export=results/pedidos.json restaurante-pedidos.js
k6 run --summary-export=results/sesion.json restaurante-sesion.js
k6 run --summary-export=results/mixed.json restaurante-mixed.js
```

Docker:

```bash
docker run --rm -i -v "$PWD:/scripts" -w /scripts \
  -e BASE_URL -e AUTH_TOKEN -e STAGE_PROFILE \
  grafana/k6 run --summary-export=results/baseline.json restaurante-baseline.js
```

## Traffic mix (`restaurante-mixed.js`)

- 40% mesas  
- 25% comandas  
- 15% pedidos  
- 10% reservas  
- 10% sesión/precuenta (or mesas fallback)

## Interpreting results

- Record **environment**, **dataset size**, **VUs**, **duration**.
- Local MySQL ≠ MariaDB 10.11 prod → label **LOCAL — NO REPRESENTATIVO DE PROD**.
- Plan §14 thresholds (mapa p95 &lt; 500ms, etc.) are **reference only** until Peak evidence exists.
- Do not equate VUs with “real users”.

## Safety

- No production load without approval.
- Mutations default **off**.
- Scripts must not embed secrets.
- Stop if sustained 5xx, integrity issues, or impact on real users.

See `FASE12_REPORT.md` for measured outcomes of a given run.
