import { Component, OnInit,TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
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
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import { FilterPipe } from '@pipes/filter.pipe';

@Component({
    selector: 'app-proveedor',
    templateUrl: './proveedor.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TagInputModule, FilterPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
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
        private duplicateCheckService: DuplicateCheckService,
        private cdr: ChangeDetectorRef,
        private feCrUbic: FeCrUbicacionService,
    ) {
        super();
    }

    esCostaRicaFe(): boolean {
        return this.feCrUbic.esCostaRicaFe();
    }

    municipiosFiltradosCr(): any[] {
        return this.feCrUbic.municipiosPorProvincia(this.municipios, this.proveedor?.cod_departamento);
    }

    distritosFiltradosCr(): any[] {
        return this.feCrUbic.distritosPorCanton(
            this.distritos,
            this.proveedor?.cod_departamento,
            this.proveedor?.cod_municipio,
        );
    }

    ngOnInit() {
        this.paises = JSON.parse(localStorage.getItem('paises') || '[]');
        this.departamentos = JSON.parse(localStorage.getItem('departamentos') || '[]');
        this.municipios = JSON.parse(localStorage.getItem('municipios') || '[]');
        this.distritos = JSON.parse(localStorage.getItem('distritos') || '[]');
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas') || '[]');

        this.feCrUbic.cargarCatalogosYLs().subscribe((r) => {
            if (r) {
                this.departamentos = r.dep;
                this.municipios = r.mun;
                this.distritos = r.dis;
                this.cdr.markForCheck();
            }
        });
        
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
                                this.cdr.markForCheck();
                            }, error => {this.alertService.error(error); this.cdr.markForCheck();});
                    }
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    console.error('Error al verificar acceso a contabilidad:', error);
                    this.contabilidadHabilitada = false;
                    this.cdr.markForCheck();
                }
            });
    }

    setPais(){
        this.proveedor.pais = this.paises.find((item:any) => item.cod == this.proveedor.cod_pais).nombre;
        this.cdr.markForCheck();
    }
    
    setDistrito(){
        let distrito = this.distritos.find((item:any) => item.cod == this.proveedor.cod_distrito && item.cod_departamento == this.proveedor.cod_departamento);
        console.log(distrito);
        if(distrito){
            this.proveedor.cod_municipio = distrito.cod_municipio;
            const mun = this.municipios.find(
                (m: any) => m.cod == distrito.cod_municipio && m.cod_departamento == distrito.cod_departamento,
            );
            if (mun) {
                this.proveedor.municipio = mun.nombre;
            }
            this.proveedor.distrito = distrito.nombre;
            this.proveedor.cod_distrito = distrito.cod;
        }
        this.cdr.markForCheck();
    }

    setMunicipio(){
        let municipio = this.municipios.find((item:any) => item.cod == this.proveedor.cod_municipio && item.cod_departamento == this.proveedor.cod_departamento);
        if(municipio){
            this.proveedor.municipio = municipio.nombre; 
            this.proveedor.cod_municipio = municipio.cod;

            this.proveedor.distrito = ''; 
            this.proveedor.cod_distrito = '';
        }
        this.cdr.markForCheck();
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
        this.cdr.markForCheck();
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
                            this.cdr.markForCheck();
                        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
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
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});
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
                this.cdr.markForCheck();
            },
            onError: () => {
                this.loading = false;
                this.cdr.markForCheck();
            }
        })
        .pipe(this.untilDestroyed())
        .subscribe();
    }

}
