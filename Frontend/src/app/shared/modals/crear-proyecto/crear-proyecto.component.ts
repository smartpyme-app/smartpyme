import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-proyecto',
  templateUrl: './crear-proyecto.component.html'
})
export class CrearProyectoComponent implements OnInit {

    public proyecto: any = {};
    public clientes: any = [];
    @Input() id_proyecto:any = null;
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
        if(!this.clientes.length){
            this.apiService.getAll('clientes/list').subscribe(clientes => {
                this.clientes = clientes;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        if(this.id_proyecto){
            this.apiService.read('proyecto/', this.id_proyecto).subscribe(proyecto => {
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
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.proyecto.tipo = tipo;
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('proyecto', this.proyecto).subscribe(proyecto => {
            this.update.emit(proyecto);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('proyecto creado', 'El proyecto ha sido agregado.');
        },error => {this.alertService.error(error); this.saving = false; });
    }


}
