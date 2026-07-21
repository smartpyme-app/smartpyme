import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-productos-consignas-compras',
  templateUrl: './productos-consignas-compras.component.html',
})
export class ProductosConsignasComprasComponent implements OnInit {
  public compras: any[] = [];
  public buscador: string = '';
  public loading: boolean = false;
  public downloading: boolean = false;
  public compra: any = {};
  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.loadAll();
  }

  public loadAll() {
    this.loading = true;
    this.apiService.getAll('productos/consignas-compras').subscribe(
      (compras) => {
        this.compras = compras;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public openDetalles(template: TemplateRef<any>, compra: any) {
    this.compra = compra;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('productos/consignas-compras/exportar', {}).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'consignas-compras.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloading = false;
      }
    );
  }
}
