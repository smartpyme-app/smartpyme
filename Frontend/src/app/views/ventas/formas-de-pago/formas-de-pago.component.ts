import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-formas-de-pago',
  templateUrl: './formas-de-pago.component.html'
})

export class FormasDePagoComponent implements OnInit {

    public formas_pago:any = [];
    public forma_pago:any = {};
    public empresa:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public wompiActivo:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.empresa = this.apiService.auth_user().empresa;
        this.loadAll();
    }

    public loadAll() {        
        this.loading = true;
        this.apiService.getAll('formas-de-pago').subscribe(formas_pago => { 
            this.formas_pago = formas_pago;
            this.wompiActivo = formas_pago.filter((item:any) => item.nombre == 'Wompi')[0].activo;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public onSubmit(nombre:any){
        this.saving = true;

        this.forma_pago.nombre = nombre;
        this.forma_pago.id_empresa = this.apiService.auth_user().id_empresa;

        this.apiService.store('forma-de-pago', this.forma_pago).subscribe(forma_pago => {
            this.alertService.success('Formas de pago actualizadas', 'Las formas de pago fueron actualizadas exitosamente.');
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public openModal(template: TemplateRef<any>){
        this.modalRef = this.modalService.show(template);
    }

    public onSubmitWompi(){
        this.saving = true;
        this.apiService.store('wompi', this.empresa).subscribe(forma_pago => {
            this.saving = false;
            this.alertService.success('Conexión exitosa', 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para tus ventas.');
            // this.modalRef.hide();
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
