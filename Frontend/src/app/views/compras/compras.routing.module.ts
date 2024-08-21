import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { ComprasComponent } from '../compras/compras.component';
import { FacturacionCompraComponent } from '../compras/facturacion/facturacion-compra.component';
import { FacturacionCompraConsignaComponent } from '../compras/facturacion/facturacion-consigna/facturacion-compra-consigna.component';
import { CompraComponent } from '../compras/compra/compra.component';
import { DevolucionesComprasComponent } from '../compras/devoluciones/devoluciones-compras.component';
import { DevolucionCompraComponent } from '../compras/devoluciones/devolucion/devolucion-compra.component';
import { DevolucionCompraNuevaComponent } from '../compras/devoluciones/devolucion-nueva/devolucion-compra-nueva.component';

import { ComprasRecurrentesComponent } from './recurrentes/compras-recurrentes.component';
import { AbonosComprasComponent } from './abonos/abonos-compras.component';

import { CotizacionesComprasComponent } from './cotizaciones/cotizaciones-compras.component';

import { ProveedoresComponent } from '../compras/proveedores/proveedores.component';
import { ProveedorComponent } from '../compras/proveedores/proveedor/proveedor.component';
import { ProveedorDetallesComponent } from '../compras/proveedores/proveedor-detalles/proveedor-detalles.component';
import { CuentasPagarComponent } from '../compras/proveedores/cuentas-pagar/cuentas-pagar.component';

import { HistorialComprasComponent } from '../reportes/compras/historial/historial-compras.component';
import { DetalleComprasComponent } from '../reportes/compras/detalle/detalle-compras.component';
import { CategoriasComprasComponent } from '../reportes/compras/categorias/categorias-compras.component';

import { GastosComponent } from './gastos/gastos.component';
import { GastosRecurrentesComponent } from './gastos/recurrentes/gastos-recurrentes.component';
import { GastoComponent } from './gastos/gasto/gasto.component';
import { GastoDetallesComponent } from './gastos/gasto-detalles/gasto-detalles.component';
import { GastosDashComponent } from './gastos/dash/gastos-dash.component';
import { GastosCategoriasComponent } from './gastos/categorias/gastos-categorias.component';
import { RetaceoComponent } from './retaceo/retaceo.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Compras',
    children: [

        { path: 'compras', component: ComprasComponent, title:'Compras' },
        { path: 'compra/crear', component: FacturacionCompraComponent, title:'Compra' },
        { path: 'compra/editar/:id', component: FacturacionCompraComponent, title:'Compra' },
        { path: 'compra/consigna/revisar/:id', component: FacturacionCompraConsignaComponent, title: 'Compra consigna'},
        { path: 'compra/:id', component: CompraComponent, title:'Compra' },

        { path: 'compras/recurrentes', component: ComprasRecurrentesComponent, title:'Compras recurrentes' },
        { path: 'compras/abonos', component: AbonosComprasComponent, title:'Abonos de compra' },

        { path: 'ordenes-de-compras', component: CotizacionesComprasComponent, title: 'Ordenes de compra' },
        { path: 'orden-de-compra/crear', component: FacturacionCompraComponent, title: 'Orden de compra' },
        { path: 'orden-de-compra/:id', component: CompraComponent, title: 'Orden de compra' },

        { path: 'devoluciones/compras', component: DevolucionesComprasComponent, title: 'Devoluciones de compras'},
        { path: 'devolucion/compra/:id', component: DevolucionCompraComponent, title: 'Devolución de compra'},
        { path: 'devolucion-compra/nueva', component: DevolucionCompraNuevaComponent, title: 'Devolución de compra'},
        

        { path: 'proveedores', component: ProveedoresComponent, title:'Proveedores' },
        { path: 'proveedor/crear', component: ProveedorComponent, title:'Proveedor' },
        { path: 'proveedor/detalles/:id', component: ProveedorDetallesComponent, title:'Proveedor' },
        { path: 'proveedor/editar/:id', component: ProveedorComponent, title:'Proveedor' },
        { path: 'proveedores/cuentas-pagar', component: CuentasPagarComponent, title:'Cuentas por pagar' },
       
        { path: 'reporte/compras/historial', component: HistorialComprasComponent },
        { path: 'reporte/compras/detalle', component: DetalleComprasComponent },
        { path: 'reporte/compras/categorias', component: CategoriasComprasComponent },

        { path: 'gastos', component: GastosComponent, title:'Gastos' },
        { path: 'gastos/recurrentes', component: GastosRecurrentesComponent, title:'Gastos recurrentes' },
        { path: 'gasto/detalles/:id', component: GastoDetallesComponent, title:'Gasto' },
        { path: 'gasto/:id', component: GastoComponent, title:'Gasto' },
        { path: 'gastos/dash', component: GastosDashComponent },
        { path: 'gastos/categorias', component: GastosCategoriasComponent },
        
        { path: 'retaceo', component: RetaceoComponent},

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ComprasRoutingModule { }
