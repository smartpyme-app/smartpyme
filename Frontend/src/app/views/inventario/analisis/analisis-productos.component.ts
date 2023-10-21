import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-analisis-productos',
  templateUrl: './analisis-productos.component.html'
})

export class AnalisisProductosComponent implements OnInit {

	public productos:any = [];
    public categorias:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    public filtro:any = {};
    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService
    ){}

    ngOnInit() {
        this.filtro.categoria_id = '';
        this.filtro.nombre = '';
        if(!this.categorias.data){
            this.apiService.getAll('categorias').subscribe(categorias => { 
                this.categorias = categorias;
            }, error => {this.alertService.error(error); });
        }
        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();
        // this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.store('productos/analisis', this.filtro).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});


    }


}
