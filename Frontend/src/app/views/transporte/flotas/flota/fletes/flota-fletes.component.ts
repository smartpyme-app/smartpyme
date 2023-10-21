import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
  selector: 'app-flota-fletes',
  templateUrl: './flota-fletes.component.html'
})
export class FlotaFletesComponent implements OnInit {

    public id:any;
	public fletes:any= [];
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
            this.fletes = [];
        }
        else{
            this.loading = true;
            this.apiService.read('flota/fletes/', this.id).subscribe(fletes => {
                this.fletes = fletes;
            	this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.fletes.path + '?page='+ event.page).subscribe(fletes => { 
            this.fletes = fletes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    onFiltrar(){
        this.filtro.id = this.id;
        this.loading = true;
        this.apiService.store('flota/fletes/filtrar', this.filtro).subscribe(fletes => { 
            this.fletes = fletes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public delete(id:number) {
        for (let i = 0; i < this.fletes['data'].length; i++) { 
            if (this.fletes['data'][i].id == id )
                this.fletes['data'].splice(i, 1);
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
    	if (confirm('¿Confirma marcar todas las fletes como cobradas?')) {
            this.cobrar();
    	}
    }

    public imprimir(){
        window.open(this.apiService.baseUrl + '/api/flota/estado-de-cuenta/' + this.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    public imprimirCobrar(){
        if (confirm('¿Confirma marcar todas las fletes como cobradas?')) {
            this.cobrar();
            setTimeout(() => {
                window.print();
            },2000)
        }
    }


    cobrar(){
        for (var i = 0; i < this.fletes.data.length; ++i) {
            if(this.fletes.data[i].estado == 'Pendiente') {
                this.fletes.data[i].estado = 'Cobrada';
                this.apiService.store('venta', this.fletes.data[i]).subscribe(venta => {
                }, error => {this.alertService.error(error); });
            }
        }
        this.loadAll();
        this.modalRef.hide();
    }



}
