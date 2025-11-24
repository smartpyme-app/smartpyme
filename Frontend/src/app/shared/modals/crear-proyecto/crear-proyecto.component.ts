import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { CrearClienteComponent } from '../crear-cliente/crear-cliente.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-proyecto',
    templateUrl: './crear-proyecto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, CrearClienteComponent],
    
})
export class CrearProyectoComponent extends BaseModalComponent implements OnInit {

    public proyecto: any = {};
    public clientes: any = [];
    @Input() id_proyecto:any = null;
    @Output() update = new EventEmitter();
    public override loading = false;
    public override saving = false;

    constructor( 
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
    }

    override openModal(template: TemplateRef<any>) {
        if(!this.clientes.length){
            this.apiService.getAll('clientes/list')
                .pipe(this.untilDestroyed())
                .subscribe(clientes => {
                this.clientes = clientes;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        if(this.id_proyecto){
            this.apiService.read('proyecto/', this.id_proyecto)
                .pipe(this.untilDestroyed())
                .subscribe(proyecto => {
            this.proyecto = proyecto;
            this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.proyecto = {};
            this.proyecto.estado = 'En proceso';
            this.proyecto.id_usuario = this.apiService.auth_user().id;
            this.proyecto.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.proyecto.id_empresa = this.apiService.auth_user().id_empresa;
        }
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.proyecto.tipo = tipo;
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('proyecto', this.proyecto)
            .pipe(this.untilDestroyed())
            .subscribe(proyecto => {
            this.update.emit(proyecto);
            this.closeModal();
            this.saving = false;
            this.alertService.success('proyecto creado', 'El proyecto ha sido agregado.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

}
