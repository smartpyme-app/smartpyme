import { ChangeDetectorRef, Component, OnInit, Type } from '@angular/core';

import { RegisterAbacoComponent } from './register-abaco.component';
import { RegisterComponent } from './register.component';

/** Hostnames exactos que deben mostrar el formulario de registro ÁBACO. */
const ABACO_HOSTS = ['abaco.smartpyme.site'];

@Component({
  selector: 'app-register-entry',
  template:
    '<ng-container *ngComponentOutlet="activeRegisterComponent"></ng-container>',
})
export class RegisterEntryComponent implements OnInit {
  activeRegisterComponent: Type<RegisterComponent | RegisterAbacoComponent> =
    RegisterComponent;

  constructor(private cdr: ChangeDetectorRef) { }

  ngOnInit(): void {
    if (typeof window !== 'undefined') {
      const host = window.location.hostname.toLowerCase();
      const esAbaco = ABACO_HOSTS.some((h) => host === h);

      console.log('[RegisterEntry] Host detectado:', host, '→ esAbaco:', esAbaco);

      this.activeRegisterComponent = esAbaco
        ? RegisterAbacoComponent
        : RegisterComponent;

      // Forzar detección de cambios para que *ngComponentOutlet refleje
      // el componente correcto desde el primer render.
      this.cdr.detectChanges();
    }
  }
}
