import { Component, OnInit, Type } from '@angular/core';

import { PagoAbacoComponent } from './pago-abaco.component';
import { PagoComponent } from './pago.component';

@Component({
  selector: 'app-pago-entry',
  standalone: false,
  template:
    '<ng-container *ngComponentOutlet="activePagoComponent"></ng-container>',
})
export class PagoEntryComponent implements OnInit {
  activePagoComponent: Type<PagoComponent | PagoAbacoComponent> = PagoComponent;

  ngOnInit(): void {
    if (typeof window !== 'undefined') {
      const host = window.location.hostname.toLowerCase();
      console.log('[PagoEntry] Host detectado:', host);

      if (host.includes('abaco')) {
        this.activePagoComponent = PagoAbacoComponent;
        console.log('[PagoEntry] Cargando PagoAbacoComponent');
      } else {
        this.activePagoComponent = PagoComponent;
        console.log('[PagoEntry] Cargando PagoComponent estándar');
      }
    }
  }
}
