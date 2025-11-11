import { NgModule, isDevMode } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HTTP_INTERCEPTORS, provideHttpClient, withInterceptorsFromDi } from '@angular/common/http';
import { AuthorizationInterceptor } from '@services/Authorization/authorization.interceptor';

import { AppRoutingModule } from './app.routing.module';
import { JwtInterceptor } from '@services/JwtInterceptor';
import { QuicklinkModule } from 'ngx-quicklink';
import { provideEnvironmentNgxMask } from 'ngx-mask';
import { ModalModule } from 'ngx-bootstrap/modal';
import { AuthGuard } from '@guards/auth.guard';
import { AdminGuard } from '@guards/admin.guard';
import { CitasGuard } from '@guards/citas.guard';
import { SuperAdminGuard } from '@guards/super-admin.guard';
import { SubscriptionGuard } from '@guards/SuscriptionGuard.guard';
import { UsuariosGuard } from '@guards/usuarios.guard';

import { AlertService } from '@services/alert.service';
import { MHService } from './services/MH.service';
import { ApiService } from '@services/api.service';
import { ConstantsService } from '@services/constants.service';
import { SumPipe } from '@pipes/sum.pipe';

import { SharedModule } from './shared/shared.module';
import { LayoutModule } from '@layout/layout.module';
import { ReactiveFormsModule } from '@angular/forms';

// Los módulos de funcionalidades están configurados para lazy loading en app.routing.module.ts
// No deben importarse aquí para mantener los beneficios del lazy loading
import { HasPermissionDirective } from './directives/has-permission.directive';
import { RoleGuard } from './guards/role.guard';
import { PermissionGuard } from './guards/permission.guard';
import { ServiceWorkerModule } from '@angular/service-worker';

@NgModule({ 
    declarations: [
    ],
    imports: [
        BrowserModule,
        CommonModule,
        FormsModule,
        RouterModule,
        AppRoutingModule,
        ModalModule.forRoot(),
        SharedModule,
        QuicklinkModule,
        LayoutModule,
        ReactiveFormsModule,
        HasPermissionDirective, // Directiva standalone
        ServiceWorkerModule.register('ngsw-worker.js', {
            enabled: !isDevMode(),
            // Register the ServiceWorker as soon as the application is stable
            // or after 30 seconds (whichever comes first).
            registrationStrategy: 'registerWhenStable:30000'
        })], 
  exports: [],
  providers: [{ provide: HTTP_INTERCEPTORS, useClass: JwtInterceptor, multi: true },
        { provide: HTTP_INTERCEPTORS, useClass: AuthorizationInterceptor, multi: true },
        AuthGuard, AdminGuard, CitasGuard, SuperAdminGuard, SubscriptionGuard, RoleGuard, PermissionGuard, UsuariosGuard, AlertService, ApiService,
        ConstantsService, MHService, SumPipe, CurrencyPipe, DatePipe, provideEnvironmentNgxMask(), provideHttpClient(withInterceptorsFromDi())] })

export class AppModule { }
