import * as Sentry from '@sentry/angular';
import { environment } from '../environments/environment';

export function initSentry(): void {
  if (!environment.sentry?.enabled || !environment.sentry.dsn) {
    return;
  }

  Sentry.init({
    dsn: environment.sentry.dsn,
    environment: environment.sentry.environment,
    ...(environment.sentry.release ? { release: environment.sentry.release } : {}),
    sendDefaultPii: false,
    integrations: [Sentry.browserTracingIntegration()],
    tracesSampleRate: environment.sentry.tracesSampleRate ?? 0.1,
    tracePropagationTargets: environment.sentry.tracePropagationTargets ?? [
      'localhost',
      /^https:\/\/api\.smartpyme\.test/,
      /^https:\/\/api.*\.smartpyme\.site/,
    ],
    beforeSend(event) {
      if (event.request?.headers) {
        delete event.request.headers['Authorization'];
        delete event.request.headers['authorization'];
      }
      return event;
    },
  });
}
