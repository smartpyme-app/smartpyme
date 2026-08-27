import { ChangeDetectorRef, Component, OnInit, Type } from '@angular/core';

import { RegisterAbacoComponent } from './register-abaco.component';
import { RegisterSivarEconomicsComponent } from './register-sivar-economics.component';
import { RegisterOnvoComponent } from './register-onvo.component';
import { RegisterComponent } from './register.component';

/** Hostnames exactos que deben mostrar el formulario de registro ÁBACO. */
const ABACO_HOSTS = ['abaco.smartpyme.site'];

/** Hostnames exactos que deben mostrar el formulario de registro Sivar Economics. */
const SIVAR_HOSTS = ['sivareconomics.sp.site'];

/** Hostnames exactos que deben mostrar el formulario de registro ONVO. */
const ONVO_HOSTS = ['onvo.smartpyme.site'];

@Component({
  selector: 'app-register-entry',
  template:
    '<ng-container *ngComponentOutlet="activeRegisterComponent"></ng-container>',
})
export class RegisterEntryComponent implements OnInit {
  activeRegisterComponent: Type<
    RegisterComponent | RegisterAbacoComponent | RegisterSivarEconomicsComponent | RegisterOnvoComponent
  > = RegisterComponent;

  constructor(private cdr: ChangeDetectorRef) { }

  ngOnInit(): void {
    if (typeof window !== 'undefined') {
      const host = window.location.hostname.toLowerCase();
      const esAbaco = ABACO_HOSTS.some((h) => host === h);
      const esSivar = SIVAR_HOSTS.some((h) => host === h);
      const esOnvo = ONVO_HOSTS.some((h) => host === h);

      console.log('[RegisterEntry] Host detectado:', host, '→ esAbaco:', esAbaco, '→ esSivar:', esSivar, '→ esOnvo:', esOnvo);

      if (esAbaco) {
        this.activeRegisterComponent = RegisterAbacoComponent;
      } else if (esSivar) {
        this.activeRegisterComponent = RegisterSivarEconomicsComponent;
      } else if (esOnvo) {
        this.activeRegisterComponent = RegisterOnvoComponent;
      } else {
        this.activeRegisterComponent = RegisterComponent;
      }

      // Forzar detección de cambios para que *ngComponentOutlet refleje
      // el componente correcto desde el primer render.
      this.cdr.detectChanges();
    }
  }
}
