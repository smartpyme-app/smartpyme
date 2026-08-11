# HN tipo de cambio BCH (híbrido)

## Meta

Sugerir TC USD→HNL desde la API del Banco Central de Honduras (indicador EC-TCR-01 / 97) y dejar el campo **editable** para alinear con BAC u otra banca.

## Comportamiento

1. Preview / resolución de documento USD en HN: intenta BCH (cache `rate_del_dia` del día).
2. Si falla la API o falta `BCH_API_KEY` → `rate_manual` si existe.
3. Si el usuario envía `exchange_rate` y `permitir_editar` / `allowManualRate` → prevalece el valor del usuario (BAC, etc.).
4. `permitir_editar: true` en plantilla HN.

## Config

- `.env`: `BCH_API_BASE_URL`, `BCH_API_KEY` (header `Ocp-Apim-Subscription-Key`), timeout.
- `MonedaDefaultPorPais` HN: `fuente=api`, `api.provider=bch`, `permitir_editar=true`.

## Archivos

- `BchTipoCambioClient`, `HondurasTipoCambioService`
- Wire en `MonedaPaisService`
- Tests unitarios con HTTP fake / mock
