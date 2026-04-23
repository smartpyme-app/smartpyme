import { Component, OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { Subject, Observable } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-facturacion-compra-consigna',
    templateUrl: './facturacion-compra-consigna.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, LazyImageDirective],
    providers: [SumPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class FacturacionCompraConsignaComponent extends BaseModalComponent implements OnInit {

    public compra: any= {};
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public sucursales:any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public canales:any = [];
    public supervisor:any = {};
    public override loading = false;
    public imprimir:boolean = false;

    // Propiedades para agregar productos/servicios
    public productos: any[] = [];
    public servicios: any[] = [];
    public productosModal!: any; // BsModalRef
    public searchTerm: string = '';
    public searchResults: any[] = [];
    public searchLoading: boolean = false;
    public searchProductos$ = new Subject<string>();
    public detalle: any = {};


    private cdr = inject(ChangeDetectorRef);

	constructor(
	    public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
	) {
        super(modalManager, alertService);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };

        // Configurar búsqueda dinámica de productos
        this.searchProductos$.pipe(
            debounceTime(300),
            distinctUntilChanged(),
            switchMap(term => {
                if (!term || term.length < 2) {
                    return of([]);
                }
                this.searchLoading = true;
                this.searchTerm = term;

                return this.apiService.store('productos/buscar-modal', {
                    termino: term,
                    id_empresa: this.apiService.auth_user().id_empresa,
                    limite: 15
                }).pipe(
                    catchError(error => {
                        console.error('Error en búsqueda:', error);
                        return of([]);
                    })
                );
            }),
            this.untilDestroyed()
        ).subscribe(results => {
            this.searchResults = results || [];
            this.searchLoading = false;
            this.cdr.markForCheck();
        });
    }

	ngOnInit() {

        this.route.params
            .pipe(this.untilDestroyed())
            .subscribe((params:any) => {
                if (params.id) {
                    this.loading = true;
                    this.apiService.read('compra/', params.id)
                        .pipe(this.untilDestroyed())
                        .subscribe(compra => {
                            this.compra = compra;
                            this.compra.cobrar_impuestos = this.compra.iva ? true : false;
                            this.loading = false;
                            this.cargarDatos();
                            this.cdr.markForCheck();
                        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
                }else{
                    this.compra = {};
                    this.compra.id_empresa = this.apiService.auth_user().id_empresa;
                    this.compra.id_usuario = this.apiService.auth_user().id;
                }
            });

    }

    public cargarDatos(){
        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('bancos/list')
            .pipe(this.untilDestroyed())
            .subscribe(bancos => {
                this.bancos = bancos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('documentos/list')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
                this.formaPagos = formaPagos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

    }

    public cambioMetodoDePago() {
        const fp = this.compra.forma_pago;
        if (fp === 'Efectivo' || fp === 'Wompi') {
            this.compra.detalle_banco = '';
            this.cdr.markForCheck();
            return;
        }
        if (this.apiService.isModuloBancos() && fp) {
            const formaPagoSeleccionada = this.formaPagos.find((f: any) => f.nombre === fp);
            if (formaPagoSeleccionada?.banco?.nombre_banco) {
                this.compra.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else {
                this.compra.detalle_banco = '';
            }
        }
        this.cdr.markForCheck();
    }

    public updateTotal(detalle:any){
        if(!detalle.cantidad){
            detalle.cantidad = 0;
        }
        if(detalle.descuento_porcentaje){
            detalle.descuento = detalle.cantidad * (detalle.costo * (detalle.descuento_porcentaje / 100));
        }else{
            detalle.descuento = 0;
        }

        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo) - parseFloat(detalle.descuento)).toFixed(4);
        this.sumTotal();
    }

    public sumTotal() {
        this.compra.sub_total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))).toFixed(2);
        this.compra.percepcion = this.compra.cobrar_percepcion ? this.compra.sub_total * 0.01 : 0;
        this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0;

        if(this.compra.cobrar_impuestos){
            this.compra.iva = ( this.compra.sub_total * 0.13 ).toFixed(2);
        }else{
            this.compra.iva = 0;
        }

        this.compra.descuento = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'descuento'))).toFixed(2);
        this.compra.total = (parseFloat(this.compra.sub_total) + parseFloat(this.compra.iva) + parseFloat(this.compra.percepcion) - parseFloat(this.compra.iva_retenido)).toFixed(2);
    }

    // Métodos para agregar productos/servicios
    public openModalProductos(template: TemplateRef<any>) {
        this.detalle = {};
        this.searchResults = [];
        this.searchTerm = '';
        this.productosModal = this.modalManager.openModal(template, { class: 'modal-lg' });
    }

    public onSearchProducts(term: string) {
        this.searchProductos$.next(term);
    }

    public selectProducto(producto: any) {
        this.detalle = {
            id_producto: producto.id,
            nombre_producto: producto.nombre,
            descripcion: producto.descripcion,
            cantidad: 1,
            costo: producto.costo || 0,
            descuento: 0,
            descuento_porcentaje: 0,
            total: producto.costo || 0,
            img: producto.img || 'default-product.png',
            tipo: producto.tipo
        };
        this.modalManager.closeModal(this.productosModal);
        this.productosModal = undefined;
        this.addDetalle();
    }

    public addDetalle() {
        if (!this.compra.detalles) {
            this.compra.detalles = [];
        }

        // Calcular total del detalle
        this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.costo) - parseFloat(this.detalle.descuento)).toFixed(2);

        this.compra.detalles.push({...this.detalle});
        this.detalle = {};
        this.sumTotal();
    }

    public delete(detalle: any) {
        const index = this.compra.detalles.indexOf(detalle);
        if (index > -1) {
            this.compra.detalles.splice(index, 1);
            this.sumTotal();
        }
    }

    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            this.openModal(template, {class: 'modal-md', backdrop:'static'});
        }

        public onFacturar(){
            if (confirm('¿Confirma procesar la ' + (this.compra.estado == 'Pre-compra' ? ' cotización.' : 'compra.') )) {
                if(!this.compra.recibido)
                    this.compra.recibido = this.compra.total;

                if(this.compra.forma_pago == 'Wompi'){
                    this.compra.estado = 'Pendiente';
                }
                this.onSubmit();
            }
        }

    // Guardar compra
        public onSubmit() {
            this.loading = true;

            this.apiService.store('compra/facturacion/consigna', this.compra).subscribe(compra => {
                this.loading = false;
                this.router.navigate(['/compras']);
                this.alertService.success('Compra creada', 'La compra fue añadida exitosamente.');
                this.cdr.markForCheck();

            },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

        }

}
