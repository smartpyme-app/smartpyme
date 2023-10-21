import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
  selector: 'app-flota-documentos',
  templateUrl: './flota-documentos.component.html'
})
export class FlotaDocumentosComponent implements OnInit {

    public documentos: any[] = [];
    public flota: any = {};
    public documento: any = {};
    public loading = false;
    public buscador:any = '';
    public estado:any = '';

    modalRef!: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        const id = +this.route.snapshot.paramMap.get('id')!;
        if(id)
            this.loadAll();
        else
            this.documentos = [];
    }


    public loadAll(){
        this.loading = true;
        this.apiService.getAll('flota/' +  this.route.snapshot.paramMap.get('id')! + '/documentos').subscribe(documentos => {
            this.documentos = documentos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, documento:any) {
        this.documento = documento;
        this.modalRef = this.modalService.show(template);
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('flota/documento/', id) .subscribe(data => {
                for (let i = 0; i < this.documentos.length; i++) { 
                    if (this.documentos[i].id == data.id )
                        this.documentos.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }

    setFile(event:any) {
        this.documento.file = event.target.files[0];        
    }

    public preview(documento:any){
        var ventana = window.open(this.apiService.baseUrl + documento.url + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=500, height=500");
    }

    public onSubmit() {
          this.loading = true;
          this.documento.flota_id = this.flota.id;

          let formData:FormData = new FormData();
          for (var key in this.documento) {
              formData.append(key, this.documento[key] == null ? '' : this.documento[key]);
          }

          this.apiService.upload('flota/documento', formData).subscribe((documento:any) => {
              if (!this.documento.id) {
                  this.documentos.push(documento);
              }
              this.documento.url = documento.url;
              this.alertService.success("Datos guardados");
              this.loading = false;
            this.modalRef.hide();
          },error => {this.alertService.error(error); this.loading = false; });
      }

}
