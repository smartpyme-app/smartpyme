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

    public gasto:any = {};
    public categorias:any = [];
    public proyectos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public bancos:any = [];
    public formaspago:any = [];
    public duplicargasto = false;
    public loading = false;
    public saving = false;
    public documentos:any = [];
    public impuestos:any = [];
    public mostrar_otros_impuestos = false;
    public impuestos_seleccionados: any[] = [];
    modalRef?: BsModalRef;

	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();

        this.mostrar_otros_impuestos = false;
        this.impuestos_seleccionados = [];

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bancos/list').subscribe(bancos => {
            this.bancos = bancos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago/list').subscribe(formaspago => {
            this.formaspago = formaspago;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('gastos/categorias').subscribe(categorias => {
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

        this.apiService.getAll('impuestos').subscribe(impuestos => {
            this.impuestos = impuestos;
            this.loading = false;

            if (this.gasto && this.gasto.otros_impuestos) {
                this.cargarImpuestosSeleccionados();
            }
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

                if (this.gasto.otros_impuestos) {
                    if (typeof this.gasto.otros_impuestos === 'object' && 
                        this.gasto.otros_impuestos.seleccionados) {
                        
                        this.gasto.otros_impuestos = this.gasto.otros_impuestos.seleccionados;
                        this.gasto.impuestos_valores = this.gasto.otros_impuestos.valores;
                    }
                }

                if (this.tieneOtrosImpuestos(this.gasto.otros_impuestos)) {
                    this.mostrar_otros_impuestos = true;
                    
                    if (this.impuestos && this.impuestos.length > 0) {
                        this.cargarImpuestosSeleccionados();
                    }
                }

                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.gasto = {};
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
            
            this.gasto.otros_impuestos = []; 
            this.gasto.impuestos_valores = [];

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

                if(this.gasto.otros_impuestos) {
                    this.mostrar_otros_impuestos = true;
                    this.cargarImpuestosSeleccionados();
                }

            }, error => {this.alertService.error(error); this.loading = false;});
        }

        this.cargarDocumentos();

    }

    private cargarImpuestosSeleccionados() {
        if (!Array.isArray(this.gasto.otros_impuestos)) {
            if (this.gasto.otros_impuestos !== null && this.gasto.otros_impuestos !== undefined && this.gasto.otros_impuestos !== false) {
                this.gasto.otros_impuestos = [this.gasto.otros_impuestos];
            } else {
                this.gasto.otros_impuestos = [];
            }
        }
        
        this.impuestos_seleccionados = [];
        
        this.gasto.otros_impuestos.forEach((impuestoId: number) => {
            const impuesto = this.impuestos.find((imp: any) => imp.id === impuestoId);
            if (impuesto) {
                this.impuestos_seleccionados.push(impuesto);
            }
        });
        
        this.calcularValoresImpuestos();
    }


    private calcularValoresImpuestos() {
        if (!this.gasto.impuestos_valores) {
            this.gasto.impuestos_valores = [];
        }

        this.gasto.impuestos_valores = [];

        this.impuestos_seleccionados.forEach(impuesto => {
            const subtotal = parseFloat(this.gasto.sub_total) || 0;
            const valor = (subtotal * (impuesto.porcentaje / 100)).toFixed(2);
            
            this.gasto.impuestos_valores.push({
                id_impuesto: impuesto.id,
                nombre: impuesto.nombre,
                porcentaje: impuesto.porcentaje,
                valor: valor
            });
        });
    }

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

    public setTotal(){
        // Asegurarse de que subtotal sea un número
        const subtotal = parseFloat(this.gasto.sub_total) || 0;
        let total = subtotal;

        // Calcular IVA si está habilitado
        if(this.gasto.impuesto){
            const ivaRate = this.apiService.auth_user().empresa.iva / 100;
            const ivaValue = subtotal * ivaRate;
            this.gasto.iva = ivaValue.toFixed(2);
            total += ivaValue;
        } else {
            this.gasto.iva = 0;
        }

        // Calcular renta si está habilitada
        if(this.gasto.renta) {
            this.gasto.renta_retenida = (subtotal * 0.10).toFixed(2);
            total -= parseFloat(this.gasto.renta_retenida);
        } else {
            this.gasto.renta_retenida = 0;
        }

        // Calcular percepción si está habilitada
        if(this.gasto.percepcion) {
            this.gasto.iva_percibido = (subtotal * 0.01).toFixed(2);
            total += parseFloat(this.gasto.iva_percibido);
        } else {
            this.gasto.iva_percibido = 0;
        }

        // Calcular otros impuestos si están habilitados
        if (this.mostrar_otros_impuestos && Array.isArray(this.gasto.otros_impuestos) && this.gasto.otros_impuestos.length > 0) {
            // Recalcular valores de impuestos
            this.calcularValoresImpuestos();
            
            // Sumar al total
            this.gasto.impuestos_valores.forEach((impValue: any) => {
                total += parseFloat(impValue.valor);
            });
        }
        
        // Establecer el total final
        this.gasto.total = total.toFixed(2);
    }

    // public setSubTotal(){

    //     if(this.gasto.impuesto){
    //         this.gasto.sub_total = (this.gasto.total / (1 + (this.apiService.auth_user().empresa.iva / 100))).toFixed(2);
    //         this.gasto.iva = (this.gasto.total - this.gasto.sub_total).toFixed(2);
    //     }else{
    //         this.gasto.iva = 0;
    //         this.gasto.sub_total = this.gasto.total;
    //     }
    //     this.gasto.iva_percibido = this.gasto.percepcion ? (this.gasto.sub_total * 0.01).toFixed(2) : 0;
    //     this.gasto.total = (parseFloat(this.gasto.total) + parseFloat(this.gasto.iva_percibido)).toFixed(2);
    // }

    public setSubTotal(){
        if(this.gasto.impuesto){
            this.gasto.sub_total = (parseFloat(this.gasto.total) / (1 + (this.apiService.auth_user().empresa.iva / 100))).toFixed(2);
            this.gasto.iva = (parseFloat(this.gasto.total) - parseFloat(this.gasto.sub_total)).toFixed(2);
        }else{
            this.gasto.iva = 0;
            this.gasto.sub_total = this.gasto.total;
        }
        
        this.setTotal();
    }

    public selectTipoDocumento(){
        if(this.gasto.tipo_documento == 'Sujeto excluido'){
            let documento = this.documentos.find((x:any) => x.nombre == this.gasto.tipo_documento);
            console.log(documento);
            this.gasto.referencia = documento.correlativo;
        }
    }

    public onSubmit(){
        this.saving = true;

        if(this.duplicargasto){
            this.gasto.recurrente = false;
        }
        
        if (this.mostrar_otros_impuestos && 
            Array.isArray(this.gasto.otros_impuestos) && 
            this.gasto.otros_impuestos.length > 0) {
            
            const datosImpuestos = {
                seleccionados: this.gasto.otros_impuestos,
                valores: this.gasto.impuestos_valores
            };
            
            this.gasto.otros_impuestos = datosImpuestos;
        } else {
            this.gasto.otros_impuestos = [];
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

    public setOtrosImpuestos() {

        if (this.mostrar_otros_impuestos) {
            
            if (!Array.isArray(this.gasto.otros_impuestos)) {
                this.gasto.otros_impuestos = [];
            }
            
            if (!this.gasto.impuestos_valores) {
                this.gasto.impuestos_valores = [];
            }
        } else {
            this.gasto.otros_impuestos = [];
            this.impuestos_seleccionados = [];
            if (this.gasto.impuestos_valores) {
                this.gasto.impuestos_valores = [];
            }
        }
        
        this.setTotal();
    }

    public onImpuestosChange() {
        if (!Array.isArray(this.gasto.otros_impuestos)) {
            this.gasto.otros_impuestos = [];
        }
        
        this.impuestos_seleccionados = [];
        
        if (this.gasto.otros_impuestos && this.gasto.otros_impuestos.length > 0) {
            this.gasto.otros_impuestos.forEach((impuestoId: number) => {
                const impuesto = this.impuestos.find((imp: any) => imp.id === impuestoId);
                if (impuesto) {
                    this.impuestos_seleccionados.push(impuesto);
                }
            });
        } else {
            this.gasto.impuestos_valores = [];
        }
        
        this.setTotal();
    }

    public setImpuesto(impuesto:any) {
        this.impuestos.push(impuesto);
        
        if (!Array.isArray(this.gasto.otros_impuestos)) {
            this.gasto.otros_impuestos = [];
        }
        
        this.gasto.otros_impuestos.push(impuesto.id);
        
        this.impuestos_seleccionados.push(impuesto);
        
        this.setTotal();
    }

    private tieneOtrosImpuestos(otrosImpuestos: any): boolean {
        if (!otrosImpuestos) return false;
        
        if (Array.isArray(otrosImpuestos)) {
            return otrosImpuestos.length > 0;
        }
        
        return otrosImpuestos !== false && otrosImpuestos !== null && otrosImpuestos !== undefined;
    }
}
