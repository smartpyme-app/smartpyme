import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { AdminGuard } from '../../guards/admin.guard';
import { FacturacionVersionGuard } from '../../guards/facturacion-version.guard';
import { CotizacionesComponent } from '@views/ventas/cotizaciones/cotizaciones.component';
import { SolicitudesCompraComponent } from '@views/ventas/solicitudes-compra/solicitudes-compra.component';

import { VentasComponent } from '@views/ventas/ventas.component';
import { VentaComponent } from '@views/ventas/venta/venta.component';

import { RecurrentesComponent } from '@views/ventas/recurrentes/recurrentes.component';
import { AbonosVentasComponent } from '@views/ventas/abonos/abonos-ventas.component';

import { CanalesComponent } from '@views/ventas/canales/canales.component';
import { FormasDePagoComponent } from '@views/ventas/formas-de-pago/formas-de-pago.component';
import { ImpuestosComponent } from '@views/ventas/impuestos/impuestos.component';
import { DocumentosComponent } from '@views/ventas/documentos/documentos.component';

import { DevolucionesVentasComponent } from '@views/ventas/devoluciones/devoluciones-ventas.component';
import { DevolucionVentaComponent } from '@views/ventas/devoluciones/devolucion/devolucion-venta.component';
import { DevolucionVentaNuevaComponent } from '@views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component';
import { FacturacionComponent } from '@views/ventas/facturacion/facturacion-tienda/facturacion.component';
import { FacturacionConsignaComponent } from '@views/ventas/facturacion/facturacion-consigna/facturacion-consigna.component';
import { FacturacionV2Component } from '@views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component';

import { ClientesComponent } from '@views/ventas/clientes/clientes.component';
import { CuentasCobrarComponent } from '@views/ventas/clientes/cuentas-cobrar/cuentas-cobrar.component';
import { ClienteComponent } from '@views/ventas/clientes/cliente/cliente.component';
import { ClienteDetallesComponent } from '@views/ventas/clientes/cliente-detalles/cliente-detalles.component';
import { ClientesDashComponent } from '@views/ventas/clientes/dash/clientes-dash.component';
import { ClienteVista360Component } from '@views/ventas/clientes/cliente-vista-360/cliente-vista-360.component';

import { HistorialVentasComponent } from '@views/reportes/ventas/historial/historial-ventas.component';
import { DetalleVentasComponent } from '@views/reportes/ventas/detalle/detalle-ventas.component';
import { CategoriasVentasComponent } from '@views/reportes/ventas/categorias/categorias-ventas.component';
import { DocumentoHistorialComponent } from '@views/ventas/documentos/historial/documento-historial.component';
import { OrdenesProduccionComponent } from '@views/ventas/orden_produccion/ordenes-produccion.component';
import { CrearOrdenProduccionComponent } from '@views/ventas/orden_produccion/crear_orden/crear-orden-produccion.component';


const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Ventas',
    children: [

        { path: 'ventas', canActivate: [AdminGuard], component: VentasComponent, title: 'Ventas'},
        { path: 'venta/crear', canActivate: [FacturacionVersionGuard], component: FacturacionComponent, title: 'Facturación'},
        { path: 'ventas-v2/crear', component: FacturacionV2Component, title: 'Facturación V2'},
        { path: 'venta/consigna/revisar/:id', component: FacturacionConsignaComponent, title: 'Facturación consigna'},
        { path: 'venta/:id', component: VentaComponent, title: 'Venta'},

        { path: 'ventas/recurrentes', canActivate: [AdminGuard], component: RecurrentesComponent, title: 'Abonos de ventas'},
        { path: 'ventas/abonos', canActivate: [AdminGuard], component: AbonosVentasComponent, title: 'Abonos de ventas'},

        { path: 'cotizaciones', component: CotizacionesComponent, title: 'Cotizaciones' },
        { path: 'cotizacion/crear', component: FacturacionComponent, title: 'Cotización' },
        { path: 'cotizacion/editar/:id', component: FacturacionComponent, title: 'Cotización' },

        { path: 'solicitudes-compra', component: SolicitudesCompraComponent, title: 'Solicitudes de compra' },

        { path: 'ordenes/produccion', component: OrdenesProduccionComponent, title: 'Órdenes de producción' },
        { path: 'orden-produccion/crear/:id', component: CrearOrdenProduccionComponent, title: 'Crear orden de producción' },
        { path: 'orden-produccion/editar/:id', component: CrearOrdenProduccionComponent, title: 'Editar orden de producción' },
        { path: 'orden-produccion/detalles/:id', component: CrearOrdenProduccionComponent, title: 'Detalle orden de producción' },
    //
        { path: 'canales', canActivate: [AdminGuard], component: CanalesComponent, title: 'Canales de venta'},
        { path: 'formas-de-pago', canActivate: [AdminGuard], component: FormasDePagoComponent, title: 'Formas de pago'},
        { path: 'impuestos', canActivate: [AdminGuard], component: ImpuestosComponent, title: 'Impuestos'},
        { path: 'documentos', canActivate: [AdminGuard], component: DocumentosComponent, title: 'Documentos'},
        { path: 'documento/historial/:nombre', canActivate: [AdminGuard], component: DocumentoHistorialComponent, title: 'Historial de documentos'},

        { path: 'devoluciones/ventas', component: DevolucionesVentasComponent, title: 'Devoluciones de ventas'},
        { path: 'devolucion/venta/:id', component: DevolucionVentaComponent, title: 'Devolución de venta'},
        { path: 'devolucion-venta/nueva', component: DevolucionVentaNuevaComponent, title: 'Devolución de venta'},

    // Clientes
        { path: 'clientes', component: ClientesComponent, title: 'Clientes'},
        { path: 'cliente/detalles/:id', component: ClienteDetallesComponent, title: 'Cliente'},
        { path: 'cliente/vista-360/:id', component: ClienteVista360Component, title: 'Vista 360° Cliente'},
        { path: 'cliente/crear', component: ClienteComponent, title: 'Cliente'},
        { path: 'cliente/editar/:id', component: ClienteComponent, title: 'Cliente'},
        { path: 'clientes/cuentas-cobrar', component: CuentasCobrarComponent, title: 'Cuentas por cobrar' },
        { path: 'clientes/crm', component: ClientesDashComponent },

    // Reportes
        { path: 'reporte/ventas/historial', canActivate: [AdminGuard], component: HistorialVentasComponent },
        { path: 'reporte/ventas/detalle', canActivate: [AdminGuard], component: DetalleVentasComponent },
        { path: 'reporte/ventas/categorias', canActivate: [AdminGuard], component: CategoriasVentasComponent },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class VentasRoutingModule { }
