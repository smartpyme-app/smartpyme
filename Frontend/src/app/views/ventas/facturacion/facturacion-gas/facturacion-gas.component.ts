import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../../pipes/sum.pipe';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-facturacion-gas',
  templateUrl: './facturacion-gas.component.html',
  providers: [ SumPipe ]
})

export class FacturacionGasComponent implements OnInit {

    public venta: any= {};
    public detalle: any = {};
    public documentos:any = [];
    public metodospago:any = [];
    public supervisor:any = {};
    public total:number = 0;
    public loading = false;
    public imprimir:boolean = true;

    modalRef!: BsModalRef;
    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;
    
    constructor( 
        private apiService: ApiService, private alertService: AlertService, private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {

        const id = +this.route.snapshot.paramMap.get('id')!;

        if(isNaN(id)){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.cargarDocumentos();
            this.apiService.read('venta/', id).subscribe(venta => {
                this.venta = venta;
                if(this.venta.cliente.nombre == '')
                    this.venta.cliente.nombre = this.venta.nombre;
                this.sumTotal();
                this.loading = false;
            }, error => {this.alertService.error(error._body);});
        }

        this.apiService.getAll('formas-pagos').subscribe(metodospago => {
            this.metodospago = metodospago;
        }, error => {this.alertService.error(error);});

    }

    cargarDocumentos(){
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});
    }

    cargarDatosIniciales(){
        this.cargarDocumentos();
        this.venta = {};
        this.venta.fecha = JSON.parse(sessionStorage.getItem('wgas_corte')!).fecha;
        this.venta.caja_id = JSON.parse(sessionStorage.getItem('wgas_corte')!).caja_id;
        this.venta.corte_id = JSON.parse(sessionStorage.getItem('wgas_corte')!).id;
        this.venta.tipo = 'Interna';
        this.venta.cliente = {};
        this.venta.detalles = [];
        this.detalle = {};
        this.sumTotal();
        this.venta.usuario_id = this.apiService.auth_user().id;
        this.imprimir = true;
    }

    public sumTotal() {
        this.venta.iva = this.sumPipe.transform(this.venta.detalles, 'iva');
        this.venta.fovial = this.sumPipe.transform(this.venta.detalles, 'fovial');
        this.venta.cotrans = this.sumPipe.transform(this.venta.detalles, 'cotrans');
        this.venta.subcosto = this.sumPipe.transform(this.venta.detalles, 'subcosto');
        this.venta.subtotal = this.sumPipe.transform(this.venta.detalles, 'subtotal');
        if(this.venta.cliente.tipo == "Grande") {
            this.venta.iva_retenido = this.venta.subtotal * 0.1;
        }else{
            this.venta.iva_retenido = 0;
        }
        this.venta.total = this.sumPipe.transform(this.venta.detalles, 'total') - this.venta.iva_retenido;
    }

    // Cliente
    clienteSelect(event:any):void{
        this.venta.cliente = event.cliente;
        if(this.venta.cliente.tipo == "Grande") {
            this.venta.retencion = 1;
            this.sumTotal();
        }
        if(parseFloat(this.venta.cliente.descuento) > 0) {
            this.hacerDescuento();
        }
    }

    updateVenta(venta:any) {
        this.venta = venta;
        this.sumTotal();
    }

    hacerDescuento(){
        for (var i = 0; i < this.venta.detalles.length; i++) {
            this.venta.detalles[i].precio = this.venta.detalles[i].precio + 0.30;
            // this.venta.detalles[i].precio = this.venta.detalles[i].precio + 0.20;

            this.venta.detalles[i].precio   = this.venta.detalles[i].precio;
            this.venta.detalles[i].cantidad = (this.venta.detalles[i].total / this.venta.detalles[i].precio).toFixed(4);
            
            this.venta.detalles[i].descuento = this.venta.cliente.descuento * this.venta.detalles[i].cantidad;
            
            this.venta.detalles[i].precio   = this.venta.detalles[i].precio - 0.30;
            // this.venta.detalles[i].precio   = this.venta.detalles[i].precio - 0.20;
            
            if(this.venta.detalles[i].tipo_impuesto == 'Gravada'){
                let iva:number = 0.13;
                // if (this.venta.detalles[i].producto_id == 1) { //super
                //     iva = 0.0475;
                //     console.log('super');
                // }
                // if (this.venta.detalles[i].producto_id == 2) { //regular
                //     iva = 0.05;
                //     console.log('regular');
                // }
                // if (this.venta.detalles[i].producto_id == 3) { //diesel
                //     iva = 0.0175;
                //     console.log('diesel');
                // }

                this.venta.detalles[i].iva           = ((this.venta.detalles[i].precio / (1 + iva)) * this.venta.detalles[i].cantidad) * iva;
            }

            this.venta.detalles[i].fovial        = this.venta.detalles[i].cantidad * 0.20;
            this.venta.detalles[i].cotrans       = this.venta.detalles[i].cantidad * 0.10;
            // this.venta.detalles[i].cotrans       = this.venta.detalles[i].cantidad * 0;
            this.venta.detalles[i].total         = (this.venta.detalles[i].precio * this.venta.detalles[i].cantidad) - this.venta.detalles[i].descuento + this.venta.detalles[i].cotrans + this.venta.detalles[i].fovial;
        }
        this.sumTotal();
    }

    // Producto o Gasolina
    productoSelect(event:any):void{
        this.detalle = Object.assign({}, event.producto);
        this.detalle.id = null; //Para que se pueda eliminar

        // Descuentos

        if(this.venta.cliente.descuento && this.detalle.id < 4) { // Solo gasolina
            this.detalle.descuento = this.venta.cliente.descuento * this.detalle.cantidad;
        }

        this.detalle.total = (this.detalle.precio * this.detalle.cantidad) - this.detalle.descuento + this.detalle.cotrans + this.detalle.fovial;
        this.detalle.subtotal = this.detalle.total - this.detalle.iva - this.detalle.fovial - this.detalle.cotrans;
        this.detalle.subcosto = this.detalle.costo * this.detalle.cantidad;
        this.venta.detalles.push(this.detalle);
        this.sumTotal();
        
        this.detalle = {};
        if (this.modalRef) { this.modalRef.hide() }

    }

    public onSubmit() {

        this.loading = true;
        if(!this.venta.cliente.id && !this.venta.cliente.nombre) {
            this.venta.cliente.id = 1;
        }
        
        if(!this.imprimir) {
            this.venta.tipo_documento = 'Ninguno';
            this.venta.correlativo = null;
        }

        // Para ventas pendientes
        if(this.venta.id) {
            this.apiService.store('venta', this.venta).subscribe(venta => {
                if(this.imprimir) {
                    if(this.venta.tipo_documento == 'Factura' || this.venta.tipo_documento == 'Credito Fiscal'){
                        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
                    }
                }
                if (this.modalRef) { this.modalRef.hide() }
                this.loading = false;
                this.cargarDatosIniciales()
                this.alertService.success("Guardado");
            },error => {this.alertService.error(error); this.loading = false; });
        }else{

            if(this.venta.tipo_documento == 'Devolucion') {
            
                this.apiService.store('devolucion/venta', this.venta).subscribe(venta => {
                    if (this.modalRef) { this.modalRef.hide() }
                    this.loading = false;
                    this.cargarDatosIniciales()
                    this.alertService.success("Guardado");
                },error => {this.alertService.error(error); this.loading = false; });
            
            }
            else{

                this.apiService.store('facturacion', this.venta).subscribe(venta => {
                    
                    // Si tiene anticipo se descuenta
                    if (this.venta.cliente.pago_anticipado > 0 && this.venta.cliente.pagoAnticipado) { 
                        let pago:any = {};
                        pago.fecha     = this.apiService.datetime();
                        pago.cliente_id = this.venta.cliente.id;
                        pago.total      = this.total;
                        pago.referencia = venta.id;
                        pago.tipo       = 'Cargo';
                        pago.usuario_id = venta.usuario_id;

                        this.apiService.store('anticipo', pago).subscribe(pago => {
                            this.alertService.success("Se actualizo el anticipo");
                        },error => {this.alertService.error(error); this.loading = false; });

                    }

                    if(this.imprimir) {
                        if(this.venta.tipo_documento == 'Factura' || this.venta.tipo_documento == 'Credito Fiscal'){
                            window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
                        }
                    }
                    if (this.modalRef) { this.modalRef.hide() }
                    this.loading = false;
                    this.cargarDatosIniciales()
                    this.alertService.success("Guardado");
                },error => {this.alertService.error(error); this.loading = false; });

            }
        }

    }

    // Facturar

        openModalFacturar(template: TemplateRef<any>, tipo:any) {
            if(!this.venta.cliente.nombre) {
                this.venta.nombre = prompt("Nombre del cliente");
                if(!this.venta.nombre)
                    this.venta.nombre = 'Consumidor Final';
            }
            if(tipo == 'Devolucion') {
                this.venta.tipo_documento = 'Devolucion';
                this.venta.correlativo = null;
                document.getElementById('nota')!.focus();
            }else{
                this.venta.tipo_documento = this.documentos[tipo].nombre;
                this.venta.correlativo = this.documentos[tipo].actual;
            }

            // this.venta.metodo_pago = 'Efectivo';
            // this.venta.estado = 'Cobrada';
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        }

        public onSelectForma(event:any){
            event = event.substr(2);// Para quitar la letra y espacio inicial que se usa para seleccionar con teclado
            this.venta.metodo_pago = event;

            if (this.venta.metodo_pago == 'Vale' || this.venta.metodo_pago == 'Credito')
                this.venta.estado = 'Pendiente';
            else
                this.venta.estado = 'Cobrada';
            
            setTimeout(()=>{
                if (this.venta.metodo_pago == 'Efectivo' || this.venta.metodo_pago == 'Credito')
                    document.getElementById('placa')!.focus();
                else
                    document.getElementById('referencia')!.focus();
            },100);

        }

        onFacturar(){
            if (confirm('¿Confirma la venta?')) {
                this.onSubmit();
            }
        }

    // Factura Simple
        openModalFacturaSimple(template: TemplateRef<any>) {
            this.venta.tipo_documento = this.documentos[1].nombre;
            this.venta.correlativo = this.documentos[1].actual;
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        }

        onFacturarSimple(){
            if (confirm('¿Confirma la venta?')) {
                this.venta.estado = 'Cobrada';
                this.venta.metodo_pago = 'Efectivo';
                if(!this.venta.nombre)
                    this.venta.nombre = 'Consumidor Final';
                this.onSubmit();
            }
        }

    // Venta sin documento
        onGuardar(){
            if (confirm('¿Confirma la venta sin factura?')) {
                this.venta.tipo_documento = 'Ninguno';
                this.venta.estado = 'Cobrada';
                this.venta.metodo_pago = 'Efectivo';
                this.onSubmit();
            }
        }

    // Posponer venta
        onPosponer(){
            if (confirm('¿Confirma que la venta se marque como pendiente?')) {
                this.venta.estado = 'Pendiente';
                this.onSubmit();
            }
        }    

    public eliminarDetalle(detalle:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            if(detalle.id) {
                this.apiService.delete('venta/detalle/', detalle.id).subscribe(detalle => {
                    for (var i = 0; i < this.venta.detalles.length; ++i) {
                        if (this.venta.detalles[i].id === detalle.id ){
                            this.venta.detalles.splice(i, 1);
                        }
                    }
                    this.alertService.success("Eliminado");
                }, error => {this.alertService.error(error._body); });
            }else{
                for (var i = 0; i < this.venta.detalles.length; ++i) {
                    if (this.venta.detalles[i].producto_id === detalle.producto_id ){
                        this.venta.detalles.splice(i, 1);
                    }
                }
                this.alertService.success("Eliminado");
            }
        }
        this.sumTotal();
    }

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
