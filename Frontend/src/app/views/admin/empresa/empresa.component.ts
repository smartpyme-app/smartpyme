import { Component, OnInit, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent } from 'ngx-bootstrap/tabs';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-empresa',
  templateUrl: './empresa.component.html'
})
export class EmpresaComponent implements OnInit {

    public empresa: any = {};
    public loading = false;
    public saving = false;

  	constructor( 
  	    public apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router
  	) { }

  	ngOnInit() {
  	    this.loading = true;
        this.apiService.read('empresa/', this.apiService.auth_user().id_empresa).subscribe(empresa => {
            this.empresa = empresa;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
  	}
     
  	public onSubmit() {
  	    this.saving = true;
  	    this.apiService.store('empresa', this.empresa).subscribe(empresa => {
  	        this.empresa = empresa;
            this.alertService.success('Empresa actualiza', 'Tus datos fueron guardados exitosamente.');
  	        this.saving = false;
  	    },error => {this.alertService.error(error); this.saving = false; });
  	}
     

    setFile(event:any) {
        this.empresa.file = event.target.files[0];
        
        let formData:FormData = new FormData();
        for (var key in this.empresa) {
            formData.append(key, this.empresa[key] == null ? '' : this.empresa[key]);
        }
        this.loading = true;
        this.apiService.store('empresa', formData).subscribe(empresa => {
            this.empresa.logo = empresa.logo;
            this.loading = false;
            this.alertService.success('Logo actualizo', 'Tu logo fue guardado exitosamente.');
        }, error => {this.alertService.error(error); this.loading = false; this.empresa = {};});
    }

}
