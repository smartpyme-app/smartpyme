import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-traslados',
  templateUrl: './traslados.component.html',
})
export class TrasladosComponent implements OnInit {

    public traslados:any = [];
    public traslado:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;

    public filtros:any = {};
    public productos:any = [];
    public sucursales:any = [];
    public conceptos:any = [];
    public producto:any = {};
    public bodegaDe:any = {};
    public bodegaPara:any = {};
    private tieneShopify: boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService, private router: Router, private route: ActivatedRoute
    ){}

    ngOnInit() {
        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        this.route.queryParams.subscribe(params => {
            this.filtros = {
                search: params['search'] || '',
                id_bodega_de: +params['id_bodega_de'] || '',
                id_bodega_para: +params['id_bodega_para'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                estado: params['estado'] || '',
                concepto: params['concepto'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarTraslados();
        });

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.id_bodega_de = '';
        this.filtros.id_bodega_para = '';
        this.filtros.id_producto = '';
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.search = '';
        this.filtros.concepto = '';
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarTraslados();
        
    }

    public filtrarTraslados(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge', // mantiene otros params si hay
        });
        this.loading = true;
        this.apiService.getAll('traslados', this.filtros).subscribe(traslados => { 
            this.traslados = traslados;
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

        this.loadAll();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.filtros.page = event.page;
        this.filtrarTraslados();
    }

    public setEstado(traslado:any){
        this.traslado = traslado;
        if (this.traslado.estado == 'Cancelado') {
            if (confirm('¿Confirma cancelar el traslado?')) {
                this.delete(this.traslado.id);
            }
        }else{
            if (confirm('¿Confirma confirmar el traslado?')) {
                this.onSubmit();
            }
        }
    }

    public setProducto(){
        this.producto = this.productos.find((item:any) => item.id == this.traslado.id_producto);
        this.traslado.costo = this.producto.costo;
    }

    public setSucursalDe(){
        this.bodegaDe = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega_de);
    }

    public setSucursalPara(){
        this.bodegaPara = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega);
    }

    public openModal(template: TemplateRef<any>) {
        this.traslado.id_producto = '';
        this.traslado.id_bodega = '';
        this.traslado.id_bodega_de = '';

        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.traslado.id_empresa = this.apiService.auth_user().id_empresa;
        this.traslado.estado = 'Confirmado';

        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop:'static'});
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('productos/list').subscribe(productos => { 
            this.productos = productos;
        }, error => {this.alertService.error(error); });
        this.apiService.getAll('traslados/conceptos').subscribe(conceptos => { 
            this.conceptos = conceptos;
        }, error => {this.alertService.error(error); });
        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        this.saving = true;
        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.apiService.store('traslado', this.traslado).subscribe(traslado => { 
            this.traslado = {};
            this.alertService.success('Traslado realizado', 'El traslado fue añadido exitosamente.');
            this.modalRef.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(id:number) {
        this.saving = true;
        this.apiService.delete('traslado/', id).subscribe(traslado => { 
            this.traslado = {};
            this.alertService.success('Traslado cancelado', 'El traslado fue cancelado exitosamente.');
            this.modalRef.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('traslados/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'traslados.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    public descargarPdfFiltrados(){
        this.downloading = true;
        const params = new URLSearchParams();
        Object.keys(this.filtros).forEach(key => {
            if (this.filtros[key] !== '' && this.filtros[key] !== null && this.filtros[key] !== undefined) {
                params.append(key, this.filtros[key]);
            }
        });
        
        this.apiService.download(`traslados/exportar-pdf?${params.toString()}`).subscribe({
            next: (response) => {
                const blob = new Blob([response], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `traslados-${new Date().toISOString().split('T')[0]}.pdf`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                this.alertService.success('PDF descargado', 'El reporte de traslados se ha descargado correctamente.');
            },
            error: (error) => {
                this.alertService.error('Error al descargar el PDF');
                this.downloading = false;
            }
        });
    }

    public descargarPdf(traslado: any) {
        this.downloading = true;
        this.apiService.download(`traslado/${traslado.id}/pdf`).subscribe({
            next: (response) => {
                const blob = new Blob([response], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `traslado-${traslado.id}.pdf`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                this.alertService.success('PDF descargado', 'El documento de traslado se ha descargado correctamente.');
            },
            error: (error) => {
                this.alertService.error('Error al descargar el PDF');
                this.downloading = false;
            }
        });
    }

    /**
     * Obtiene el nombre completo del producto (nombre + nombre_variante si aplica)
     */
    getNombreCompleto(producto: any): string {
        if (this.tieneShopify && producto.nombre_variante) {
            return `${producto.nombre} ${producto.nombre_variante}`;
        }
        return producto.nombre;
    }

}
