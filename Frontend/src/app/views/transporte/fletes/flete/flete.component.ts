import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../../pipes/sum.pipe';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-flete',
  templateUrl: './flete.component.html',
  providers: [ SumPipe ]
})

export class FleteComponent implements OnInit {

    public flete: any= {};
    public cliente: any = {};
    public motoristas: any = [];
    public clientes: any = [];
    public proveedores: any = [];
    public flotas: any = [];
    public loading = false;
    
    modalRef!: BsModalRef;
   
    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {

        const id = +this.route.snapshot.paramMap.get('id')!;
        if(isNaN(id)){
            this.flete = {};
            this.flete.fecha = this.apiService.date();
            this.flete.fecha_pago = this.apiService.date();
            this.flete.tipo = 'Local';
            this.flete.estado = 'Pendiente';
            this.flete.tipo_embalaje = 'Granel';
            this.flete.tipo_transporte = 'Furgón';
            this.flete.metodo_pago = 'Efectivo';
            this.flete.subtotal = 0;
            this.flete.motorista = 0;
            this.flete.combustible = 0;
            this.flete.gastos = 0;
            this.flete.seguro = 0;
            this.flete.otros = 0;
            this.flete.cliente = {};
            this.flete.detalles = [];
            this.flete.usuario_id = this.apiService.auth_user().id;
            this.flete.sucursal_id = this.apiService.auth_user().sucursal_id;
        }
        else{
            this.loading = true;
            this.apiService.read('flete/', id).subscribe(flete => {
                this.flete = flete;
                this.cliente = flete.cliente;
                this.setTotal();
                this.loading = false;
            }, error => {this.alertService.error(error);this.loading = false;});
        }

        this.apiService.getAll('motoristas/list').subscribe(motoristas => { 
            this.motoristas = motoristas;
            this.loading = false;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('flotas').subscribe(flotas => { 
            this.flotas = flotas;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public clienteSelect(cliente:any):void{
        this.cliente = cliente;
        this.flete.cliente_id = cliente.id;
    }

    public onSelectMotorista(item:any){
        this.flete.motorista_id = item.item.id;
    }

    public clearCliente():void{
        if (this.flete.nombre == '') {
            this.flete.cliente_id = null;
        }
    }

    updateFlete(flete:any) {
        this.flete = flete;
        this.setTotal();
    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.flete.proveedor_id = proveedor.id;
    }

    public setCliente(cliente:any){
        this.clientes.push(cliente);
        this.flete.cliente_id = cliente.id;
    }

    public setTotal(){
        this.flete.total_unidades = this.sumPipe.transform(this.flete.detalles, 'unidades');
        this.flete.total_bultos = this.sumPipe.transform(this.flete.detalles, 'bultos');
        this.flete.total_peso = this.sumPipe.transform(this.flete.detalles, 'peso');
        this.flete.total_valor_carga = this.sumPipe.transform(this.flete.detalles, 'valor_carga');
        
        this.flete.total = (parseFloat(this.flete.subtotal) + parseFloat(this.flete.motorista) + parseFloat(this.flete.combustible) + parseFloat(this.flete.gastos) + parseFloat(this.flete.seguro)).toFixed(2);
    }

    public onSelectForma(event:any){
        this.flete.metodo_pago = event;
        if (this.flete.metodo_pago == 'Crédito' || this.flete.metodo_pago == 'Contra entrega') {
            this.flete.estado = 'Pendiente';
        }else{
            this.flete.estado = 'Pagado';
        }          
    }

    public onSubmit() {
        this.loading = true;
        this.apiService.store('flete', this.flete).subscribe(flete => {
            this.router.navigate(['/fletes']);
            this.alertService.success("Guardado");
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public imprimirOrdenDeCarga(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/orden-de-carga/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

    public imprimirCartaDePorte(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/carta-de-porte/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

    public imprimirManifiestoDeCarga(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/manifiesto-de-carga/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }


}
