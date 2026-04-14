import { Component, OnInit, OnChanges, SimpleChanges, Input, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-producto-imagenes',
    templateUrl: './producto-imagenes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductoImagenesComponent implements OnInit, OnChanges {

    @Input() producto: any = {};
    public imagen:any = {};
    public loading:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        public apiService:ApiService, 
        private alertService:AlertService,
        private route: ActivatedRoute, 
        private router: Router,
        private cdr: ChangeDetectorRef
    ) { }

    ngOnInit() {
        this.ensureImagenes();
    }

    ngOnChanges(changes: SimpleChanges) {
        if (changes['producto']) {
            this.ensureImagenes();
        }
    }

    private ensureImagenes(): void {
        if (this.producto && !Array.isArray(this.producto.imagenes)) {
            this.producto.imagenes = [];
        }
    }


    setFile(event:any) {
        if (!this.producto?.id) {
            this.alertService.error('Guarde el producto antes de subir imágenes.');
            event.target.value = '';
            return;
        }
        this.ensureImagenes();
        this.imagen.file = event.target.files[0];
        this.imagen.id_producto = this.producto.id;
        // this.imagen.orden = this.imagenes.length + 1;
        
        let formData:FormData = new FormData();
        for (var key in this.imagen) {
            formData.append(key, this.imagen[key]);
        }
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.store('producto/imagen', formData)
          .pipe(this.untilDestroyed())
          .subscribe(imagen => {
            if(!this.imagen.id) {
                this.producto.imagenes.push(imagen);
            }
            this.imagen = {};
            this.loading = false;
            this.cdr.markForCheck();
            this.alertService.success('Imagen agregada', 'La imagen fue añadida exitosamente.');
        }, error => {this.alertService.error(error); this.loading = false; this.imagen = {}; this.cdr.markForCheck();});
    }

    // onOrder(){
    //     this.loading = true;
    //     for (var i = 0; i < this.imagenes.length - 1; ++i) {
    //         this.imagenes[i].orden = i;
    //         this.apiService.store('producto/imagen', this.imagenes[i]).subscribe(imagen => {
    //             this.loading = false;
    //         },error => {this.alertService.error(error); this.loading = false;});
    //     }
    // }

    delete(imagen:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/imagen/', imagen.id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                this.ensureImagenes();
                for (let i = 0; i < this.producto.imagenes.length; i++) { 
                    if (this.producto.imagenes[i].id == data.id )
                        this.producto.imagenes.splice(i, 1);
                }
                this.alertService.success('Imagen eliminada', 'La imagen fue eliminada exitosamente');
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
                   
        }
    }


}
