import { Component, OnInit, ViewChild, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef, } from 'ngx-bootstrap/modal';
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

    public estadisticasPruebas: any = null;
    public documentosBase: any[] = [];
    public tipoSeleccionado: string = '';
    public cantidadFaltante: number = 1;
    public documentoBaseSeleccionado: any = null;
    public procesando: boolean = false;
    public modalRef!: BsModalRef;
    public procesandoPruebas: boolean = false;
    @ViewChild('modalTemplate')
    modalTemplate!: TemplateRef<any>;

    public estadoPruebasCompletado: boolean = false;
    public fechaCompletadoPruebas: string = '';

    public showpassword:boolean = false;
    public showpassword2:boolean = false;

    constructor( 
        public apiService: ApiService, public mhService: MHService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loadAll();

        this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
        this.municipios = JSON.parse(localStorage.getItem('municipios')!);
        this.distritos = JSON.parse(localStorage.getItem('distritos')!);
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas')!);

        setTimeout(() => {
            if (this.empresa && this.empresa.fe_ambiente === '00') {
                this.cargarEstadisticasPruebas();
                this.cargarDocumentosBase();
            }
        }, 1000); 

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

                if(this.empresa.fe_ambiente == '01'){
                    localStorage.setItem('SP_mh_url_base', 'https://api.dtes.mh.gob.sv');
                }else{
                    localStorage.setItem('SP_mh_url_base', 'https://apitest.dtes.mh.gob.sv');
                }

                this.alertService.success('Empresa actualiza', 'Tus datos fueron guardados exitosamente.');
                this.saving = false;
                resolve(null);
            },error => {this.alertService.error(error); this.saving = false; resolve(null);});
            
        });
    }

    setGiro(){
        this.empresa.giro = this.actividad_economicas.find((item:any) => item.cod == this.empresa.cod_actividad_economica).nombre;
        console.log(this.empresa);
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

    cargarEstadisticasPruebas() {
        if(this.empresa.fe_ambiente == '00') {
            this.mhService.obtenerEstadisticasPruebasMasivas().subscribe(
                (data) => {
                    this.estadisticasPruebas = data.tipos;
                    this.estadoPruebasCompletado = data.estado.completado;
                    this.fechaCompletadoPruebas = data.estado.fecha_completado;
                },
                (error) => {
                    console.error('Error al cargar estadísticas de pruebas:', error);
                    this.alertService.error('No se pudieron cargar las estadísticas de pruebas masivas');
                }
            );
        }
    }
    
      getKeysPruebas(): string[] {
        return this.estadisticasPruebas ? Object.keys(this.estadisticasPruebas) : [];
      }
    
      getLabelTipo(tipo: string): string {
        const labels: { [key: string]: string } = {
          'facturas': 'Facturas',
          'creditosFiscales': 'CCF',
        //   'notasCredito': 'Notas Crédito',
        //   'notasDebito': 'Notas Débito',
        //   'facturasExportacion': 'Exportación',
        //   'sujetoExcluido': 'Sujeto Excluido'
        };
        
        return labels[tipo] || tipo;
      }
    
      isPruebaCompleta(tipo: string): boolean {
        if (!this.estadisticasPruebas || !this.estadisticasPruebas[tipo]) {
          return false;
        }
        
        return this.estadisticasPruebas[tipo].emitidas >= this.estadisticasPruebas[tipo].requeridas;
      }
    
      getProgresoTipo(tipo: string): number {
        if (!this.estadisticasPruebas || !this.estadisticasPruebas[tipo]) {
          return 0;
        }
        
        const { emitidas, requeridas } = this.estadisticasPruebas[tipo];
        return Math.min(100, Math.round((emitidas / requeridas) * 100));
      }
    
      getTotalProgress(): number {
        if (!this.estadisticasPruebas) {
          return 0;
        }
        
        // Verificar si todos los tipos de documentos han alcanzado el mínimo requerido
        const todosCompletados = Object.values(this.estadisticasPruebas).every((stat: any) => 
          stat.emitidas >= stat.requeridas
        );
        
        // Si todos los tipos han alcanzado el mínimo, mostrar 100%
        if (todosCompletados) {
          return 100;
        }
        
        // Caso contrario, calcular el porcentaje real pero limitado a 100%
        let totalEmitidos = 0;
        let totalRequeridos = 0;
        
        Object.values(this.estadisticasPruebas).forEach((stat: any) => {
          // Para cada tipo, considerar como máximo el número requerido
          totalEmitidos += Math.min(stat.emitidas, stat.requeridas);
          totalRequeridos += stat.requeridas;
        });
        
        return Math.min(100, Math.round((totalEmitidos / totalRequeridos) * 100));
      }
    
      cargarDocumentosBase() {
        this.apiService.getAll('mh/pruebas-masivas/documentos-base').subscribe(
          (data) => {
            this.documentosBase = data;
          },
          (error) => {
            console.error('Error al cargar documentos base:', error);
            this.alertService.error('Error al cargar documentos base');
          }
        );
      }
      
      // Modifica este método para que abra el modal
      ejecutarPruebasMasivas(template: TemplateRef<any>, tipo: string) {
        this.tipoSeleccionado = tipo;
        
        // Calcular cuántos documentos faltan
        if (this.estadisticasPruebas && this.estadisticasPruebas[tipo]) {
          const { emitidas, requeridas } = this.estadisticasPruebas[tipo];
          
          // Mostrar el modal usando el template pasado como parámetro
          this.modalRef = this.modalService.show(template, {
            class: 'modal-md'
          });
        }
      }
      
      // Método para confirmar y ejecutar la emisión
      confirmarEjecucion() {
        this.modalRef.hide();
        this.procesando = true;
        
        // Llamada al servicio para ejecutar las pruebas
        this.mhService.ejecutarPruebasMasivas(
          this.tipoSeleccionado, 
          this.cantidadFaltante, 
          this.documentoBaseSeleccionado?.id
        ).subscribe(
          (response) => {
            this.procesando = false;
            this.cargarEstadisticasPruebas(); // Recargar estadísticas
            
            if (response.success) {
              this.alertService.success('Proceso completado', response.message);
            } else {
              this.alertService.error(response.message);
            }
          },
          (error) => {
            this.procesando = false;
            this.alertService.error('Error al ejecutar pruebas masivas: ' + error);
          }
        );
      }

}
