import { Component, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import Swal from 'sweetalert2';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-producto-shopify-sync',
    templateUrl: './producto-shopify-sync.component.html',
})
export class ProductoShopifySyncComponent {

    @Input() bodegas: any[] = [];
    @Output() stockActualizado = new EventEmitter<void>();

    @ViewChild('mShopifySync') private modalTpl!: TemplateRef<any>;

    public modalShopifyRef!: BsModalRef;
    public productoShopifySeleccionado: any = null;
    public comparativaShopify: any = null;
    public cargandoComparativaShopify: boolean = false;
    public sincronizandoShopify: boolean = false;
    public errorComparativaShopify: string = '';
    public syncShopifyConfig: any = this.configInicial();
    public mostrarDesgloseBodegas: boolean = false;

    constructor(
        private apiService: ApiService,
        private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    public abrir(producto: any): void {
        this.productoShopifySeleccionado = producto;
        this.comparativaShopify = null;
        this.cargandoComparativaShopify = true;
        this.errorComparativaShopify = '';
        this.mostrarDesgloseBodegas = false;
        this.syncShopifyConfig = this.configInicial();

        this.modalShopifyRef = this.modalService.show(this.modalTpl, { class: 'modal-lg modal-dialog-centered', backdrop: 'static' });
        this.cargarComparativaShopify(producto.id);
    }

    public cargarComparativaShopify(productoId: number): void {
        this.cargandoComparativaShopify = true;
        this.errorComparativaShopify = '';

        this.apiService.getAll(`shopify/producto/${productoId}/comparativa`).subscribe({
            next: (data: any) => {
                this.cargandoComparativaShopify = false;
                this.comparativaShopify = data;
                if (!data.encontrado_en_shopify) {
                    this.syncShopifyConfig.direccion = 'sp_to_shopify';
                }
            },
            error: (err: any) => {
                this.cargandoComparativaShopify = false;
                this.errorComparativaShopify = err?.error?.mensaje || err?.message || 'Error al obtener datos comparativos desde Shopify.';
            }
        });
    }

    public ejecutarSincronizacionShopify(): void {
        if (!this.productoShopifySeleccionado?.id) return;

        const esHaciaShopify = this.syncShopifyConfig.direccion === 'sp_to_shopify';
        const dirTexto = esHaciaShopify
            ? 'hacia Shopify (sobrescribirá precio y stock en la tienda en línea)'
            : 'hacia SmartPyme (sobrescribirá precio y stock local)';

        Swal.fire({
            title: '¿Confirmar sincronización?',
            text: `¿Desea sincronizar "${this.productoShopifySeleccionado.nombre}" ${dirTexto}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: esHaciaShopify ? 'Sí, enviar a Shopify' : 'Sí, traer a SmartPyme',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: esHaciaShopify ? '#0d6efd' : '#198754'
        }).then((result) => {
            if (result.isConfirmed) {
                this.sincronizandoShopify = true;
                const payload = {
                    direccion: this.syncShopifyConfig.direccion,
                    sincronizar_precio: this.syncShopifyConfig.sync_precio,
                    sincronizar_stock: this.syncShopifyConfig.sync_stock,
                    sincronizar_imagenes: this.syncShopifyConfig.sync_imagenes,
                    id_bodega: this.syncShopifyConfig.id_bodega
                };

                this.apiService.store(`shopify/producto/${this.productoShopifySeleccionado.id}/sincronizar`, payload).subscribe({
                    next: (res: any) => {
                        this.sincronizandoShopify = false;
                        Swal.fire({
                            title: '¡Sincronizado!',
                            text: res?.mensaje || 'Producto sincronizado correctamente con Shopify.',
                            icon: 'success',
                            confirmButtonColor: '#198754'
                        });
                        this.alertService.success('Shopify', res?.mensaje || 'Sincronización exitosa');
                        this.cargarComparativaShopify(this.productoShopifySeleccionado.id);
                        if (!esHaciaShopify) {
                            this.stockActualizado.emit();
                        }
                    },
                    error: (err: any) => {
                        this.sincronizandoShopify = false;
                        const errorMsg = err?.error?.mensaje || err?.message || 'Error al ejecutar sincronización individual.';
                        Swal.fire('Error', errorMsg, 'error');
                        this.alertService.error(err);
                    }
                });
            }
        });
    }

    public nombreImpuesto(): string {
        const pais = this.apiService.auth_user()?.empresa?.pais;
        const nombres: Record<string, string> = {
            'El Salvador': 'IVA',
            'Guatemala': 'IVA',
            'Nicaragua': 'IVA',
            'Costa Rica': 'IVA',
            'México': 'IVA',
            'Honduras': 'ISV',
            'Panamá': 'ITBMS',
            'Belice': 'GST',
        };
        return nombres[pais] || 'Impuesto';
    }

    private configInicial() {
        return {
            direccion: 'sp_to_shopify',
            sync_precio: true,
            sync_stock: true,
            sync_imagenes: false,
            id_bodega: 'todas'
        };
    }

}
