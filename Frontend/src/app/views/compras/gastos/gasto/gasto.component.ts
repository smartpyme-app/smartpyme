import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-gasto',
  templateUrl: './gasto.component.html'
})
export class GastoComponent implements OnInit {

    public gasto:any = {iva: 0, renta_retenida: 0, iva_percibido: 0, otros_impuestos: 0};
    public categorias:any = [];
    public proyectos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    // public bancos:any = [];
    public formaspago:any = [];
    public duplicargasto = false;
    public loading = false;
    public saving = false;
    public documentos:any = [];
    modalRef?: BsModalRef;

    public opAvanzadas: boolean = false;
    public otrosImpuestos: boolean = false;

	constructor(public apiService: ApiService, private alertService: AlertService, private route: ActivatedRoute, private router: Router, private modalService: BsModalService) {}

	ngOnInit(){
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        // this.apiService.getAll('bancos/list').subscribe(bancos => {
        //     this.bancos = bancos;
        // }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago/list').subscribe(formaspago => {
            this.formaspago = formaspago;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('gastos/categorias/list').subscribe(categorias => {
            this.categorias = categorias;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('proyectos/list').subscribe(proyectos => {
            this.proyectos = proyectos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('gasto/', id).subscribe(gasto => {
                this.gasto = gasto;
                if(this.gasto.iva > 0)
                    this.gasto.impuesto = true;

                if(this.gasto.iva_percibido > 0)
                    this.gasto.percepcion = true;

                if(this.gasto.renta_retenida > 0)
                    this.gasto.renta = true;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.gasto.forma_pago = 'Efectivo';
            this.gasto.estado = 'Confirmado';
            this.gasto.tipo_documento = 'Factura';
            this.gasto.detalle_banco = '';
            this.gasto.tipo = '';
            this.gasto.id_categoria = '';
            this.gasto.id_proveedor = '';
            // this.gasto.fecha_pago = this.apiService.date();
            this.gasto.fecha = this.apiService.date();
            this.gasto.id_empresa = this.apiService.auth_user().id_empresa;
            this.gasto.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.gasto.id_usuario = this.apiService.auth_user().id;

            if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
                this.gasto.id_proyecto = +this.route.snapshot.queryParamMap.get('id_proyecto')!;
            }

        }

        // Duplicar gasto

        if (this.route.snapshot.queryParamMap.get('recurrente')! && this.route.snapshot.queryParamMap.get('id_gasto')!) {
            this.duplicargasto = true;
            this.apiService.read('gasto/', +this.route.snapshot.queryParamMap.get('id_gasto')!).subscribe(gasto => {
                this.gasto = gasto;
                this.gasto.fecha = this.apiService.date();
                this.gasto.id = null;
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        this.cargarDocumentos();

    }

    toggleDiv(): void { this.opAvanzadas = !this.opAvanzadas;}

    public cargarDocumentos(){
        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
            this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.gasto.id_sucursal);
            this.documentos = this.documentos.filter((x:any) => x.nombre != 'Cotización' && x.nombre != 'Orden de compra'  && x.nombre!= 'Nota de crédito');
            if(!this.gasto.tipo_documento)
                this.gasto.tipo_documento = 'Factura';
        }, error => {this.alertService.error(error);});
    }

    public setCategoria(categoria:any){
        this.categorias.push(categoria);
        this.gasto.id_categoria = categoria.id;
    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.gasto.id_proveedor = proveedor.id;
    }

    // Proyecto
    public setProyecto(proyecto:any){
        if(!this.gasto.id_proyecto){
            this.proyectos.push(proyecto);
        }
        this.gasto.id_proyecto = proyecto.id;
    }

    public setFechaPago(){
        if (this.gasto.condicion == 'Contado') {
            this.gasto.estado = 'Pagado';    
            this.gasto.fecha_pago = moment().format('YYYY-MM-DD');
        }else{
            this.gasto.estado = 'Pendiente';
            this.gasto.fecha_pago = moment().add(this.gasto.condicion.split(' ')[0], 'days').format('YYYY-MM-DD');
        }
    }

    public setCredito(){
        if(this.gasto.credito){
            this.gasto.estado = 'Pendiente';
        }else{
            this.gasto.estado = 'Confirmado';
        }
    }

    otros_impuestos: boolean = false;
    otros_impuestos_val: number = 0;

    setImpuesto(impuesto: string){

        switch (impuesto){
            case 'iva':
                if(this.gasto.iva == 0){
                    this.gasto.iva = Number((this.gasto.sub_total * 0.13).toFixed(2));
                    this.gasto.total += this.gasto.iva;
                }else{ this.gasto.total -= this.gasto.iva; this.gasto.iva = 0;}
                break;
            case 'renta':
                if(this.gasto.renta_retenida == 0){
                    this.gasto.renta_retenida = Number((this.gasto.sub_total * 0.10).toFixed(2));
                    this.gasto.total += this.gasto.renta_retenida;
                }else{ this.gasto.total -= this.gasto.renta_retenida; this.gasto.renta_retenida = 0;}
                break;
            case 'percepcion':
                if(this.gasto.iva_percibido == 0){
                    this.gasto.iva_percibido = Number((this.gasto.sub_total * 0.01).toFixed(2));
                    this.gasto.total += this.gasto.iva_percibido;
                }else{ this.gasto.total -= this.gasto.iva_percibido; this.gasto.iva_percibido = 0;}
                break;
            case 'otros':
                if(this.otros_impuestos == false){
                    this.otros_impuestos_val = this.gasto.otros_impuestos;
                    this.gasto.total += this.gasto.otros_impuestos;
                    this.otros_impuestos = true;
                }else{
                    this.gasto.total -= this.otros_impuestos_val;
                    this.gasto.total += this.gasto.otros_impuestos;
                    this.otros_impuestos_val = this.gasto.otros_impuestos;
                }     
                
                break;
        
            default:
                break;
        }
 
    }

    public selectTipoDocumento(){
        if(this.gasto.tipo_documento == 'Sujeto excluido'){
            let documento = this.documentos.find((x:any) => x.nombre == this.gasto.tipo_documento);
            this.gasto.referencia = documento.correlativo;
        }
    }

    public onSubmit(){
        this.saving = true;

        if(this.duplicargasto){
            this.gasto.recurrente = false;
        } 

        this.apiService.store('gasto', this.gasto).subscribe(gasto => {
            if (!this.gasto.id) {
                this.alertService.success('Gasto guardado', 'El gasto fue guardado exitosamente.');
            }else{
                this.alertService.success('Gasto creado', 'El gasto fue añadido exitosamente.');
            }
            this.router.navigate(['/gastos']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
