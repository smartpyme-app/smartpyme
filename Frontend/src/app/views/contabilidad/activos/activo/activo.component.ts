import { Component, OnInit, ViewChild, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';

import { CommonModule } from '@angular/common';

import { FormsModule } from '@angular/forms';

import { RouterModule, ActivatedRoute, Router } from '@angular/router';

import { NgSelectModule } from '@ng-select/ng-select';

import { CurrencyPipe } from '@pipes/currency-format.pipe';

import { AlertService } from '@services/alert.service';

import { ApiService } from '@services/api.service';

import { BaseComponent } from '@shared/base/base.component';

import { CrearCategoriaActivoComponent } from '@shared/modals/crear-categoria-activo/crear-categoria-activo.component';

import { BajaActivoComponent } from '@shared/modals/baja-activo/baja-activo.component';



@Component({

    selector: 'app-activo',

    templateUrl: './activo.component.html',

    standalone: true,

    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, CrearCategoriaActivoComponent, BajaActivoComponent, CurrencyPipe],

    changeDetection: ChangeDetectionStrategy.OnPush,

})

export class ActivoComponent extends BaseComponent implements OnInit {



    @ViewChild('bajaActivo') bajaActivo!: BajaActivoComponent;



    public activo: any = {};

    public categorias: any[] = [];

    public sucursales: any[] = [];

    public usuarios: any[] = [];

    public loading = false;

    public saving = false;

    public depreciaciones: any[] = [];

    public loadingDepreciaciones = false;

    public origenEgreso: any = null;
    public origenCompraDetalle: any = null;
    private compraCapitalizacionId: number | null = null;



    constructor(

        public apiService: ApiService,

        protected alertService: AlertService,

        private route: ActivatedRoute,
        private router: Router,
        private cdr: ChangeDetectorRef,

    ) {

        super();

    }



    ngOnInit() {

        this.apiService.getAll('activos/categorias', { list: 1 })

            .pipe(this.untilDestroyed())

            .subscribe(categorias => {

                this.categorias = categorias;

                this.cdr.markForCheck();

            }, error => this.alertService.error(error));



        this.apiService.getAll('sucursales/list')

            .pipe(this.untilDestroyed())

            .subscribe(sucursales => {

                this.sucursales = sucursales;

                this.cdr.markForCheck();

            }, error => this.alertService.error(error));



        this.apiService.getAll('usuarios/list')

            .pipe(this.untilDestroyed())

            .subscribe(usuarios => {

                this.usuarios = usuarios;

                this.cdr.markForCheck();

            }, error => this.alertService.error(error));



        this.route.params.pipe(this.untilDestroyed()).subscribe((params: any) => {

            if (params.id) {

                this.loading = true;

                this.apiService.read('activo/', params.id)

                    .pipe(this.untilDestroyed())

                    .subscribe(activo => {

                        this.activo = activo;

                        this.loading = false;

                        this.cargarDepreciaciones(params.id);

                        this.cdr.markForCheck();

                    }, error => {

                        this.alertService.error(error);

                        this.loading = false;

                        this.cdr.markForCheck();

                    });

            } else {

                this.initNuevo();

                this.route.queryParams.pipe(this.untilDestroyed()).subscribe((query: any) => {
                    if (query.compra_id) {
                        this.compraCapitalizacionId = Number(query.compra_id);
                    }
                    if (query.egreso_id) {
                        this.cargarPrefillEgreso(query.egreso_id);
                    }
                    if (query.compra_detalle_id) {
                        this.cargarPrefillCompraDetalle(query.compra_detalle_id);
                    }
                });

            }

        });

    }



    private cargarPrefillEgreso(egresoId: number | string) {

        this.loading = true;

        this.apiService.read('activos/prefill-egreso/', Number(egresoId))

            .pipe(this.untilDestroyed())

            .subscribe(prefill => {

                this.origenEgreso = prefill;

                this.activo = {

                    ...this.activo,

                    ...prefill,

                    estado: 'En uso',

                    valor_residual: this.activo.valor_residual ?? 0,

                    es_usado: false,

                    metadata: this.activo.metadata ?? {},

                };

                this.loading = false;

                this.cdr.markForCheck();

            }, error => {

                this.alertService.error(error);

                this.loading = false;

                this.cdr.markForCheck();

            });

    }



