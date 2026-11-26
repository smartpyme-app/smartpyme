import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';

/** ponytail: placeholder hasta Task 6; evita 404 en crear/ver/editar. */
@Component({
    selector: 'app-programada-stub',
    standalone: true,
    imports: [RouterModule],
    template: `
        <section class="bg-white p-4 rounded mb-3">
            <h2 class="page-heading">Facturas Programadas</h2>
            <p class="text-muted mb-3">El formulario y el detalle se implementan en la siguiente tarea.</p>
            <a routerLink="/ventas/programadas" class="btn btn-light text-danger">
                <i class="fa fa-arrow-left"></i> Regresar al listado
            </a>
        </section>
    `,
})
export class ProgramadaStubComponent {}