import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-recurrentes',
  templateUrl: './recurrentes.component.html'
})

export class RecurrentesComponent implements OnInit {

    public ventas:any = [];
    public venta:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;

    public clientes:any = [];
    public usuario:any = {};
    public usuarios:any = [];
    public sucursales:any = [];
    public formaPagos:any = [];
    public documentos:any = [];
    public canales:any = [];
    public filtros:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarVentas();
    }

    public loadAll() {
        const filtrosGuardados = localStorage.getItem('ventasRecurrentesFiltros');

        if (filtrosGuardados) {
            this.filtros = JSON.parse(filtrosGuardados);
            // console.log(this.filtros);
          } else {
            this.filtros.id_sucursal = '';
            this.filtros.id_cliente = '';
            this.filtros.id_usuario = '';
            this.filtros.id_canal = '';
            this.filtros.id_documento = '';
            this.filtros.forma_pago = '';
            this.filtros.recurrente = true;
            this.filtros.estado = '';
            this.filtros.buscador = '';
            this.filtros.orden = 'fecha';
            this.filtros.direccion = 'desc';
            this.filtros.paginate = 10;
          }

        this.filtrarVentas();
    }

    public filtrarVentas(){
        localStorage.setItem('ventasRecurrentesFiltros', JSON.stringify(this.filtros));
        this.loading = true;
        this.apiService.getAll('ventas', this.filtros).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
    }

    public setEstado(venta:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la venta?')){
                this.venta = venta;
                this.venta.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la venta?')){
                this.venta = venta;
                this.venta.estado = estado;
                this.onSubmit();
            }
        }

    }

    public setRecurrencia(venta:any){
        this.venta = venta;
        this.venta.recurrente = false;
        
        this.apiService.store('venta', this.venta).subscribe(venta => {
            this.venta = {};
            this.loadAll();
            this.alertService.success('Venta guardada', 'La venta se marco como no recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }
    

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('venta/', id) .subscribe(data => {
                for (let i = 0; i < this.ventas['data'].length; i++) { 
                    if (this.ventas['data'][i].id == data.id )
                        this.ventas['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.ventas.path + '?page='+ event.page, this.filtros).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public reemprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // Editar

    public openModalEdit(template: TemplateRef<any>, venta:any) {
        this.venta = venta;
        
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago').subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template);
    }
    
    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('formas-de-pago').subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });
        
        this.apiService.getAll('documentos/list-nombre').subscribe(documentos => { 
            this.documentos = documentos;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('canales').subscribe(canales => { 
            this.canales = canales;
        }, error => {this.alertService.error(error); });
        
        this.modalRef = this.modalService.show(template);
    }

    public openDescargar(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('ventas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas-recurrentes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => {this.alertService.error(error); this.downloading = false;}
        );
    }

    public descargarVentas(){
        this.apiService.export('ventas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar ventas:', error); }
        );
    }

    public descargarDetalles(){
        this.apiService.export('ventas-detalles/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar ventas:', error); }
        );
    }

    public imprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public linkWompi(venta:any){
        window.open(this.apiService.baseUrl + '/api/venta/wompi-link/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('venta', this.venta).subscribe(venta => {
            this.venta = {};
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardada', 'La venta fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.modalRef = this.modalService.show(template);
    }

    public limpiarFiltros() {
        localStorage.removeItem('ventasRecurrentesFiltros');
        this.loadAll();
    }


}
