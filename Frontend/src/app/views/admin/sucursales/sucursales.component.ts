import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { finalize } from 'rxjs/operators';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { TranslatePipe } from '@ngx-translate/core';
import { FE_PAIS_CR, FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import {
    ContribuyenteActividadOption,
    mapContribuyenteAeResponseToActividades,
} from '@services/facturacion-electronica/contribuyente-hacienda.mapper';
import { HaciendaContribuyenteClientService } from '@services/facturacion-electronica/hacienda-contribuyente-client.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import {
    alCambiarDepartamento,
    alCambiarDistrito,
    alCambiarMunicipio,
    hidratarCodigosUbicacion,
    trackUbicacionCod,
} from '@utils/ubicacion-catalogo.util';

@Component({
    selector: 'app-sucursales',
    templateUrl: './sucursales.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TranslatePipe, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SucursalesComponent extends BaseCrudComponent<any> implements OnInit {

    public sucursales: any = [];
    public sucursal: any = {};
    public sucursales_activas: any = 0;
    public actividad_economicas: any[] = [];
    public actividadesContribuyenteCr: ContribuyenteActividadOption[] = [];
    public actividadContribuyenteSeleccionada: ContribuyenteActividadOption | null = null;
    public contribuyenteCargandoCr = false;
    public departamentos: any[] = [];
    public municipios: any[] = [];
    public distritos: any[] = [];
    readonly trackUbicacion = trackUbicacionCod;

    readonly compareActividadContribuyenteCr = (
        a: ContribuyenteActividadOption,
        b: ContribuyenteActividadOption,
    ): boolean => String(a?.codigo ?? '').replace(/\D/g, '') === String(b?.codigo ?? '').replace(/\D/g, '');

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute,
        private router: Router,
        private cdr: ChangeDetectorRef,
        private haciendaContribuyenteClient: HaciendaContribuyenteClientService,
        private feCrUbic: FeCrUbicacionService,
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'sucursal',
            itemsProperty: 'sucursales',
            itemProperty: 'sucursal',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.activo = 1;
                return item;
            },
            afterSave: (item, isNew) => {
                if (isNew) {
                    this.sucursales.data.push(item);
                }
                this.contarActivos();
                this.sucursal = {};
            },
            afterDelete: () => {
                this.contarActivos();
            }
        });
    }

    ngOnInit() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.cargarCatalogosUbicacion();
        this.loadAll();
    }

    public override loadAll() {
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.getAll('sucursales', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
            this.sucursales = sucursales;
            this.loading = false;
            this.contarActivos();
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    protected aplicarFiltros(): void {
        this.cdr.markForCheck();
        this.loadAll();
    }

    override openModal(template: TemplateRef<any>, sucursal?: any) {
        super.openModal(template, sucursal, {class: 'modal-lg'});
        this.hidratarUbicacion();
        this.prepararGiroModal();
    }

    public esCostaRicaFe(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
    }

    public esElSalvadorFe(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_SV;
    }

    public municipiosFiltradosCr(): any[] {
        return this.feCrUbic.municipiosPorProvincia(this.municipios, this.sucursal?.cod_departamento);
    }

    public distritosFiltradosCr(): any[] {
        return this.feCrUbic.distritosPorCanton(
            this.distritos,
            this.sucursal?.cod_departamento,
            this.sucursal?.cod_municipio,
        );
    }

    public setDepartamento(cod?: unknown): void {
        alCambiarDepartamento(this.sucursal, this.departamentos, cod ?? this.sucursal.cod_departamento);
        this.cdr.markForCheck();
    }

    public setMunicipio(cod?: unknown): void {
        alCambiarMunicipio(this.sucursal, this.municipios, cod ?? this.sucursal.cod_municipio);
        this.cdr.markForCheck();
    }

    public setDistrito(cod?: unknown): void {
        alCambiarDistrito(this.sucursal, this.distritos, this.municipios, cod ?? this.sucursal.cod_distrito);
        this.cdr.markForCheck();
    }

    public setGiro(): void {
        const hit = this.actividad_economicas.find((item: any) => item.cod == this.sucursal.cod_actividad_economica);
        this.sucursal.giro = hit ? hit.nombre : null;
        if (this.sucursal.cod_actividad_economica == null || this.sucursal.cod_actividad_economica === '') {
            this.sucursal.cod_actividad_economica = null;
            this.sucursal.giro = null;
        }
        this.cdr.markForCheck();
    }

    public onActividadContribuyenteCrChange(item: ContribuyenteActividadOption | null): void {
        if (item) {
            this.sucursal.cod_actividad_economica = item.codigo;
            this.sucursal.giro = item.descripcion;
        } else {
            this.sucursal.cod_actividad_economica = null;
            this.sucursal.giro = null;
        }
        this.cdr.markForCheck();
    }

    private prepararGiroModal(): void {
        try {
            this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas') || '[]');
        } catch {
            this.actividad_economicas = [];
        }
        this.actividadContribuyenteSeleccionada = null;
        this.actividadesContribuyenteCr = [];
        if (this.esCostaRicaFe()) {
            const cod = String(this.sucursal?.cod_actividad_economica ?? '').trim();
            if (cod) {
                const desc = String(this.sucursal?.giro ?? '');
                this.actividadContribuyenteSeleccionada = {
                    codigo: cod,
                    descripcion: desc,
                    label: desc ? `${cod} — ${desc}` : cod,
                };
            }
            this.cargarActividadesCr();
        }
        this.cdr.markForCheck();
    }

    private cargarActividadesCr(): void {
        const nit = String(this.apiService.auth_user()?.empresa?.nit ?? '').replace(/\D/g, '');
        if (nit.length < 9 || nit.length > 12) {
            return;
        }
        this.contribuyenteCargandoCr = true;
        this.haciendaContribuyenteClient.getContribuyente(nit)
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
                    this.actividadesContribuyenteCr =
                        sel?.codigo && !list.some((a) => this.compareActividadContribuyenteCr(a, sel))
                            ? [sel, ...list]
                            : list;
                    this.cdr.markForCheck();
                },
                error: (e) => this.alertService.error(e),
            });
    }

    private cargarCatalogosUbicacion(): void {
        try {
            this.departamentos = JSON.parse(localStorage.getItem('departamentos') || '[]');
            this.municipios = JSON.parse(localStorage.getItem('municipios') || '[]');
            this.distritos = JSON.parse(localStorage.getItem('distritos') || '[]');
        } catch {
            this.departamentos = [];
            this.municipios = [];
            this.distritos = [];
        }
        this.feCrUbic.cargarCatalogosYLs().pipe(this.untilDestroyed()).subscribe((r) => {
            if (r) {
                this.departamentos = r.dep;
                this.municipios = r.mun;
                this.distritos = r.dis;
                this.hidratarUbicacion();
                this.cdr.markForCheck();
            }
        });
    }

    private hidratarUbicacion(): void {
        if (!this.sucursal || !this.esCostaRicaFe()) {
            return;
        }
        hidratarCodigosUbicacion(this.sucursal, {
            departamentos: this.departamentos,
            municipios: this.municipios,
            distritos: this.distritos,
        });
    }

    public contarActivos(){
        this.sucursales_activas = this.sucursales.data?.filter((item:any) => item.activo == '1').length || 0;
        this.cdr.markForCheck();
    }

    public setEstado(sucursal:any){
        this.apiService.store('sucursal', sucursal)
            .pipe(this.untilDestroyed())
            .subscribe(sucursal => {
            if(sucursal.activo == '1'){
                this.alertService.success('Sucursal activada', 'La sucursal fue activada exitosamente.');
            }else{
                this.alertService.success('Sucursal desactivada', 'La sucursal fue desactivada exitosamente.');
            }
            this.contarActivos();
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

}
