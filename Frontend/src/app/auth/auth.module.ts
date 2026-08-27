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
import { LoginSivarEconomicsComponent } from './../auth/login/login-sivar-economics.component';
import { LoginOnvoComponent } from './../auth/login/login-onvo.component';
import { LoginEntryComponent } from './../auth/login/login-entry.component';
import { LockComponent } from './../auth/lock/lock.component';
import { RegisterComponent } from './../auth/register/register.component';
import { RegisterAbacoComponent } from './../auth/register/register-abaco.component';
import { RegisterSivarEconomicsComponent } from './../auth/register/register-sivar-economics.component';
import { RegisterOnvoComponent } from './../auth/register/register-onvo.component';
import { RegisterEntryComponent } from './../auth/register/register-entry.component';
import { PagoComponent } from './../auth/register/pago/pago.component';
import { PagoAbacoComponent } from './../auth/register/pago/pago-abaco.component';
import { PagoSivarEconomicsComponent } from './../auth/register/pago/pago-sivar-economics.component';
import { PagoOnvoComponent } from './../auth/register/pago/pago-onvo.component';
import { PagoEntryComponent } from './../auth/register/pago/pago-entry.component';
import { ForgetComponent } from './../auth/forget/forget.component';
import { ResetPasswordComponent } from './../auth/reset-password/reset-password.component';
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
    HeroVideoAutoplayDirective,
    ResetPasswordComponent,
  ],
  declarations: [
    LoginAbacoComponent,
    LoginSivarEconomicsComponent,
    LoginOnvoComponent,
    LoginEntryComponent,
    RegisterAbacoComponent,
    RegisterSivarEconomicsComponent,
    RegisterOnvoComponent,
    RegisterEntryComponent,
    PagoAbacoComponent,
    PagoSivarEconomicsComponent,
    PagoOnvoComponent,
    PagoEntryComponent,
  ],
  exports: [
    LoginComponent,
    LoginAbacoComponent,
    LoginSivarEconomicsComponent,
    LoginOnvoComponent,
    LoginEntryComponent,
    LockComponent,
    ForgetComponent,
    ResetPasswordComponent,
    RegisterComponent,
    RegisterAbacoComponent,
    RegisterSivarEconomicsComponent,
    RegisterOnvoComponent,
    RegisterEntryComponent,
    PagoComponent,
    PagoAbacoComponent,
    PagoSivarEconomicsComponent,
    PagoOnvoComponent,
    PagoEntryComponent,
    PaymentSuccessComponent
  ]
})
export class AuthModule { }
