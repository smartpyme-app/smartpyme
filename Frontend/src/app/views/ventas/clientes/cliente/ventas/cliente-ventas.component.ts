import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cliente-ventas',
  templateUrl: './cliente-ventas.component.html'
})
export class ClienteVentasComponent implements OnInit {

    public id:any;
	public ventas:any= [];
	public loading:boolean = false;

    public filtro:any = {};

	modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
        this.loadAll();
        
        this.filtro.estado = "";
        this.filtro.metodo_pago = "";

        if(this.route.snapshot.paramMap.get('estado')){
            this.filtro.estado = this.route.snapshot.paramMap.get('estado');
        }

        this.filtro.fecha = this.apiService.date();

    }

    public loadAll() {
        this.id = +this.route.snapshot.paramMap.get('id')!;

                	        
        if(isNaN(this.id)){
            this.ventas = [];
        }
        else{
            this.loading = true;
            this.apiService.read('cliente/ventas/', this.id).subscribe(ventas => {
                this.ventas = ventas;
            	this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.ventas.path + '?page='+ event.page).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    onFiltrar(){
        this.filtro.id = this.id;
        this.loading = true;
        this.apiService.store('cliente/ventas/filtrar', this.filtro).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public delete(id:number) {
        for (let i = 0; i < this.ventas['data'].length; i++) { 
            if (this.ventas['data'][i].id == id )
                this.ventas['data'].splice(i, 1);
        }
    }

    public setEstado(venta:any, estado:string){
        venta.estado = estado;
        this.apiService.store('venta', venta).subscribe(venta => {
            this.loadAll();
            this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); });
    }

    openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public cobrarTodo(){
    	if (confirm('¿Confirma marcar todas las ventas como cobradas?')) {
            this.cobrar();
    	}
    }

    public imprimir(){
        window.open(this.apiService.baseUrl + '/api/cliente/estado-de-cuenta/' + this.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    public imprimirCobrar(){
        if (confirm('¿Confirma marcar todas las ventas como cobradas?')) {
            this.cobrar();
            setTimeout(() => {
                window.print();
            },2000)
        }
    }


    cobrar(){
        for (var i = 0; i < this.ventas.data.length; ++i) {
            if(this.ventas.data[i].estado == 'Pendiente') {
                this.ventas.data[i].estado = 'Cobrada';
                this.apiService.store('venta', this.ventas.data[i]).subscribe(venta => {
                }, error => {this.alertService.error(error); });
            }
        }
        this.loadAll();
        this.modalRef.hide();
    }



}
