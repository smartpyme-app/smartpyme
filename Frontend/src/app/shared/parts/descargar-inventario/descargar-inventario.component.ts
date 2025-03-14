import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-descargar-inventario',
  templateUrl: './descargar-inventario.component.html'
})
export class DescargarInventarioComponent implements OnInit {

	public filtros:any = [];
    public downloading:boolean = false;
    modalRef!: BsModalRef;

	constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

	ngOnInit() {}


    public openModal(template: TemplateRef<any>) {
        this.filtros.id_empresa = this.apiService.auth_user().id_empresa;
        this.filtros.fecha = this.apiService.date();
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('inventarios/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'inventario.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }


}
