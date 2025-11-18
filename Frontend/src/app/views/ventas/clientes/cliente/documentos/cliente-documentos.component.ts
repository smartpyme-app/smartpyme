import { Component, OnInit, Input, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-cliente-documentos',
    templateUrl: './cliente-documentos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class ClienteDocumentosComponent implements OnInit {
    public documento:any = {};
    public documentos:any = [];
    public loading:boolean = false;
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( public apiService:ApiService, private alertService:AlertService,
            private route: ActivatedRoute, private router: Router,
    ) { }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('cliente/' + this.route.snapshot.paramMap.get('id')! + '/documentos').pipe(this.untilDestroyed()).subscribe(documentos => {
            this.documentos = documentos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }


    public updateNombre(documento:any) {
        this.loading = true;
        this.apiService.store('cliente/documento', documento).pipe(this.untilDestroyed()).subscribe(documento => {
            this.alertService.success('Documento guardado', 'El documento fue guardado exitosamente');
        }, error => {this.alertService.error(error); this.loading = false; this.documento = {};});
    }


    public setFile(event:any) {
        this.documento.file = event.target.files[0];
        this.documento.cliente_id = this.route.snapshot.paramMap.get('id')!;
        
        let formData:FormData = new FormData();
        for (var key in this.documento) {
            formData.append(key, this.documento[key]);
        }

        this.loading = true;
        this.apiService.store('cliente/documento', formData).pipe(this.untilDestroyed()).subscribe(documento => {
            if(!this.documento.id) {
                this.documentos.push(documento);
            }
            this.documento = {};
            this.loading = false;
            this.alertService.success('Documento guardado', 'El documento fue guardado exitosamente');
        }, error => {this.alertService.error(error); this.loading = false; this.documento = {};});
    }

    public delete(documento:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('cliente/documento/', documento.id).pipe(this.untilDestroyed()).subscribe(data => {
                for (let i = 0; i < this.documentos.length; i++) { 
                    if (this.documentos[i].id == data.id )
                        this.documentos.splice(i, 1);
                }
                this.alertService.success('Documento eliminado', 'El documento fue eliminado exitosamente');
            }, error => {this.alertService.error(error); });
                   
        }
    }

    public verDocumento(documento:any){
        var ventana = window.open(this.apiService.baseUrl + "/img/" + documento.url + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }


}
