
import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-departamento',
  templateUrl: './crear-departamento-empresa.component.html'
})
export class CrearDepartamentoComponent implements OnInit {

    public departamento: any = {};
    @Input() id_departamento: any = null;
    @Output() update = new EventEmitter();
    public loading = false;
    public saving = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        if(this.id_departamento){
            this.loading = true;
            this.departamento.activo = 1;
            this.apiService.read('departamentosPlanilla/', this.id_departamento).subscribe(departamento => {
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
        
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('departamentosPlanilla', this.departamento).subscribe(departamento => {
            this.update.emit(departamento);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
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