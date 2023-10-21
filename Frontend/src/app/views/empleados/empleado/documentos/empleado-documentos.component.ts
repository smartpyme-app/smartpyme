import { Component, OnInit, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-empleado-documentos',
  templateUrl: './empleado-documentos.component.html'
})
export class EmpleadoDocumentosComponent implements OnInit {

    @Input() empleado: any = {};
    public documento:any = {};
    public loading:boolean = false;

    constructor( public apiService:ApiService, private alertService:AlertService,
            private route: ActivatedRoute, private router: Router,
    ) { }

    ngOnInit() {

    }


    public updateNombre(documento:any) {
        this.loading = true;
        this.apiService.store('empleado/documento', documento).subscribe(documento => {
            this.alertService.success('Guardado');
        }, error => {this.alertService.error(error); this.loading = false; this.documento = {};});
    }


    public setFile(event:any) {
        this.documento.file = event.target.files[0];
        this.documento.empleado_id = this.empleado.id;
        
        let formData:FormData = new FormData();
        for (var key in this.documento) {
            formData.append(key, this.documento[key]);
        }

        this.loading = true;
        this.apiService.store('empleado/documento', formData).subscribe(documento => {
            if(!this.documento.id) {
                this.empleado.documentos.push(documento);
            }
            this.documento = {};
            this.loading = false;
            this.alertService.success('Guardado');
        }, error => {this.alertService.error(error); this.loading = false; this.documento = {};});
    }

    public delete(documento:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('empleado/documento/', documento.id) .subscribe(data => {
                for (let i = 0; i < this.empleado.documentos.length; i++) { 
                    if (this.empleado.documentos[i].id == data.id )
                        this.empleado.documentos.splice(i, 1);
                }
                this.alertService.success('Eliminado');
            }, error => {this.alertService.error(error); });
                   
        }
    }

    public verDocumento(documento:any){
        var ventana = window.open(this.apiService.baseUrl + "/img/" + documento.url + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }


}
