import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FocusModule } from 'angular2-focus';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask'
import { PipesModule } from '@pipes/pipes.module';

import { LoginComponent } from './../auth/login/login.component';
import { LockComponent } from './../auth/lock/lock.component';
import { RegisterComponent } from './../auth/register/register.component';
import { PagoComponent } from './../auth/register/pago/pago.component';
import { ForgetComponent } from './../auth/forget/forget.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    NgSelectModule,
    PipesModule,
    NgxMaskDirective, NgxMaskPipe,
    FocusModule.forRoot()
  ],
  declarations: [
  	LoginComponent,
    LockComponent,
    ForgetComponent,
    RegisterComponent,
    PagoComponent
  ],
  exports: [
  	LoginComponent,
    LockComponent,
    ForgetComponent,
    RegisterComponent,
    PagoComponent
  ]
})
export class AuthModule { }
