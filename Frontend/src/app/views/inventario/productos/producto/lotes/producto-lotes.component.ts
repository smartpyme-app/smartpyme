import { Component, OnInit, OnChanges, SimpleChanges, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

import Swal from 'sweetalert2';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-lotes',
  templateUrl: './producto-lotes.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule],
})
export class ProductoLotesComponent implements OnInit, OnChanges {

    @Input() producto: any = {};
    @Input() refreshKey = 0;
    public lotes: any[] = [];
    public bodegas: any[] = [];
    public lote: any = {};
    public loading: boolean = false;
    public guardar: boolean = false;
    public migrandoStock: boolean = false;
    public migracionPreview: any = null;
    public filtros: any = {
        id_bodega: null,
        numero_lote: '',
        vencimiento_proximo: false,
        vencidos: false,
        con_stock: false,
        sin_stock: false
    };

    modalRef!: BsModalRef;

    constructor(
        private apiService: ApiService, 
        private alertService: AlertService,  
        private route: ActivatedRoute, 
        private router: Router,
        private modalService: BsModalService
    ){ }

    ngOnInit() {
        this.loadBodegas();
        if (this.producto.id) {
            this.loadLotes();
            this.loadMigracionPreview();
        }
    }

    ngOnChanges(changes: SimpleChanges) {
        if (!this.producto?.id) {
            return;
        }
        if (changes['refreshKey'] && !changes['refreshKey'].firstChange) {
            this.loadLotes();
            this.loadMigracionPreview();
            return;
        }
        if (changes['producto'] && !changes['producto'].firstChange) {
            this.loadLotes();
            this.loadMigracionPreview();
        }
    }

    get puedeMigrarStock(): boolean {
        return !!this.migracionPreview?.requiere_migracion;
    }

    loadMigracionPreview() {
        if (!this.producto?.id || !this.producto.inventario_por_lotes) {
            this.migracionPreview = null;
            return;
        }
        this.apiService.getAll(`producto/${this.producto.id}/preview-migracion-lotes`).subscribe(
            (preview) => { this.migracionPreview = preview; },
            () => { this.migracionPreview = null; }
        );
    }

