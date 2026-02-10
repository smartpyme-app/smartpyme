import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-activar-lotes-masivo',
  templateUrl: './activar-lotes-masivo.component.html',
  styleUrls: ['./activar-lotes-masivo.component.css']
})
export class ActivarLotesMasivoComponent implements OnInit {

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
        
        if (!confirm(`¿Está seguro de ${this.habilitarLotes ? 'habilitar' : 'deshabilitar'} el inventario por lotes para todos los productos de las categorías seleccionadas?`)) {
            return;
        }
        
        this.procesandoLotes = true;
        this.apiService.store('productos/habilitar-lotes-masivo', {
            categorias: this.categoriasSeleccionadas,
            habilitar: this.habilitarLotes
        }).subscribe((response: any) => {
            this.alertService.success(
                response.message || 'Operación completada exitosamente',
                `Se actualizaron ${response.productos_actualizados} productos.`
            );
            this.procesandoLotes = false;
            this.modalRef?.hide();
            this.categoriasSeleccionadas = [];
        }, error => {
            this.alertService.error(error);
            this.procesandoLotes = false;
        });
    }

}
