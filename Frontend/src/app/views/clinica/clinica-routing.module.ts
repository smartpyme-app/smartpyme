import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { PacientesComponent } from './pacientes/pacientes.component';
import { PacienteFormComponent } from './pacientes/paciente-form.component';
import { PacienteFichaComponent } from './pacientes/paciente-ficha.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    canActivate: [FuncionalidadGuard],
    data: { funcionalidadSlug: 'clinica-pacientes' },
    children: [
      { path: 'clinica/pacientes', component: PacientesComponent, title: 'Pacientes' },
      { path: 'clinica/pacientes/nuevo', component: PacienteFormComponent, title: 'Nuevo paciente' },
      { path: 'clinica/pacientes/:id/editar', component: PacienteFormComponent, title: 'Editar paciente' },
      { path: 'clinica/pacientes/:id', component: PacienteFichaComponent, title: 'Ficha del paciente' },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ClinicaRoutingModule {}
