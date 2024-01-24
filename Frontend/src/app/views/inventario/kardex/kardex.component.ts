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
	public filtros:any = {};
	public loading:boolean = false;

    constructor(private apiService: ApiService, private alertService: AlertService,  private route: ActivatedRoute, private router: Router){ }

	ngOnInit() {
        this.filtros.inicio = this.apiService.date();
        this.filtros.fin = this.apiService.date();
        this.filtros.id_inventario = this.apiService.auth_user().id_sucursal;
        this.filtros.detalle = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        const id = +this.route.snapshot.paramMap.get('id')!;
        if(!isNaN(id)){
            this.filtros.id_producto = id;
            this.loadAll();
        }

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    public loadAll() {

     	this.loading = true; 
        this.apiService.getAll('productos/kardex', this.filtros).subscribe(producto => {
            this.producto = producto;
     		this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    selectProducto(producto:any){
        this.filtros.id_producto = producto.id;
        console.log(this.filtros);
    }

    public descargarKardex(){
        this.apiService.export('productos/kardex/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = this.apiService.slug(this.producto.nombre) + '-kardex.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar kardex:', error); }
        );
    }

}
