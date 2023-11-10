import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FocusModule } from 'angular2-focus';

import { LoginComponent } from './../auth/login/login.component';
import { LockComponent } from './../auth/lock/lock.component';
import { RegisterComponent } from './../auth/register/register.component';
import { ForgetComponent } from './../auth/forget/forget.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    FocusModule.forRoot()
  ],
  declarations: [
  	LoginComponent,
    LockComponent,
    ForgetComponent,
    RegisterComponent
  ],
  exports: [
  	LoginComponent,
    LockComponent,
    ForgetComponent,
    RegisterComponent
  ]
})
export class AuthModule { }
