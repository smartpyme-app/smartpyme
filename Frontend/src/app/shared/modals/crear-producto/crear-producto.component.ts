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
    @Output() update = new EventEmitter();
    public categorias: any[] = [];
    public medidas: any[] = [];
    public loading = false;
    public guardar = false;
    public usuario: any;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, 
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

    public calPrecioBase() {
        if (this.usuario.empresa.iva > 0) {
            this.producto.impuesto = this.usuario.empresa.iva / 100;
            this.producto.precio = (this.producto.precio_final / (1 + (this.producto.impuesto * 1))).toFixed(4);
        }
    }

    public calPrecioFinal() {
        if (this.usuario.empresa.iva > 0) {
            this.producto.impuesto = this.usuario.empresa.iva / 100;
            this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
        }
    }

    public onSubmit() {
        this.guardar = true;
        if (!this.producto.id) {
            if (!this.producto.costo) {
                this.producto.costo = this.producto.costo_promedio;
            }
            if (!this.producto.costo_promedio) {
                this.producto.costo_promedio = this.producto.costo;
            }
        }

        this.producto.tipo = 'Producto';
        // this.producto.empresa_id = this.apiService.auth_user().empresa_id;
        this.producto.id_empresa = this.apiService.auth_user().id_empresa;

        this.apiService.store('producto', this.producto).subscribe(producto => {
            this.guardar = false;
            this.producto = producto;
            this.update.emit(producto);
            this.modalRef?.hide();
            this.alertService.success('Producto creado', 'El producto fue añadido exitosamente.');
        }, error => {
            this.alertService.error(error);
            this.guardar = false;
        });
    }

    public isLotesActivo(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        
        // Si custom_empresa es string, parsearlo
        const customConfig = typeof empresa.custom_empresa === 'string' 
            ? JSON.parse(empresa.custom_empresa) 
            : empresa.custom_empresa;
        
        return customConfig?.configuraciones?.lotes_activo === true;
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
