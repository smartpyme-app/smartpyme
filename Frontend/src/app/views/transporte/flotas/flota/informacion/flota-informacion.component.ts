import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
  selector: 'app-flota-informacion',
  templateUrl: './flota-informacion.component.html'
})
export class FlotaInformacionComponent implements OnInit {

    public flota:any = {};
    public loading = false;
    modalRef?: BsModalRef;

    // Img Upload
      public file?:File;
      public preview = false;
      public url_img_preview:string = '';

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('flota/', id).subscribe(flota => {
                this.flota = flota;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.flota = {};
            this.flota.usuario_id = this.apiService.auth_user().id;
            this.flota.sucursal_id = this.apiService.auth_user().sucursal_id;
        }
    }

    public submit():void{
        this.loading = true;

        let formData:FormData = new FormData();
        for (var key in this.flota) {
            formData.append(key, this.flota[key] == null ? '' : this.flota[key]);
        }

        this.apiService.store('flota', this.flota).subscribe(flota => { 
            this.router.navigate(['/flotas']);
            this.alertService.success('Guardado');
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    setFile(event:any){
        this.file = event.target.files[0];
        this.flota.file = this.file;
        var reader = new FileReader();
        reader.onload = ()=> {
            var url:any;
            url = reader.result;
            this.url_img_preview = url;
            this.preview = true;
           };
        reader.readAsDataURL(this.file!);
    }

}
