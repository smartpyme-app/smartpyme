import { Component, OnInit, ViewChild, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef, } from 'ngx-bootstrap/modal';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent, TabsModule } from 'ngx-bootstrap/tabs';
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective } from 'ngx-mask';
import { FilterPipe } from '@pipes/filter.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-empresa',
    templateUrl: './empresa.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe, TabsModule, NgxMaskDirective],
    
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
    public downloading:boolean = false;
    public filtros:any = {};

    public estadisticasPruebas: any = null;
    public documentosBase: any[] = [];
    public tipoSeleccionado: string = '';
    public cantidadFaltante: number = 1;
    public documentoBaseSeleccionado: any = null;
    public procesando: boolean = false;
    public modalRef!: BsModalRef;
    public procesandoPruebas: boolean = false;
    public correlativoInicial: number | undefined = undefined; 

    @ViewChild('modalTemplate')
    modalTemplate!: TemplateRef<any>;

    public estadoPruebasCompletado: boolean = false;
    public fechaCompletadoPruebas: string = '';

    public showpassword:boolean = false;
    public showpassword2:boolean = false;
    public canales:any = [];

    public customConfig: any = {
        columnas: {
            columna_proyecto: false
        }
    };

    constructor( 
        public apiService: ApiService, public mhService: MHService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {

        this.apiService.getAll('canales').subscribe(canales => { 
            this.canales = canales;
        }, error => {this.alertService.error(error); });

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

            this.initializeCustomConfig();
            this.loading = false;

            //Se cargan las facturas cuando ya se ha inicializado la empresa
            if (this.empresa && this.empresa.fe_ambiente === '00') {
                this.cargarEstadisticasPruebas();
                this.cargarDocumentosBase();
            }
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public onSubmit(): Promise<any> {

        return new Promise((resolve, reject) => {
            this.saving = true;
            this.apiService.store('empresa', this.empresa).subscribe(empresa => {
                this.empresa = empresa;

                this.initializeCustomConfig();

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
        if(this.empresa.pais == 'México'){
            this.empresa.moneda = 'MXN';
            this.empresa.iva = 16;
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
     

    setFile(event: any, type: string = 'logo') {
        const file = event.target.files[0];
        
 
        if (file && file.size > 2 * 1024 * 1024) {
            this.alertService.error('El archivo es demasiado grande. Máximo permitido: 2MB');
            event.target.value = '';
            return;
        }

     
        if (type === 'sello') {
            this.empresa.sello_file = file;
        } else if (type === 'firma') {
            this.empresa.firma_file = file;
        } else {
            this.empresa.file = file;
        }

        let formData: FormData = new FormData();
        formData.append('file', file);
        formData.append('type', type);
        formData.append('id', this.empresa.id);

        this.loading = true;
   
        let endpoint = 'empresa/imagenes';
       

        this.apiService.store(endpoint, formData).subscribe(
            (response: any) => {
                if (type === 'sello') {
                    this.empresa.sello = response.path;
                    this.alertService.success('Sello actualizado', 'Tu sello fue guardado exitosamente.');
                } else if (type === 'firma') {
                    this.empresa.firma = response.path;
                    this.alertService.success('Firma actualizada', 'Tu firma fue guardada exitosamente.');
                }else{
                    this.empresa.logo = response.path;
                    this.alertService.success('Logo actualizado', 'Tu logo fue guardado exitosamente.');
                }
                this.loading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
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
          'notasCredito': 'Notas Crédito',
          'notasDebito': 'Notas Débito',
          'facturasExportacion': 'Exportación',
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

      /** NUEVO: Verificar si se pueden generar notas */
      puedeGenerarNotas(tipo: string): boolean {
        if (!this.estadisticasPruebas) return false;
        
        // Solo permitir generar notas si hay CCF emitidos
        if (tipo === 'notasCredito' || tipo === 'notasDebito') {
            const ccfEmitidos = this.estadisticasPruebas['creditosFiscales']?.emitidas || 0;
            return ccfEmitidos > 0;
        }
        
        return true;
      }

      calcularCantidadFaltante(tipo: string): number {
        if (!this.estadisticasPruebas || !this.estadisticasPruebas[tipo]) {
            return 1;
        }
        
        const { emitidas, requeridas } = this.estadisticasPruebas[tipo];
        
        // Para notas, limitamos la cantidad a los CCF disponibles
        if (tipo === 'notasCredito' || tipo === 'notasDebito') {
            const ccfEmitidos = this.estadisticasPruebas['creditosFiscales']?.emitidas || 0;
            const faltantes = Math.max(0, requeridas - emitidas);
            return Math.min(faltantes, ccfEmitidos - emitidas);
        }
        
        return Math.max(0, requeridas - emitidas);
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
                
                this.documentosBase = data.filter((doc: any) => {
                    if (this.tipoSeleccionado === 'facturas') return doc.tipo_dte === '01';
                    if (this.tipoSeleccionado === 'creditosFiscales') return doc.tipo_dte === '03';
                    if (this.tipoSeleccionado === 'notasCredito') return doc.tipo_dte === '03';
                    if (this.tipoSeleccionado === 'notasDebito') return doc.tipo_dte === '03';
                    if (this.tipoSeleccionado === 'facturasExportacion') return doc.tipo_dte === '11';
                    // if (this.tipoSeleccionado === 'sujetoExcluido') return doc.tipo_dte === '14';
                    
                    return false;
                });
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
        
        // Verificar si se pueden generar notas
        if ((tipo === 'notasCredito' || tipo === 'notasDebito') && !this.puedeGenerarNotas(tipo)) {
            this.alertService.error(
                'Debe tener al menos un Comprobante de Crédito Fiscal emitido para poder generar notas'
            );
            return;
        }
        
        this.cargarDocumentosBase();
    

        // Calcular cantidad considerando limitaciones
        // this.cantidadFaltante = this.calcularCantidadFaltante(tipo);
        
        if (this.cantidadFaltante <= 0) {
            this.alertService.info(
                'Pruebas completadas', 
                `Ya se han completado todas las pruebas requeridas para ${this.getLabelTipo(tipo)}`
            );
            return;
        }
        
        // Mostrar el modal
        this.modalRef = this.modalService.show(template, {
            class: 'modal-md'
        });
    }
      
    // Método para confirmar y ejecutar la emisión
    confirmarEjecucion() {
        this.modalRef.hide();
        this.procesando = true;
        
        // Llamada al servicio para ejecutar las pruebas
        this.mhService.ejecutarPruebasMasivas(
            this.tipoSeleccionado, 
            this.cantidadFaltante, 
            this.documentoBaseSeleccionado?.id,
            this.correlativoInicial || undefined
        ).subscribe(
            (response) => {
            this.procesando = false;
            
            if (response.success) {
                // Mostrar un mensaje más específico cuando se encola el trabajo
                if (response.queued) {
                this.alertService.success(
                    'Proceso iniciado', 
                    'Las pruebas se están ejecutando en segundo plano. Recibirá una notificación por correo electrónico cuando el proceso finalice.'
                );
                } else {
                this.alertService.success('Proceso completado', response.message);
                }
                
                // Refrescar las estadísticas después de un breve retraso
                setTimeout(() => {
                this.cargarEstadisticasPruebas();
                }, 2000);
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
    getMensajeConfirmacion(): string {
        let mensaje = `<div class="alert alert-info mt-2">
            <i class="fa fa-info-circle me-2"></i>
            <strong>Proceso automático:</strong> Además de los ${this.cantidadFaltante} CCF, 
            se generarán automáticamente ${this.cantidadFaltante} Notas de Crédito y 
            ${this.cantidadFaltante} Notas de Débito relacionadas.
        </div>
        `;
        
        mensaje += `Está a punto de emitir <strong>${this.cantidadFaltante}</strong> documentos de tipo <strong>${this.getLabelTipo(this.tipoSeleccionado)}</strong> en el ambiente de pruebas.`;
        
        return mensaje;
    }

    public copyToClipboard(text: string): void {
        const selBox = document.createElement('textarea');
        selBox.style.position = 'fixed';
        selBox.style.left = '0';
        selBox.style.top = '0';
        selBox.style.opacity = '0';
        selBox.value = text;
        document.body.appendChild(selBox);
        selBox.focus();
        selBox.select();
        document.execCommand('copy');
        document.body.removeChild(selBox);
        this.alertService.success('Copiado', 'Texto copiado al portapapeles');
    }

    public saveCredentials(tipo: 'shopify' | 'woocommerce') {
        this.saving = true;
    
        const config = this.getCredentialConfig(tipo);
        
        const missingFields = config.requiredFields.filter(field => 
            !this.empresa[field] || this.empresa[field] === '' || this.empresa[field] === '0' || this.empresa[field] === 0
        );
    
        if (missingFields.length > 0) {
            this.saving = false;
            Swal.fire({
                title: 'Error',
                text: config.validationMessages.requiredFields,
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
            return;
        }
    
        const canalField = tipo === 'shopify' ? 'shopify_canal_id' : 'woocommerce_canal_id';
        if (!this.empresa[canalField] || this.empresa[canalField] == 0 || this.empresa[canalField] == '0') {
            this.saving = false;
            Swal.fire({
                title: 'Error',
                text: 'Selecciona un canal',
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
            return;
        }
    

        const otherPlatform = tipo === 'shopify' ? 'woocommerce' : 'shopify';
        const otherStatusField = `${otherPlatform}_status`;
        
        if (this.empresa[otherStatusField] === 'connected') {
            this.saving = false;
            Swal.fire({
                title: 'Error',
                text: `Ya tienes ${otherPlatform === 'woocommerce' ? 'WooCommerce' : 'Shopify'} conectado. Solo puedes tener una integración activa.`,
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
            return;
        }
    
        const credentials = this.prepareCredentials(tipo);
        
        Swal.fire({
            title: 'Conectando...',
            text: `Verificando conexión con ${config.platformName}`,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        this.apiService.store(config.endpoint, credentials).subscribe(
            response => {
                this.saving = false;
                this.loadAll();
                Swal.close();
                Swal.fire({
                    title: 'Conexión Exitosa',
                    text: `Credenciales guardadas y conexión con ${config.platformName} establecida`,
                    icon: 'success',
                    confirmButtonText: 'Aceptar'
                });
            },
            error => {
                this.saving = false;
                Swal.close();
                this.loadAll();
                Swal.fire({
                    title: 'Error de Conexión',
                    text: error.error && error.error.mensaje ? error.error.mensaje : `No se pudieron guardar las credenciales de ${config.platformName}`,
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        );
    }

    private getCredentialConfig(tipo: 'shopify' | 'woocommerce') {
        const configs = {
            woocommerce: {
                platformName: 'WooCommerce',
                endpoint: 'usuario/save-credentials',
                requiredFields: ['woocommerce_store_url', 'woocommerce_consumer_key', 'woocommerce_consumer_secret'],
                validationMessages: {
                    requiredFields: 'La URL de la tienda, clave de API y clave secreta son requeridos'
                }
            },
            shopify: {
                platformName: 'Shopify',
                endpoint: 'usuario/save-credentials',
                requiredFields: ['shopify_store_url', 'shopify_consumer_secret'],
                validationMessages: {
                    requiredFields: 'La URL de la tienda y la clave secreta son requeridos'
                }
            }
        };
    
        return configs[tipo];
    }
    
    private prepareCredentials(tipo: 'shopify' | 'woocommerce') {
        if (tipo === 'woocommerce') {
            return {
                store_url: this.empresa.woocommerce_store_url,
                tipo: 'woocommerce',
                consumer_key: this.empresa.woocommerce_consumer_key,
                consumer_secret: this.empresa.woocommerce_consumer_secret,
                canal_id: this.empresa.woocommerce_canal_id
            };
        } else {
            return {
                store_url: this.empresa.shopify_store_url,
                tipo: 'shopify',
                consumer_secret: this.empresa.shopify_consumer_secret,
                canal_id: this.empresa.shopify_canal_id
            };
        }
    }
    


    public disconnectWooCommerce() {
        this.saving = true;

        this.empresa.woocommerce_store_url = '';
        this.empresa.woocommerce_consumer_key = '';
        this.empresa.woocommerce_consumer_secret = '';
        
        this.apiService.store('usuario/disconnect-woocommerce', {}).subscribe(
            response => {
                this.saving = false;
                this.loadAll();
                Swal.fire({
                    title: 'Desconexión Exitosa',
                    text: 'Desconectado de WooCommerce',
                    icon: 'success',
                    confirmButtonText: 'Aceptar'
                });

            }, error => {
                this.saving = false;
                this.loadAll();
                Swal.fire({
                    title: 'Error de Conexión',
                    text: error.error && error.error.mensaje ? error.error.mensaje : 'No se pudo desconectar de WooCommerce',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        );


    }

    public disconnectShopify() {
        this.saving = true;

        this.empresa.shopify_store_url = '';
        this.empresa.shopify_consumer_secret = '';
        
        this.apiService.store('usuario/disconnect-shopify', {}).subscribe(
            response => {
                this.saving = false;
                this.loadAll();
                Swal.fire({
                    title: 'Desconexión Exitosa',
                    text: 'Desconectado de Shopify',
                    icon: 'success',
                    confirmButtonText: 'Aceptar'
                });

            }, error => {
                this.saving = false;
                this.loadAll();
                Swal.fire({
                    title: 'Error de Conexión',
                    text: error.error && error.error.mensaje ? error.error.mensaje : 'No se pudo desconectar de WooCommerce',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        );


    }

    public exportarWooCommerce(){
        Swal.fire({
            title: '¿Está seguro de exportar sus productos a WooCommerce?',
            html: `
                <p>Esta acción iniciará una migración asincrónica de productos a WooCommerce:</p>
                <ul style="text-align: left; margin-top: 1em;">
                    <li>Solo se migrarán los productos relacionados con su usuario y sucursal actual</li>
                    <li>Los productos vinculados a otras sucursales no serán exportados</li>
                    <li>El proceso se ejecutará en segundo plano y puede tomar varios minutos</li>
                    <li>Esta acción no se puede revertir</li>
                </ul>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, iniciar exportación',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Iniciando exportación...',
                    text: 'La migración de productos ha comenzado y continuará en segundo plano',
                    icon: 'info',
                    showConfirmButton: false,
                    timer: 3000
                });

                this.apiService.store('producto/exportar-woocommerce', {}).subscribe(
                    response => {
                        Swal.fire({
                            title: 'Proceso iniciado',
                            text: 'La migración de productos a WooCommerce se está ejecutando en segundo plano',
                            icon: 'success'
                        });
                    },
                    error => {
                        this.alertService.error(error);
                    }
                );
            }
        });
    }

    public exportarShopify(){
        Swal.fire({
            title: '¿Está seguro de exportar sus productos a Shopify?',
            html: `
                <p>Esta acción iniciará una migración asincrónica de productos a Shopify:</p>
                <ul style="text-align: left; margin-top: 1em;">
                    <li>Solo se migrarán los productos relacionados con su usuario y sucursal actual</li>
                    <li>Los productos vinculados a otras sucursales no serán exportados</li>
                    <li>El proceso se ejecutará en segundo plano y puede tomar varios minutos</li>
                    <li>Esta acción no se puede revertir</li>
                </ul>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, iniciar exportación',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Iniciando exportación...',
                    text: 'La migración de productos ha comenzado y continuará en segundo plano',
                    icon: 'info',
                    showConfirmButton: false,
                    timer: 3000
                });

                this.apiService.store('producto/exportar-shopify', {}).subscribe(
                    response => {
                        Swal.fire({
                            title: 'Proceso iniciado',
                            text: 'La migración de productos a Shopify se está ejecutando en segundo plano',
                            icon: 'success'
                        });
                    },
                    error => {
                        this.alertService.error(error);
                    }
                );
            }
        });
    }
    //descargarWooCommerce
    public descargarWooCommerce() {
        console.log('descargarWooCommerce');
        this.downloading = true;

        Swal.fire({
            title: 'Exportando productos a WooCommerce',
            text: 'Estamos preparando el archivo CSV con los productos de WooCommerce',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        this.apiService.export('productos/exportar/woocommerce', this.filtros).subscribe(
            (data: Blob) => {
                Swal.close();

                Swal.fire({
                    title: 'Exportando productos a WooCommerce',
                    text: 'El archivo CSV está listo para descargar',
                    icon: 'success',
                    showConfirmButton: true
                });
                const blob = new Blob([data], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = 'productos_woocommerce_' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                
                this.alertService.success('Exportación completada', 'El archivo CSV ha sido generado correctamente.');
            },
            (error) => { 
                this.alertService.error('Error en la exportación: ' + error); 
                this.downloading = false; 
            }
        );
    }

    public descargarShopify() {
        console.log('descargarShopify');
        this.downloading = true;

        Swal.fire({
            title: 'Exportando productos a Shopify',
            text: 'Estamos preparando el archivo CSV con los productos de Shopify',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        this.apiService.export('productos/exportar/shopify', this.filtros).subscribe(
            (data: Blob) => {
                Swal.close();

                Swal.fire({
                    title: 'Exportando productos a Shopify',
                    text: 'El archivo CSV está listo para descargar',
                    icon: 'success',
                    showConfirmButton: true
                });
                const blob = new Blob([data], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = 'productos_shopify_' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                
                this.alertService.success('Exportación completada', 'El archivo CSV ha sido generado correctamente.');
            },
            (error) => { 
                this.alertService.error('Error en la exportación: ' + error); 
                this.downloading = false; 
            }
        );
    }

    public confirmarImportacionShopify() {
        Swal.fire({
            title: '¿Estás seguro de continuar?',
            html: `
                <p>Importarás tu inventario completo de Shopify en tu cuenta de SmartPyme, escribe <strong>"confirmar"</strong> en el campo de abajo para confirmar:</p>
                <input type="text" id="confirmacionInput" class="swal2-input" placeholder="confirmar">
                <br><br>
                <p style="font-size: 14px; color: #666; margin-top: 10px;">
                    <strong>Nota:</strong> Los productos activos se importarán como activos, y los productos en borrador se importarán como inactivos.
                </p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, importar productos',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const confirmacionInput = document.getElementById('confirmacionInput') as HTMLInputElement;
                const valor = confirmacionInput.value.toLowerCase().trim();

                if (valor !== 'confirmar') {
                    Swal.showValidationMessage('Debes escribir exactamente "confirmar" para continuar');
                    return false;
                }
                return true;
            },
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                this.importarProductosDesdeShopify();
            }
        });
    }

    public importarProductosDesdeShopify() {
        Swal.fire({
            title: 'Importando productos...',
            text: 'Estamos importando los productos desde Shopify',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Preparar los datos de la empresa para enviar al backend
        const datosEmpresa = {
            shopify_store_url: this.empresa.shopify_store_url,
            shopify_consumer_secret: this.empresa.shopify_consumer_secret,
            id_empresa: this.empresa.id,
            id_usuario: this.apiService.auth_user().id,
            id_sucursal: this.apiService.auth_user().id_sucursal,
            incluir_drafts: true // Siempre incluir productos draft como inactivos
        };

        // Usar timeout más corto ya que la respuesta es inmediata
        this.apiService.storeWithTimeout('producto/importar-shopify', datosEmpresa, 30000).subscribe(
            response => {
                Swal.close();

                if (response.procesando) {
                    // Respuesta de procesamiento en segundo plano
                    Swal.fire({
                        title: 'Procesamiento Iniciado',
                        html: `
                            <p><strong>¡Procesamiento iniciado exitosamente!</strong></p>
                            <p>Total productos a procesar: <strong>${response.total_productos_shopify || 0}</strong></p>
                            <br>
                            <p>Los productos se están procesando en segundo plano.</p>
                            <p>Esto puede tomar varios minutos dependiendo de la cantidad de productos.</p>
                            <br>
                            <p><strong>Puedes cerrar esta ventana y continuar trabajando.</strong></p>
                            <p>Los productos aparecerán en tu inventario una vez completado el procesamiento.</p>
                        `,
                        icon: 'info',
                        confirmButtonText: 'Entendido',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    });

                    console.log('=== PROCESAMIENTO INICIADO ===');
                    console.log('Total productos en Shopify:', response.total_productos_shopify);
                    console.log('Estado:', response.estado);
                } else {
                    // Respuesta de importación completada (modo síncrono)
                    Swal.fire({
                        title: 'Procesando importación de productos',
                        text: 'Los productos están siendo procesados en segundo plano.',
                        icon: 'info',
                        confirmButtonText: 'Aceptar'
                    });

                    console.log('=== IMPORTACIÓN DESDE SHOPIFY COMPLETADA ===');
                    console.log('Total productos en Shopify:', response.total_productos_shopify);
                    console.log('Productos importados:', response.productos_importados);
                }
            },
                error => {
                    Swal.close();
                    
                    // Manejar diferentes tipos de errores
                    let errorMessage = 'Error al importar productos desde Shopify: ';
                    
                    if (error.status === 0) {
                        errorMessage += 'Error de conexión o timeout. ';
                        errorMessage += 'El procesamiento puede haberse completado en el servidor. ';
                        errorMessage += 'Verifica los logs del sistema o intenta nuevamente en unos minutos.';
                    } else if (error.error?.codigo_error === 'IMPORTACION_YA_REALIZADA') {
                        // Error específico: Ya se realizó una importación
                        Swal.fire({
                            title: 'Importación Ya Realizada',
                            html: `
                                <p><strong>Ya se realizó una importación exitosa de productos desde Shopify.</strong></p>
                                <p>Para evitar duplicados, no se puede volver a importar.</p>
                                <br>
                                <p>Si necesitas re-importar los productos, contacta al administrador del sistema.</p>
                            `,
                            icon: 'warning',
                            confirmButtonText: 'Entendido',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        });
                        return;
                    } else if (error.error?.mensaje) {
                        errorMessage += error.error.mensaje;
                    } else if (error.message) {
                        errorMessage += error.message;
                    } else {
                        errorMessage += 'Error desconocido. Verifica los logs del sistema.';
                    }
                    
                    this.alertService.error(errorMessage);
                    
                    // Log del error para debugging
                    console.error('Error en importación Shopify:', error);
                    console.log('Status del error:', error.status);
                    console.log('Error completo:', error);
                }
        );
    }


    private initializeCustomConfig() {
        // Estructura por defecto
        const defaultConfig = {
            columnas: {
                columna_proyecto: false
            },
            modulos: {},
            configuraciones: {
                ticket_en_pdf: false
            },
            campos_personalizados: {}
        };
    
        if (this.empresa.custom_empresa) {
            // Hacer deep merge de la configuración existente con los valores por defecto
            this.customConfig = this.deepMerge(defaultConfig, this.empresa.custom_empresa);
            
            // Convertir arrays a objetos si es necesario
            if (Array.isArray(this.customConfig.configuraciones)) {
                this.customConfig.configuraciones = defaultConfig.configuraciones;
            }
            if (Array.isArray(this.customConfig.modulos)) {
                this.customConfig.modulos = defaultConfig.modulos;
            }
            if (Array.isArray(this.customConfig.campos_personalizados)) {
                this.customConfig.campos_personalizados = defaultConfig.campos_personalizados;
            }
        } else {
            this.customConfig = defaultConfig;
        }
    }
    
    private deepMerge(target: any, source: any): any {
        const result = { ...target };
        
        for (const key in source) {
            if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                result[key] = this.deepMerge(result[key] || {}, source[key]);
            } else {
                result[key] = source[key];
            }
        }
        
        return result;
    }

    public updateColumnConfig(columnName: string, enabled: boolean) {
        this.customConfig.columnas[columnName] = enabled;
        this.empresa.custom_empresa = this.customConfig;
        
        // Guardar automáticamente
        this.onSubmit().then(() => {
            this.alertService.success(
                'Configuración actualizada', 
                `Columna ${columnName} ${enabled ? 'habilitada' : 'deshabilitada'} correctamente`
            );
        });
    }

    public isColumnEnabled(columnName: string): boolean {
        return this.customConfig.columnas && this.customConfig.columnas[columnName] === true;
    }

    public toggleColumn(columnName: string) {
        const currentValue = this.isColumnEnabled(columnName);
        this.updateColumnConfig(columnName, !currentValue);
    }

    // Método para agregar nuevas configuraciones dinámicamente
    public addCustomConfig(section: string, key: string, value: any) {
        if (!this.customConfig[section]) {
            this.customConfig[section] = {};
        }
        this.customConfig[section][key] = value;
        this.empresa.custom_empresa = this.customConfig;
    }

    // Método para obtener configuración específica
    public getCustomConfig(section: string, key?: string, defaultValue?: any) {
        if (!this.customConfig[section] || Array.isArray(this.customConfig[section])) {
            return defaultValue;
        }
        
        if (key) {
            return this.customConfig[section][key] !== undefined ? this.customConfig[section][key] : defaultValue;
        }
        
        return this.customConfig[section];
    }

    // Método para verificar si ticket en PDF está habilitado
    public isTicketEnPdfEnabled(): boolean {
        return this.getCustomConfig('configuraciones', 'ticket_en_pdf', false);
    }

    // Método para cambiar la configuración de ticket en PDF
    public updateTicketEnPdf(enabled: boolean) {
        this.addCustomConfig('configuraciones', 'ticket_en_pdf', enabled);
        
        // Guardar automáticamente
        this.onSubmit().then(() => {
            this.alertService.success(
                'Configuración actualizada', 
                `Ticket en PDF ${enabled ? 'habilitado' : 'deshabilitado'} correctamente`
            );
        });
    }

    // Método para alternar ticket en PDF
    public toggleTicketEnPdf() {
        const currentValue = this.isTicketEnPdfEnabled();
        this.updateTicketEnPdf(!currentValue);
    }

    setCamposRenta(){
        this.onSubmit().then(() => {
            this.mhService.auth().subscribe(response => {

                this.apiService.getAll('set-campos-nuevos').subscribe((usuario) => {
                    this.alertService.success(
                      'Usuario guardado',
                      'El usuario fue guardado exitosamente.'
                    );
                  }, (error) => {this.alertService.error(error); this.saving = false; }
                );
                
            },error => {this.alertService.error(error); this.cheking = false; });
        });
    }

}
