import { Component, Type } from '@angular/core';

import { LoginAbacoComponent } from './login-abaco.component';
import { LoginComponent } from './login.component';

function resolveLoginComponent(): Type<LoginComponent | LoginAbacoComponent> {
  const host =
    typeof window !== 'undefined'
      ? window.location.hostname.toLowerCase()
      : '';
  if (host.startsWith('abaco.')) {
    return LoginAbacoComponent;
  }
  return LoginComponent;
}

@Component({
  selector: 'app-login-entry',
  template:
    '<ng-container *ngComponentOutlet="activeLoginComponent"></ng-container>',
})
export class LoginEntryComponent {
  readonly activeLoginComponent = resolveLoginComponent();
}
