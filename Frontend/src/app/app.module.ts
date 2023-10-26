import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';

import { AppRoutingModule } from './app.routing.module';
import { JwtInterceptor } from '@services/JwtInterceptor';
import { QuicklinkModule } from 'ngx-quicklink';

import { AuthGuard } from '@guards/auth.guard';
import { AdminGuard } from '@guards/admin.guard';

import { NotifierModule } from 'angular-notifier';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SumPipe } from '@pipes/sum.pipe';

import { SharedModule } from './shared/shared.module';
import { AppComponent } from './app.component';

import { AuthModule } from './auth/auth.module';

import { DashModule } from '@views/dash/dash.module';
import { LayoutModule } from '@layout/layout.module';

// Ventas
  import { OrdenesModule } from '@views/ordenes/ordenes.module';
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

// Transporte
  import { TransporteModule } from '@views/transporte/transporte.module';

// Creditos
  import { CreditosModule } from '@views/creditos/creditos.module';

// Empleados
  import { EmpleadosModule } from '@views/empleados/empleados.module';

 // Admin
  import { AdminModule } from '@views/admin/admin.module';
  import { ReportesModule } from '@views/reportes/reportes.module';


@NgModule({
  declarations: [
    AppComponent
  ],
  imports: [
    BrowserModule,
    HttpClientModule,
    AppRoutingModule,
    NotifierModule.withConfig({position: {horizontal:{ position:'middle' } }}),
    SharedModule,
    QuicklinkModule,
    LayoutModule,
    AuthModule,
    DashModule,
    OrdenesModule,
    VentasModule,
    FacturacionModule,
    ClientesModule,
    InventarioModule,
    ComprasModule,
    ProveedoresModule,
    ContabilidadModule,
    CreditosModule,
    EmpleadosModule,
    AdminModule,
    ReportesModule,
  ],
  providers: [{ provide: HTTP_INTERCEPTORS, useClass: JwtInterceptor, multi: true },
                AuthGuard, AdminGuard, AlertService, ApiService, SumPipe],
  bootstrap: [AppComponent]
})

export class AppModule { }
