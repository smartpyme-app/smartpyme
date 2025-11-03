import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-crear-area-empresa',
    templateUrl: './crear-area-empresa.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearAreaEmpresaComponent implements OnInit {

    public area: any = {};
    public departamentos: any = [];
    @Input() id_area: any = null;
    @Input() id_departamento_preselected: any = null;
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

        if(!this.departamentos.length){
            this.loading = true;
            this.apiService.getAll('departamentosEmpresa/list').subscribe(departamentos => {
                this.departamentos = departamentos.map((dept: any) => ({
                    ...dept,
                    id: dept.id.toString()
                }));
                this.loading = false;
            }, error => {
                this.alertService.error(error); 
                this.loading = false;
            });
        }

        if(this.id_area){
            this.loading = true;
            this.apiService.read('area-empresa/', this.id_area).subscribe(area => {
                this.area = area;
                if(this.area.id_departamento) {
                    this.area.id_departamento = this.area.id_departamento.toString();
                }
                this.loading = false;
            }, error => {
                this.alertService.error(error); 
                this.loading = false;
            });
        } else {
            this.area = {};
            this.area.estado = 1;
            this.area.id_empresa = this.apiService.auth_user().id_empresa;
            
            // Preselecciona departamento si se proporciona
            if(this.id_departamento_preselected) {
                this.area.id_departamento = this.id_departamento_preselected.toString();
            }
        }
        
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public setDepartamento(departamento: any) {
        this.departamentos.push(departamento);
        this.area.id_departamento = departamento.id;
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('area-empresa', this.area).subscribe(area => {
            this.update.emit(area);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            if(!this.id_area) {
                this.alertService.success('Área creada', 'El área ha sido agregada exitosamente.');
            } else {
                this.alertService.success('Área actualizada', 'El área ha sido actualizada exitosamente.');
            }
        }, error => {
            this.alertService.error(error); 
            this.saving = false; 
        });
    }
}
