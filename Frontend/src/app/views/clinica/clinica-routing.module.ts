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
import { TratamientoFormComponent } from './tratamientos/tratamiento-form.component';
import { TratamientoFichaComponent } from './tratamientos/tratamiento-ficha.component';
import { DiagnosticosPacienteComponent } from './diagnosticos/diagnosticos-paciente.component';
import { DiagnosticoFormComponent } from './diagnosticos/diagnostico-form.component';
import { DiagnosticoFichaComponent } from './diagnosticos/diagnostico-ficha.component';

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
      { path: 'clinica/pacientes/:id/diagnosticos/nuevo', component: DiagnosticoFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.diagnosticos.crear' }, title: 'Nuevo diagnóstico' },
      { path: 'clinica/pacientes/:id/diagnosticos/:idDiagnostico/editar', component: DiagnosticoFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.diagnosticos.editar' }, title: 'Editar diagnóstico' },
      { path: 'clinica/pacientes/:id/diagnosticos/:idDiagnostico', component: DiagnosticoFichaComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.diagnosticos.ver' }, title: 'Diagnóstico clínico' },
      { path: 'clinica/pacientes/:id/diagnosticos', component: DiagnosticosPacienteComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.diagnosticos.ver|clinica.expediente.ver' }, title: 'Diagnósticos' },
      { path: 'clinica/pacientes/:id/tratamientos/nuevo', component: TratamientoFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.tratamientos.crear' }, title: 'Nuevo tratamiento' },
      { path: 'clinica/pacientes/:id/tratamientos/:idTratamiento/editar', component: TratamientoFormComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.tratamientos.editar' }, title: 'Editar tratamiento' },
      { path: 'clinica/pacientes/:id/tratamientos/:idTratamiento', component: TratamientoFichaComponent, canActivate: [FuncionalidadGuard, PermissionGuard], data: { funcionalidadSlug: 'clinica-pacientes', permission: 'clinica.tratamientos.ver' }, title: 'Tratamiento clínico' },
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
