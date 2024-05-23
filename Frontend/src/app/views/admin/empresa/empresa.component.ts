import { Component, OnInit, ViewChild, ElementRef, Renderer2} from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent } from 'ngx-bootstrap/tabs';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-empresa',
  templateUrl: './empresa.component.html'
})
export class EmpresaComponent implements OnInit {

    @ViewChild("takeInput") inputVar!: ElementRef<any>;
    @ViewChild("imageProfPic") picProf!: ElementRef<any>;
    public empresa: any = {};
    public loading = false;
    public saving = false;
    public cheking = false;
    public departamentos:any = [];
    public municipios:any = [];
    public actividad_economicas:any = [];

    public showpassword:boolean = false;
    public showpassword2:boolean = false;


  	constructor(
  	    public apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router, public renderer2: Renderer2
  	) { }

  	ngOnInit() {
        this.loadAll();

        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas')!);

  	}

    public loadAll() {
  	    this.loading = true;
        this.apiService.read('empresa/', this.apiService.auth_user().id_empresa).subscribe(empresa => {
            this.empresa = empresa;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
    }

  	public onSubmit(): Promise<any> {

        return new Promise((resolve, reject) => {
      	    this.saving = true;
      	    this.apiService.store('empresa', this.empresa).subscribe(empresa => {
      	        this.empresa = empresa;

                let user:any = {};
                user = JSON.parse(localStorage.getItem('SP_auth_user')!);
                user.empresa = empresa;
                localStorage.setItem('SP_auth_user', JSON.stringify(user));

                this.alertService.success('Empresa actualiza', 'Tus datos fueron guardados exitosamente.');
      	        this.saving = false;
                resolve(null);
      	    },error => {this.alertService.error(error); this.saving = false; resolve(null);});

        });
  	}

    setGiro(){
        this.empresa.giro = this.actividad_economicas.find((item:any) => item.cod == this.empresa.cod_actividad_economica).nombre;
    }

    setMunicipio(){
        this.empresa.municipio = this.municipios.find((item:any) => item.cod == this.empresa.cod_municipio).nombre;
    }

    setDepartamento(){
        this.empresa.departamento = this.departamentos.find((item:any) => item.cod == this.empresa.cod_departamento).nombre;
    }

    setPais(){
        if(this.empresa.pais == 'El Salvador'){
            this.empresa.moneda = 'USD';
            this.empresa.iva = 13;
        }
        if(this.empresa.pais == 'Belice'){
            this.empresa.moneda = 'BZD';
            this.empresa.iva = 12.5;
        }
        if(this.empresa.pais == 'Guatemala'){
            this.empresa.moneda = 'GTQ';
            this.empresa.iva = 12;
        }
        if(this.empresa.pais == 'Honduras'){
            this.empresa.moneda = 'HNL';
            this.empresa.iva = 15;
        }
        if(this.empresa.pais == 'Nicaragua'){
            this.empresa.moneda = 'NIO';
            this.empresa.iva = 15;
        }
        if(this.empresa.pais == 'Costa Rica'){
            this.empresa.moneda = 'CRC';
            this.empresa.iva = 13;
        }
        if(this.empresa.pais == 'Panamá'){
            this.empresa.moneda = 'PAB';
            this.empresa.iva = 7;
        }
        console.log(this.empresa);
    }

    setCobrarIVA(){
        console.log(this.empresa.cobra_iva);
        if(this.empresa.cobra_iva == 'Si'){
            this.empresa.cobra_iva = 'No';
        }else{
            this.empresa.cobra_iva = 'Si';
        }
        console.log(this.empresa.cobra_iva);
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


  resetFileUploader() {
    const img_pic= this.picProf.nativeElement;

    this.inputVar.nativeElement.value = "";


    this.empresa.file = null;

    let formData:FormData = new FormData();
    for (var key in this.empresa) {
        formData.append(key, this.empresa[key] == null ? '' : this.empresa[key]);
    }
    this.loading = true;
    this.apiService.store('empresa', formData).subscribe(empresa => {
        this.empresa.logo = null;
        this.loading = false;
        this.alertService.warning('Logo actualizo', 'Para guardar los cambios haga click en el boton Guardar');
    }, error => {this.alertService.error(error); this.loading = false; this.empresa = {};});

    this.renderer2.setAttribute(img_pic,'src', 'https://t4.ftcdn.net/jpg/00/64/67/63/360_F_64676383_LdbmhiNM6Ypzb3FM4PPuFP9rHe7ri8Ju.jpg');
  }


}
