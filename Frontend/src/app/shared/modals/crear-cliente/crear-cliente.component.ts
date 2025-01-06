import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-cliente',
  templateUrl: './crear-cliente.component.html'
})
export class CrearClienteComponent implements OnInit {

    public cliente: any = {};
    @Input() id_cliente:any = null;
    @Output() update = new EventEmitter();
    public loading = false;
    public saving = false;
    public paises:any = [];
    public departamentos:any = [];
    public municipios:any = [];
    public distritos:any = [];
    public actividad_economicas:any = [];

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        this.paises = JSON.parse(localStorage.getItem('paises')!);
        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.distritos = JSON.parse(localStorage.getItem('distritos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas')!);
        
        if(this.id_cliente){
            this.apiService.read('cliente/', this.id_cliente).subscribe(cliente => {
            this.cliente = cliente;
            this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.cliente = {};
            this.cliente.tipo = 'Persona';
            this.cliente.id_usuario = this.apiService.auth_user().id;
            this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.cliente.tipo = tipo;
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => {
            this.update.emit(cliente);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Cliente creado', 'El cliente ha sido agregado.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    setPais(){
        this.cliente.pais = this.paises.find((item:any) => item.cod == this.cliente.cod_pais).nombre;
    }

    setGiro(){
        this.cliente.giro = this.actividad_economicas.find((item:any) => item.cod == this.cliente.cod_giro).nombre;
    }

    setDistrito(){
        let distrito = this.distritos.find((item:any) => item.cod == this.cliente.cod_distrito && item.cod_departamento == this.cliente.cod_departamento);
        console.log(distrito);
        if(distrito){
            this.cliente.cod_municipio = distrito.cod_municipio;
            this.setMunicipio();
            this.cliente.distrito = distrito.nombre; 
            this.cliente.cod_distrito = distrito.cod;
        }
    }

    setMunicipio(){
        let municipio = this.municipios.find((item:any) => item.cod == this.cliente.cod_municipio && item.cod_departamento == this.cliente.cod_departamento);
        if(municipio){
            this.cliente.municipio = municipio.nombre; 
            this.cliente.cod_municipio = municipio.cod;

            this.cliente.distrito = ''; 
            this.cliente.cod_distrito = '';
        }
    }

    setDepartamento(){
        let departamento = this.departamentos.find((item:any) => item.cod == this.cliente.cod_departamento);
        if(departamento){
            this.cliente.departamento = departamento.nombre; 
            this.cliente.cod_departamento = departamento.cod;

        }
        this.cliente.municipio = ''; 
        this.cliente.cod_municipio = '';
        this.cliente.distrito = ''; 
        this.cliente.cod_distrito = '';
    }

    public verificarSiExiste(){
        if(this.cliente.nombre && this.cliente.apellido){
            this.apiService.getAll('clientes', { nombre: this.cliente.nombre, apellido: this.cliente.apellido, estado: 1, }).subscribe(clientes => { 
                if(clientes.data[0]){
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.', 
                        'Por favor, verificar. Puedes ignorar esta alerta si consideras que no estas duplicando el registro.'
                    );
                }
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }
    }


}
