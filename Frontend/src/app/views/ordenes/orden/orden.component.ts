import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../pipes/sum.pipe';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-orden',
  templateUrl: './orden.component.html'
})
export class OrdenComponent implements OnInit {

    public orden_id:number = 0;

    public orden:any = {};
    public canales:any = [];
    public detalle:any = {};
    public loading = false;
    public pendientes = false;
    public solicitar = false;
    public guardar = false;

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

      ngOnInit() {

        this.orden_id = +this.route.snapshot.paramMap.get('id')!;
      
        if(isNaN(this.orden_id)){
            this.crearOrden();
        }
        else{
            this.cargarOrden();
        }

        this.apiService.getAll('canales').subscribe(canales => {
            this.canales = canales;
        }, error => {this.alertService.error(error);});

    }

    crearOrden(){
        this.orden = {};
        this.orden.fecha = this.apiService.date();
        this.orden.estado = 'En Proceso';
        this.orden.canal_id = 1;
        this.orden.cliente = {};
        this.orden.metodo_pago = 'Efectivo';
        this.orden.tipo_documento = 'Ticket';
        this.orden.condicion = 'Contado';
        this.orden.fecha_pago = this.apiService.date();
        this.orden.iva_retenido = 0;
        this.orden.iva = 0;
        this.orden.subcosto = 0;
        this.orden.subtotal = 0;
        this.orden.detalles = [];
        this.orden.total = 0;
        this.detalle = {};
        this.orden.usuario_id = this.apiService.auth_user().id;
        this.orden.sucursal_id = this.apiService.auth_user().sucursal_id;
        this.onSubmit();
    }

    public sumTotal() {
        this.orden.total = (parseFloat(this.sumPipe.transform(this.orden.detalles, 'total'))).toFixed(2);
    }

    public cargarOrden(){
        this.orden.cliente = {};
        this.loading = true;
        this.apiService.read('orden/', this.orden_id).subscribe(orden => {
            this.orden = orden;
            if (!this.orden.cliente_id) {
                this.orden.cliente = {};
            }
            this.loading = false;
            this.hayPendientes();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    updateOrden(orden:any) {
        this.orden = orden;
        this.sumTotal();
    }

    // Seleccionar Cliente
        clienteSelect(cliente:any):void{
            this.orden.cliente = cliente;
            this.orden.cliente_id = cliente.id;
            this.orden.nombre = cliente.nombre;
            document.getElementById("nota")!.focus();
        }

        clearCliente():void{
            if (this.orden.nombre == '') {
                this.orden.cliente_id = null;
            }
            console.log(this.orden);
        }


    // Agregar detalle
        productoSelect(producto:any):void{
            this.detalle = Object.assign({}, producto);
            this.detalle.id = null;
            
            // Impuestos
            if(this.detalle.tipo_impuesto == "Gravada") {
                this.detalle.iva     = (((this.detalle.precio - this.detalle.descuento) / 1.13) * this.detalle.cantidad) * 0.13;
            }
            
            this.detalle.total = ((this.detalle.precio - this.detalle.descuento) * this.detalle.cantidad);
            this.detalle.subcosto = (this.detalle.costo * this.detalle.cantidad);
            this.detalle.subtotal = (this.detalle.total - this.detalle.iva);
            

            // if(!detalle)
            this.detalle.venta_id = this.orden.id;
            this.loading = true;
            this.apiService.store('orden/detalle', this.detalle).subscribe(detalle => {
                this.loading = false;
                this.orden.detalles.push(detalle);
                this.sumTotal();
                this.detalle = {};
                this.alertService.success('Agregado');
                this.hayPendientes();

                // Mantener el scroll hasta abajo en la lista de productos
                setTimeout(function(){
                    var objDiv = document.getElementById("detallesList")!;
                    console.log(objDiv);
                    objDiv.scrollTop = objDiv.scrollHeight;
                },300);

            },error => {this.alertService.error(error); this.loading = false; });

        }

    // Guardar orden
        public onSubmit() {

            this.guardar = true;

            this.apiService.store('orden', this.orden).subscribe(orden => {
                this.guardar = false;
                if (this.orden.id){
                    this.alertService.success("Guardado");
                    this.hayPendientes();
                }else{
                    this.alertService.success("Creada");
                    this.router.navigate(['orden/'+ orden.id]);
                }
            },error => {this.alertService.error(error); this.guardar = false; });

        }

        public onSolicitar() {
            
            for (var i = 0; i < this.orden.detalles.length; ++i) {
                if (this.orden.detalles[i].estado == 'Agregada') {
                    this.orden.detalles[i].estado = 'Solicitada';
                    this.solicitar = true;
                    this.apiService.store('orden/detalle', this.orden.detalles[i]).subscribe(orden => {
                        this.solicitar = false;
                        this.hayPendientes();
                    },error => {this.alertService.error(error); this.solicitar = false; });
                }
            }


        }

        public hayPendientes(){
            if (this.orden.id && this.orden.detalles.length && this.orden.detalles.filter((item:any) => item.estado == 'Agregada').length) {
                this.pendientes = true;
            }else{
                this.pendientes = false;
            }
        }


}
