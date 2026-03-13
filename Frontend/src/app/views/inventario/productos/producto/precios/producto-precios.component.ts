import { Component, OnInit, TemplateRef, Input, AfterViewInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { LazyImageDirective } from '../../../../../directives/lazy-image.directive';

declare var bootstrap: any;

@Component({
    selector: 'app-producto-precios',
    templateUrl: './producto-precios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductoPreciosComponent extends BaseModalComponent implements OnInit, AfterViewInit {

    @Input() producto: any = {};
    public precio: any = {};
    public usuarios: any = [];
    public buscador:string = '';

    constructor(
        public apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router,
        private cdr: ChangeDetectorRef
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {}

    ngAfterViewInit() {
        // Inicializar tooltips de Bootstrap
        setTimeout(() => {
            this.initializeTooltips();
        }, 100);
    }

    private initializeTooltips() {
        // Destruir tooltips existentes
        const existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        existingTooltips.forEach(element => {
            const tooltip = bootstrap.Tooltip.getInstance(element);
            if (tooltip) {
                tooltip.dispose();
            }
        });

        // Inicializar nuevos tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    override openModal(template: TemplateRef<any>, precio:any) {
        this.precio = precio;
        if(!this.precio.id){
            this.precio.clasificacion = null;
        }
        this.apiService.getAll('usuarios/list')
          .pipe(this.untilDestroyed())
          .subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        super.openModal(template, {class: 'modal-md'});
    }

    public marcarTodos(){
        this.usuarios.forEach((item:any) =>{
            item.autorizado = !item.autorizado;
        });
        this.cdr.markForCheck();
    }

    /** Porcentaje de impuesto: del producto o de la empresa. */
    public getPorcentajeProducto(): number {
        const p = this.producto?.porcentaje_impuesto;
        if (p != null && p !== '') return Number(p);
        return Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
    }

    public calPrecioBase(){
        const iva = this.getPorcentajeProducto();
        if (iva > 0) {
            this.precio.impuesto = iva / 100;
            this.precio.precio = (this.precio.precio_final / (1 + (this.precio.impuesto * 1))).toFixed(4);
        }
    }

    public calPrecioFinal(){
        const iva = this.getPorcentajeProducto();
        if (iva > 0) {
            this.precio.impuesto = iva / 100;
            this.precio.precio_final = ((this.precio.precio * 1) + (this.precio.precio * this.precio.impuesto)).toFixed(2);
        }
    }

    onSubmit(){
       
        this.loading = true;
        this.cdr.markForCheck();
        this.precio.id_producto = this.producto.id;
        this.precio.usuarios = this.usuarios;
        this.apiService.store('producto/precio', this.precio)
          .pipe(this.untilDestroyed())
          .subscribe(precio => {
            if(!this.precio.id) {
                this.precio.id = precio.id;
                this.producto.precios.unshift(precio);
                this.alertService.success('Precio creado', 'El precio fue añadido exitosamente.');
            }else{
                this.alertService.success('Precio guardado', 'El precio fue guardado exitosamente.');
            }
            this.precio = {};
            this.loading = false;
            this.cdr.markForCheck();
            this.closeModal();
            
            // Reinicializar tooltips después de agregar nuevo precio
            setTimeout(() => {
                this.initializeTooltips();
            }, 100);
        },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

    }

    delete(precio:any){
        if (confirm('¿Desea eliminar el Registro?')) {        
            this.apiService.delete('producto/precio/', precio.id)
              .pipe(this.untilDestroyed())
              .subscribe(precio => {
                for (var i = 0; i < this.producto.precios.length; ++i) {
                    if (this.producto.precios[i].id === precio.id ){
                        this.producto.precios.splice(i, 1);
                    }
                }
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
        }
    }

    getUsuariosAutorizados(precio: any): string {
        if (!precio.usuarios || precio.usuarios.length === 0) {
            return 'Ninguno';
        }
        
        if (precio.usuarios.length === 1) {
            return precio.usuarios[0].nombre || 'Usuario';
        }
        
        if (precio.usuarios.length > 5) {
            return `${precio.usuarios.length} usuarios`;
        }
        
        return precio.usuarios.map((u: any) => u.nombre).join(', ');
    }

    getTooltipUsuarios(precio: any): string {
        if (!precio.usuarios || precio.usuarios.length === 0) {
            return 'Ningún usuario autorizado';
        }
        
        return precio.usuarios.map((u: any) => u.nombre).join(', ');
    }

}
