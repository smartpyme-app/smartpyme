import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

// import * as moment from 'moment';

@Component({
  selector: 'app-admin-facturacion',
  templateUrl: './facturacion.component.html',
  providers: [ SumPipe ]
})

export class FacturacionComponent implements OnInit {

    public facturacion: any= {};
    public evento: any= {};
    public detalle: any = {};
    public clientes:any = [];
    public proyectos:any = [];
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public sucursales:any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public canales:any = [];
    public supervisor:any = {};
    public loading = false;
    public saving = false;
    public duplicarventa = false;
    public facturarCotizacion = false;
    public api:boolean = false;
    
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
    }

    public loadData(){

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
            if(this.apiService.auth_user().tipo != 'Administrador' && this.apiService.auth_user().tipo != 'Supervisor'){
                this.usuarios = this.usuarios.filter((item:any) => item.id == this.apiService.auth_user().id );
            }
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


    public cargarDatosIniciales(){
        this.facturacion = {};
        this.facturacion.fecha = this.apiService.date();
        this.facturacion.fecha_pago = this.apiService.date();
        this.facturacion.forma_pago = 'Efectivo';
        this.facturacion.tipo = 'Interna';
        this.facturacion.estado = 'Pagada';
        this.facturacion.condicion = 'Contado';
        this.facturacion.detalle_banco = '';
        this.facturacion.id_cliente = '';
        this.facturacion.detalles = [];
        this.facturacion.descuento = 0;
        this.facturacion.sub_total = 0;
        this.facturacion.iva_percibido = 0;
        this.facturacion.iva_retenido = 0;
        this.facturacion.cotizacion = 0;
        this.facturacion.iva = 0;
        this.facturacion.total_costo = 0;
        this.facturacion.total = 0;
        this.detalle = {};
        this.facturacion.cobrar_impuestos = (this.apiService.auth_user().empresa.cobra_iva == 'Si') ? true : false;
        this.facturacion.id_bodega = this.apiService.auth_user().id_bodega;
        this.facturacion.id_usuario = this.apiService.auth_user().id;
        this.facturacion.id_vendedor = this.apiService.auth_user().id;
        this.facturacion.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.facturacion.id_empresa = this.apiService.auth_user().id_empresa;
        let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
        if (corte) {
            this.facturacion.fecha = JSON.parse(sessionStorage.getItem('SP_corte')!).fecha;
            this.facturacion.caja_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
            this.facturacion.corte_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
        }

        // Para proyectos
        if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
            this.facturacion.id_proyecto = +this.route.snapshot.queryParamMap.get('id_proyecto')!;
        }

        // Para cotizaciones Pre-venta
        if (this.route.snapshot.queryParamMap.get('cotizacion')) {
            this.facturacion.cotizacion = 1;
            this.facturacion.estado = 'Pendiente';
        }

        // Para editar cotizaciones Pre-venta
        if (this.route.snapshot.paramMap.get('id')!) {
            this.apiService.read('venta/', +this.route.snapshot.paramMap.get('id')!).subscribe(venta => {
                this.facturacion = venta;
                this.facturacion.cobrar_impuestos = (this.facturacion.iva > 0) ?true : false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        // Facturar venta recurrente
        // Duplicar venta

        if (this.route.snapshot.queryParamMap.get('recurrente')! && this.route.snapshot.queryParamMap.get('id_venta')!) {
            this.duplicarventa = true;
            this.apiService.read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!).subscribe(venta => {
                this.facturacion = venta;
                this.facturacion.cobrar_impuestos = (this.facturacion.iva > 0) ?true : false;
                this.facturacion.fecha = this.apiService.date();
                this.facturacion.fecha_pago = this.apiService.date();
                this.facturacion.id_documento = null;
                this.facturacion.correlativo = null;
                this.facturacion.id = null;
                this.facturacion.detalles.forEach((detalle:any) => {
                    detalle.id = null;
                });
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        this.loadData();
    }

    // Cliente
    // public setCliente(cliente:any){
    //     if(!this.venta.id_cliente){
    //         this.clientes.push(cliente);
    //     }
    //     this.venta.id_cliente = cliente.id;
    //     if(cliente.tipo_contribuyente == "Grande") {
    //         this.venta.retencion = 1;
    //         this.sumTotal();
    //     }
    // }

    // Guardar venta
        public onSubmit() {

            this.saving = true;

            // Si se esta duplicando una venta, esta ya no se marca como recurrente para
            // que no aparezca en las ventas recurrentes
            // if(this.duplicarventa){
            //     this.venta.recurrente = false;
            // }

            this.apiService.store('facturacion', this.facturacion).subscribe(facturacion => {

                

                this.router.navigate(['/ventas']);
                this.alertService.success('Venta creado', 'La venta fue añadida exitosamente.');
                    

                if (this.modalRef) { this.modalRef.hide() }
                this.saving = false;


            },error => {this.alertService.error(error); this.saving = false; });

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
