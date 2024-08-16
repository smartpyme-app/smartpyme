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
    public departamentos:any = [];
    public municipios:any = [];
    public actividad_economicas:any = [];
    public loading = false;
    public saving = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas')!);
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

    setGiro(){
        this.proveedor.giro = this.actividad_economicas.find((item:any) => item.cod == this.proveedor.cod_giro).nombre;
    }

    setMunicipio(){
        this.proveedor.municipio = this.municipios.find((item:any) => item.cod == this.proveedor.cod_municipio && item.cod_departamento == this.proveedor.cod_departamento).nombre;
    }

    setDepartamento(){
        this.proveedor.departamento = this.departamentos.find((item:any) => item.cod == this.proveedor.cod_departamento).nombre;
        this.proveedor.cod_municipio = null;
        this.proveedor.municipio = null;
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

    public verificarSiExiste(){
        if(this.proveedor.nombre && this.proveedor.apellido){
            this.apiService.getAll('proveedores', { nombre: this.proveedor.nombre, apellido: this.proveedor.apellido, estado: 1, }).subscribe(proveedores => { 
                if(proveedores.data[0]){
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.', 
                        'Por favor, verificar. Puedes ignorar esta alerta si consideras que no estas duplicando el registro.'
                    );
                }
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }
    }


}
