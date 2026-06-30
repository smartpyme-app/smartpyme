import { Component, OnInit, TemplateRef, Input, Output, EventEmitter, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { TranslatePipe } from '@ngx-translate/core';
import { NgSelectModule } from '@ng-select/ng-select';
import { TagInputModule } from 'ngx-chips';
import { CrearCategoriaComponent } from '../crear-categoria/crear-categoria.component';
import { CrearSubCategoriaComponent } from '../crear-subcategoria/crear-subcategoria.component';

@Component({
    selector: 'app-crear-producto',
    templateUrl: './crear-producto.component.html',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        TooltipModule,
        TranslatePipe,
        NgSelectModule,
        TagInputModule,
        CrearCategoriaComponent,
        CrearSubCategoriaComponent,
    ],

})
export class CrearProductoComponent extends BaseModalComponent implements OnInit {
    @Input() producto: any = {};
    /** Si es true, el usuario puede elegir Producto, Servicio o Compuesto (p. ej. facturación / importación JSON de compras). */
    @Input() permitirElegirTipo = false;
    @Output() update = new EventEmitter();
    public categorias: any[] = [];
    public subcategorias: any[] = [];
    public subcategoriasFiltradas: any[] = [];
    public medidas: any[] = [];
    public override loading = false;
    public guardar = false;
    public usuario: any;


    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private route: ActivatedRoute,
        private router: Router
    ) {
        super(modalManager, alertService);
        this.usuario = this.apiService.auth_user();
    }

    ngOnInit() {
        this.producto.empresa_id = this.apiService.auth_user().empresa_id;

        this.apiService.getAll('categorias/padre')
            .pipe(this.untilDestroyed())
            .subscribe(categorias => {
            this.categorias = categorias;
        }, error => { this.alertService.error(error); });

        this.apiService.getAll('subcategorias')
            .pipe(this.untilDestroyed())
            .subscribe(subcategorias => {
            this.subcategorias = subcategorias;
            this.filtrarSubcategorias();
        }, error => { this.alertService.error(error); });

        this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);
    }

    override openModal(template: TemplateRef<any>) {
        this.producto = {};
        this.subcategoriasFiltradas = [];
        if (this.permitirElegirTipo) {
            this.producto.tipo = 'Producto';
        }
      super.openModal(template, {
        class: 'modal-lg',
            backdrop: 'static',
            keyboard: false,
            ignoreBackdropClick: true
        });
    }

    public onSelectCategoria(categoriaId: number | null) {
        this.producto.id_subcategoria = null;
        this.filtrarSubcategorias(categoriaId ?? this.producto.id_categoria);
    }

    private filtrarSubcategorias(categoriaId?: number | null) {
        const id = categoriaId ?? this.producto.id_categoria;
        if (!id) {
            this.subcategoriasFiltradas = [];
            return;
        }
        this.subcategoriasFiltradas = this.subcategorias.filter(
            (sub: any) => sub.id_cate_padre == id
        );
    }

    public setCategoria(categoria: any) {
        if (categoria.subcategoria) {
            this.subcategorias.push(categoria);
            this.producto.id_categoria = categoria.id_cate_padre;
            this.producto.id_subcategoria = categoria.id;
        } else {
            this.categorias.push(categoria);
            this.producto.id_categoria = categoria.id;
            this.producto.id_subcategoria = null;
        }
        this.filtrarSubcategorias();
    }

    public setSubCategoria(subcategoria: any) {
        if (!this.subcategorias.some((sub: any) => sub.id === subcategoria.id)) {
            this.subcategorias.push(subcategoria);
        }
        this.producto.id_categoria = subcategoria.id_cate_padre;
        this.producto.id_subcategoria = subcategoria.id;
        this.filtrarSubcategorias();
    }

    public setCompuesto() {
        if (this.producto.tipo == 'Producto') {
            this.producto.tipo = 'Compuesto';
        } else {
            this.producto.tipo = 'Producto';
        }
    }

    public actualizarCostoPromedio() {
        this.producto.costo_promedio = this.producto.costo;
    }

    public actualizarCosto() {
        this.producto.costo = this.producto.costo_promedio;
    }

    /** Porcentaje de impuesto: del producto o de la empresa. */
    public getPorcentajeProducto(): number {
        const p = this.producto?.porcentaje_impuesto;
        if (p != null && p !== '') return Number(p);
        return Number(this.usuario?.empresa?.iva ?? 0);
    }

    public calPrecioBase() {
        const pct = this.getPorcentajeProducto();
        if (pct <= 0) return;
        this.producto.impuesto = pct / 100;
        this.producto.precio = (this.producto.precio_final / (1 + (this.producto.impuesto * 1))).toFixed(4);
    }

    public calPrecioFinal() {
        const pct = this.getPorcentajeProducto();
        if (pct <= 0) return;
        this.producto.impuesto = pct / 100;
        this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
    }

    public onSubmit() {
        this.guardar = true;
        if (this.apiService.isSupervisorLimitado()) {
            const p = parseFloat(this.producto.precio) || 0;
            this.producto.costo = p;
            this.producto.costo_promedio = p;
        }
        if (!this.producto.id) {
            if (!this.producto.costo) {
                this.producto.costo = this.producto.costo_promedio;
            }
            if (!this.producto.costo_promedio) {
                this.producto.costo_promedio = this.producto.costo;
            }
        }

        if (this.permitirElegirTipo) {
            const t = this.producto.tipo;
            this.producto.tipo =
                t === 'Servicio' || t === 'Compuesto' || t === 'Producto' ? t : 'Producto';
        } else {
            this.producto.tipo = 'Producto';
        }
        if (this.producto.tipo === 'Servicio' && !this.producto.medida) {
            this.producto.medida = 'Unidad';
        }
        // this.producto.empresa_id = this.apiService.auth_user().empresa_id;
        this.producto.id_empresa = this.apiService.auth_user().id_empresa;

        this.apiService.store('producto', this.producto)
            .pipe(this.untilDestroyed())
            .subscribe(producto => {
            this.guardar = false;
            this.producto = producto;
            this.update.emit(producto);
            this.modalRef?.hide();
            const esServicio = producto.tipo === 'Servicio';
            this.alertService.success(
                esServicio ? 'Servicio creado' : 'Producto creado',
                esServicio
                    ? 'El servicio fue añadido exitosamente.'
                    : 'El producto fue añadido exitosamente.'
            );
        }, error => {
            this.alertService.error(error);
            this.guardar = false;
        });
    }

    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

    public isComponenteQuimicoHabilitado(): boolean {
        return this.apiService.isComponenteQuimicoHabilitado();
    }

    public barcode() {
        const raw = String(this.producto.barcode || this.producto.codigo || '').trim();
        if (!raw) {
            return;
        }
        window.open(
            this.apiService.baseUrl + '/api/barcode/' + encodeURIComponent(raw) + '?token=' + this.apiService.auth_token(),
            '_new',
            'toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900'
        );
    }

    public verificarSiExiste() {
        if (this.producto.nombre) {
            this.apiService.getAll('productos', {
                nombre: this.producto.nombre,
                estado: 1
            })
                .pipe(this.untilDestroyed())
                .subscribe(productos => {
                if (productos.data[0]) {
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                        'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' +
                        this.apiService.appUrl + '/producto/editar/' + productos.data[0].id + '">Ver producto</a>. ' +
                        '<br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
                    );
                }
                this.loading = false;
            }, error => {
                this.alertService.error(error);
                this.loading = false;
            });
        }
    }
}
