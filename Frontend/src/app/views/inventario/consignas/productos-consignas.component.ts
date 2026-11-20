import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { NgSelectModule } from '@ng-select/ng-select';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
@Component({
    selector: 'app-productos-consignas',
    templateUrl: './productos-consignas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PopoverModule, SumPipe, PaginationComponent, LazyImageDirective, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ProductosConsignasComponent extends BaseCrudComponent<any> implements OnInit {

    public productos:any = { data: [] };
    public downloading:boolean = false;
    public downloadingTomaInventario = false;
    public clientes: any[] = [];
    public idClienteTomaInventario: number | null = null;
    public producto:any = {};
    /** ponytail: separado de `producto` del CRUD para que el modal no pierda `ventas` */
    public modalConsigna: any = null;
    public sucursales:any = [];
    public categorias:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef,
        private router: Router
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'producto',
            itemsProperty: 'productos',
            itemProperty: 'producto',
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'Consigna guardada',
                updated: 'Consigna guardada',
                deleted: 'Consigna eliminada exitosamente.',
                createTitle: 'Consigna guardada',
                updateTitle: 'Consigna guardada',
                deleteTitle: 'Consigna eliminada',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.onFiltrar();
    }

    ngOnInit() {
        this.filtros = {
            ...(this.filtros ?? {}),
            paginate: 25,
            buscador: '',
            categoria: '',
            page: 1,
        };
        this.loadAll();

        this.apiService.getAll('categorias/list')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (categorias) => {
              this.categorias = categorias;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });

        this.apiService.getAll('clientes/list')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (clientes) => {
              this.clientes = Array.isArray(clientes) ? clientes : (clientes?.data ?? []);
              this.cdr.markForCheck();
            },
            error: () => this.cdr.markForCheck(),
          });

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (sucursales) => {
              this.sucursales = sucursales;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });
    }

    public override loadAll() {
        this.loading = true;
        this.apiService.getAll('productos/consignas', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (productos) => {
              this.productos = productos?.data !== undefined ? productos : { data: Array.isArray(productos) ? productos : [] };
              this.loading = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.loading = false;
              this.cdr.markForCheck();
            }
          });
    }

    public buscar() {
        this.filtros.page = 1;
        this.loadAll();
    }

    public cambiarPaginacion() {
        this.filtros.page = 1;
        this.loadAll();
    }

    public override setPagination(event: number) {
        this.filtros.page = event;
        this.loadAll();
    }

    public override delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/', id)
              .pipe(this.untilDestroyed())
              .subscribe({
                next: (data) => {
                  for (let i = 0; i < this.productos['data'].length; i++) { 
                      if (this.productos['data'][i].id == data.id )
                          this.productos['data'].splice(i, 1);
                  }
                  this.cdr.markForCheck();
                },
                error: (error) => {
                  this.alertService.error(error);
                  this.cdr.markForCheck();
                }
              });
        }
    }

    public onFiltrar(){
        this.loading = true;
        this.apiService.store('productos/filtrar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (productos) => {
              this.productos = productos;
              this.loading = false;
              this.closeModal();
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.loading = false;
              this.cdr.markForCheck();
            }
          });
    }

    public mostrarColumnasLote(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa?.custom_empresa?.configuraciones) {
            return false;
        }
        const cfg = empresa.custom_empresa.configuraciones;
        return !!cfg.consignaciones_ledger_activo && !!cfg.lotes_activo;
    }

    public abrirModalConsigna(template: TemplateRef<any>, fila: any) {
        if (!fila?.id) {
            return;
        }
        this.modalConsigna = { ...fila, ventas: [...(fila.ventas ?? [])] };
        this.modalRef = this.modalManager.openModal(template, { class: 'modal-xl', backdrop: 'static' });
        const params = { ...this.filtros };
        if (fila.lote_id != null && fila.lote_id !== '') {
            params.lote_id = fila.lote_id;
        }
        this.apiService.getAll(`productos/consignas/${fila.id}/ventas`, params).subscribe({
            next: (res: any) => {
                this.modalConsigna = {
                    ...this.modalConsigna,
                    nombre: res?.nombre ?? this.modalConsigna.nombre,
                    stock: res?.stock ?? this.modalConsigna.stock,
                    ventas: res?.ventas ?? this.modalConsigna.ventas ?? [],
                };
                this.cdr.markForCheck();
            },
            error: () => {
                this.cdr.markForCheck();
            },
        });
        this.cdr.markForCheck();
    }

    public cerrarModalConsigna(): void {
        this.modalConsigna = null;
        this.closeModal();
        this.cdr.markForCheck();
    }

    public verFacturaVentaConsigna(idVenta: number): void {
        this.cerrarModalConsigna();
        void this.router.navigate(['/venta', idVenta]);
    }

    public override async onSubmit() {
        this.loading = true;
        this.saving = true;
        try {
            await this.apiService.store('producto', this.producto)
          .pipe(this.untilDestroyed())
                .toPromise();
            
              this.producto = {};
              this.alertService.success('Consigna guardada', 'La consigna fue guardado exitosamente.');
              this.closeModal();
              this.loadAll();
              this.cdr.markForCheck();
        } catch (error: any) {
              this.alertService.error(error);
        } finally {
              this.loading = false;
            this.saving = false;
            this.cdr.markForCheck();
            }
    }

    public etiquetaCliente(cliente: any): string {
        if (cliente == null || cliente === '') {
            return '';
        }
        if (typeof cliente === 'number') {
            const encontrado = this.clientes.find((c) => Number(c.id) === cliente);
            return encontrado ? this.etiquetaCliente(encontrado) : '';
        }
        const base =
            cliente?.tipo === 'Empresa'
                ? cliente?.nombre_empresa
                : [cliente?.nombre, cliente?.apellido].filter(Boolean).join(' ').trim()
                    || cliente?.nombre_completo;
        const comercial = cliente?.nombre_comercial ? ` (${cliente.nombre_comercial})` : '';
        const etiqueta = ((base ?? '') + comercial).trim();
        return etiqueta || `Cliente #${cliente?.id ?? ''}`;
    }

    public etiquetaClienteToma(): string {
        if (!this.idClienteTomaInventario) {
            return '';
        }
        const cliente = this.clientes.find((c) => Number(c.id) === Number(this.idClienteTomaInventario));
        return cliente ? this.etiquetaCliente(cliente) : '';
    }

    public descargarTomaInventarioPdf(): void {
        this.downloadingTomaInventario = true;
        const params: Record<string, unknown> = {};
        if (this.idClienteTomaInventario) {
            params['id_cliente'] = this.idClienteTomaInventario;
        }
        this.apiService.export('productos/consignas/exportar-toma-inventario-pdf', params).subscribe({
            next: (blob) => {
                const nombre = this.idClienteTomaInventario
                    ? `toma-inventario-consigna-cliente-${this.idClienteTomaInventario}.pdf`
                    : 'toma-inventario-consigna-todos.pdf';
                this.apiService.downloadFile(blob, nombre);
                this.downloadingTomaInventario = false;
                this.closeModal();
                this.cdr.markForCheck();
            },
            error: (err) => {
                this.alertService.error(err);
                this.downloadingTomaInventario = false;
                this.cdr.markForCheck();
            },
        });
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('productos/consignas/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (data:Blob) => {
              const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
              const url = window.URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = 'productos-en-consigna.xlsx';
              document.body.appendChild(a);
              a.click();
              document.body.removeChild(a);
              window.URL.revokeObjectURL(url);
              this.downloading = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.downloading = false;
              this.cdr.markForCheck();
            }
          });
    }

    /**
     * Verifica si Shopify está activo en la empresa
     */
    public isShopifyActive(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa) return false;
        
        // Verificar si Shopify está configurado y conectado
        return !!(empresa.shopify_store_url && 
                 empresa.shopify_consumer_secret && 
                 empresa.shopify_status === 'connected');
    }

}
