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
    public tipoAnterior = '';

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
                this.tipoAnterior = cliente.tipo;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.cliente = {};
            this.cliente.tipo = 'Persona';
            this.tipoAnterior = 'Persona';
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
        let routeUrl = this.id_cliente ? 'cliente/update' : 'cliente';
        this.apiService.store(routeUrl, this.cliente).subscribe(cliente => {
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

    setGiro() {
        this.cliente.giro = this.actividad_economicas.find(
          (item: any) => item.cod == this.cliente.cod_giro
        ).nombre;
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

    onTipoChange() {
        if (!this.id_cliente) {
            this.limpiarTodosSinTipo();
        } else {
            const tipoAnterior = this.tipoAnterior;
            const nuevoTipo = this.cliente.tipo;
            this.mapearCamposEntreTipos(tipoAnterior, nuevoTipo);
        }

        this.tipoAnterior = this.cliente.tipo;
    }

    limpiarTodosSinTipo() {
        this.cliente.codigo_cliente = '';
        this.cliente.nombre = '';
        this.cliente.apellido = '';
        this.cliente.correo = '';
        this.cliente.telefono = '';
        this.cliente.direccion = '';
        this.cliente.pais = '';
        this.cliente.departamento = '';
        this.cliente.municipio = '';
        this.cliente.distrito = '';

        // Campos de persona
        this.cliente.dui = '';
        this.cliente.fecha_cumpleanos = '';
        this.cliente.red_social = '';
        this.cliente.etiquetas = [];
        this.cliente.nota = '';

        // Campos de empresa
        this.cliente.nombre_empresa = '';
        this.cliente.nit = '';
        this.cliente.ncr = '';
        this.cliente.tipo_contribuyente = '';
        this.cliente.giro = '';
        this.cliente.empresa_telefono = '';
        this.cliente.empresa_direccion = '';

        // Campos de extranjero
        this.cliente.tipo_documento = '';
        this.cliente.tipo_persona = '';

        // Códigos de ubicación
        this.cliente.cod_pais = '';
        this.cliente.cod_departamento = '';
        this.cliente.cod_municipio = '';
        this.cliente.cod_distrito = '';
        this.cliente.cod_giro = '';
    }

    mapearCamposEntreTipos(desde: string, hacia: string) {
        const datosComunes = {
            codigo_cliente: this.cliente.codigo_cliente,
            nombre: this.cliente.nombre,
            apellido: this.cliente.apellido,
            correo: this.cliente.correo,
            telefono: this.cliente.telefono,
            direccion: this.cliente.direccion,
            pais: this.cliente.pais,
            departamento: this.cliente.departamento,
            municipio: this.cliente.municipio,
            distrito: this.cliente.distrito
        };

        const mapeos: any = {
            'Persona->Empresa': {
                ...datosComunes,
                nombre_empresa: [this.cliente.nombre, this.cliente.apellido]
                .filter(Boolean)
                .join(' ') || this.cliente.nombre_empresa || '',
                empresa_telefono: this.cliente.telefono,
                empresa_direccion: this.cliente.direccion
            },
            'Empresa->Persona': {
                ...datosComunes,
                telefono: this.cliente.empresa_telefono || this.cliente.telefono,
                direccion: this.cliente.empresa_direccion || this.cliente.direccion
            },
            'Persona->Extranjero': {
                ...datosComunes,
                tipo_persona: 'Persona Natural',
                tipo_documento: '13', // DUI
                dui: this.cliente.dui
            },
            'Empresa->Extranjero': {
                ...datosComunes,
                nombre_empresa: this.cliente.nombre_empresa,
                tipo_persona: 'Persona Juridica',
                tipo_documento: '36', // NIT
                giro: this.cliente.giro,
                telefono: this.cliente.empresa_telefono || this.cliente.telefono
            },
            'Extranjero->Persona': {
                ...datosComunes,
                dui: this.cliente.dui
            },
            'Extranjero->Empresa': {
                ...datosComunes,
                nombre_empresa: this.cliente.nombre_empresa || [this.cliente.nombre, this.cliente.apellido].filter(Boolean).join(' '),
                giro: this.cliente.giro,
                empresa_telefono: this.cliente.telefono
            }
        };

        const clave = `${desde}->${hacia}`;
        const mapeo = mapeos[clave];

        if (mapeo) {
            this.limpiarTodosSinTipo();
            Object.assign(this.cliente, mapeo);

            this.alertService.info(
                'Datos adaptados',
                'Los campos se han adaptado automáticamente al nuevo tipo de cliente.'
            );
        } else {
            this.limpiarTodosSinTipo();
            Object.assign(this.cliente, datosComunes);
        }
    }

}
