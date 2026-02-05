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
    public productoFiltro:any = {};
    public bodegaDe:any = {};
    public bodegaPara:any = {};
    public lotes: any[] = [];
    public lotesDestino: any[] = [];
    public loadingLotes: boolean = false;
    public loadingLotesDestino: boolean = false;
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

    public productoSelect(producto: any) {
        this.producto = producto;
        this.traslado.id_producto = producto.id;
        this.traslado.costo = producto.costo;
        this.traslado.lote_id = null;
        this.traslado.lote_id_destino = null;
        this.lotes = [];
        this.lotesDestino = [];
        
        // Si el producto tiene inventario por lotes, cargar los lotes cuando se seleccione la bodega origen
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }
        
        // Si ya hay bodega destino, cargar lotes de destino
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega) {
            this.cargarLotesDestino();
        }
    }

    public limpiarProducto() {
        this.producto = {};
        this.traslado.id_producto = null;
        this.traslado.id_bodega_de = null;
        this.traslado.id_bodega = null;
        this.traslado.lote_id = null;
        this.traslado.lote_id_destino = null;
        this.traslado.cantidad = null;
        this.lotes = [];
        this.lotesDestino = [];
        this.bodegaDe = {};
        this.bodegaPara = {};
    }

    public productoFiltroSelect(producto: any) {
        this.productoFiltro = producto;
        this.filtros.id_producto = producto.id;
    }

    public limpiarProductoFiltro() {
        this.productoFiltro = {};
        this.filtros.id_producto = null;
    }

    public cargarLotes() {
        if (!this.traslado.id_producto || !this.traslado.id_bodega_de) return;
        
        this.loadingLotes = true;
        this.apiService.getAll(`lotes/producto/${this.traslado.id_producto}`, {
            id_bodega: this.traslado.id_bodega_de
        }).subscribe(lotes => {
            this.lotes = Array.isArray(lotes) ? lotes : [];
            this.loadingLotes = false;
        }, error => {
            this.alertService.error(error);
            this.loadingLotes = false;
            this.lotes = [];
        });
    }

    public cargarLotesDestino() {
        if (!this.traslado.id_producto || !this.traslado.id_bodega) return;
        
        this.loadingLotesDestino = true;
        this.apiService.getAll(`lotes/producto/${this.traslado.id_producto}`, {
            id_bodega: this.traslado.id_bodega
        }).subscribe(lotes => {
            this.lotesDestino = Array.isArray(lotes) ? lotes : [];
            this.loadingLotesDestino = false;
        }, error => {
            this.alertService.error(error);
            this.loadingLotesDestino = false;
            this.lotesDestino = [];
        });
    }

    public setLoteOrigen() {
        // Recargar lotes para obtener stock actualizado cuando se selecciona un lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }
    }

    public validarStockLote() {
        // Recargar lotes para obtener stock actualizado cuando se cambia la cantidad
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }
    }

    public stockLoteSuficiente(): boolean {
        if (!this.producto?.inventario_por_lotes || !this.isLotesActivo() || !this.traslado.lote_id || !this.traslado.cantidad) {
            return true;
        }
        
        const loteSeleccionado = this.lotes.find((l: any) => l.id == this.traslado.lote_id);
        if (!loteSeleccionado) {
            return false;
        }
        
        const stockDisponible = parseFloat(loteSeleccionado.stock) || 0;
        const cantidadRequerida = parseFloat(this.traslado.cantidad) || 0;
        
        return stockDisponible >= cantidadRequerida;
    }

    public getStockOrigen(): number {
        // Si tiene lotes activos y hay un lote seleccionado, usar el stock del lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id) {
            const loteSeleccionado = this.lotes.find((l: any) => l.id == this.traslado.lote_id);
            if (loteSeleccionado) {
                return parseFloat(loteSeleccionado.stock) || 0;
            }
        }
        // Si no tiene lotes, usar el stock tradicional de la bodega
        return this.bodegaDe?.stock ? parseFloat(this.bodegaDe.stock) : 0;
    }

    public getStockDestino(): number {
        // Si tiene lotes activos y hay un lote destino seleccionado, usar el stock del lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id_destino) {
            const loteDestinoSeleccionado = this.lotesDestino.find((l: any) => l.id == this.traslado.lote_id_destino);
            if (loteDestinoSeleccionado) {
                return parseFloat(loteDestinoSeleccionado.stock) || 0;
            }
        }
        // Si no tiene lotes, usar el stock tradicional de la bodega
        return this.bodegaPara?.stock ? parseFloat(this.bodegaPara.stock) : 0;
    }

    public getStockOrigenDespues(): number {
        if (!this.traslado.cantidad) {
            return this.getStockOrigen();
        }
        const cantidad = Number(this.traslado.cantidad) || 0;
        const stockOrigen = this.getStockOrigen();
        return Math.max(0, stockOrigen - cantidad);
    }

    public getStockDestinoDespues(): number {
        if (!this.traslado.cantidad) {
            return this.getStockDestino();
        }
        const cantidad = Number(this.traslado.cantidad) || 0;
        const stockDestino = this.getStockDestino();
        return stockDestino + cantidad;
    }

    public isLotesActivo(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        
        const customConfig = typeof empresa.custom_empresa === 'string' 
            ? JSON.parse(empresa.custom_empresa) 
            : empresa.custom_empresa;
        
        return customConfig?.configuraciones?.lotes_activo === true;
    }

    public setSucursalDe(){
        this.bodegaDe = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega_de);
        this.traslado.lote_id = null;
        this.lotes = [];
        
        // Si el producto tiene inventario por lotes, cargar los lotes
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }
    }

    public setSucursalPara(){
        this.bodegaPara = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega);
        this.traslado.lote_id_destino = null;
        this.lotesDestino = [];
        
        // Si el producto tiene inventario por lotes, cargar los lotes de destino
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega) {
            this.cargarLotesDestino();
        }
    }

    public openModal(template: TemplateRef<any>) {
        this.traslado = {};
        this.producto = {};
        this.bodegaDe = {};
        this.bodegaPara = {};
        this.lotes = [];
        this.lotesDestino = [];
        this.traslado.id_producto = null;
        this.traslado.id_bodega = null;
        this.traslado.id_bodega_de = null;
        this.traslado.lote_id = null;
        this.traslado.lote_id_destino = null;

        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.traslado.id_empresa = this.apiService.auth_user().id_empresa;
        this.traslado.estado = 'Confirmado';

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
        // Validar que si el producto tiene lotes, se haya seleccionado un lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && !this.traslado.lote_id) {
            this.alertService.error('Debe seleccionar un lote para este producto.');
            return;
        }

        // Validar stock del lote antes de enviar
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id && this.traslado.cantidad) {
            const loteSeleccionado = this.lotes.find((l: any) => l.id == this.traslado.lote_id);
            if (loteSeleccionado) {
                const stockDisponible = parseFloat(loteSeleccionado.stock) || 0;
                const cantidadRequerida = parseFloat(this.traslado.cantidad) || 0;
                if (stockDisponible < cantidadRequerida) {
                    this.alertService.error(`El lote no tiene stock suficiente. Stock disponible: ${stockDisponible.toFixed(2)}, Cantidad requerida: ${cantidadRequerida.toFixed(2)}`);
                    // Recargar lotes para obtener stock actualizado
                    this.cargarLotes();
                    return;
                }
            }
        }

        this.saving = true;
        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.apiService.store('traslado', this.traslado).subscribe(traslado => { 
            this.traslado = {};
            this.producto = {};
            this.bodegaDe = {};
            this.bodegaPara = {};
            this.lotes = [];
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

    public imprimir(traslado:any){
        window.open(this.apiService.baseUrl + '/api/traslado/' + traslado.id + '/pdf?token=' + this.apiService.auth_token());
    }

}
