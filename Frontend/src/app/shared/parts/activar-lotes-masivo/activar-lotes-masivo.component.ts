import { Component, OnInit, Output, EventEmitter, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import Swal from 'sweetalert2';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-activar-lotes-masivo',
  templateUrl: './activar-lotes-masivo.component.html',
  styleUrls: ['./activar-lotes-masivo.component.css']
})
export class ActivarLotesMasivoComponent implements OnInit {

    @Output() lotesMasivoCompletado = new EventEmitter<any>();
    @ViewChild('mlotesMasivo') public mlotesMasivoTemplate!: TemplateRef<any>;
    
    public categorias: any[] = [];
    public categoriasSeleccionadas: number[] = [];
    public habilitarLotes: boolean = true;
    public procesandoLotes: boolean = false;
    public loading: boolean = false;

    modalRef?: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.cargarCategorias();
    }

    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

    public cargarCategorias() {
        this.loading = true;
        this.apiService.getAll('categorias').subscribe(categorias => { 
            this.categorias = categorias;
            this.loading = false;
        }, error => {
            this.alertService.error(error);
            this.loading = false;
        });
    }

    public openModal() {
        if (!this.isLotesActivo()) {
            this.alertService.warning(
                'Lotes no activo',
                'Debe activar el módulo de lotes en las configuraciones de la empresa antes de usar esta funcionalidad.'
            );
            return;
        }
        
        this.categoriasSeleccionadas = [];
        this.habilitarLotes = true;
        this.cargarCategorias();
        this.modalRef = this.modalService.show(this.mlotesMasivoTemplate, {class: 'modal-lg', backdrop: 'static'});
    }
    
    public toggleCategoriaLote(categoriaId: number) {
        const index = this.categoriasSeleccionadas.indexOf(categoriaId);
        if (index > -1) {
            this.categoriasSeleccionadas.splice(index, 1);
        } else {
            this.categoriasSeleccionadas.push(categoriaId);
        }
    }
    
    public isCategoriaSeleccionada(categoriaId: number): boolean {
        return this.categoriasSeleccionadas.includes(categoriaId);
    }
    
    public seleccionarTodasCategorias() {
        this.categoriasSeleccionadas = this.categorias
            .filter((c: any) => c.enable)
            .map((c: any) => c.id);
    }
    
    public deseleccionarTodasCategorias() {
        this.categoriasSeleccionadas = [];
    }
    
    public todasCategoriasSeleccionadas(): boolean {
        const categoriasActivas = this.categorias.filter((c: any) => c.enable);
        return categoriasActivas.length > 0 && 
               this.categoriasSeleccionadas.length === categoriasActivas.length;
    }
    
    public habilitarLotesMasivo() {
        if (this.categoriasSeleccionadas.length === 0) {
            this.alertService.error('Debe seleccionar al menos una categoría');
            return;
        }
        
        const n = this.categoriasSeleccionadas.length;
        const htmlHabilitar = `
            <p class="text-start mb-2">
                Los productos (excepto servicios) de <strong>${n}</strong> categoría(s) pasarán a manejar stock <strong>por lotes</strong>.
            </p>
            <p class="text-start mb-2">
                Donde haya inventario sin lotes, el sistema creará un <strong>lote inicial</strong>
                (<em>STOCK-INICIAL</em>) por bodega con el stock actual. Luego podrá editar cada lote
                (código y fecha de vencimiento).
            </p>
            <p class="text-start mb-0 text-muted">
                Los productos que ya tenían lotes activos pero sin stock migrado también se completarán en este proceso.
            </p>
        `;
        const htmlDeshabilitar = `
            <p class="text-start mb-0">
                Se desactivará el inventario por lotes en los productos de <strong>${n}</strong> categoría(s).
                El stock en lotes no se elimina; solo deja de exigirse el control por lote en ventas y movimientos.
            </p>
        `;

        Swal.fire({
            title: this.habilitarLotes ? 'Inventario por lotes (masivo)' : 'Deshabilitar lotes (masivo)',
            html: this.habilitarLotes ? htmlHabilitar : htmlDeshabilitar,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: this.habilitarLotes ? 'Aplicar' : 'Deshabilitar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }
            this.ejecutarHabilitarLotesMasivo();
        });
    }

    private ejecutarHabilitarLotesMasivo() {
        this.procesandoLotes = true;
        this.apiService.store('productos/habilitar-lotes-masivo', {
            categorias: this.categoriasSeleccionadas,
            habilitar: this.habilitarLotes
        }).subscribe((response: any) => {
            let detalle = `Se actualizaron ${response.productos_actualizados} producto(s).`;
            const trasladados = response.lotes_trasladados ?? response.lotes_creados ?? 0;
            if (trasladados > 0) {
                detalle += ` Stock trasladado a ${trasladados} lote(s) STOCK-INICIAL (${response.unidades_migradas} uds en ${response.productos_con_migracion} producto(s)).`;
            }
            this.alertService.success(
                response.message || 'Operación completada exitosamente',
                detalle
            );
            this.lotesMasivoCompletado.emit(response);
            this.procesandoLotes = false;
            this.modalRef?.hide();
            this.categoriasSeleccionadas = [];
        }, error => {
            this.alertService.error(error);
            this.procesandoLotes = false;
        });
    }

}
