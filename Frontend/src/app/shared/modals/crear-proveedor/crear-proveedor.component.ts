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
import {
    ContribuyenteActividadOption,
    mapContribuyenteAeResponseToActividades,
} from '@services/facturacion-electronica/contribuyente-hacienda.mapper';
import { finalize } from 'rxjs/operators';

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
    actividadesContribuyenteCr: ContribuyenteActividadOption[] = [];
    actividadContribuyenteSeleccionada: ContribuyenteActividadOption | null = null;
    contribuyenteCargandoCr = false;
    private nitCrActividadesTimer: ReturnType<typeof setTimeout> | null = null;

    readonly compareActividadContribuyenteCr = (a: ContribuyenteActividadOption, b: ContribuyenteActividadOption): boolean => {
        if (!a || !b) {
            return false;
        }
        return this.normalizarCodigoActividadCr(a.codigo) === this.normalizarCodigoActividadCr(b.codigo);
    };

    readonly actividadCrNgSelectSearchFn = (term: string, item: ContribuyenteActividadOption): boolean => {
        const raw = String(term ?? '').trim();
        if (raw === '') {
            return true;
        }
        const t = raw.toLowerCase();
        const label = String(item?.label ?? '').toLowerCase();
        if (label.includes(t)) {
            return true;
        }
        const digitsTerm = t.replace(/\D/g, '');
        const digitsCode = String(item?.codigo ?? '').replace(/\D/g, '');
        if (digitsTerm.length > 0 && digitsCode.includes(digitsTerm)) {
            return true;
        }
        const desc = String(item?.descripcion ?? '').toLowerCase();
        return desc.includes(t);
    };

    onActividadCrSelectOpen(): void {
        if (!this.esCostaRicaFe() || this.proveedor?.tipo !== 'Empresa') {
            return;
        }
        const nit = String(this.proveedor?.nit ?? '').replace(/\D/g, '');
        if (nit.length < 9 || nit.length > 12) {
            return;
        }
        if (this.contribuyenteCargandoCr) {
            return;
        }
        if (this.actividadesContribuyenteCr.length === 0) {
            this.cargarActividadesContribuyenteDesdeHacienda({ silenciosoSiNitInvalido: true });
        }
    }

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
            if (this.esCostaRicaFe() && this.proveedor?.tipo === 'Empresa') {
                this.syncActividadContribuyenteCrDesdeProveedor();
                const nitCr = String(this.proveedor?.nit ?? '').replace(/\D/g, '');
                if (nitCr.length >= 9 && nitCr.length <= 12) {
                    this.cargarActividadesContribuyenteDesdeHacienda({ silenciosoSiNitInvalido: true });
                }
            }
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.proveedor = {};
            this.proveedor.tipo = 'Persona';
            this.proveedor.id_usuario = this.apiService.auth_user().id;
            this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
            this.actividadesContribuyenteCr = [];
            this.actividadContribuyenteSeleccionada = null;
        }
        super.openModal(template, { class: 'modal-xl', backdrop: 'static' });
    }

    setGiro(){
        if (this.esCostaRicaFe()) {
            return;
        }
        const hit = this.actividad_economicas.find((item:any) => item.cod == this.proveedor.cod_giro);
        if (hit) {
            this.proveedor.giro = hit.nombre;
        }
    }

    onNitProveedorChange(): void {
        if (!this.esCostaRicaFe() || this.proveedor?.tipo !== 'Empresa') {
            return;
        }
        if (this.nitCrActividadesTimer !== null) {
            clearTimeout(this.nitCrActividadesTimer);
        }
        this.nitCrActividadesTimer = setTimeout(() => {
            this.nitCrActividadesTimer = null;
            this.cargarActividadesContribuyenteDesdeHacienda({ silenciosoSiNitInvalido: true });
        }, 600);
    }

    onActividadContribuyenteCrChange(item: ContribuyenteActividadOption | null): void {
        if (item) {
            this.proveedor.cod_giro = item.codigo;
            this.proveedor.giro = item.descripcion;
        } else {
            this.proveedor.cod_giro = null;
            this.proveedor.giro = '';
        }
    }

    private normalizarCodigoActividadCr(codigo: string): string {
        return String(codigo ?? '').replace(/\D/g, '');
    }

    cargarActividadesContribuyenteDesdeHacienda(opciones?: { silenciosoSiNitInvalido?: boolean }): void {
        const nit = String(this.proveedor?.nit ?? '').replace(/\D/g, '');
        if (nit.length < 9 || nit.length > 12) {
            if (!opciones?.silenciosoSiNitInvalido) {
                this.alertService.warning(
                    'Identificación',
                    'Ingrese una identificación válida (9 a 12 dígitos) en el campo NIT del proveedor.',
                );
            }
            return;
        }

        this.contribuyenteCargandoCr = true;
        this.apiService
            .getAll('fe-cr/contribuyente', { identificacion: nit })
            .pipe(
                this.untilDestroyed(),
                finalize(() => {
                    this.contribuyenteCargandoCr = false;
                }),
            )
            .subscribe({
                next: (body) => {
                    const list = mapContribuyenteAeResponseToActividades(body);
                    const sel = this.actividadContribuyenteSeleccionada;
                    let merged = list;
                    if (sel?.codigo) {
                        if (list.length > 0 && !list.some((a) => this.compareActividadContribuyenteCr(a, sel))) {
                            merged = [sel, ...list];
                        }
                        if (list.length === 0) {
                            merged = [sel];
                        }
                    }
                    this.actividadesContribuyenteCr = merged;
                    if (list.length === 0) {
                        this.alertService.warning(
                            'Hacienda',
                            'No se encontraron actividades económicas para esta identificación. Verifique el NIT o intente más tarde.',
                        );
                    }
                    this.reconciliarSeleccionActividadContribuyenteCr();
                },
                error: (e) => this.alertService.error(e),
            });
    }

    private reconciliarSeleccionActividadContribuyenteCr(): void {
        const sel = this.actividadContribuyenteSeleccionada;
        if (!sel?.codigo) {
            return;
        }
        const hit = this.actividadesContribuyenteCr.find((a) => this.compareActividadContribuyenteCr(a, sel));
        if (hit) {
            this.actividadContribuyenteSeleccionada = hit;
            this.onActividadContribuyenteCrChange(hit);
        }
    }

    private syncActividadContribuyenteCrDesdeProveedor(): void {
        const rawCod = String(this.proveedor?.cod_giro ?? '').trim();
        const desc = String(this.proveedor?.giro ?? '').trim();
        const soloDigitos = rawCod.replace(/\D/g, '');
        if (soloDigitos.length === 13) {
            this.actividadContribuyenteSeleccionada = null;
            this.actividadesContribuyenteCr = [];
            return;
        }
        if (rawCod === '' && desc === '') {
            this.actividadContribuyenteSeleccionada = null;
            return;
        }
        const synthetic: ContribuyenteActividadOption = {
            codigo: rawCod,
            descripcion: desc,
            label: desc !== '' ? `${rawCod} — ${desc}` : rawCod,
        };
        this.actividadContribuyenteSeleccionada = synthetic;
        if (!this.actividadesContribuyenteCr.some((a) => this.compareActividadContribuyenteCr(a, synthetic))) {
            this.actividadesContribuyenteCr = [synthetic, ...this.actividadesContribuyenteCr];
        }
        this.reconciliarSeleccionActividadContribuyenteCr();
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
