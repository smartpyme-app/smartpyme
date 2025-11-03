import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
    selector: 'app-producto-informacion',
    templateUrl: './producto-informacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ProductoInformacionComponent implements OnInit {

    @Input() producto: any = {};
    public categoria:any = {};
    public subcategoria:any = {};
    public categorias:any[] = [];
    public bodegas:any[] = [];
    public loading = false;

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
        this.apiService.getAll('bodegas').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

    }

    // calcular(){
    //     this.producto.precio = parseFloat(this.producto.costo + (this.producto.costo * (this.producto.margen / 100))).toFixed(2);
    // }

    public onSubmit() {
        this.loading = true;
        this.apiService.store('producto', this.producto).subscribe(producto => {
            this.loading = false;
            if(!this.producto.id) {
                this.producto = producto;
                this.router.navigate(['/producto/'+ producto.id]);
            }
            this.alertService.success("Producto guardado");
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public barcode(){
        var ventana = window.open(this.apiService.baseUrl + "/api/producto/barcode/" + this.producto.id + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    

}
