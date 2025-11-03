import { Component, OnInit, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
    selector: 'app-producto-imagenes',
    templateUrl: './producto-imagenes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ProductoImagenesComponent implements OnInit {

    @Input() producto: any = {};
    public imagen:any = {};
    public loading:boolean = false;

    constructor( public apiService:ApiService, private alertService:AlertService,
            private route: ActivatedRoute, private router: Router,
    ) { }

    ngOnInit() {

    }


    setFile(event:any) {
        this.imagen.file = event.target.files[0];
        this.imagen.producto_id = this.producto.id;
        // this.imagen.orden = this.imagenes.length + 1;
        
        let formData:FormData = new FormData();
        for (var key in this.imagen) {
            formData.append(key, this.imagen[key]);
        }
        this.loading = true;
        this.apiService.store('producto/imagen', formData).subscribe(imagen => {
            if(!this.imagen.id) {
                this.producto.imagenes.push(imagen);
            }
            this.imagen = {};
            this.loading = false;
            this.alertService.success('Guardado');
        }, error => {this.alertService.error(error); this.loading = false; this.imagen = {};});
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
            this.apiService.delete('producto/imagen/', imagen.id) .subscribe(data => {
                for (let i = 0; i < this.producto.imagenes.length; i++) { 
                    if (this.producto.imagenes[i].id == data.id )
                        this.producto.imagenes.splice(i, 1);
                }
                this.alertService.success('Eliminado');
            }, error => {this.alertService.error(error); });
                   
        }
    }


}
