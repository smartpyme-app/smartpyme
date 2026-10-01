import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { PacientesComponent } from './pacientes/pacientes.component';
import { PacienteFormComponent } from './pacientes/paciente-form.component';
import { PacienteFichaComponent } from './pacientes/paciente-ficha.component';
import { ProfesionalesComponent } from './profesionales/profesionales.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
      { path: 'clinica/pacientes', component: PacientesComponent, canActivate: [FuncionalidadGuard], data: { funcionalidadSlug: 'clinica-pacientes' }, title: 'Pacientes' },
      { path: 'clinica/pacientes/nuevo', component: PacienteFormComponent, canActivate: [FuncionalidadGuard], data: { funcionalidadSlug: 'clinica-pacientes' }, title: 'Nuevo paciente' },
      { path: 'clinica/pacientes/:id/editar', component: PacienteFormComponent, canActivate: [FuncionalidadGuard], data: { funcionalidadSlug: 'clinica-pacientes' }, title: 'Editar paciente' },
      { path: 'clinica/pacientes/:id', component: PacienteFichaComponent, canActivate: [FuncionalidadGuard], data: { funcionalidadSlug: 'clinica-pacientes' }, title: 'Ficha del paciente' },
      { path: 'clinica/profesionales', component: ProfesionalesComponent, canActivate: [FuncionalidadGuard], data: { funcionalidadSlug: 'clinica-profesionales' }, title: 'Profesionales' },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ClinicaRoutingModule {}
