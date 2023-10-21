import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../../pipes/sum.pipe';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-facturacion-tienda',
  templateUrl: './facturacion-tienda.component.html',
  providers: [ SumPipe ]
})

export class FacturacionTiendaComponent implements OnInit {

    public venta: any= {};
    public flete: any= {};
    public detalle: any = {};
    public documentos:any = [];
    public clientes:any = [];
    public supervisor:any = {};
    public loading = false;
    public imprimir:boolean = true;
    
    modalRef!: BsModalRef;
    modalCredito!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('mcredito')
    public creditoTemplate!: TemplateRef<any>;

    
	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
	) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

	ngOnInit() {

        this.cargarDatosIniciales();

        if(+this.route.snapshot.queryParamMap.get('id')!){
            this.loadAll();
        }
        if (+this.route.snapshot.queryParamMap.get('flete')!) {
            this.cargarFlete();
        }

        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
        }, error => {this.alertService.error(error);});

    }

    public cargarDocumentos(){
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
            this.venta.tipo_documento = 'Ticket';
            this.setDocumento('Ticket');
        }, error => {this.alertService.error(error);});
    }

    public loadAll(){
        this.loading = true;
        this.apiService.read('venta/', this.venta.id).subscribe(venta => {
        this.venta = venta;
        this.loading = false;
        this.sumTotal();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public cargarFlete(){
        this.loading = true;
        this.apiService.read('flete/', +this.route.snapshot.queryParamMap.get('flete')!).subscribe(flete => {
            this.flete = flete;
            this.venta.detalles = [];
            this.venta.cliente_id = this.flete.cliente_id;            

            this.apiService.read('servicio/', 1).subscribe(producto => {
                producto.precio = this.flete.subtotal;
                producto.costo = this.flete.total - this.flete.subtotal;
                
                let iva:any = 0;
                if (producto.tipo_impuesto == 'Gravada') {
                    let valorIva   = (parseFloat(producto.precio) + parseFloat(producto.precio) * parseFloat(producto.impuesto)).toFixed(4);
                    iva       = (parseFloat(valorIva) - parseFloat(producto.precio)).toFixed(4);
                }
                let nota = this.flete.nota_facturacion ? this.flete.nota_facturacion : '';
                this.venta.detalles.push({
                   'nombre_producto' : producto.nombre + ' de ' + this.flete.punto_origen + ' hacia ' + this.flete.punto_destino + '. ' + nota,
                   'producto_id' : producto.id,
                   'cantidad' : 1,
                   'precio' : producto.precio,
                   'costo' : producto.costo,
                   'descuento' : 0,
                   'tipo_impuesto' : producto.tipo_impuesto,
                   'impuesto' : producto.impuesto,
                   'subcosto' : producto.costo,
                   'subtotal' : producto.precio,
                   'gravada' : producto.tipo_impuesto == 'Gravada' ? producto.precio : 0,
                   'exenta' : producto.tipo_impuesto == 'Exenta' ? producto.precio : 0,
                   'no_sujeta' : this.flete.no_sujeto ? this.flete.no_sujeto : 0,
                   'iva' : iva,
                   'total' : parseFloat(iva) + parseFloat(this.flete.no_sujeto) + parseFloat(producto.precio),
               });

                console.log(this.venta);

                this.sumTotal();
                this.loading = false;
            }, error => {this.alertService.error(error);this.loading = false;});

            
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public cargarDatosIniciales(){
        this.cargarDocumentos();
        this.venta = {};
        this.venta.fecha = this.apiService.date();
        this.venta.fecha_pago = this.apiService.date();
        this.venta.canal_id = 1;
        this.venta.metodo_pago = 'Efectivo';
        this.venta.tipo = 'Interna';
        this.venta.estado = 'Pagada';
        this.venta.condicion = 'Contado';
        this.venta.cliente = {};
        this.venta.credito = {};
        this.venta.detalles = [];
        this.venta.descuento = 0;
        this.detalle = {};
        this.venta.bodega_id = this.apiService.auth_user().bodega_id;
        this.venta.usuario_id = this.apiService.auth_user().id;
        this.venta.vendedor_id = this.apiService.auth_user().empleado_id;
        this.venta.sucursal_id = this.apiService.auth_user().sucursal_id;
        let corte = JSON.parse(sessionStorage.getItem('worder_corte')!);
        if (corte) {
            this.venta.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
            this.venta.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).caja_id;
            this.venta.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        }
        this.sumTotal();
        this.imprimir = true;
    }

    public sumTotal() {
        this.venta.subtotal = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'subtotal'))).toFixed(2);
        this.venta.iva_percibido = this.venta.percepcion ? this.venta.subtotal * 0.01 : 0; 
        this.venta.iva_retenido = this.venta.retencion ? this.venta.subtotal * 0.01 : 0; 

        this.venta.iva = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'iva'))).toFixed(2);
        this.venta.descuento = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento') + parseFloat(this.venta.descuento))).toFixed(2);
        this.venta.subcosto = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'subcosto'))).toFixed(2);
        this.venta.no_sujeta = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'no_sujeta'))).toFixed(2);
        this.venta.exenta = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'exenta'))).toFixed(2);
        this.venta.gravada = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'gravada'))).toFixed(2);
        this.venta.total = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total')) + parseFloat(this.venta.iva_percibido) - parseFloat(this.venta.iva_retenido)).toFixed(2);
    }

    // Cliente
    public setCliente(cliente:any){
        this.clientes.push(cliente);
        this.venta.cliente_id = cliente.id;
        if(cliente.tipo_contribuyente == "Grande") {
            this.venta.retencion = 1;
            this.sumTotal();
        }
    }


    public updateVenta(venta:any) {
        this.venta = venta;
        this.sumTotal();
    }

    public setCondicion() {
        if(this.venta.condicion == 'Crédito') { 
            this.venta.estado = 'Pendiente';
            
            this.venta.credito.forma_de_pago     = 'Meses';
            this.venta.credito.numero_de_cuotas  = 1;
            this.venta.credito.prima             = 0;
            this.venta.credito.periodo_de_gracia = 0;
            this.venta.credito.interes_anual     = 0;
            this.venta.credito.tipo_cuota        = 'Sobre Saldos';
            this.venta.credito.nota              = '';
        }else if (this.venta.condicion == 'Contado') {
            this.venta.estado = 'Pagada';    
            this.venta.fecha_pago = moment().format('YYYY-MM-DD');
        }else{
            this.venta.estado = 'Pendiente';
            this.venta.fecha_pago = moment().add(this.venta.condicion.split(' ')[0], 'days').format('YYYY-MM-DD');
        }
    }

    public setFechaPago(){
        if (this.venta.credito.forma_de_pago == 'Meses') {
            this.venta.fecha_pago = moment().add(this.venta.credito.numero_de_cuotas, 'months').format('YYYY-MM-DD');
        }
        if (this.venta.credito.forma_de_pago == 'Dias') {
            this.venta.fecha_pago = moment().add(this.venta.credito.numero_de_cuotas, 'days').format('YYYY-MM-DD');
        }
        if (this.venta.credito.forma_de_pago == 'Semanas') {
            this.venta.fecha_pago = moment().add(this.venta.credito.numero_de_cuotas, 'weeks').format('YYYY-MM-DD');
        }
    }

    public setDocumento(doc:any){
        let documento = this.documentos.find((x:any) => x.nombre == doc);
        this.venta.tipo_documento = documento.nombre;
        this.venta.correlativo = documento.actual;
    }
	
    // Orden

        openModalOrden(template: TemplateRef<any>) {
            if (!this.venta.id && !this.venta.cliente_id){
                this.venta.estado = 'Pendiente';
                this.modalRef = this.modalService.show(template, {class: 'modal-sm', backdrop:'static'});
            }
            else{
                this.onSubmit();
            }
        }
        
        onPosponer(){
            if (confirm('¿Confirma que la venta se marque como pendiente?')) {
                this.venta.metodo_pago = 'Efectivo';
                this.venta.tipo_documento = 'Ticket';
                this.onSubmit();
            }
        }    

    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop:'static'});
        }

        public onFacturar(){
            if (confirm('¿Confirma la venta?')) {
                if(!this.venta.recibido)
                    this.venta.recibido = this.venta.total;
                this.onSubmit();
            }
        }

    // Guardar venta
        public onSubmit() {

            this.loading = true;            
            this.apiService.store('facturacion', this.venta).subscribe(venta => {
                if (venta.estado == 'Pagada' && this.flete.id) {
                    this.flete.estado = 'Pagado';
                    this.flete.venta_id = venta.id;
                    this.apiService.store('flete', this.flete).subscribe(flete => {
                        this.loading = false;
                    },error => {this.alertService.error(error); this.loading = false; });
                }

                if(this.imprimir) {
                    window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
                }

                if (this.modalRef) { this.modalRef.hide() }
                this.loading = false;
                this.cargarDatosIniciales();
                this.router.navigate(['/facturacion']);
                this.alertService.success("Guardado");
            },error => {this.alertService.error(error); this.loading = false; });

        }

    //Limpiar

        public limpiar(){
            this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
        }

        public supervisorCheck(){
            this.loading = true;
            this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
                this.modalRef.hide();
                this.cargarDatosIniciales();
                this.loading = false;
                this.supervisor = {};
            },error => {this.alertService.error(error); this.loading = false; });
        }


}
