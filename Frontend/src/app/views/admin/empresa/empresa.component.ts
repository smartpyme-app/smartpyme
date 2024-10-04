import { Component, OnInit, ViewChild } from '@angular/core';
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

    public empresa: any = {};
    public loading = false;
    public saving = false;
    public cheking = false;
    public departamentos:any = [];
    public distritos:any = [];
    public municipios:any = [];
    public actividad_economicas:any = [];

    public showpassword:boolean = false;
    public showpassword2:boolean = false;

    constructor( 
        public apiService: ApiService, public mhService: MHService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router
    ) { }

    ngOnInit() {
        this.loadAll();

        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.distritos = JSON.parse(localStorage.getItem('distritos')!);
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
        this.empresa.giro = this.actividad_economicas.find((item:any) => item.cod == this.empresa.cod_giro).nombre;
    }

    setDistrito(){
        let distrito = this.distritos.find((item:any) => item.cod == this.empresa.cod_distrito && item.cod_departamento == this.empresa.cod_departamento);
        console.log(distrito);
        if(distrito){
            this.empresa.cod_municipio = distrito.cod_municipio;
            this.setMunicipio();
            this.empresa.distrito = distrito.nombre; 
            this.empresa.cod_distrito = distrito.cod;
        }
    }

    setMunicipio(){
        let municipio = this.municipios.find((item:any) => item.cod == this.empresa.cod_municipio && item.cod_departamento == this.empresa.cod_departamento);
        if(municipio){
            this.empresa.municipio = municipio.nombre; 
            this.empresa.cod_municipio = municipio.cod;

            this.empresa.distrito = ''; 
            this.empresa.cod_distrito = '';
        }
    }

    setDepartamento(){
        let departamento = this.departamentos.find((item:any) => item.cod == this.empresa.cod_departamento);
        if(departamento){
            this.empresa.departamento = departamento.nombre; 
            this.empresa.cod_departamento = departamento.cod;

        }
        this.empresa.municipio = ''; 
        this.empresa.cod_municipio = '';
        this.empresa.distrito = ''; 
        this.empresa.cod_distrito = '';
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

        this.empresa.cod_departamento= " ";
        this.empresa.cod_municipio= " ";

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
        for (let key in this.empresa) {
            if (this.empresa.hasOwnProperty(key)) {
                let value = this.empresa[key];
                if (typeof value === 'boolean') {
                    formData.append(key, value ? '1' : '0');
                } else {
                    formData.append(key, value == null ? '' : value);
                }
            }
        }
        this.loading = true;
        this.apiService.store('empresa', formData).subscribe(empresa => {
            this.empresa.logo = empresa.logo;
            this.loading = false;
            this.alertService.success('Logo actualizo', 'Tu logo fue guardado exitosamente.');
        }, error => {this.alertService.error(error); this.loading = false; this.empresa = {};});
    }

    public onCheckMH():void {
        this.cheking = true;
        
        this.onSubmit().then(() => {
            this.mhService.auth().subscribe(response => {

                if(response.status == 'ERROR'){
                    this.cheking = false;
                    this.alertService.info('Revisar', response.body.descripcionMsg);
                }else{
                    this.cheking = false;
                    this.alertService.success('Conexión a la API exitosa', 'El proceso se realizo correctamente.');
                }
            },error => {this.alertService.error(error); this.cheking = false; });
        });

    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  
    
    public mostrarPassword2(){
        this.showpassword2 = !this.showpassword2;
    } 

    public onCheckFE() {
        this.cheking = true;
        
            this.mhService.verificarFirmador().subscribe(response => {
                this.cheking = false;
                console.log(response.status)
                if (response.status === 200) {
                  this.alertService.success('Conexión al firmador exitosa.', 'El proceso se realizo correctamente.');
                } else {
                  this.alertService.warning('Datos incorrectos','No se pudo conectar al firmador');
                };
            },error => {
                console.log(error)
                if (error.status == 200) {
                  this.alertService.success('Conexión al firmador exitosa.', 'El proceso se realizo correctamente.');
                } else {
                  this.alertService.warning('Datos incorrectos','No se pudo conectar al firmador');
                };
                this.cheking = false;
            });

    }


}