    private cargarPrefillCompraDetalle(detalleId: number | string) {
        this.loading = true;
        this.apiService.read('activos/prefill-compra-detalle/', Number(detalleId))
            .pipe(this.untilDestroyed())
            .subscribe(prefill => {
                this.origenCompraDetalle = prefill;
                this.compraCapitalizacionId = prefill.id_compra ?? this.compraCapitalizacionId;
                this.activo = {
                    ...this.activo,
                    ...prefill,
                    estado: 'En uso',
                    valor_residual: this.activo.valor_residual ?? 0,
                    es_usado: false,
                    metadata: this.activo.metadata ?? {},
                };
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    private cargarDepreciaciones(id: number) {

        this.loadingDepreciaciones = true;

        this.apiService.getToUrl(`${this.apiService.apiUrl}activo/${id}/depreciaciones`)

            .pipe(this.untilDestroyed())

            .subscribe(lineas => {

                this.depreciaciones = lineas ?? [];

                this.loadingDepreciaciones = false;

                this.cdr.markForCheck();

            }, error => {

                this.depreciaciones = [];

                this.loadingDepreciaciones = false;

                this.alertService.error(error);

                this.cdr.markForCheck();

            });

    }



    private initNuevo() {

        this.activo = {

            fecha_compra: this.apiService.date(),

            estado: 'En uso',

            valor_residual: 0,

            es_usado: false,

            metadata: {},

        };

        this.cdr.markForCheck();

    }



    public setCategoria(categoria: any) {
        this.categorias = [...this.categorias, categoria].sort((a, b) => a.nombre.localeCompare(b.nombre));
        this.aplicarDefaultsCategoria(categoria);
        this.cdr.markForCheck();
    }

    public onCategoriaChange(idCategoria: number) {
        const categoria = this.categorias.find(c => c.id === idCategoria);
        if (categoria) {
            this.aplicarDefaultsCategoria(categoria);
        }
        this.cdr.markForCheck();
    }

    private aplicarDefaultsCategoria(categoria: any) {
        this.activo.id_categoria = categoria.id;
        if (categoria.vida_util_anios && !this.activo.vida_util) {
            this.activo.vida_util = categoria.vida_util_anios;
        }
        if (categoria.valor_residual_default != null && !this.activo.valor_residual) {
            this.activo.valor_residual = categoria.valor_residual_default;
        }
    }



    get activoVigente(): boolean {

        return !!this.activo?.id && this.activo?.estado_registro !== 'baja';

    }



    public onBajaAplicada(activo: any) {

        this.activo = activo;

        this.cargarDepreciaciones(activo.id);

        this.cdr.markForCheck();

    }



    public onSubmit() {

        this.saving = true;

        this.apiService.store('activo', this.activo)

            .pipe(this.untilDestroyed())

            .subscribe(activo => {
                this.activo = activo;
                this.saving = false;
                this.origenEgreso = null;
                this.origenCompraDetalle = null;
                this.alertService.success('Guardado', 'Activo registrado correctamente.');
                this.cargarDepreciaciones(activo.id);

                const compraId = this.compraCapitalizacionId;
                if (compraId && activo.id_compra_detalle) {
                    this.apiService.read('activos/pendientes-compra/', compraId)
                        .pipe(this.untilDestroyed())
                        .subscribe((pendientes: any[]) => {
                            if (pendientes?.length) {
                                this.router.navigate(['/contabilidad/activos/capitalizar-compra', compraId]);
                            }
                            this.cdr.markForCheck();
                        }, () => this.cdr.markForCheck());
                } else {
                    this.cdr.markForCheck();
                }
            }, error => {

                this.alertService.error(error);

                this.saving = false;

                this.cdr.markForCheck();

            });

    }

}


