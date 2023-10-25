import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-informacion',
  templateUrl: './producto-informacion.component.html'
})
export class ProductoInformacionComponent implements OnInit {

    @Input() producto: any = {};
    public categorias:any[] = [];
    public categoria:any = {};
    public bodegas:any[] = [];
    public loading = false;
    public guardar = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        
        this.apiService.getAll('categorias').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

    }

    public setCategoria(categoria:any){
        this.categorias.push(categoria);
        this.producto.id_categoria = categoria.id;
    }


    public onSubmit() {
        this.guardar = true;
        this.apiService.store('producto', this.producto).subscribe(producto => {
            this.guardar = false;
            if(!this.producto.id) {
                this.producto = producto;
                this.router.navigate(['/producto/'+ producto.id]);
            }
            this.alertService.success("Producto guardado");
        },error => {this.alertService.error(error); this.guardar = false; });
    }

    public barcode(){
        var ventana = window.open(this.apiService.baseUrl + "/api/barcode/" + this.producto.codigo + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    

}
