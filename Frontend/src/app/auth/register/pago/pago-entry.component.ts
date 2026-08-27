import { Component, OnInit, Type } from '@angular/core';

import { PagoAbacoComponent } from './pago-abaco.component';
import { PagoSivarEconomicsComponent } from './pago-sivar-economics.component';
import { PagoOnvoComponent } from './pago-onvo.component';
import { PagoComponent } from './pago.component';

@Component({
  selector: 'app-pago-entry',
  template:
    '<ng-container *ngComponentOutlet="activePagoComponent"></ng-container>',
})
export class PagoEntryComponent implements OnInit {
  activePagoComponent: Type<PagoComponent | PagoAbacoComponent | PagoSivarEconomicsComponent | PagoOnvoComponent> = PagoComponent;

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
      } else if (host.includes('onvo')) {
        this.activePagoComponent = PagoOnvoComponent;
        console.log('[PagoEntry] Cargando PagoOnvoComponent');
      } else {
        this.activePagoComponent = PagoComponent;
        console.log('[PagoEntry] Cargando PagoComponent estándar');
      }
    }
  }
}
