/** Mantener alineado con src/manifest.webmanifest → version */
export const environment = {
  production: true,
  appVersion: '25.11.26',
  API_URL: 'https://apiunificado.smartpyme.site',
  //API_URL: 'https://api.smartpyme.site',
  /**
   * Enlaces internos que abren otra pestaña (p. ej. "Ver producto" en avisos de duplicados).
   * Se usa el dominio actual para no mandar al usuario a otro dominio donde no tiene sesión
   * (app, app-unificado, abaco, onvo, etc. sirven la misma app).
   */
  APP_URL: typeof window !== 'undefined' ? window.location.origin : 'https://app.smartpyme.site',
  /**
   * Dominios del frontend donde nginx reenvía /api/ a apiunificado: ahí las peticiones van a /api/...
   * en el mismo dominio y no hay preflight CORS. Para desactivarlo, dejar la lista vacía.
   */
  apiMismoOrigen: {
    hosts: [
      'app.smartpyme.site',
      'app-unificado.smartpyme.site',
      'abaco.smartpyme.site',
      'onvo.smartpyme.site',
      'sivareconomics.smartpyme.site',
      'contadores.smartpyme.site',
    ],
  },
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
    release: '25.11.26',
    tracesSampleRate: 0.1,
    tracePropagationTargets: [
      /^\/api\//,
      /^https:\/\/(app|app-unificado|abaco|onvo|sivareconomics|contadores)\.smartpyme\.site\/api\//,
      /^https:\/\/apiunificado\.smartpyme\.site/,
      /^https:\/\/api.*\.smartpyme\.site/,
    ],
  },
};
