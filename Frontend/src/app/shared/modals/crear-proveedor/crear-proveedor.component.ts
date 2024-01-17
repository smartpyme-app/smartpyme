import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-proveedor',
  templateUrl: './crear-proveedor.component.html'
})
export class CrearProveedorComponent implements OnInit {

    public proveedor: any = {};
    @Input() id_proveedor:any = null;
    @Output() update = new EventEmitter();
    public loading = false;
    public saving = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        if(this.id_proveedor){
            this.apiService.read('proveedor/', this.id_proveedor).subscribe(proveedor => {
            this.proveedor = proveedor;
            this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.proveedor = {};
            this.proveedor.tipo = 'Persona';
            this.proveedor.id_usuario = this.apiService.auth_user().id;
            this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.proveedor.tipo = tipo;
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('proveedor', this.proveedor).subscribe(proveedor => {
            this.update.emit(proveedor);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Proveedor creado', 'Tu proveedor fue añadido exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });
    }


}
