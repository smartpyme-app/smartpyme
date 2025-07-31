import { NgModule, isDevMode } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';
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
import { TourNgxBootstrapModule } from 'ngx-ui-tour-ngx-bootstrap';

import { NotifierModule } from 'angular-notifier';
import { AlertService } from '@services/alert.service';
import { MHService } from './services/MH.service';
import { ApiService } from '@services/api.service';
import { SumPipe } from '@pipes/sum.pipe';

import { SharedModule } from './shared/shared.module';
import { AppComponent } from './app.component';

import { AuthModule } from './auth/auth.module';
import { DashModule } from '@views/dash/dash.module';
import { LayoutModule } from '@layout/layout.module';
import { ReactiveFormsModule } from '@angular/forms';
// Super Admin
import { SuperAdminModule } from '@views/super-admin/super-admin.module';

// Organizacion Admin
import { OrganizacionesAdminModule } from '@views/organizaciones-admin/organizaciones-admin.module';

// Ventas
import { VentasModule } from '@views/ventas/ventas.module';
import { ClientesModule } from '@views/ventas/clientes/clientes.module';
import { FacturacionModule } from '@views/ventas/facturacion/facturacion.module';

// Inventario
import { InventarioModule } from '@views/inventario/inventario.module';

// Compras
import { ComprasModule } from '@views/compras/compras.module';
import { ProveedoresModule } from '@views/compras/proveedores/proveedores.module';

// Contabilidad
import { ContabilidadModule } from '@views/contabilidad/contabilidad.module';


// Planillas
import { PlanillasModule } from '@views/planillas/planillas.module';


// Paquetes
import { PaquetesModule } from '@views/paquetes/paquetes.module';

// Proyectos
import { ProyectosModule } from '@views/proyectos/proyectos.module';

 // Admin
  import { AdminModule } from '@views/admin/admin.module';
  import { ReportesModule } from '@views/reportes/reportes.module';
  import { CitasModule } from '@views/citas/citas.module';
  import { HasPermissionDirective } from './directives/has-permission.directive';
  import { RoleGuard } from './guards/role.guard';
  import { PermissionGuard } from './guards/permission.guard';
  import { ServiceWorkerModule } from '@angular/service-worker';

@NgModule({
  declarations: [
    AppComponent,
    HasPermissionDirective
  ],
  imports: [
    BrowserModule,
    HttpClientModule,
    TourNgxBootstrapModule,
    AppRoutingModule,
    ModalModule.forRoot(),
    NotifierModule.withConfig({ position: { horizontal: { position: 'middle' } }, theme: 'material' }),
    SharedModule,
    QuicklinkModule,
    LayoutModule,
    AuthModule,
    DashModule,
    SuperAdminModule,
    VentasModule,
    FacturacionModule,
    ClientesModule,
    InventarioModule,
    ComprasModule,
    ProveedoresModule,
    ContabilidadModule,
    AdminModule,
    ReportesModule,
    CitasModule,
    PaquetesModule,
    ProyectosModule,
    PlanillasModule,
    ReactiveFormsModule ,
    ServiceWorkerModule.register('ngsw-worker.js', {
      enabled: !isDevMode(),
      // Register the ServiceWorker as soon as the application is stable
      // or after 30 seconds (whichever comes first).
      registrationStrategy: 'registerWhenStable:30000'
    }),
  ],
  providers: [{ provide: HTTP_INTERCEPTORS, useClass: JwtInterceptor, multi: true },
                { provide: HTTP_INTERCEPTORS, useClass: AuthorizationInterceptor, multi: true },
                AuthGuard, AdminGuard, CitasGuard, SuperAdminGuard, SubscriptionGuard, RoleGuard, PermissionGuard, AlertService, ApiService,
                MHService, SumPipe, provideEnvironmentNgxMask()],
  bootstrap: [AppComponent]
})

export class AppModule { }
