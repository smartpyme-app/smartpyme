import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask';
import { PipesModule } from '@pipes/pipes.module';

import { LoginComponent } from './../auth/login/login.component';
import { LoginAbacoComponent } from './../auth/login/login-abaco.component';
import { LoginEntryComponent } from './../auth/login/login-entry.component';
import { LockComponent } from './../auth/lock/lock.component';
import { RegisterComponent } from './../auth/register/register.component';
import { RegisterAbacoComponent } from './../auth/register/register-abaco.component';
import { RegisterEntryComponent } from './../auth/register/register-entry.component';
import { PagoComponent } from './../auth/register/pago/pago.component';
import { PagoAbacoComponent } from './../auth/register/pago/pago-abaco.component';
import { PagoEntryComponent } from './../auth/register/pago/pago-entry.component';
import { ForgetComponent } from './../auth/forget/forget.component';
import { PaymentSuccessComponent } from './../auth/register/pago/payment-success.component';
import { HeroVideoAutoplayDirective } from './shared/hero-video-autoplay.directive';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    NgSelectModule,
    PipesModule,
    NgxMaskDirective, NgxMaskPipe,
    LoginComponent,
    LockComponent,
    ForgetComponent,
    RegisterComponent,
    PagoComponent,
    PaymentSuccessComponent,
  ],
  declarations: [
    LoginAbacoComponent,
    LoginEntryComponent,
    RegisterAbacoComponent,
    RegisterEntryComponent,
    PagoAbacoComponent,
    PagoEntryComponent,
    HeroVideoAutoplayDirective,
  ],
  exports: [
    LoginComponent,
    LoginAbacoComponent,
    LoginEntryComponent,
    LockComponent,
    ForgetComponent,
    RegisterComponent,
    RegisterAbacoComponent,
    RegisterEntryComponent,
    PagoComponent,
    PagoAbacoComponent,
    PagoEntryComponent,
    PaymentSuccessComponent
  ]
})
export class AuthModule { }
