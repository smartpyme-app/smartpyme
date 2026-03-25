import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';

@Component({
  selector: 'app-clientes',
  templateUrl: './clientes.component.html',
})
export class ClientesComponent implements OnInit {

    public clientes:any = [];
    public cliente:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;

    public filtros:any = {};
    public producto:any = {};
    public categorias:any = [];
    public tieneFidelizacionHabilitada: boolean = false;
    modalRef!: BsModalRef;

    constructor(
        public apiService:ApiService,
        private alertService:AlertService,
        private modalService: BsModalService,
        private funcionalidadesService: FuncionalidadesService
    ){}

    ngOnInit() {
        this.verificarFidelizacionHabilitada();
        this.loadAll();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.tipo_contribuyente = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.estado = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarClientes();

        // Ocultar modal de importación
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

    public filtrarClientes(){
        this.loading = true;
        this.apiService.getAll('clientes', this.filtros).subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.loadAll();
    }

    public setTipo(cliente:any){
        this.cliente = cliente;
        this.onSubmit();
    }

    public setActivo(cliente:any, estado:any){
        this.cliente = cliente;
        this.cliente.enable = estado;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => {
            this.cliente = {};
            this.saving = false;
            this.alertService.success('Cliente actualizado', 'El cliente fue actualizado exitosamente.');
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(cliente:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('cliente/', cliente.id) .subscribe(data => {
                for (let i = 0; i < this.clientes.data.length; i++) {
                    if (this.clientes.data[i].id == data.id )
                        this.clientes.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });

        }
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.clientes.path + '?page='+ event.page).subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    openModal(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    public descargarPersonas(){
        this.downloading = true;
        this.apiService.export('clientes-personas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-personas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.alertService.modal = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; this.alertService.modal = false;}
        );
    }

    public descargarEmpresas(){
        this.downloading = true;
        this.alertService.modal = false;
        this.apiService.export('clientes-empresas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-empresas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.alertService.modal = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; this.alertService.modal = false;}
        );
    }


    public descargarExtranjeros(){
        this.downloading = true;
        this.alertService.modal = false;
        this.apiService.export('clientes-extranjeros/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-extranjeros.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.alertService.modal = false;
        }, (error) => { this.alertService.error(error); this.downloading = false; this.alertService.modal = false;}
        );
    }

    /**
     * Verificar si la empresa tiene fidelización habilitada
     */
    private verificarFidelizacionHabilitada(): void {
        this.funcionalidadesService.verificarAcceso('fidelizacion-clientes').subscribe({
            next: (tieneAcceso: boolean) => {
                this.tieneFidelizacionHabilitada = tieneAcceso;
            },
            error: (error) => {
                console.error('Error al verificar acceso a fidelización:', error);
                this.tieneFidelizacionHabilitada = false;
            }
        });
    }

    public generarEstadoCuenta(cliente: any){
        window.open(this.apiService.baseUrl + '/api/cliente/estado-de-cuenta/' + cliente.id + '?token=' + this.apiService.auth_token(), '_blank');
    }

}
