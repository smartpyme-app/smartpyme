import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminGuard } from '@guards/admin.guard';
import { LayoutComponent } from '@layout/layout.component';

import { ConsumidorFinalComponent } from '@views/contabilidad/libro-iva/consumidor-final/consumidor-final.component';
import { ContribuyentesComponent } from '@views/contabilidad/libro-iva/contribuyentes/contribuyentes.component';
import { LibroComprasComponent } from '@views/contabilidad/libro-compras/libro-compras.component';
import { LibroAnuladosComponent } from '@views/contabilidad/libro-anulados/libro-anulados.component';
import { LibroComprasSujetosExcluidosComponent } from '@views/contabilidad/libro-compras-sujetos-excluidos/libro-compras-sujetos-excluidos.component';

import { PresupuestosComponent } from '@views/contabilidad/presupuestos/presupuestos.component';
import { PresupuestoComponent } from '@views/contabilidad/presupuestos/presupuesto/presupuesto.component';
import { PresupuestoDetallesComponent } from '@views/contabilidad/presupuestos/presupuesto-detalles/presupuesto-detalles.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        { path: 'presupuestos', component: PresupuestosComponent, title: 'Presupuestos'},

        { path: 'presupuesto/crear', component: PresupuestoComponent, title: 'Presupuesto'},
        { path: 'presupuesto/editar/:id', component: PresupuestoComponent, title: 'Presupuesto'},
        { path: 'presupuesto/detalles/:id', component: PresupuestoDetallesComponent, title: 'Presupuesto'},

        { path: 'libro-iva/contribuyentes', component: ContribuyentesComponent,
          title: 'Contribuyentes'},
        { path: 'libro-iva/consumidor-final', component: ConsumidorFinalComponent,
          title: 'Consumidor final'},
        { path: 'libro-compras', component: LibroComprasComponent, title: 'Libro de compras'},
        { path: 'libro-anulados', component: LibroAnuladosComponent, title: 'Libro de anulados'},
        { path: 'libro-compras-sujetos-excluidos', component: LibroComprasSujetosExcluidosComponent, title: 'Libro de compras sujetos excluidos'}
    ]
  }
]; 

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ContabilidadRoutingModule { }
