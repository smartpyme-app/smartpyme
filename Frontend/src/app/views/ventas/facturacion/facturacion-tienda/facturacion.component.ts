import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-facturacion',
  templateUrl: './facturacion.component.html',
  providers: [ SumPipe ]
})

export class FacturacionComponent implements OnInit {

    public venta: any= {};
    public evento: any= {};
    public detalle: any = {};
    public clientes:any = [];
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public sucursales:any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public canales:any = [];
    public supervisor:any = {};
    public loading = false;
    public duplicarventa = false;
    public imprimir:boolean = false;
    
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

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bancos/list').subscribe(bancos => {
            this.bancos = bancos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => {
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('canales/list').subscribe(canales => {
            this.canales = canales;
            this.venta.id_canal = this.canales[0].id;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('impuestos').subscribe(impuestos => {
            this.impuestos = impuestos;
            this.venta.impuestos = this.impuestos;
            this.sumTotal();

        }, error => {this.alertService.error(error);});

        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public cargarDocumentos(){
        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
            this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.venta.id_sucursal);
            
            if(!this.venta.id_documento && !this.venta.correlativo){
                
                let documento = this.documentos.find((x:any) => x.predeterminado == 1);
                if(documento){
                    this.venta.id_documento = documento.id;
                    this.venta.correlativo = documento.correlativo;
                }else{
                    this.venta.id_documento = documentos[0].id;
                    this.venta.correlativo = documentos[0].correlativo;
                }

                if(this.venta.estado == 'Pre-venta'){
                    let documento = this.documentos.find((x:any) => x.nombre == 'Cotización');
                    if(documento){
                        this.venta.id_documento = documento.id;
                        this.venta.correlativo = documento.correlativo;
                    }
                }
            }

        }, error => {this.alertService.error(error);});
    }

    public cargarDatosIniciales(){
        this.venta = {};
        this.venta.fecha = this.apiService.date();
        this.venta.fecha_pago = this.apiService.date();
        this.venta.forma_pago = 'Efectivo';
        this.venta.tipo = 'Interna';
        this.venta.estado = 'Pagada';
        this.venta.condicion = 'Contado';
        this.venta.detalle_banco = '';
        this.venta.id_cliente = '';
        this.venta.detalles = [];
        this.venta.descuento = 0;
        this.venta.sub_total = 0;
        this.venta.iva_percibido = 0;
        this.venta.iva_retenido = 0;
        this.venta.iva = 0;
        this.venta.total_costo = 0;
        this.venta.total = 0;
        this.detalle = {};
        this.venta.cobrar_impuestos = (this.apiService.auth_user().empresa.cobra_iva == 'Si') ? true : false;
        this.venta.id_bodega = this.apiService.auth_user().id_bodega;
        this.venta.id_usuario = this.apiService.auth_user().id;
        this.venta.id_vendedor = this.apiService.auth_user().id_empleado;
        this.venta.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.venta.id_empresa = this.apiService.auth_user().id_empresa;
        let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
        if (corte) {
            this.venta.fecha = JSON.parse(sessionStorage.getItem('SP_corte')!).fecha;
            this.venta.caja_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
            this.venta.corte_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
        }

        // Para cotizaciones Pre-venta
        if (this.route.snapshot.queryParamMap.get('estado')!) {
            this.venta.estado = this.route.snapshot.queryParamMap.get('estado')!;
        }

        // Para editar cotizaciones Pre-venta
        if (this.route.snapshot.paramMap.get('id')!) {
            this.apiService.read('venta/', +this.route.snapshot.paramMap.get('id')!).subscribe(venta => {
                this.venta = venta;
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        // Duplicar venta

        if (this.route.snapshot.queryParamMap.get('recurrente')! && this.route.snapshot.queryParamMap.get('id_venta')!) {
            this.duplicarventa = true;
            this.apiService.read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!).subscribe(venta => {
                this.venta = venta;
                this.venta.fecha = this.apiService.date();
                this.venta.fecha_pago = this.apiService.date();
                this.venta.id = null;
                this.venta.detalles.forEach((detalle:any) => {
                    detalle.id = null;
                });
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        // Editar cotizacion


        // Cita a venta
        if (this.route.snapshot.queryParamMap.get('id_cita')!) {
            this.loading = true;
            this.apiService.read('evento/', +this.route.snapshot.queryParamMap.get('id_cita')!).subscribe(evento => {
                this.evento = evento;
                this.venta.id_cliente = evento.id_cliente;
                this.venta.id_evento = evento.id;
                this.apiService.read('servicio/', evento.id_servicio).subscribe(servicio => {
                    let detalle:any = {};
                    detalle.id_producto    = servicio.id;
                    detalle.nombre_producto = servicio.nombre;
                    detalle.img            = servicio.img;
                    detalle.precio         = parseFloat(servicio.precio);
                    detalle.costo          = parseFloat(servicio.costo);
                    // if(servicio.inventarios.length > 0){
                    //     servicio.inventarios   = servicio.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
                    //     detalle.stock          = parseFloat(this.sumPipe.transform(servicio.inventarios, 'stock'));
                    // }else{
                        detalle.stock = null;
                    // }
                    detalle.cantidad       = 1;
                    detalle.descuento      = 0;
                    detalle.descuento_porcentaje      = 0;
                    detalle.total_costo = detalle.costo;
                    detalle.total      = detalle.precio;
                    this.venta.detalles.push(detalle);
                    this.sumTotal();
                    this.loading = false;
                    console.log(this.venta);
                }, error => {this.alertService.error(error); this.loading = false;});
            }, error => {this.alertService.error(error); this.loading = false;});
        }
        this.cargarDocumentos();
    }

    public sumTotal() {
        this.venta.sub_total = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total'))).toFixed(4);
        this.venta.iva_percibido = this.venta.percepcion ? this.venta.sub_total * 0.01 : 0; 
        this.venta.iva_retenido = this.venta.retencion ? this.venta.sub_total * 0.01 : 0; 

        this.venta.impuestos.forEach((impuesto:any) => {
            if(this.venta.cobrar_impuestos){
                impuesto.monto = this.venta.sub_total * (impuesto.porcentaje / 100);
            }else{
                impuesto.monto = 0;
            }
        });

        this.venta.iva = (parseFloat(this.sumPipe.transform(this.venta.impuestos, 'monto'))).toFixed(4);
        this.venta.descuento = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'))).toFixed(4);
        this.venta.total_costo = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'))).toFixed(4);
        this.venta.total = (parseFloat(this.venta.sub_total) + parseFloat(this.venta.iva) + parseFloat(this.venta.iva_percibido) - parseFloat(this.venta.iva_retenido)).toFixed(4);
    }

    // Cliente
    public setCliente(cliente:any){
        if(!this.venta.id_cliente){
            this.clientes.push(cliente);
        }
        this.venta.id_cliente = cliente.id;
        if(cliente.tipo_contribuyente == "Grande") {
            this.venta.retencion = 1;
            this.sumTotal();
        }
    }

    public setCredito(){
        if(this.venta.credito){
            this.venta.estado = 'Pendiente';
            this.venta.fecha_pago = moment().add(1, 'month').format('YYYY-MM-DD');
        }else{
            this.venta.estado = 'Pagada';
            this.venta.fecha_pago = moment().format('YYYY-MM-DD');
        }
    }

    public setConsigna(){
        if(this.venta.consigna){
            this.venta.estado = 'Consigna';
        }else{
            this.setCredito();
        }
    }


    public updateVenta(venta:any) {
        this.venta = venta;
        this.sumTotal();
    }

    public setDocumento(id_documento:any){
        let documento = this.documentos.find((x:any) => x.id == id_documento);
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;
    }
   

    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop:'static'});
        }

        public onFacturar(){
            if (confirm('¿Confirma procesar la ' + (this.venta.estado == 'Pre-venta' ? ' cotización.' : 'venta.') )) {
                if(!this.venta.recibido)
                    this.venta.recibido = this.venta.total;

                if(this.venta.forma_pago == 'Wompi'){
                    this.venta.estado = 'Pendiente';
                }
                this.onSubmit();
            }
        }

    // Guardar venta
        public onSubmit() {

            this.loading = true;

            // Si se esta duplicando una venta, esta ya no se marca como recurrente para
            // que no aparezca en las ventas recurrentes
            if(this.duplicarventa){
                this.venta.recurrente = false;
            }

            this.apiService.store('facturacion', this.venta).subscribe(venta => {

                if(this.imprimir) {
                    window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
                }
                if (this.modalRef) { this.modalRef.hide() }
                this.loading = false;
                // this.cargarDatosIniciales();
                // this.router.navigate(['/venta/crear']);
                if(this.venta.estado == 'Pre-venta'){
                    this.router.navigate(['/cotizaciones']);
                    this.alertService.success('Cotización creada', 'La cotizacion fue añadida exitosamente.');
                }else{
                    this.router.navigate(['/ventas']);
                    this.alertService.success('Venta creado', 'La venta fue añadida exitosamente.');
                }

                // Si viene desde citas
                if(this.evento.id){
                    this.evento.tipo = 'Pagado';
                    this.apiService.store('evento', this.evento).subscribe(evento => {

                    },error => {this.alertService.error(error); this.loading = false; });
                }


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
