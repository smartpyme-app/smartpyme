import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-descargar-inventario',
    templateUrl: './descargar-inventario.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule]
})
export class DescargarInventarioComponent extends BaseModalComponent implements OnInit {

	public filtros:any = [];
    public bodegas:any = [];
    public downloading:boolean = false;

	constructor( 
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

	ngOnInit() {
        this.filtros.id_empresa = this.apiService.auth_user().id_empresa;
        this.filtros.id_bodega = '';
        this.filtros.fecha = this.apiService.date();

        this.apiService.getAll('bodegas/list').subscribe(bodegas => { 
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error); });
        
    }

    public override openModal(template: TemplateRef<any>) {
        super.openModal(template);
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
