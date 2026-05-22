import { Component, OnInit, Type } from '@angular/core';

import { LoginAbacoComponent } from './login-abaco.component';
import { LoginComponent } from './login.component';

@Component({
  selector: 'app-login-entry',
  standalone: false,
  template:
    '<ng-container *ngComponentOutlet="activeLoginComponent"></ng-container>',
})
export class LoginEntryComponent implements OnInit {
  activeLoginComponent: Type<LoginComponent | LoginAbacoComponent> = LoginComponent;

  ngOnInit(): void {
    if (typeof window !== 'undefined') {
      const host = window.location.hostname.toLowerCase();
      console.log('[LoginEntry] Host detectado:', host);

      if (host.includes('abaco')) {
        this.activeLoginComponent = LoginAbacoComponent;
        console.log('[LoginEntry] Cargando LoginAbacoComponent');
      } else {
        this.activeLoginComponent = LoginComponent;
        console.log('[LoginEntry] Cargando LoginComponent estándar');
      }
    }
  }
}
