import { Component, OnInit, TemplateRef, Output, EventEmitter, DestroyRef, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-crear-cargo-empleado',
    templateUrl: './crear-cargo-empleado.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearCargoEmpleadoComponent implements OnInit {

    public cargo: any = {};
    @Output() update = new EventEmitter();
    public loading = false;

    modalRef?: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        this.cargo = {};
        this.modalRef = this.modalService.show(template, { class: 'modal-sm', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.cargo.empresa_id = this.apiService.auth_user().empresa_id;
        this.apiService.store('empleados/cargo', this.cargo)
            .pipe(this.untilDestroyed())
            .subscribe(cargo => {
            this.update.emit(cargo);
            this.modalRef?.hide();
            this.loading = false;
            this.alertService.success('Cargo creado', 'El cargo ha sido agregado.');
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
