import { Component, OnInit, Type } from '@angular/core';

import { LoginAbacoComponent } from './login-abaco.component';
import { LoginSivarEconomicsComponent } from './login-sivar-economics.component';
import { LoginOnvoComponent } from './login-onvo.component';
import { LoginContadoresComponent } from './login-contadores.component';
import { LoginComponent } from './login.component';
import { loginHostFromHostname } from './login-host';

@Component({
  selector: 'app-login-entry',
  standalone: false,
  template:
    '<ng-container *ngComponentOutlet="activeLoginComponent"></ng-container>',
})
export class LoginEntryComponent implements OnInit {
  activeLoginComponent: Type<LoginComponent | LoginAbacoComponent | LoginSivarEconomicsComponent | LoginOnvoComponent | LoginContadoresComponent> = LoginComponent;

  ngOnInit(): void {
    if (typeof window === 'undefined') {
      return;
    }

    const host = window.location.hostname;
    console.log('[LoginEntry] Host detectado:', host);

    switch (loginHostFromHostname(host)) {
      case 'abaco':
        this.activeLoginComponent = LoginAbacoComponent;
        break;
      case 'sivar':
        this.activeLoginComponent = LoginSivarEconomicsComponent;
        break;
      case 'onvo':
        this.activeLoginComponent = LoginOnvoComponent;
        break;
      case 'contadores':
        this.activeLoginComponent = LoginContadoresComponent;
        break;
      default:
        this.activeLoginComponent = LoginComponent;
    }
  }
}
