import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { PermissionGuard } from '@guards/permission.guard';
import { PacientesComponent } from './pacientes/pacientes.component';
import { PacienteFormComponent } from './pacientes/paciente-form.component';
import { PacienteFichaComponent } from './pacientes/paciente-ficha.component';
import { ProfesionalesComponent } from './profesionales/profesionales.component';
import { ExpedienteFichaComponent } from './expediente/expediente-ficha.component';
import { HistorialExpedienteComponent } from './historial/historial-expediente.component';
import { ConsultaFormComponent } from './consultas/consulta-form.component';
import { ConsultaFichaComponent } from './consultas/consulta-ficha.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
      { path: 'clinica/pacientes', component: PacientesComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.pacientes.ver' }, title: 'Pacientes' },
      { path: 'clinica/pacientes/nuevo', component: PacienteFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.pacientes.crear' }, title: 'Nuevo paciente' },
      { path: 'clinica/pacientes/:id/editar', component: PacienteFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.pacientes.editar' }, title: 'Editar paciente' },
      { path: 'clinica/pacientes/:id/expediente/historial', component: HistorialExpedienteComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.expediente.ver' }, title: 'Historial clínico' },
      { path: 'clinica/pacientes/:id/expediente', component: ExpedienteFichaComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.expediente.ver' }, title: 'Expediente clínico' },
      { path: 'clinica/pacientes/:id/consultas/nuevo', component: ConsultaFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.consultas.crear' }, title: 'Nueva consulta' },
      { path: 'clinica/pacientes/:id/consultas/:idConsulta/editar', component: ConsultaFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.consultas.editar' }, title: 'Editar consulta' },
      { path: 'clinica/pacientes/:id/consultas/:idConsulta', component: ConsultaFichaComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.consultas.ver' }, title: 'Consulta clínica' },
      { path: 'clinica/pacientes/:id', component: PacienteFichaComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.pacientes.ver' }, title: 'Ficha del paciente' },
      { path: 'clinica/profesionales', component: ProfesionalesComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-profesionales', permission: 'clinica.profesionales.ver' }, title: 'Profesionales' },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ClinicaRoutingModule {}
