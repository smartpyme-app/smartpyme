import { Component, OnInit, Type } from '@angular/core';

import { RegisterAbacoComponent } from './register-abaco.component';
import { RegisterComponent } from './register.component';

@Component({
  selector: 'app-register-entry',
  standalone: false,
  template:
    '<ng-container *ngComponentOutlet="activeRegisterComponent"></ng-container>',
})
export class RegisterEntryComponent implements OnInit {
  activeRegisterComponent: Type<RegisterComponent | RegisterAbacoComponent> =
    RegisterComponent;

  ngOnInit(): void {
    if (typeof window !== 'undefined') {
      const host = window.location.hostname.toLowerCase();
      console.log('[RegisterEntry] Host detectado:', host);

      if (host.includes('abaco')) {
        this.activeRegisterComponent = RegisterAbacoComponent;
        console.log('[RegisterEntry] Cargando RegisterAbacoComponent');
      } else {
        this.activeRegisterComponent = RegisterComponent;
        console.log('[RegisterEntry] Cargando RegisterComponent estándar');
      }
    }
  }
}
