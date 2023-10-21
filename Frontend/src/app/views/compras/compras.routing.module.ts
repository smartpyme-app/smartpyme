import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { ComprasComponent } from '../compras/compras.component';
import { CompraComponent } from '../compras/compra/compra.component';
import { DevolucionesComprasComponent } from '../compras/devoluciones/devoluciones-compras.component';
import { DevolucionCompraComponent } from '../compras/devoluciones/devolucion/devolucion-compra.component';
import { DevolucionCompraNuevaComponent } from '../compras/devoluciones/devolucion-nueva/devolucion-compra-nueva.component';

import { ProveedoresComponent } from '../compras/proveedores/proveedores.component';
import { ProveedorComponent } from '../compras/proveedores/proveedor/proveedor.component';
import { CuentasPagarComponent } from '../compras/proveedores/cuentas-pagar/cuentas-pagar.component';

import { HistorialComprasComponent } from '../reportes/compras/historial/historial-compras.component';
import { DetalleComprasComponent } from '../reportes/compras/detalle/detalle-compras.component';
import { CategoriasComprasComponent } from '../reportes/compras/categorias/categorias-compras.component';

import { GastosComponent } from './gastos/gastos.component';
import { GastoComponent } from './gastos/gasto/gasto.component';
import { GastosDashComponent } from './gastos/dash/gastos-dash.component';
import { GastosCategoriasComponent } from './gastos/categorias/gastos-categorias.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Compras',
    children: [
        { path: 'compras', component: ComprasComponent, title:'Compras' },
        { path: 'compra/:id', component: CompraComponent, title:'Compra' },
        { path: 'devoluciones/compras', component: DevolucionesComprasComponent, title:'Devoluciones de compras' },
        { path: 'devolucion-compra/:id', component: DevolucionCompraComponent, title:'Devolución de compra' },
        { path: 'devolucion-compra-nueva', component: DevolucionCompraNuevaComponent, title:'Devolución de compra' },
        { path: 'proveedores', component: ProveedoresComponent, title:'Proveedores' },
        { path: 'proveedores/cuentas-pagar', component: CuentasPagarComponent, title:'Cuentas por pagar' },
        { path: 'proveedor/:id', component: ProveedorComponent, title:'Proveedor' },
       
        { path: 'reporte/compras/historial', component: HistorialComprasComponent },
        { path: 'reporte/compras/detalle', component: DetalleComprasComponent },
        { path: 'reporte/compras/categorias', component: CategoriasComprasComponent },

        { path: 'gastos', component: GastosComponent, title:'Gastos' },
        { path: 'gasto/:id', component: GastoComponent, title:'Gasto' },
        { path: 'gastos/dash', component: GastosDashComponent },
        { path: 'gastos/categorias', component: GastosCategoriasComponent },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ComprasRoutingModule { }
