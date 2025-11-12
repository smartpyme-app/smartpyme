import { bootstrapApplication } from '@angular/platform-browser';
import { importProvidersFrom } from '@angular/core';
import { provideRouter, withPreloading } from '@angular/router';
import { provideHttpClient, withInterceptorsFromDi, HTTP_INTERCEPTORS } from '@angular/common/http';
import { provideAnimations } from '@angular/platform-browser/animations';
import { NoPreloading } from '@angular/router';
import { provideEnvironmentNgxMask } from 'ngx-mask';
import { ModalModule } from 'ngx-bootstrap/modal';
import { ServiceWorkerModule } from '@angular/service-worker';
import { isDevMode } from '@angular/core';

import { AppComponent } from './app/app.component';
import { routes } from './app/app.routing.module';
import { JwtInterceptor } from './app/services/JwtInterceptor';
import { AuthorizationInterceptor } from './app/services/Authorization/authorization.interceptor';
import { AuthGuard } from './app/guards/auth.guard';
import { AdminGuard } from './app/guards/admin.guard';
import { CitasGuard } from './app/guards/citas.guard';
import { SuperAdminGuard } from './app/guards/super-admin.guard';
import { SubscriptionGuard } from './app/guards/SuscriptionGuard.guard';
import { UsuariosGuard } from './app/guards/usuarios.guard';
import { RoleGuard } from './app/guards/role.guard';
import { PermissionGuard } from './app/guards/permission.guard';
import { AlertService } from './app/services/alert.service';
import { ApiService } from './app/services/api.service';
import { ConstantsService } from './app/services/constants.service';
import { MHService } from './app/services/MH.service';
import { SumPipe } from './app/pipes/sum.pipe';
import { CurrencyPipe, DatePipe } from '@angular/common';
import { SharedModule } from './app/shared/shared.module';
import { LayoutModule } from './app/layout/layout.module';

bootstrapApplication(AppComponent, {
  providers: [
    importProvidersFrom(
      ModalModule.forRoot(),
      SharedModule,
      LayoutModule,
      ServiceWorkerModule.register('ngsw-worker.js', {
        enabled: !isDevMode(),
        registrationStrategy: 'registerWhenStable:30000'
      })
    ),
    provideRouter(routes, withPreloading(NoPreloading)),
    provideHttpClient(withInterceptorsFromDi()),
    provideAnimations(),
    provideEnvironmentNgxMask(),
    { provide: HTTP_INTERCEPTORS, useClass: JwtInterceptor, multi: true },
    { provide: HTTP_INTERCEPTORS, useClass: AuthorizationInterceptor, multi: true },
    AuthGuard,
    AdminGuard,
    CitasGuard,
    SuperAdminGuard,
    SubscriptionGuard,
    RoleGuard,
    PermissionGuard,
    UsuariosGuard,
    AlertService,
    ApiService,
    ConstantsService,
    MHService,
    SumPipe,
    CurrencyPipe,
    DatePipe
  ]
}).catch(err => console.error(err));
