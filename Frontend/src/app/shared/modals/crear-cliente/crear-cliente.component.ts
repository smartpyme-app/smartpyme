import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { FilterPipe } from '@pipes/filter.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';
import { DuplicateCheckService } from '@services/duplicate-check.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-crear-cliente',
    templateUrl: './crear-cliente.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe],

})
export class CrearClienteComponent extends BaseModalComponent implements OnInit {

    public cliente: any = {
        contactos: [], // Inicializar el array de contactos
    };
    @Input() id_cliente:any = null;
    @Output() update = new EventEmitter();
    public override loading = false;
    public override saving = false;
    public paises:any = [];
    public departamentos:any = [];
    public municipios:any = [];
    public distritos:any = [];
    public actividad_economicas:any = [];
    public contacto: any = {};
    public vendedores:any = [];
    //loading
    public loading_contacto = false;
    public esNuevo = false;
    public tipoAnterior = '';
    public modalRefContacto: any;

    public diasCreditoOpciones = [3, 8, 10, 15, 30, 45, 60];


  constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private duplicateCheckService: DuplicateCheckService,
        private feCrUbic: FeCrUbicacionService,
    ) {
        super(modalManager, alertService);
    }

    esCostaRicaFe(): boolean {
        return this.feCrUbic.esCostaRicaFe();
    }

    municipiosFiltradosCr(): any[] {
        return this.feCrUbic.municipiosPorProvincia(this.municipios, this.cliente?.cod_departamento);
    }

    distritosFiltradosCr(): any[] {
        return this.feCrUbic.distritosPorCanton(
            this.distritos,
            this.cliente?.cod_departamento,
            this.cliente?.cod_municipio,
        );
    }

    puedeEditarCreditoCliente(): boolean {
        const tipo = this.apiService.auth_user()?.tipo || '';
        return ['Administrador', 'Supervisor', 'Supervisor Limitado'].includes(tipo);
    }

    onHabilitaCreditoChange() {
        if (this.cliente.habilita_credito && !this.cliente.dias_credito) {
            const clasificacion = this.cliente.clasificacion?.toUpperCase();
            if (clasificacion === 'A' || clasificacion === 'B') {
                this.cliente.dias_credito = 30;
            } else if (clasificacion === 'C') {
                this.cliente.dias_credito = 15;
            } else {
                this.cliente.dias_credito = 30;
            }
        }
        if (!this.cliente.habilita_credito) {
            this.cliente.dias_credito = null;
            this.cliente.limite_credito = null;
        }
    }

    ngOnInit() {
        this.paises = JSON.parse(localStorage.getItem('paises') || '[]');
        this.departamentos = JSON.parse(localStorage.getItem('departamentos') || '[]');
        this.distritos = JSON.parse(localStorage.getItem('distritos') || '[]');
        this.municipios = JSON.parse(localStorage.getItem('municipios') || '[]');
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas') || '[]');

        this.feCrUbic.cargarCatalogosYLs().subscribe((r) => {
            if (r) {
                this.departamentos = r.dep;
                this.municipios = r.mun;
                this.distritos = r.dis;
            }
        });

        // Cargar vendedores
        this.apiService.getAll('usuarios/list').subscribe(
            (usuarios) => {
                this.vendedores = usuarios;
            },
            (error) => {
                this.alertService.error(error);
            }
        );
    }

    override openModal(template: TemplateRef<any>) {
        if(this.id_cliente){
            this.esNuevo = false;
            this.loading = true;
            this.apiService.read('cliente/', this.id_cliente)
                .pipe(this.untilDestroyed())
                .subscribe(cliente => {
                this.cliente = cliente;
                this.tipoAnterior = cliente.tipo;
                this.loading = false;
                if (!this.cliente.contactos) {
                    this.cliente.contactos = [];
                }
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.esNuevo = true;
            this.cliente = {};
            this.cliente.tipo = 'Persona';
            this.cliente.contactos = [];
            this.cliente.tipo_contribuyente = '';
            this.cliente.habilita_credito = false;
            this.cliente.dias_credito = null;
            this.cliente.limite_credito = null;
            this.cliente.id_usuario = this.apiService.auth_user().id;
            this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
        }
        super.openModal(template, { class: 'modal-xl', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.cliente.tipo = tipo;
    }

    public onSubmit() {
        this.saving = true;
        let routeUrl = this.esNuevo ? 'cliente' : 'cliente/update';

        this.apiService.store(routeUrl, this.cliente)
            .pipe(this.untilDestroyed())
            .subscribe({
            next: (cliente) => {
                const titulo = this.esNuevo ? 'Cliente creado' : 'Cliente actualizado';
                const mensaje = this.esNuevo
                    ? 'El cliente fue creado exitosamente.'
                    : 'El cliente fue actualizado exitosamente.';

                this.alertService.success(titulo, mensaje);

                this.update.emit(cliente);
                this.closeModal();
                this.saving = false;
            },
            error: (error) => {
                this.alertService.error(error);
                this.saving = false;
            },
        });
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
            const mun = this.municipios.find(
                (m: any) => m.cod == distrito.cod_municipio && m.cod_departamento == distrito.cod_departamento,
            );
            if (mun) {
                this.cliente.municipio = mun.nombre;
            }
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
        this.duplicateCheckService.verificarSiExiste({
            endpoint: 'clientes',
            searchParams: {
                nombre: this.cliente.nombre,
                apellido: this.cliente.apellido,
                estado: 1,
            },
            showEditLink: false, // No mostrar enlace en el modal de creación
            onComplete: () => {
                this.loading = false;
            },
            onError: () => {
                this.loading = false;
            }
        })
        .pipe(this.untilDestroyed())
        .subscribe();
    }

    openModalContacto(template: TemplateRef<any>, contacto: any) {
        if (!contacto || contacto === null) {
            this.contacto = {};
        } else {
            this.contacto = { ...contacto };
        }

        this.modalRefContacto = this.modalManager.openModal(template, {
            class: 'modal-lg',
            backdrop: 'static',
        });
    }

    agregarContacto(template: TemplateRef<any>) {
        this.contacto = {};
        this.modalRefContacto = this.modalManager.openModal(template, {
            class: 'modal-lg',
            backdrop: 'static',
        });
    }

    submit(event: Event) {
        event.preventDefault();

        if (!this.cliente.contactos) {
            this.cliente.contactos = [];
        }

        if (!this.contacto.nombre && !this.contacto.apellido) {
            Swal.fire(
                '🚨 Alerta',
                'Debes ingresar al menos un nombre o apellido.',
                'warning'
            );
            return;
        }

        if (!this.contacto.telefono && !this.contacto.correo) {
            Swal.fire(
                '🚨 Alerta',
                'Debes ingresar al menos un teléfono o correo.',
                'warning'
            );
            return;
        }
        const nuevoContacto = {
            id: this.contacto.id || Date.now(),
            nombre: this.contacto.nombre,
            apellido: this.contacto.apellido,
            correo: this.contacto.correo,
            telefono: this.contacto.telefono,
            cargo: this.contacto.cargo,
            fecha_nacimiento: this.contacto.fecha_nacimiento,
            red_social: this.contacto.red_social,
            nota: this.contacto.nota,
            sexo: this.contacto.sexo,
            id_cliente: this.cliente.id,
        };

        if (this.cliente.id) {
            this.loading_contacto = true;

            this.apiService.store('cliente/contacto', nuevoContacto)
                .pipe(this.untilDestroyed())
                .subscribe({
                next: (contactoGuardado) => {
                    const index = this.cliente.contactos.findIndex(
                        (c: any) => c.id === contactoGuardado.id
                    );

                    if (index !== -1) {
                        this.cliente.contactos[index] = contactoGuardado;
                    } else {
                        this.cliente.contactos.push(contactoGuardado);
                    }

                    this.alertService.success(
                        'Contacto guardado',
                        'El contacto fue guardado exitosamente.'
                    );

                    this.contacto = {};
                    this.loading_contacto = false;
                    if (this.modalRefContacto) {
                        this.modalManager.closeModal(this.modalRefContacto);
                        this.modalRefContacto = undefined;
                    }
                },
                error: (error) => {
                    this.alertService.error('Error al guardar el contacto: ' + error);
                    this.loading_contacto = false;
                },
            });
        } else {
            const index = this.cliente.contactos.findIndex(
                (c: any) => c.id === nuevoContacto.id
            );

            if (index !== -1) {
                this.cliente.contactos[index] = { ...nuevoContacto };
            } else {
                this.cliente.contactos.push(nuevoContacto);
            }

            this.contacto = {};
            if (this.modalRefContacto) {
                this.modalManager.closeModal(this.modalRefContacto);
                this.modalRefContacto = undefined;
            }

            this.alertService.success(
                'Contacto agregado',
                'El contacto fue agregado a la lista. Se guardará cuando guarde el cliente.'
            );
        }
    }

    eliminarContacto(contacto: any) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡No podrás revertir esto!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (result.isConfirmed) {
                if (contacto.id && this.cliente.id) {
                    this.loading = true;

                    this.apiService.delete('cliente/contacto/', contacto.id)
                        .pipe(this.untilDestroyed())
                        .subscribe({
                        next: () => {
                            const index = this.cliente.contactos.findIndex(
                                (c: any) => c.id === contacto.id
                            );
                            if (index !== -1) {
                                this.cliente.contactos.splice(index, 1);
                            }

                            this.alertService.success(
                                'Contacto eliminado',
                                'El contacto fue eliminado exitosamente.'
                            );
                            this.loading = false;
                        },
                        error: (error) => {
                            this.alertService.error(
                                'Error al eliminar el contacto: ' + error
                            );
                            this.loading = false;
                        },
                    });
                } else {
                    const index = this.cliente.contactos.findIndex(
                        (c: any) => c.id === contacto.id
                    );
                    if (index !== -1) {
                        this.cliente.contactos.splice(index, 1);
                        this.alertService.success(
                            'Contacto eliminado',
                            'El contacto fue eliminado de la lista.'
                        );
                    }
                }
            }
        });
    }

    onTipoChange() {
        if (this.esNuevo) {
            // Creando: limpiar todo
            this.limpiarTodosSinTipo();
        } else {
            // Editando: mapeo inteligente
            const tipoAnterior = this.tipoAnterior;
            const nuevoTipo = this.cliente.tipo;
            this.mapearCamposEntreTipos(tipoAnterior, nuevoTipo);
        }

        this.tipoAnterior = this.cliente.tipo;
    }

    limpiarTodosSinTipo() {
        // Campos comunes
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
                nombre_empresa: this.cliente.nombre_empresa || this.cliente.nombre + ' ' + this.cliente.apellido,
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
