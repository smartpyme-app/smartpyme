import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { LazyImageDirective } from '../../directives/lazy-image.directive';
import { ClinicaRoutingModule } from './clinica-routing.module';
import { PacientesComponent } from './pacientes/pacientes.component';
import { PacienteFormComponent } from './pacientes/paciente-form.component';
import { PacienteFichaComponent } from './pacientes/paciente-ficha.component';
import { ProfesionalesComponent } from './profesionales/profesionales.component';

@NgModule({
  declarations: [PacientesComponent, PacienteFormComponent, PacienteFichaComponent, ProfesionalesComponent],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    ClinicaRoutingModule,
    PaginationComponent,
    LazyImageDirective,
    ModalModule.forRoot(),
    PopoverModule.forRoot(),
    TooltipModule.forRoot(),
  ],
})
export class ClinicaModule {}
