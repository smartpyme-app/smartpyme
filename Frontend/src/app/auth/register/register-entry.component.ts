import { Component, Type } from '@angular/core';

import { RegisterAbacoComponent } from './register-abaco.component';
import { RegisterComponent } from './register.component';

function resolveRegisterComponent(): Type<RegisterComponent | RegisterAbacoComponent> {
  const host =
    typeof window !== 'undefined' ? window.location.hostname.toLowerCase() : '';
  if (host.startsWith('abaco.')) {
    return RegisterAbacoComponent;
  }
  return RegisterComponent;
}

@Component({
  selector: 'app-register-entry',
  template:
    '<ng-container *ngComponentOutlet="activeRegisterComponent"></ng-container>',
})
export class RegisterEntryComponent {
  readonly activeRegisterComponent = resolveRegisterComponent();
}
