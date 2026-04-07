import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';
import { NgSelectModule } from '@ng-select/ng-select';
import { FilterPipe } from '@pipes/filter.pipe';

@Component({
    selector: 'app-crear-proveedor',
    templateUrl: './crear-proveedor.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe],
    
})
export class CrearProveedorComponent extends BaseModalComponent implements OnInit {

    public proveedor: any = {};
    @Input() id_proveedor:any = null;
    @Output() update = new EventEmitter();
    public paises:any = [];
    public departamentos:any = [];
    public distritos:any = [];
    public municipios:any = [];
    public actividad_economicas:any = [];
    public override loading = false;
    public override saving = false;

    constructor( 
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private feCrUbic: FeCrUbicacionService,
    ) {
        super(modalManager, alertService);
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
        
    }

    override openModal(template: TemplateRef<any>) {
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
        
        if(this.id_proveedor){
            this.apiService.read('proveedor/', this.id_proveedor)
                .pipe(this.untilDestroyed())
                .subscribe(proveedor => {
            this.proveedor = proveedor;
            this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.proveedor = {};
            this.proveedor.tipo = 'Persona';
            this.proveedor.id_usuario = this.apiService.auth_user().id;
            this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
        }
        super.openModal(template, { class: 'modal-xl', backdrop: 'static' });
    }

    setGiro(){
        this.proveedor.giro = this.actividad_economicas.find((item:any) => item.cod == this.proveedor.cod_giro).nombre;
    }

    setPais(){
        this.proveedor.pais = this.paises.find((item:any) => item.cod == this.proveedor.cod_pais).nombre;
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

    public setTipo(tipo:any){
        this.proveedor.tipo = tipo;
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('proveedor', this.proveedor)
            .pipe(this.untilDestroyed())
            .subscribe(proveedor => {
            this.update.emit(proveedor);
            this.closeModal();
            this.saving = false;
            this.alertService.success('Proveedor creado', 'Tu proveedor fue añadido exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    public verificarSiExiste(){
        if(this.proveedor.nombre && this.proveedor.apellido){
            this.apiService.getAll('proveedores', { nombre: this.proveedor.nombre, apellido: this.proveedor.apellido, estado: 1, })
                .pipe(this.untilDestroyed())
                .subscribe(proveedores => { 
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
