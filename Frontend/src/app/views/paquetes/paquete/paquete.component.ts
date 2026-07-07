import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-paquete',
  templateUrl: './paquete.component.html',
  styleUrls: ['./paquete.component.css']
})
export class PaqueteComponent implements OnInit {

    public paquete:any = {};
    public categorias:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public clientes:any = [];
    public loading = false;
    public saving = false;
    public tieneBoxful = false;
    public tipoPaquete: 'S' | 'M' | 'L' | null = null;
    modalRef?: BsModalRef;


	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('boxful/status').subscribe({
            next: (res: any) => {
                this.tieneBoxful = res && res.connected;
            },
            error: () => {
                this.tieneBoxful = false;
            }
        });
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('paquete/', id).subscribe(paquete => {
                this.paquete = paquete;
                if (paquete.boxful_shipment && paquete.boxful_shipment.parcels && paquete.boxful_shipment.parcels.length > 0) {
                    const firstParcel = paquete.boxful_shipment.parcels[0];
                    this.paquete.contenido = firstParcel.contenido;
                    this.paquete.alto = firstParcel.alto;
                    this.paquete.ancho = firstParcel.ancho;
                    this.paquete.largo = firstParcel.largo;
                    this.paquete.es_fragil = firstParcel.es_fragil;
                } else {
                    this.paquete.contenido = '';
                    this.paquete.alto = 10;
                    this.paquete.ancho = 10;
                    this.paquete.largo = 10;
                    this.paquete.es_fragil = false;
                }
                this.detectarTipoPaquete();
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.paquete = {};
            this.paquete.forma_pago = 'Efectivo';
            this.paquete.estado = 'En bodega';
            this.paquete.id_cliente = '';
            this.paquete.id_proveedor = '';
            // this.paquete.fecha_pago = this.apiService.date();
            this.paquete.fecha = this.apiService.date();
            this.paquete.id_empresa = this.apiService.auth_user().id_empresa;
            this.paquete.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.paquete.id_usuario = this.apiService.auth_user().id;
            this.paquete.contenido = '';
            this.paquete.alto = 10;
            this.paquete.ancho = 10;
            this.paquete.largo = 10;
            this.paquete.es_fragil = false;
            this.detectarTipoPaquete();
        }

    }

    public seleccionarTipoPaquete(tipo: 'S' | 'M' | 'L', alto: number, ancho: number, largo: number): void {
        this.tipoPaquete = tipo;
        this.paquete.alto = alto;
        this.paquete.ancho = ancho;
        this.paquete.largo = largo;
    }

    public detectarTipoPaquete(): void {
        const a = Number(this.paquete.alto);
        const w = Number(this.paquete.ancho);
        const l = Number(this.paquete.largo);

        if (a === 11 && w === 43 && l === 47.5) {
            this.tipoPaquete = 'S';
        } else if (a === 22 && w === 43 && l === 47.5) {
            this.tipoPaquete = 'M';
        } else if (a === 34 && w === 43 && l === 47.5) {
            this.tipoPaquete = 'L';
        } else {
            // Default to S
            this.seleccionarTipoPaquete('S', 11, 43, 47.5);
        }
    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.paquete.id_proveedor = proveedor.id;
    }


    public onSubmit(){
        if (this.tieneBoxful) {
            if (!this.paquete.contenido || !this.paquete.contenido.trim()) {
                this.alertService.error('El contenido del paquete es obligatorio cuando Boxful está activo.');
                return;
            }
        }

        this.saving = true;

        // ponytail: prevent database integrity violations by default-initializing numeric fields to 0 if null/empty
        if (this.paquete.otros === undefined || this.paquete.otros === null || this.paquete.otros === '') {
            this.paquete.otros = 0;
        }
        if (this.paquete.cuenta_a_terceros === undefined || this.paquete.cuenta_a_terceros === null || this.paquete.cuenta_a_terceros === '') {
            this.paquete.cuenta_a_terceros = 0;
        }
        if (this.paquete.precio === undefined || this.paquete.precio === null || this.paquete.precio === '') {
            this.paquete.precio = 0;
        }
        if (this.paquete.total === undefined || this.paquete.total === null || this.paquete.total === '') {
            this.paquete.total = 0;
        }

        this.apiService.store('paquete', this.paquete).subscribe(paquete => {
            if (!this.paquete.id) {
                this.alertService.success('Paquete guardado', 'El paquete fue guardado exitosamente.');
            }else{
                this.alertService.success('Paquete creado', 'El paquete fue añadido exitosamente.');
            }
            this.router.navigate(['/paquetes']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