    migrarStockExistente() {
        if (!this.producto?.id || !this.puedeMigrarStock) {
            return;
        }

        const preview = this.migracionPreview;
        const listaHtml = (preview.bodegas || [])
            .map((b: any) => `<li>${b.nombre_bodega}: <strong>${b.stock_inventario}</strong> uds</li>`)
            .join('');

        Swal.fire({
            title: 'Trasladar stock al lote inicial',
            html: `
                <p class="text-start mb-2">
                    Hay stock registrado en inventario que aún no está en lotes.
                    Se trasladará a un <strong>lote inicial</strong> (<em>${preview.numero_lote || 'STOCK-INICIAL'}</em>)
                    por bodega, para que pueda seguir vendiendo y controlando existencias por lote.
                </p>
                <p class="text-start mb-2">
                    Luego puede editar cada lote y definir su <strong>código</strong> y
                    <strong>fecha de vencimiento</strong>.
                </p>
                <p class="text-start mb-1"><strong>Stock a trasladar</strong> (${preview.total_bodegas} bodega(s), ${preview.total_unidades} uds):</p>
                <ul class="text-start mb-0">${listaHtml}</ul>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Trasladar stock',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }
            this.ejecutarMigracionStock();
        });
    }

    private ejecutarMigracionStock() {
        this.migrandoStock = true;
        this.apiService.store(`producto/${this.producto.id}/migrar-stock-lotes`, {}).subscribe(
            (res: any) => {
                this.migrandoStock = false;
                const m = res.migracion_lotes;
                if (m && (m.lotes_creados > 0 || m.lotes_actualizados > 0)) {
                    this.alertService.success(
                        res.message || 'Stock migrado',
                        `Se importaron ${m.unidades_migradas} unidad(es) en ${m.lotes_creados + (m.lotes_actualizados || 0)} bodega(s).`
                    );
                } else {
                    this.alertService.success(res.message || 'Sin cambios', 'No había stock pendiente de migrar.');
                }
                this.loadLotes();
                this.loadMigracionPreview();
            },
            (error) => {
                this.migrandoStock = false;
                this.alertService.error(error);
            }
        );
    }

    loadBodegas() {
        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
        }, error => {
            this.alertService.error(error);
        });
    }

    loadLotes() {
        this.loading = true;
        const params: any = {
            id_producto: this.producto.id
        };

        if (this.filtros.id_bodega) {
            params.id_bodega = this.filtros.id_bodega;
        }
        if (this.filtros.numero_lote) {
            params.numero_lote = this.filtros.numero_lote;
        }
        if (this.filtros.vencimiento_proximo) {
            params.vencimiento_proximo = true;
        }
        if (this.filtros.vencidos) {
            params.vencidos = true;
        }
        if (this.filtros.con_stock) {
            params.con_stock = true;
        }
        if (this.filtros.sin_stock) {
            params.sin_stock = true;
        }

        // Usar endpoint específico para lotes de un producto
        this.apiService.getAll(`lotes/producto/${this.producto.id}`, params).subscribe(lotes => {
            // Este endpoint siempre devuelve un array directo (sin paginación)
            this.lotes = Array.isArray(lotes) ? lotes : [];
            this.loading = false;
        }, error => {
            this.alertService.error(error);
            this.loading = false;
            this.lotes = [];
        });
    }

    aplicarFiltros() {
        this.loadLotes();
    }

    limpiarFiltros() {
        this.filtros = {
            id_bodega: null,
            numero_lote: '',
            vencimiento_proximo: false,
            vencidos: false,
            con_stock: false,
            sin_stock: false
        };
        this.loadLotes();
    }

    openModal(template: TemplateRef<any>, lote?: any) {
        if (lote) {
            this.lote = Object.assign({}, lote);
            // input type="date" solo muestra valores en formato YYYY-MM-DD
            if (this.lote.fecha_vencimiento) {
                this.lote.fecha_vencimiento = this.toDateInputValue(this.lote.fecha_vencimiento);
            }
            if (this.lote.fecha_fabricacion) {
                this.lote.fecha_fabricacion = this.toDateInputValue(this.lote.fecha_fabricacion);
            }
        } else {
            this.lote = {
                id_producto: this.producto.id,
                stock: 0,
                stock_inicial: 0
            };
        }
        this.modalRef = this.modalService.show(template, {class: 'modal-lg'});
    }

    /** Convierte fecha (ISO o Date) a YYYY-MM-DD para input type="date" */
    private toDateInputValue(date: string | Date): string {
        if (!date) return '';
        const d = typeof date === 'string' ? new Date(date) : date;
        if (isNaN(d.getTime())) return '';
        return d.toISOString().slice(0, 10);
    }

    public setAjuste(event: any) {
        const index = this.lotes.findIndex(l => l.id === event.lote_id);
        if (index !== -1) {
            this.lotes[index].stock = event.stock_real;
            this.lotes[index].stock_inicial = event.stock_inicial;
        }
    }

    public onSubmit() {
        this.guardar = true;
        this.lote.id_empresa = this.apiService.auth_user().id_empresa;

        if (this.lote.id) {
            this.apiService.update('lotes', this.lote.id, this.lote).subscribe(lote => {
                const index = this.lotes.findIndex(l => l.id === lote.id);
                if (index !== -1) {
                    this.lotes[index] = lote;
                }
                this.lote = {};
                this.guardar = false;
                this.modalRef.hide();
                this.alertService.success('Lote actualizado', 'El lote fue actualizado exitosamente.');
            }, error => {
                this.alertService.error(error);
                this.guardar = false;
            });
        } else {
            // Al crear un lote nuevo, el stock inicial y stock se establecen en 0
            // Solo se pueden modificar mediante compras o ajustes
            this.lote.stock = 0;
            this.lote.stock_inicial = 0;
            this.apiService.store('lotes', this.lote).subscribe(lote => {
                this.lotes.push(lote);
                this.lote = {};
                this.guardar = false;
                this.modalRef.hide();
                this.alertService.success('Lote creado', 'El lote fue creado exitosamente. Puede agregar stock mediante compras o ajustes.');
            }, error => {
                this.alertService.error(error);
                this.guardar = false;
            });
        }
    }

    public delete(id: number) {
        if (confirm('¿Desea eliminar el lote? Esta acción no se puede deshacer.')) {
            this.apiService.delete('lotes/', id).subscribe(data => {
                this.lotes = this.lotes.filter(l => l.id !== id);
                this.alertService.success('Lote eliminado', 'El lote fue eliminado exitosamente.');
            }, error => {
                this.alertService.error(error);
            });
        }
    }

    getEstadoVencimiento(lote: any): string {
        if (!lote.fecha_vencimiento) {
            return 'sin_vencimiento';
        }

        const hoy = new Date();
        const vencimiento = new Date(lote.fecha_vencimiento);
        const diasRestantes = Math.ceil((vencimiento.getTime() - hoy.getTime()) / (1000 * 60 * 60 * 24));

        if (diasRestantes < 0) {
            return 'vencido';
        } else if (diasRestantes === 0) {
            return 'venciendo_hoy';
        } else if (diasRestantes <= 7) {
            return 'venciendo_proximo';
        } else {
            return 'vigente';
        }
    }

    getClaseEstado(estado: string): string {
        switch (estado) {
            case 'vencido':
                return 'badge bg-danger';
            case 'venciendo_hoy':
                return 'badge bg-warning';
            case 'venciendo_proximo':
                return 'badge bg-info';
            case 'vigente':
                return 'badge bg-success';
            default:
                return 'badge bg-secondary';
        }
    }

    getTextoEstado(estado: string): string {
        switch (estado) {
            case 'vencido':
                return 'Vencido';
            case 'venciendo_hoy':
                return 'Vence hoy';
            case 'venciendo_proximo':
                return 'Vence pronto';
            case 'vigente':
                return 'Vigente';
            default:
                return 'Sin vencimiento';
        }
    }

    getTotalStock(): number {
        if (!Array.isArray(this.lotes) || this.lotes.length === 0) {
            return 0;
        }
        return this.lotes.reduce((sum, lote) => sum + (parseFloat(lote.stock) || 0), 0);
    }
}
