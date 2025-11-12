import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-crear-impuesto',
    templateUrl: './crear-impuesto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearImpuestoComponent implements OnInit {

    public impuesto: any = {};
    @Input() id_impuesto:any = null;
    @Output() update = new EventEmitter();
    public loading = false;
    public saving = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, 
        public alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        
    }

    openModal(template: TemplateRef<any>) {
        if(this.id_impuesto){
            this.apiService.read('impuesto/', this.id_impuesto).subscribe(impuesto => {
                this.impuesto = impuesto;
                this.loading = false;
            }, error => {
                this.alertService.error(error); 
                this.loading = false;
            });
        } else {
            this.impuesto = {};
            this.impuesto.estado = 1;
            this.impuesto.id_usuario = this.apiService.auth_user().id;
            this.impuesto.id_empresa = this.apiService.auth_user().id_empresa;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('impuesto', this.impuesto).subscribe(impuesto => {
            this.update.emit(impuesto);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Impuesto guardado', 'El impuesto fue guardado exitosamente.');
        }, error => {
            this.alertService.error(error); 
            this.saving = false;
        });
    }

    public verificarSiExiste(){
        if(this.impuesto.nombre){
            this.apiService.getAll('impuestos', { 
                nombre: this.impuesto.nombre, 
                estado: 1, 
            }).subscribe(impuestos => { 
                if(impuestos.data[0]){
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.', 
                        'Por favor, verificar. Puedes ignorar esta alerta si consideras que no estas duplicando el registro.'
                    );
                }
                this.loading = false;
            }, error => {
                this.alertService.error(error); 
                this.loading = false;
            });
        }
    }
}
