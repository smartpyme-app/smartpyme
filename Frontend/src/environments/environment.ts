// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

export const environment = {
  production: false,
      // API_URL: 'http://localhost:8000',
//  API_URL: 'https://api.smartpyme.site',
  API_URL: 'https://api.smartpyme.test',
  // API_URL: 'https://apiconta.smartpyme.site',
  // API_URL: 'https://apitest.smartpyme.site',
  APP_URL: 'http://localhost:4200',
  goApiUrl:    'http://localhost:8080',     // la nueva API de Go
  goApiSecret: 'cdd5761bffbff3f7e6f93c6c1adcdc911d786b91b646e5d19f9f4a5d4a4bbc33'
};

/*
 * For easier debugging in development mode, you can import the following file
 * to ignore zone related error stack frames such as `zone.run`, `zoneDelegate.invokeTask`.
 *
 * This import should be commented out in production mode because it will have a negative impact
 * on performance if an error is thrown.
 */
// import 'zone.js/plugins/zone-error';  // Included with Angular CLI.
