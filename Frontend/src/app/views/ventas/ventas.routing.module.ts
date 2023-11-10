import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { CotizacionesComponent } from '@views/ventas/cotizaciones/cotizaciones.component';

import { VentasComponent } from '@views/ventas/ventas.component';
import { VentaComponent } from '@views/ventas/venta/venta.component';

import { CanalesComponent } from '@views/ventas/canales/canales.component';
import { DocumentosComponent } from '@views/ventas/documentos/documentos.component';

import { DevolucionesVentasComponent } from '@views/ventas/devoluciones/devoluciones-ventas.component';
import { DevolucionVentaComponent } from '@views/ventas/devoluciones/devolucion/devolucion-venta.component';
import { DevolucionVentaNuevaComponent } from '@views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component';
import { FacturacionComponent } from './facturacion/facturacion.component';

import { ClientesComponent } from '@views/ventas/clientes/clientes.component';
import { CuentasCobrarComponent } from '@views/ventas/clientes/cuentas-cobrar/cuentas-cobrar.component';
import { ClienteComponent } from '@views/ventas/clientes/cliente/cliente.component';
import { ClientesDashComponent } from '@views/ventas/clientes/dash/clientes-dash.component';

import { HistorialVentasComponent } from '@views/reportes/ventas/historial/historial-ventas.component';
import { DetalleVentasComponent } from '@views/reportes/ventas/detalle/detalle-ventas.component';
import { CategoriasVentasComponent } from '@views/reportes/ventas/categorias/categorias-ventas.component';


const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Ventas',
    children: [
        { path: 'facturacion', component: FacturacionComponent, title: 'Facturación'},

        { path: 'cotizaciones', component: CotizacionesComponent },

        { path: 'ventas', component: VentasComponent, title: 'Ventas'},
        { path: 'venta/:id', component: VentaComponent, title: 'Venta'},
    // 
        { path: 'canales', component: CanalesComponent, title: 'Canales de venta'},
        { path: 'documentos', component: DocumentosComponent, title: 'Documentos'},

        { path: 'devoluciones/ventas', component: DevolucionesVentasComponent, title: 'Devoluciones de ventas'},
        { path: 'devolucion/venta/:id', component: DevolucionVentaComponent, title: 'Devolución de venta'},
        { path: 'devolucion-venta/nueva', component: DevolucionVentaNuevaComponent, title: 'Devolución de venta'},
    
    // Clientes
        { path: 'clientes', component: ClientesComponent, title: 'Clientes'},
        { path: 'cliente/crear', component: ClienteComponent, title: 'Cliente'},
        { path: 'cliente/editar/:id', component: ClienteComponent, title: 'Cliente'},
        { path: 'clientes/cuentas-cobrar', component: CuentasCobrarComponent },
        { path: 'clientes/crm', component: ClientesDashComponent },

    // Reportes 
        { path: 'reporte/ventas/historial', component: HistorialVentasComponent },
        { path: 'reporte/ventas/detalle', component: DetalleVentasComponent },
        { path: 'reporte/ventas/categorias', component: CategoriasVentasComponent },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class VentasRoutingModule { }
