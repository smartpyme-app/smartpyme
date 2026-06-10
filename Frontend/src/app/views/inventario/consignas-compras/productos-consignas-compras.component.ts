import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-productos-consignas-compras',
  templateUrl: './productos-consignas-compras.component.html',
})
export class ProductosConsignasComprasComponent implements OnInit {

    public productos: any = [];
    public buscador: string = '';
    public loading: boolean = false;
    public downloading: boolean = false;
    
    public filtros: any = {};
    public producto: any = {};
    public categorias: any = [];

    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => { this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.categoria = '';
        this.loading = true;
        this.apiService.getAll('productos/consignas-compras').subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => { this.alertService.error(error); this.loading = false; });
    }

    public openModal(template: TemplateRef<any>, producto: any) {
        this.producto = producto;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public descargar() {
        this.downloading = true;
        this.apiService.export('productos/consignas-compras/exportar', this.filtros).subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'consignas-compras.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
        }, (error) => { this.alertService.error(error); this.downloading = false; });
    }

    public isShopifyActive(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa) return false;
        return !!(empresa.shopify_store_url && 
                 empresa.shopify_consumer_secret && 
                 empresa.shopify_status === 'connected');
    }
}
