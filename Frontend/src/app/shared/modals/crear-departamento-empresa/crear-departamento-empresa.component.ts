
import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-departamento',
    templateUrl: './crear-departamento-empresa.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearDepartamentoComponent extends BaseModalComponent implements OnInit {

    public departamento: any = {};
    @Input() id_departamento: any = null;
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
        if(this.id_departamento){
            this.loading = true;
            this.departamento.activo = 1;
            this.apiService.read('departamentosEmpresa/', this.id_departamento).subscribe(departamento => {
                this.departamento = departamento;
                this.loading = false;
            }, error => {
                this.alertService.error(error); 
                this.loading = false;
            });
        } else {
            this.departamento = {};
            this.departamento.estado = 1;
            this.departamento.id_empresa = this.apiService.auth_user().id_empresa;
        }
        
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('departamentosEmpresa', this.departamento).subscribe(departamento => {
            this.update.emit(departamento);
            this.closeModal();
            this.saving = false;
            if(!this.id_departamento) {
                this.alertService.success('Departamento creado', 'El departamento ha sido agregado exitosamente.');
            } else {
                this.alertService.success('Departamento actualizado', 'El departamento ha sido actualizado exitosamente.');
            }
        }, error => {
            this.alertService.error(error); 
            this.saving = false; 
        });
    }
}
