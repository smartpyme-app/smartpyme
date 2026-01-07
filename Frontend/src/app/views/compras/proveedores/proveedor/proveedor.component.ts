import { Component, OnInit,TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TagInputModule } from 'ngx-chips';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { BaseComponent } from '@shared/base/base.component';
import { DuplicateCheckService } from '@services/duplicate-check.service';

@Component({
    selector: 'app-proveedor',
    templateUrl: './proveedor.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TagInputModule],
    
})
export class ProveedorComponent extends BaseComponent implements OnInit {

    public proveedor:any = {};
    public paises:any = [];
    public departamentos:any = [];
    public municipios:any = [];
    public distritos:any = [];
    public actividad_economicas:any = [];
    public catalogo:any = [];
    public loading = false;
    public saving = false;
    public contabilidadHabilitada: boolean = false;

    modalRef?: BsModalRef;

    constructor( 
        protected apiService: ApiService, 
        protected alertService: AlertService,
        private route: ActivatedRoute, 
        private router: Router, 
        private modalService: BsModalService,
        private funcionalidadesService: FuncionalidadesService,
        private duplicateCheckService: DuplicateCheckService
    ) {
        super();
    }

    ngOnInit() {
        this.paises = JSON.parse(localStorage.getItem('paises')!);
        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.distritos = JSON.parse(localStorage.getItem('distritos')!);
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas')!);
        
        // Verificar si tiene contabilidad habilitada
        this.verificarAccesoContabilidad();
        
        this.loadAll();
    }

    verificarAccesoContabilidad() {
        this.funcionalidadesService.verificarAcceso('contabilidad')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (acceso) => {
                    this.contabilidadHabilitada = acceso;
                    // Solo cargar catálogo si tiene contabilidad habilitada
                    if (acceso) {
                        this.apiService.getAll('catalogo/list')
                            .pipe(this.untilDestroyed())
                            .subscribe(catalogo => {
                                this.catalogo = catalogo;
                            }, error => {this.alertService.error(error);});
                    }
                },
                error: (error) => {
                    console.error('Error al verificar acceso a contabilidad:', error);
                    this.contabilidadHabilitada = false;
                }
            });
    }

    setPais(){
        this.proveedor.pais = this.paises.find((item:any) => item.cod == this.proveedor.cod_pais).nombre;
    }
    
    setDistrito(){
        let distrito = this.distritos.find((item:any) => item.cod == this.proveedor.cod_distrito && item.cod_departamento == this.proveedor.cod_departamento);
        console.log(distrito);
        if(distrito){
            this.proveedor.cod_municipio = distrito.cod_municipio;
            this.setMunicipio();
            this.proveedor.distrito = distrito.nombre; 
            this.proveedor.cod_distrito = distrito.cod;
        }
    }

    setMunicipio(){
        let municipio = this.municipios.find((item:any) => item.cod == this.proveedor.cod_municipio && item.cod_departamento == this.proveedor.cod_departamento);
        if(municipio){
            this.proveedor.municipio = municipio.nombre; 
            this.proveedor.cod_municipio = municipio.cod;

            this.proveedor.distrito = ''; 
            this.proveedor.cod_distrito = '';
        }
    }

    setDepartamento(){
        let departamento = this.departamentos.find((item:any) => item.cod == this.proveedor.cod_departamento);
        if(departamento){
            this.proveedor.departamento = departamento.nombre; 
            this.proveedor.cod_departamento = departamento.cod;

        }
        this.proveedor.municipio = ''; 
        this.proveedor.cod_municipio = '';
        this.proveedor.distrito = ''; 
        this.proveedor.cod_distrito = '';
    }

    public loadAll(){
        this.route.params
            .pipe(this.untilDestroyed())
            .subscribe((params:any) => {
                if (params.id) {
                    this.loading = true;
                    this.apiService.read('proveedor/', params.id)
                        .pipe(this.untilDestroyed())
                        .subscribe(proveedor => {
                            this.proveedor = proveedor;
                            this.loading = false;
                        }, error => {this.alertService.error(error); this.loading = false;});
                }else{
                    this.proveedor = {};
                    this.proveedor.tipo = 'Persona';
                    this.proveedor.tipo_contribuyente = '';
                    this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
                    this.proveedor.id_usuario = this.apiService.auth_user().id;
                }
            });
    }

    public setTipo(tipo:any){
        this.proveedor.tipo = tipo;
    }

    public onSubmit():void{
        this.saving = true;

        this.apiService.store('proveedor', this.proveedor)
            .pipe(this.untilDestroyed())
            .subscribe(proveedor => { 
                if(this.proveedor.id) {
                    this.alertService.success('Proveedor guardado', 'El proveedor fue guardado exitosamente.');
                }else {
                    this.alertService.success('Proveedor creado', 'El proveedor fue añadido exitosamente.');
                }
                this.router.navigate(['/proveedores']);
                this.proveedor = proveedor;
                this.saving = false;
            }, error => {this.alertService.error(error); this.saving = false;});
    }


    public verificarSiExiste(){
        this.duplicateCheckService.verificarSiExiste({
            endpoint: 'proveedores',
            searchParams: {
                nombre: this.proveedor.nombre,
                apellido: this.proveedor.apellido,
                estado: 1,
            },
            editUrl: '/proveedor/editar/',
            message: 'Puedes ignorar esta alerta si consideras que no estas duplicando el registros.',
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

}
