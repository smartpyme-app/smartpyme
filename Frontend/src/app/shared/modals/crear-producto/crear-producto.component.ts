import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-producto',
  templateUrl: './crear-producto.component.html'
})
export class CrearProductoComponent implements OnInit {
    @Input() producto: any = {};
    /** Si es true, el usuario puede elegir Producto, Servicio o Compuesto (p. ej. facturación / importación JSON de compras). */
    @Input() permitirElegirTipo = false;
    @Output() update = new EventEmitter();
    public categorias: any[] = [];
    public medidas: any[] = [];
    public loading = false;
    public guardar = false;
    public usuario: any;

    modalRef?: BsModalRef;

    constructor( 
        public apiService: ApiService, 
        private alertService: AlertService,
        private route: ActivatedRoute, 
        private router: Router,
        private modalService: BsModalService
    ) {
        this.usuario = this.apiService.auth_user();
    }

    ngOnInit() {
        this.producto.empresa_id = this.apiService.auth_user().empresa_id;
        
        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => { this.alertService.error(error); });

        this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);
    }

    openModal(template: TemplateRef<any>) {
        this.producto = {};
        if (this.permitirElegirTipo) {
            this.producto.tipo = 'Producto';
        }
        this.modalRef = this.modalService.show(template, { 
            class: 'modal-lg', 
            backdrop: 'static',
            keyboard: false,
            ignoreBackdropClick: true
        });
    }

    public setCategoria(categoria: any) {
        this.categorias.push(categoria);
        this.producto.id_categoria = categoria.id;
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

        this.apiService.store('producto', this.producto).subscribe(producto => {
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
        var ventana = window.open(
            this.apiService.baseUrl + "/api/barcode/" + this.producto.codigo + "?token=" + this.apiService.auth_token(),
            "_new",
            "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900"
        );
    }

    public verificarSiExiste() {
        if (this.producto.nombre) {
            this.apiService.getAll('productos', { 
                nombre: this.producto.nombre, 
                estado: 1 
            }).subscribe(productos => { 
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
