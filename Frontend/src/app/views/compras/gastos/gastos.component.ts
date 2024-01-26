import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-gastos',
  templateUrl: './gastos.component.html'
})

export class GastosComponent implements OnInit {

    public gastos:any = [];
    public gasto:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public proveedores:any = [];
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
        this.filtros.forma_pago = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarGastos();
    }

    public filtrarGastos(){
        this.loading = true;
        this.apiService.getAll('gastos', this.filtros).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
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


    public setEstado(gasto:any){
        this.apiService.store('gasto', gasto).subscribe(gasto => { 
            this.alertService.success('Gasto guardado', 'El gasto fue guardado exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public setRecurrencia(gasto:any){
        this.gasto = gasto;
        this.gasto.recurrente = true;
        
        this.apiService.store('gasto', this.gasto).subscribe(gasto => {
            this.gasto = {};
            this.alertService.success('Gasto guardado', 'El gasto se marco como recurrente exitosamente.');
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

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.gastos.path + '?page='+ event.page).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public descargar(){
        this.downloading = true;
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
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
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
