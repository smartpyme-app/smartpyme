import { Component, OnInit, ViewChild, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-suscripcion',
  templateUrl: './suscripcion.component.html'
})
export class SuscripcionComponent implements OnInit {

    public suscripcion: any = {};
    public usuario: any = {};
    public loading = false;
    public saving = false;

    modalRef?: BsModalRef;

  	constructor( 
  	    public apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
  	) { }

  	ngOnInit() {
  	    this.loading = true;
        this.usuario = this.apiService.auth_user();
        this.apiService.getAll('suscripcion').subscribe(suscripcion => {
            this.suscripcion = suscripcion;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
  	}
     
  	public onSubmit() {
  	    this.saving = true;
  	    this.apiService.store('suscripcion', this.suscripcion).subscribe(suscripcion => {
  	        this.suscripcion = suscripcion;
  	        this.alertService.success('Suscripción guardada', 'Los datos de tu plan fueron guardados exitosamente.');
  	        this.saving = false;
  	    },error => {this.alertService.error(error); this.saving = false; });
  	} 

    public onCancelar() {
        this.saving = true;
        this.apiService.store('cancelar-suscripcion', this.usuario).subscribe(usuario => {
            this.usuario = usuario;
            this.alertService.success('Suscripción cancelada', 'Tu cuenta ha sido desactivada.');
            this.router.navigate(['login']);
            this.modalRef!.hide();
            this.saving = false;
        },error => {this.alertService.error(error); this.saving = false; });
    }

    public imprimirDoc(recibo:any){
        window.open(this.apiService.baseUrl + '/api/recibo/pdf/' + recibo.id + '?token=' + this.apiService.auth_token());
    }

    openModal(template: TemplateRef<any>) {
        // this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

}
