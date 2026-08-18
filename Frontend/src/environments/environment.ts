// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

export const environment = {
  production: false,
  // API_URL: 'http://localhost:8000',
  // API_URL: 'https://api.smartpyme.bk.test',
  // API_URL: 'https://api.smartpyme.bk.test',
  API_URL: 'https://apiunificado.smartpyme.site',
  // API_URL: 'https://apiconta.smartpyme.site',
  // API_URL: 'https://apitest.smartpyme.site',
  APP_URL: 'http://localhost:4200',
  goApiUrl: 'http://localhost:8080',     // la nueva API de Go
  goApiSecret: 'f93a080d7ca3dc5842f6112f7053ef3ada44ce213f04a6cc62c3c31d15beee63',
  haciendaPublicApiUrl: 'https://api.hacienda.go.cr',
  /** Fase 6: off por defecto — sin Reverb la UI sigue por HTTP GET. */
  restauranteRealtime: {
    enabled: false,
    key: '',
    wsHost: 'localhost',
    wsPort: 8080,
    wssPort: 443,
    forceTLS: false,
  },
};

/*
 * For easier debugging in development mode, you can import this file
 * to ignore zone related error stack frames such as `zone.run`, `zoneDelegate.invokeTask`.
 *
 * This import should be commented out in production mode because it will have a negative impact
 * on performance if an error is thrown.
 */
// import 'zone.js/plugins/zone-error';  // Included with Angular CLI.
