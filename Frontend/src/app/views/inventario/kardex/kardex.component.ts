import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-kardex',
  templateUrl: './kardex.component.html',
})
export class KardexComponent implements OnInit {

	public producto:any = [];
	public productos:any[] = [];
	public sucursales:any[] = [];
	public filtro:any = {};
	public loading:boolean = false;

    constructor(private apiService: ApiService, private alertService: AlertService,  private route: ActivatedRoute, private router: Router){ }

	ngOnInit() {
        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();
        this.filtro.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.filtro.detalle = '';
        const id = +this.route.snapshot.paramMap.get('id')!;
        if(!isNaN(id)){
            this.filtro.id_producto = id;
            this.loadAll();
        }

        this.apiService.getAll('sucursales').subscribe(sucursales => {
            this.sucursales = sucursales;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    public loadAll() {

     	this.loading = true; 
            this.apiService.store('producto/kardex', this.filtro).subscribe(producto => {
            	this.producto = producto;
     		this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    selectProducto(producto:any){
        this.filtro.id_producto = producto.id;
        console.log(this.filtro);
    }

}
