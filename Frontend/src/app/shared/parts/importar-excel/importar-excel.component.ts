import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-importar-excel',
  templateUrl: './importar-excel.component.html'
})
export class ImportarExcelComponent implements OnInit {

    @Input() nombre:string = '';
    @Output() loadAll = new EventEmitter();
    public loading:boolean = false;
    public file:any = {};

    modalRef!: BsModalRef;

    constructor( 
        private apiService: ApiService, public alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    setFile(event:any){
        this.file.file = event.target.files[0];
    }

    onSubmit(event:any) {

        console.log(this.file);
        
        let formData:FormData = new FormData();
        for (var key in this.file) {
            formData.append(key, this.file[key]);
        }

        console.log(formData);
        this.loading = true;
        this.apiService.store(this.nombre.toLowerCase() + '/importar', formData).subscribe(data => {
            this.loading = false;
            this.alertService.success('Importación exitosa', data + ' ' + this.nombre.replace('-', ' ') + ' agregados');
            setTimeout(()=>{
                this.modalRef.hide();
                this.loadAll.emit();
                this.alertService.modal = false;
            }, 2000);
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
