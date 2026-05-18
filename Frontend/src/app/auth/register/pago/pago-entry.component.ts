import { Component, Type } from '@angular/core';

import { PagoAbacoComponent } from './pago-abaco.component';
import { PagoComponent } from './pago.component';

/** Producción Ábaco (p. ej. abaco.smartpyme.site) y otros host `abaco.*` (desarrollo). */
function resolvePagoComponent(): Type<PagoComponent | PagoAbacoComponent> {
  const host =
    typeof window !== 'undefined' ? window.location.hostname.toLowerCase() : '';
  if (host.startsWith('abaco.')) {
    return PagoAbacoComponent;
  }
  return PagoComponent;
}

@Component({
  selector: 'app-pago-entry',
  template:
    '<ng-container *ngComponentOutlet="activePagoComponent"></ng-container>',
})
export class PagoEntryComponent {
  readonly activePagoComponent = resolvePagoComponent();
}
