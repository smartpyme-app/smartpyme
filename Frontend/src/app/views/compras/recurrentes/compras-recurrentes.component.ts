import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

declare var $:any;

@Component({
    selector: 'app-compras-recurrentes',
    templateUrl: './compras-recurrentes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class ComprasRecurrentesComponent implements OnInit {

    public compras:any = [];
    public compra:any = {};
    public formaPagos:any = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;

    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.id_usuario = '';
        this.filtros.id_canal = '';
        this.filtros.id_documento = '';
        this.filtros.recurrente = true;
        this.filtros.forma_pago = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarCompras();
    }

    public filtrarCompras(){
        this.loading = true;
        this.apiService.getAll('compras', this.filtros).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCompras();
    }

    public setEstado(compra:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }

    }

    public setRecurrencia(compra:any){
        this.compra = compra;
        this.compra.recurrente = false;
        
        this.apiService.store('marcar-recurrente', this.compra).subscribe(compra => {
            this.compra = {};
            this.loadAll();
            this.alertService.success('Compra guardada', 'La compra se marco como no recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }
    
    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('compra/', id) .subscribe(data => {
                for (let i = 0; i < this.compras['data'].length; i++) { 
                    if (this.compras['data'][i].id == data.id )
                        this.compras['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public openModalEdit(template: TemplateRef<any>, compra:any) {
        this.compra = compra;

        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago').subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template);
    }


    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('compras/filtrar/' + filtro + '/', txt).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('compra', this.compra).subscribe(compra => {
            this.compra = {};
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardado', 'La compra fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.compras.path + '?page='+ event.page, this.filtros).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('compras/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'compras-recurrentes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => {this.alertService.error(error); this.downloading = false;}
        );
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template);
    }

}
