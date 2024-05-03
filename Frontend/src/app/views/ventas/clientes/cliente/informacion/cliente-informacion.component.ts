import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cliente-informacion',
  templateUrl: './cliente-informacion.component.html'
})
export class ClienteInformacionComponent implements OnInit {

    public cliente:any = {};
    public loading = false;
    public saving = false;
    public departamentos:any = [];
    public municipios:any = [];
    public actividad_economicas:any = [];

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loadAll();
        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas')!);
    }

    public loadAll(){
        this.route.params.subscribe((params:any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('cliente/', params.id).subscribe(cliente => {
                    this.cliente = cliente;
                    this.loading = false;
                }, error => {this.alertService.error(error); this.loading = false;});
            }else{
                this.cliente = {};
                this.cliente.tipo = 'Persona';
                this.cliente.tipo_contribuyente = '';
                this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
                this.cliente.id_usuario = this.apiService.auth_user().id;
            }
        });
    }

    public setTipo(tipo:any){
        this.cliente.tipo = tipo;
    }

    setGiro(){
        this.cliente.giro = this.actividad_economicas.find((item:any) => item.cod == this.cliente.cod_giro).nombre;
        console.log(this.cliente.giro);
    }

    setMunicipio(){
        this.cliente.municipio = this.municipios.find((item:any) => item.cod == this.cliente.cod_municipio && item.cod_departamento == this.cliente.cod_departamento).nombre;
    }

    setDepartamento(){
        this.cliente.departamento = this.departamentos.find((item:any) => item.cod == this.cliente.cod_departamento).nombre;
        this.cliente.cod_municipio = null;
        this.cliente.municipio = null;
    }

    public onSubmit():void{
        this.saving = true;

        this.apiService.store('cliente', this.cliente).subscribe(cliente => { 
            if (!this.cliente.id) {
                this.alertService.success('Cliente guardado', 'El cliente fue guardado exitosamente.');
            }else{
                this.alertService.success('Cliente creado', 'El cliente fue añadido exitosamente.');
            }
           this.router.navigate(['/clientes']);
            this.cliente = cliente;
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public verificarSiExiste(){
        if(this.cliente.nombre && this.cliente.apellido){
            this.apiService.getAll('clientes', { nombre: this.cliente.nombre, apellido: this.cliente.apellido, estado: 1, }).subscribe(clientes => { 
                if(clientes.data[0]){
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.', 
                        'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' + this.apiService.appUrl + '/cliente/editar/' + clientes.data[0].id + '">Ver cliente</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
                    );
                }
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }
    }


}
