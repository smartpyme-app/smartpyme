import { Component, OnInit, Type } from '@angular/core';

import { PagoAbacoComponent } from './pago-abaco.component';
import { PagoSivarEconomicsComponent } from './pago-sivar-economics.component';
import { PagoComponent } from './pago.component';

@Component({
  selector: 'app-pago-entry',
  template:
    '<ng-container *ngComponentOutlet="activePagoComponent"></ng-container>',
})
export class PagoEntryComponent implements OnInit {
  activePagoComponent: Type<PagoComponent | PagoAbacoComponent | PagoSivarEconomicsComponent> = PagoComponent;

  ngOnInit(): void {
    if (typeof window !== 'undefined') {
      const host = window.location.hostname.toLowerCase();
      console.log('[PagoEntry] Host detectado:', host);

      if (host.includes('abaco')) {
        this.activePagoComponent = PagoAbacoComponent;
        console.log('[PagoEntry] Cargando PagoAbacoComponent');
      } else if (host.includes('sivar')) {
        this.activePagoComponent = PagoSivarEconomicsComponent;
        console.log('[PagoEntry] Cargando PagoSivarEconomicsComponent');
      } else {
        this.activePagoComponent = PagoComponent;
        console.log('[PagoEntry] Cargando PagoComponent estándar');
      }
    }
  }
}
