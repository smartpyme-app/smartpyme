/** Mantener alineado con src/manifest.webmanifest → version */
export const environment = {
  production: true,
  appVersion: '26.10.26',
  API_URL: 'https://apiunificado.smartpyme.site',
  //API_URL: 'https://api.smartpyme.site',
  APP_URL: 'https://app-unificado.smartpyme.site',
  productImagesUrl: 'https://sp-imagenes-productos.s3.us-east-2.amazonaws.com',
  goApiUrl:    'https://go-api.smartpyme.site',
  goApiSecret: 'f93a080d7ca3dc5842f6112f7053ef3ada44ce213f04a6cc62c3c31d15beee63',
  // Lucas (servicio de IA en Python/FastAPI)
  lucasApiUrl: 'http://localhost:8000',
  lucasApiKey: '',
  haciendaPublicApiUrl: 'https://api.hacienda.go.cr',
  /** Fase 6: habilitar solo cuando Reverb esté desplegado. */
  restauranteRealtime: {
    enabled: false,
    key: '',
    wsHost: '',
    wsPort: 443,
    wssPort: 443,
    forceTLS: true,
  },
  sentry: {
    enabled: true,
    dsn: 'https://13e8c6e50613d342129d8bfae46171ab@o4512296240807936.ingest.us.sentry.io/4512296255881216',
    environment: 'production',
    /** Opcional: debe coincidir con SENTRY_RELEASE al subir maps (p. ej. frontend@abc1234). */
    release: '',
    tracesSampleRate: 0.1,
    tracePropagationTargets: [
      /^https:\/\/apiunificado\.smartpyme\.site/,
      /^https:\/\/api.*\.smartpyme\.site/,
    ],
  },
};
