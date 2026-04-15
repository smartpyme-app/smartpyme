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
import {
    ContribuyenteActividadOption,
    mapContribuyenteAeResponseToActividades,
} from '@services/facturacion-electronica/contribuyente-hacienda.mapper';
import { finalize } from 'rxjs/operators';

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
    /** Actividad económica CR (Hacienda, vía fe-cr/contribuyente). */
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

    /** Búsqueda en ng-select: código puede tener puntos (6810.0); el usuario suele teclear solo dígitos. */
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

    /** Al abrir el desplegable, si aún no hay ítems y el NIT es válido, vuelve a consultar Hacienda. */
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

    private parseLocalJson(key: string): any[] {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) {
                return [];
            }
            const data = JSON.parse(raw);
            return Array.isArray(data) ? data : [];
        } catch {
            return [];
        }
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
        this.paises = this.parseLocalJson('paises');
        this.departamentos = this.parseLocalJson('departamentos');
        this.municipios = this.parseLocalJson('municipios');
        this.distritos = this.parseLocalJson('distritos');
        this.actividad_economicas = this.parseLocalJson('actividad_economicas');

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

        if (!this.municipios.length) {
            this.apiService.getAll('municipios')
                .pipe(this.untilDestroyed())
                .subscribe((m) => {
                    this.municipios = Array.isArray(m) ? m : [];
                    this.cdr.markForCheck();
                }, () => { this.cdr.markForCheck(); });
        }
        if (!this.distritos.length) {
            this.apiService.getAll('distritos')
                .pipe(this.untilDestroyed())
                .subscribe((d) => {
                    this.distritos = Array.isArray(d) ? d : [];
                    this.cdr.markForCheck();
                }, () => { this.cdr.markForCheck(); });
        }
        if (!this.departamentos.length) {
            this.apiService.getAll('departamentos')
                .pipe(this.untilDestroyed())
                .subscribe((dep) => {
                    this.departamentos = Array.isArray(dep) ? dep : [];
                    this.cdr.markForCheck();
                }, () => { this.cdr.markForCheck(); });
        }

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

    setGiro() {
        if (this.esCostaRicaFe()) {
            return;
        }
        const hit = this.actividad_economicas.find((item: any) => item.cod == this.proveedor.cod_giro);
        if (hit) {
            this.proveedor.giro = hit.nombre;
        }
        this.cdr.markForCheck();
    }

    /** Costa Rica FE: al editar NIT del proveedor (empresa), consulta actividades en Hacienda. */
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
        this.cdr.markForCheck();
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
        this.cdr.markForCheck();
        this.apiService
            .getAll('fe-cr/contribuyente', { identificacion: nit })
            .pipe(
                this.untilDestroyed(),
                finalize(() => {
                    this.contribuyenteCargandoCr = false;
                    this.cdr.markForCheck();
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
        this.cdr.markForCheck();
    }

    private syncActividadContribuyenteCrDesdeProveedor(): void {
        const rawCod = String(this.proveedor?.cod_giro ?? '').trim();
        const desc = String(this.proveedor?.giro ?? '').trim();
        const soloDigitos = rawCod.replace(/\D/g, '');
        if (soloDigitos.length === 13) {
            this.actividadContribuyenteSeleccionada = null;
            this.actividadesContribuyenteCr = [];
            this.cdr.markForCheck();
            return;
        }
        if (rawCod === '' && desc === '') {
            this.actividadContribuyenteSeleccionada = null;
            this.cdr.markForCheck();
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
                            if (this.esCostaRicaFe() && this.proveedor?.tipo === 'Empresa') {
                                this.syncActividadContribuyenteCrDesdeProveedor();
                                const nitCr = String(this.proveedor?.nit ?? '').replace(/\D/g, '');
                                if (nitCr.length >= 9 && nitCr.length <= 12) {
                                    this.cargarActividadesContribuyenteDesdeHacienda({ silenciosoSiNitInvalido: true });
                                }
                            }
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
