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
    selector: 'app-gastos-recurrentes',
    templateUrl: './gastos-recurrentes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class GastosRecurrentesComponent implements OnInit {

    public gastos:any = [];
    public gasto:any = {};
    public formaPagos:any = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    public saving:boolean = false;

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

        this.filtrarGastos();
    }

    public filtrarGastos(){
        this.loading = true;
        this.apiService.getAll('gastos', this.filtros).subscribe(gastos => { 
            this.gastos = gastos;
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

        this.filtrarGastos();
    }

    public setEstado(gasto:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la gasto?')){
                this.gasto = gasto;
                this.gasto.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la gasto?')){
                this.gasto = gasto;
                this.gasto.estado = estado;
                this.onSubmit();
            }
        }

    }

    public setRecurrencia(gasto:any){
        this.gasto = gasto;
        this.gasto.recurrente = false;
        
        this.apiService.store('gasto', this.gasto).subscribe(gasto => {
            this.gasto = {};
            this.loadAll();
            this.alertService.success('Gasto guardada', 'La gasto se marco como no recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }
    
    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id) .subscribe(data => {
                for (let i = 0; i < this.gastos['data'].length; i++) { 
                    if (this.gastos['data'][i].id == data.id )
                        this.gastos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public openModalEdit(template: TemplateRef<any>, gasto:any) {
        this.gasto = gasto;

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
        this.apiService.read('gastos/filtrar/' + filtro + '/', txt).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('gasto', this.gasto).subscribe(gasto => {
            this.gasto = {};
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardado', 'La gasto fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.gastos.path + '?page='+ event.page, this.filtros).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public openDescargar(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargarGastos(){
        this.apiService.export('gastos/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar gastos:', error); }
        );
    }

    public descargarDetalles(){
        this.apiService.export('gastos-detalles/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar gastos:', error); }
        );
    }

    public openAbono(template: TemplateRef<any>, gasto:any){
        this.gasto = gasto;
        this.modalRef = this.modalService.show(template);
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
