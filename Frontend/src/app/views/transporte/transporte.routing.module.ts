import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { FletesComponent } from './fletes/fletes.component';
import { FleteComponent } from './fletes/flete/flete.component';

import { FlotasComponent } from './flotas/flotas.component';
import { FlotaComponent } from './flotas/flota/flota.component';

import { MotoristasComponent } from './motoristas/motoristas.component';
import { EmpleadoComponent } from '../empleados/empleado/empleado.component';

import { MantenimientosComponent } from './mantenimientos/mantenimientos.component';
import { MantenimientoComponent } from './mantenimientos/mantenimiento/mantenimiento.component';

import { RepuestosComponent } from './repuestos/repuestos.component';
import { ProductoComponent } from '../inventario/productos/producto/producto.component';

import { MotoristasFletesComponent } from '../reportes/fletes/motoristas/motoristas-fletes.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: '',
    children: [
        { path: 'fletes', component: FletesComponent },
        { path: 'flete/:id', component: FleteComponent },

        { path: 'flotas', component: FlotasComponent },
        { path: 'flota/:id', component: FlotaComponent },

        { path: 'motoristas', component: MotoristasComponent },
        { path: 'motorista/:id', component: EmpleadoComponent },

        { path: 'mantenimientos', component: MantenimientosComponent },
        { path: 'mantenimiento/:id', component: MantenimientoComponent },

        { path: 'repuestos', component: RepuestosComponent },
        { path: 'repuesto/:id', component: ProductoComponent },

        { path: 'reporte/motoristas/fletes', component: MotoristasFletesComponent },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class TransporteRoutingModule { }
