import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-admin-facturacion',
  templateUrl: './admin-facturacion.component.html',
  providers: [ SumPipe ]
})

export class AdminFacturacionComponent implements OnInit {

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
    public duplicarfacturacion = false;
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

        this.apiService.getAll('empresas/list').subscribe(empresas => {
            this.clientes = empresas;
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
        this.facturacion.id_usuario = this.apiService.auth_user().id;

        // Para cotizaciones Pre-facturacion
        if (this.route.snapshot.queryParamMap.get('id_empresa')) {
            this.facturacion.id_empresa = +this.route.snapshot.queryParamMap.get('id_empresa')!;
        }

        // Para editar cotizaciones Pre-facturacion
        if (this.route.snapshot.paramMap.get('id')!) {
            this.apiService.read('facturacion/', +this.route.snapshot.paramMap.get('id')!).subscribe(facturacion => {
                this.facturacion = facturacion;
                this.facturacion.cobrar_impuestos = (this.facturacion.iva > 0) ?true : false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }


        this.loadData();
    }

    public setCredito(){
        if(this.facturacion.credito){
            this.facturacion.estado = 'Pendiente';
            this.facturacion.fecha_pago = moment().add(1, 'month').format('YYYY-MM-DD');
        }else{
            this.facturacion.estado = 'Pagada';
            this.facturacion.fecha_pago = moment().format('YYYY-MM-DD');
        }
    }

    // Cliente
    public setCliente(cliente:any){
        if(!this.facturacion.id_cliente){
            this.clientes.push(cliente);
        }
        this.facturacion.id_cliente = cliente.id;
        if(cliente.tipo_contribuyente == "Grande") {
            this.facturacion.retencion = 1;
        }
    }

    // Guardar facturacion
        public onSubmit() {

            this.saving = true;

            // Si se esta duplicando una facturacion, esta ya no se marca como recurrente para
            // que no aparezca en las facturacions recurrentes
            // if(this.duplicarfacturacion){
            //     this.facturacion.recurrente = false;
            // }

            this.apiService.store('facturacion', this.facturacion).subscribe(facturacion => {

                

                this.router.navigate(['/facturacions']);
                this.alertService.success('Venta creado', 'La facturacion fue añadida exitosamente.');
                    

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
