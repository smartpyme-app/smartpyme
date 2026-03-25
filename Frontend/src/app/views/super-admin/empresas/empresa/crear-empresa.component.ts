import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-crear-empresa',
  templateUrl: './crear-empresa.component.html'
})

export class CrearEmpresaComponent implements OnInit {

    public empresa:any = {};
    public clientes:any = [];
    public documentos:any = [];
    public vendedores:any = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public licencia:boolean = false;
    public filtros:any = {};
    public departamentos:any = [];
    public municipios:any = [];
    public actividad_economicas:any = [];

    modalRef!: BsModalRef;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loadAll();
        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas')!);

        this.apiService.getAll('clientes/list').subscribe((clientes) => {
            this.clientes = clientes;
            this.loading = false;
        }, (error) => {this.alertService.error(error); this.loading = false; } );

        this.apiService.getAll('documentos/list').subscribe((documentos) => {
            this.documentos = documentos;
            this.loading = false;
        }, (error) => {this.alertService.error(error); this.loading = false; } );

        this.apiService.getAll('admin-usuarios/list-vendedores').subscribe((response) => {
            let usuarios = [];
            if (response && response.data) {
                usuarios = response.data;
            } else if (Array.isArray(response)) {
                usuarios = response;
            }
            this.vendedores = usuarios.map((usuario: any) => ({
                id: usuario.id,
                nombre: usuario.name
            }));
        }, (error) => {this.alertService.error(error); } );

    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('empresa/', id).subscribe(empresa => {
                this.empresa = empresa;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.empresa = {};
            this.empresa.industria = '';
            this.empresa.iva = 13; 
            this.empresa.moneda = 'USD';
            this.empresa.plan = 'Emprendedor';
            this.empresa.tipo_plan = 'Mensual';
            this.empresa.pais = 'El Salvador';
            this.empresa.tipo_contribuyente = '';
            this.empresa.activo = 1;
            this.empresa.modulo_citas = 1;
            this.empresa.vender_sin_stock = 1;
            this.empresa.editar_precio_venta = 1;
            this.empresa.numero_lineas_impresion = 6;
            this.setPlan();
        }

        // Para cotizaciones Pre-venta
        if (this.route.snapshot.queryParamMap.get('licencia')) {
            this.licencia = true;
        }
    }

    public setCliente(cliente: any) {
      if (!this.empresa.id_cliente) {
        this.clientes.push(cliente);
      }
      this.empresa.id_cliente = cliente.id;
    }

    public onSubmit() {
        this.saving = true;
        this.empresa.isRegister = false;
        this.apiService.store('empresa', this.empresa).subscribe(empresa => {
            this.saving = false;
            if(!this.empresa.id){
                this.empresa = empresa;
                this.alertService.success('Empresa creada', 'La empresa fue añadida exitosamente.');
                this.router.navigate(['/admin/empresa/' + empresa.id]);
            }else{
                this.alertService.success('Empresa guardada', 'La empresa fue guardada exitosamente.');
            }
            
            if(this.licencia){
                let data:any = {};
                data.id_empresa = empresa.id;
                data.id_licencia = this.apiService.auth_user().empresa.licencia.id;                
                this.apiService.store('licencia/empresa', data).subscribe(empresa => {
                },error => {this.alertService.error(error); this.saving = false; });
            }
        },error => {this.alertService.error(error); this.saving = false; });

    }


    setGiro(){
        this.empresa.giro = this.actividad_economicas.find((item:any) => item.cod == this.empresa.cod_giro).nombre;
    }

    setMunicipio(){
        this.empresa.municipio = this.municipios.find((item:any) => item.cod == this.empresa.cod_municipio && item.cod_departamento == this.empresa.cod_departamento).nombre;
    }

    setDepartamento(){
        this.empresa.departamento = this.departamentos.find((item:any) => item.cod == this.empresa.cod_departamento).nombre;
        this.empresa.cod_municipio = null;
        this.empresa.municipio = null;
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
        if(this.empresa.pais == 'México'){
            this.empresa.moneda = 'MXN';
            this.empresa.iva = 16;
        }
        console.log(this.empresa);
    }

    public setPlan(){
        if(this.empresa.plan == 'Emprendedor'){
            this.empresa.user_limit = 1;
            this.empresa.sucursal_limit = 1;

            if(this.empresa.tipo_plan == 'Mensual'){
                this.empresa.total = 16.95;
            }else{
                this.empresa.total = 203.4;
            }
        }

        if(this.empresa.plan == 'Estándar'){
            this.empresa.user_limit = 2;
            this.empresa.sucursal_limit = 1;

            if(this.empresa.tipo_plan == 'Mensual'){
                this.empresa.total = 28.25;
            }else{
                this.empresa.total = 339;
            }
        }

        if(this.empresa.plan == 'Avanzado'){
            this.empresa.user_limit = 5;
            this.empresa.sucursal_limit = 2;

            if(this.empresa.tipo_plan == 'Mensual'){
                this.empresa.total = 56.5;
            }else{
                this.empresa.total = 678;
            }
        }

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


    openModal(template: TemplateRef<any>, empresa:any) {
        this.empresa = empresa;
        if (!this.empresa.id) {
            this.empresa.industria = '';
            this.empresa.iva = 13; 
            this.empresa.moneda = 'USD';
            this.empresa.plan = 'Emprendedor';
            this.empresa.tipo_plan = 'Mensual';
            this.empresa.activo = 1;
            this.empresa.modulo_citas = 1;
            this.empresa.vender_sin_stock = 1;
            this.empresa.editar_precio_venta = 1;
            this.empresa.numero_lineas_impresion = 6;
        }
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }


}
