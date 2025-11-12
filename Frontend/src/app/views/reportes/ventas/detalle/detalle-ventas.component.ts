import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

declare var $:any;

@Component({
    selector: 'app-detalle-ventas',
    templateUrl: './detalle-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})

export class DetalleVentasComponent implements OnInit {

	public ventas:any = [];
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
        this.loadAll();
        this.apiService.getAll('categorias').subscribe(categorias => { 
            this.categorias = categorias;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {

        if(!this.filtro.inicio) {
            this.filtro.inicio     = this.apiService.date();
            this.filtro.fin        = this.apiService.date();
            this.filtro.nombre     = '';
            this.filtro.categoria_id  = '';
        }

        this.loading = true;

        this.apiService.store('ventas/detalle', this.filtro).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
