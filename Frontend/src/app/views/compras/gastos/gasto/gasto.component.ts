import { Component, OnInit,TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { CrearProveedorComponent } from '@shared/modals/crear-proveedor/crear-proveedor.component';
import { CrearProyectoComponent } from '@shared/modals/crear-proyecto/crear-proyecto.component';
import { CrearAreaEmpresaComponent } from '@shared/modals/crear-area-empresa/crear-area-empresa.component';
import { CrearImpuestoComponent } from '@shared/modals/crear-impuesto/crear-impuesto.component';
import { CrearDepartamentoComponent } from '@shared/modals/crear-departamento-empresa/crear-departamento-empresa.component';
import { CrearAbonoGastoComponent } from '@shared/modals/crear-abono-gasto/crear-abono-gasto.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-gasto',
    templateUrl: './gasto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, CrearProveedorComponent, CrearProyectoComponent, CrearAreaEmpresaComponent, CrearImpuestoComponent, CrearDepartamentoComponent, CrearAbonoGastoComponent, LazyImageDirective],
    
})
export class GastoComponent implements OnInit {
  public gasto: any = {iva: 0, renta_retenida: 0, iva_percibido: 0, otros_impuestos: 0};
  public categorias: any = [];
  public proyectos: any = [];
  public proveedores: any = [];
  public usuarios: any = [];

  public sucursales: any = [];
  public bancos: any = [];
  public formaspago: any = [];
  public duplicargasto = false;
  public loading = false;
  public saving = false;
  public documentos: any = [];
  public impuestos: any = [];
  public mostrar_otros_impuestos = false;
  public impuestos_seleccionados: any[] = [];

  public jsonContent: string = '';
  public processingJson: boolean = false;

  public opAvanzadas: boolean = false;
  public otrosImpuestos: boolean = false;
  public areasDisponibles: any[] = [];
  public loadingAreas: boolean = false;
  public departamentos: any[] = [];

  modalRef?: BsModalRef;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private modalService: BsModalService
  ) {}

  public openAbono(template: TemplateRef<any>, gasto:any){
    this.gasto = gasto;
    this.modalRef = this.modalService.show(template);
  }

  public setEstado(abono: any){
    this.apiService.store('gasto/abono', abono).subscribe(abono => {
      this.loadAll();
    }, error => {this.alertService.error(error); });
  }

	ngOnInit(){
        this.loadAll();
        this.loadDepartamentos();


    this.mostrar_otros_impuestos = false;
    this.impuestos_seleccionados = [];

    this.apiService.getAll('sucursales/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('usuarios/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (usuarios) => {
        this.usuarios = usuarios;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('bancos/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (bancos) => {
        this.bancos = bancos;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('formas-de-pago/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (formaspago) => {
        this.formaspago = formaspago;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('gastos/categorias/list')
      .pipe(this.untilDestroyed())
      .subscribe(categorias => {
      this.categorias = categorias;
      this.loading = false;
    }, error => {this.alertService.error(error); this.loading = false;});

    this.apiService.getAll('proveedores/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (proveedores) => {
        this.proveedores = proveedores;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );

    this.apiService.getAll('proyectos/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (proyectos) => {
        this.proyectos = proyectos;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );


    this.apiService.getAll('impuestos')
      .pipe(this.untilDestroyed())
      .subscribe(impuestos => {
      this.impuestos = impuestos;
      this.loading = false;

      if (this.gasto && this.gasto.otros_impuestos && this.gasto.otros_impuestos.length > 0) {
          this.cargarImpuestosSeleccionados();
          this.mostrar_otros_impuestos = true;
      }
    }, error => {this.alertService.error(error); this.loading = false;});

  }

  public loadAll() {
    const id = +this.route.snapshot.paramMap.get('id')!;
    if (id) {
      this.loading = true;
      this.apiService.read('gasto/', id)
        .pipe(this.untilDestroyed())
        .subscribe(
        (gasto) => {
          this.gasto = gasto;

          // Inicializar el campo credito basándose en el estado
          // credito = false significa pendiente (toggle apagado)
          // credito = true significa no pendiente (toggle encendido)
          if (this.gasto.estado === 'Pendiente') {
            this.gasto.credito = false;
          } else {
            this.gasto.credito = true;
          }

          // console.log('Gasto completo:', this.gasto);
          // console.log('otros_impuestos raw:', this.gasto.otros_impuestos);
          // console.log('Tipo de otros_impuestos:', typeof this.gasto.otros_impuestos);


          if (this.gasto.iva > 0) this.gasto.impuesto = true;

          if (this.gasto.iva_percibido > 0) this.gasto.percepcion = true;

          if (this.gasto.renta_retenida > 0)
            this.gasto.renta = true;

          if(!this.gasto.area_empresa)
            this.gasto.area_empresa = '';

          if (!this.gasto.id_area_empresa) {
            this.gasto.id_area_empresa = '';
          }

          // Cargar áreas si existe id_departamento
          if (this.gasto.id_departamento) {
            this.loadAreasPorDepartamento(this.gasto.id_departamento);
          }

          if (this.gasto.otros_impuestos) {
            if (typeof this.gasto.otros_impuestos === 'object' &&
                this.gasto.otros_impuestos.seleccionados) {

                const valoresGuardados = this.gasto.otros_impuestos.valores || [];
                this.gasto.otros_impuestos = this.gasto.otros_impuestos.seleccionados;
                this.gasto.impuestos_valores = valoresGuardados;
            }

            // Activar switch SIEMPRE que hay otros_impuestos (aunque sea array simple)
            if (this.gasto.otros_impuestos && this.gasto.otros_impuestos.length > 0) {
              this.mostrar_otros_impuestos = true;
              // console.log('Switch activado para:', this.gasto.otros_impuestos);
            }
          }

          if (Array.isArray(this.gasto.otros_impuestos) && this.gasto.otros_impuestos.length > 0) {
            this.mostrar_otros_impuestos = true;
            // console.log('Switch activado:', this.mostrar_otros_impuestos);
            // console.log('otros_impuestos:', this.gasto.otros_impuestos);
            // console.log('impuestos_valores:', this.gasto.impuestos_valores);
            // console.log('impuestos disponibles:', this.impuestos.length);
          }

          if (this.tieneOtrosImpuestos(this.gasto.otros_impuestos)) {
              this.mostrar_otros_impuestos = true;

              if (this.impuestos && this.impuestos.length > 0) {
                  this.cargarImpuestosSeleccionados();
              }
          }

          if (!this.gasto.area_empresa) {
            this.gasto.area_empresa = '';
          }

          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
    } else {
      this.gasto = {};
      this.gasto.forma_pago = 'Efectivo';
      this.gasto.estado = 'Confirmado';
      this.gasto.credito = false; // Por defecto no está pendiente
      this.gasto.tipo_documento = 'Factura';
      this.gasto.detalle_banco = '';
      this.gasto.tipo = '';
      this.gasto.tipo_clasificacion = 'Gasto';
      this.gasto.tipo_operacion = 'Gravada';
      this.gasto.tipo_costo_gasto = 'Gastos de venta sin donación';
      this.gasto.tipo_sector = this.apiService.auth_user().empresa.tipo_sector ?? null;
      this.gasto.id_categoria = '';
      this.gasto.id_proveedor = '';
      // this.gasto.fecha_pago = this.apiService.date();
      this.gasto.fecha = this.apiService.date();
      this.gasto.id_empresa = this.apiService.auth_user().id_empresa;
      this.gasto.id_sucursal = this.apiService.auth_user().id_sucursal;
      this.gasto.id_usuario = this.apiService.auth_user().id;
      this.gasto.otros_impuestos = [];
      this.gasto.impuestos_valores = [];
      this.gasto.area_empresa = '';
      this.gasto.id_area_empresa = '';
      this.gasto.es_retaceo = false;

      if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
        this.gasto.id_proyecto =
          +this.route.snapshot.queryParamMap.get('id_proyecto')!;
      }
      this.setTotal();
    }

    // Duplicar gasto

    if (
      this.route.snapshot.queryParamMap.get('recurrente')! &&
      this.route.snapshot.queryParamMap.get('id_gasto')!
    ) {
      this.duplicargasto = true;
      this.apiService
        .read('gasto/', +this.route.snapshot.queryParamMap.get('id_gasto')!)
        .pipe(this.untilDestroyed())
        .subscribe(
          (gasto) => {
            this.gasto = gasto;
            this.gasto.fecha = this.apiService.date();
            this.gasto.id = null;

            if (this.gasto.id_departamento) {
              this.gasto.id_departamento = this.gasto.id_departamento.toString();
            }
            if (this.gasto.id_area_empresa) {
              this.gasto.id_area_empresa = this.gasto.id_area_empresa.toString();
            }

            // Cargar áreas para gasto duplicado
            if (this.gasto.id_departamento) {
              this.loadAreasPorDepartamento(this.gasto.id_departamento);
            }

            if(this.gasto.otros_impuestos) {
              this.mostrar_otros_impuestos = true;
              this.cargarImpuestosSeleccionados();
            }
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
    }

    this.cargarDocumentos();
  }

  private cargarImpuestosSeleccionados() {
    if (!Array.isArray(this.gasto.otros_impuestos)) {
        if (this.gasto.otros_impuestos !== null && this.gasto.otros_impuestos !== undefined && this.gasto.otros_impuestos !== false) {
            this.gasto.otros_impuestos = [this.gasto.otros_impuestos];
        } else {
            this.gasto.otros_impuestos = [];
        }
    }

    this.impuestos_seleccionados = [];

    this.gasto.otros_impuestos.forEach((impuestoId: number) => {
        const impuesto = this.impuestos.find((imp: any) => imp.id === impuestoId);
        if (impuesto) {
            this.impuestos_seleccionados.push(impuesto);
        }
    });

    // Si no hay impuestos_valores, crearlos
    if (!this.gasto.impuestos_valores || this.gasto.impuestos_valores.length === 0) {
        this.gasto.impuestos_valores = [];
        this.impuestos_seleccionados.forEach(impuesto => {
            const subtotal = parseFloat(this.gasto.sub_total) || 0;
            const valor = (subtotal * (impuesto.porcentaje / 100)).toFixed(2);

            this.gasto.impuestos_valores.push({
                id_impuesto: impuesto.id,
                nombre: impuesto.nombre,
                porcentaje: impuesto.porcentaje,
                valor: valor
            });
        });
    }

    if (!this.gasto.impuestos_valores || this.gasto.impuestos_valores.length === 0) {
      this.calcularValoresImpuestos();
    }
      // this.calcularValoresImpuestos();
    }

    private calcularValoresImpuestos() {
      if (!this.gasto.impuestos_valores) {
          this.gasto.impuestos_valores = [];
      }

      this.gasto.impuestos_valores = [];

      this.impuestos_seleccionados.forEach(impuesto => {
          const subtotal = parseFloat(this.gasto.sub_total) || 0;
          const valor = (subtotal * (impuesto.porcentaje / 100)).toFixed(2);

          this.gasto.impuestos_valores.push({
              id_impuesto: impuesto.id,
              nombre: impuesto.nombre,
              porcentaje: impuesto.porcentaje,
              valor: valor
          });
      });
    }

  toggleDiv(): void { this.opAvanzadas = !this.opAvanzadas;}

  public cargarDocumentos() {
    this.apiService.getAll('documentos/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (documentos) => {
        this.documentos = documentos;
        this.documentos = this.documentos.filter(
          (x: any) => x.id_sucursal == this.gasto.id_sucursal
        );
        this.documentos = this.documentos.filter(
          (x: any) =>
            x.nombre != 'Cotización' &&
            x.nombre != 'Orden de compra' &&
            x.nombre != 'Nota de crédito'
        );
        if (!this.gasto.tipo_documento) this.gasto.tipo_documento = 'Factura';
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public setCategoria(categoria: any) {
    this.categorias.push(categoria);
    this.gasto.id_categoria = categoria.id;
  }

  public setProveedor(proveedor: any) {
    this.proveedores.push(proveedor);
    this.gasto.id_proveedor = proveedor.id;
  }

  // Proyecto
  public setProyecto(proyecto: any) {
    if (!this.gasto.id_proyecto) {
      this.proyectos.push(proyecto);
    }
    this.gasto.id_proyecto = proyecto.id;
  }

  public setFechaPago() {
    if (this.gasto.condicion == 'Contado') {
      this.gasto.estado = 'Pagado';
      this.gasto.fecha_pago = moment().format('YYYY-MM-DD');
    } else {
      this.gasto.estado = 'Pendiente';
      this.gasto.fecha_pago = moment()
        .add(this.gasto.condicion.split(' ')[0], 'days')
        .format('YYYY-MM-DD');
    }
  }

  public setCredito() {
    if (this.gasto.credito) {
      this.gasto.estado = 'Pendiente';
    } else {
      this.gasto.estado = 'Confirmado';
    }
  }

  public setPendiente(event: any) {
    // El toggle representa "Pendiente": cuando está encendido (true), está pendiente
    // Cuando está apagado (false), no está pendiente (ya pagado)
    const estaPendiente = event.target.checked;
    
    if (estaPendiente) {
      // Si está pendiente (toggle encendido), deshabilitar fecha de pago y limpiar el valor
      this.gasto.estado = 'Pendiente';
      this.gasto.credito = false;
      this.gasto.fecha_pago = null;
    } else {
      // Si no está pendiente (toggle apagado), habilitar fecha de pago y establecer fecha actual si no tiene
      this.gasto.estado = 'Confirmado';
      this.gasto.credito = true;
      if (!this.gasto.fecha_pago) {
        this.gasto.fecha_pago = moment().format('YYYY-MM-DD');
      }
    }
  }


  public setTotal(){
    const subtotal = parseFloat(this.gasto.sub_total) || 0;
    let total = subtotal;

    if(this.gasto.impuesto){
        const ivaRate = this.apiService.auth_user().empresa.iva / 100;
        const ivaValue = subtotal * ivaRate;
        this.gasto.iva = ivaValue.toFixed(2);
        total += ivaValue;
    } else {
        this.gasto.iva = 0;
    }

    if(this.gasto.renta) {
        this.gasto.renta_retenida = (subtotal * 0.10).toFixed(2);
        total -= parseFloat(this.gasto.renta_retenida);
    } else {
        this.gasto.renta_retenida = 0;
    }

    if(this.gasto.percepcion) {
        this.gasto.iva_percibido = (subtotal * 0.01).toFixed(2);
        total += parseFloat(this.gasto.iva_percibido);
    } else {
        this.gasto.iva_percibido = 0;
    }

    // USAR calcularTotalConImpuestosEditados en lugar de calcularValoresImpuestos
    if (this.mostrar_otros_impuestos && Array.isArray(this.gasto.otros_impuestos) && this.gasto.otros_impuestos.length > 0) {
        // Sumar valores existentes sin recalcular
        this.gasto.impuestos_valores.forEach((impValue: any) => {
            total += parseFloat(impValue.valor) || 0;
        });
    }

    this.gasto.total = total.toFixed(2);

    // Asignar tipoOperacion según los detalles
        if (this.gasto.impuesto) {
          this.gasto.tipo_operacion = 'Gravada'; // Aplica IVA
        } else {
          this.gasto.tipo_operacion = 'No Gravada'; // No aplica IVA
        }
}

  public setSubTotal(){
    if(this.gasto.impuesto){
        this.gasto.sub_total = (parseFloat(this.gasto.total) / (1 + (this.apiService.auth_user().empresa.iva / 100))).toFixed(2);
        this.gasto.iva = (parseFloat(this.gasto.total) - parseFloat(this.gasto.sub_total)).toFixed(2);
    }else{
        this.gasto.iva = 0;
        this.gasto.sub_total = this.gasto.total;
    }

    this.setTotal();
  }

  otros_impuestos: boolean = false;
  otros_impuestos_val: number = 0;

  setImpuesto(impuesto: string){

    switch (impuesto){
      case 'iva':
        if(this.gasto.iva == 0){
          this.gasto.iva = Number((this.gasto.sub_total * 0.13).toFixed(2));
          this.gasto.total += this.gasto.iva;
        }else{ this.gasto.total -= this.gasto.iva; this.gasto.iva = 0;}
        break;
      case 'renta':
        if(this.gasto.renta_retenida == 0){
          this.gasto.renta_retenida = Number((this.gasto.sub_total * 0.10).toFixed(2));
          this.gasto.total -= this.gasto.renta_retenida;
        }else{ this.gasto.total += this.gasto.renta_retenida; this.gasto.renta_retenida = 0;}
        break;
      case 'percepcion':
        if(this.gasto.iva_percibido == 0){
          this.gasto.iva_percibido = Number((this.gasto.sub_total * 0.01).toFixed(2));
          this.gasto.total += this.gasto.iva_percibido;
        }else{ this.gasto.total -= this.gasto.iva_percibido; this.gasto.iva_percibido = 0;}
        break;
      case 'otros':
        if(this.otros_impuestos == false){
          this.otros_impuestos_val = this.gasto.otros_impuestos;
          this.gasto.total += this.gasto.otros_impuestos;
          this.otros_impuestos = true;
        }else{
          this.gasto.total -= this.otros_impuestos_val;
          this.gasto.total += this.gasto.otros_impuestos;
          this.otros_impuestos_val = this.gasto.otros_impuestos;
        }

        break;

      default:
        break;
    }

  }

  public selectTipoDocumento() {
    if (this.gasto.tipo_documento == 'Sujeto excluido') {
      let documento = this.documentos.find(
        (x: any) => x.nombre == this.gasto.tipo_documento
      );
      // console.log(documento);
      this.gasto.referencia = documento.correlativo;
    }
  }

  public onSubmit() {
    this.saving = true;

    if (this.duplicargasto) {
      this.gasto.recurrente = false;
    }

    if (this.mostrar_otros_impuestos &&
      Array.isArray(this.gasto.otros_impuestos) &&
      this.gasto.otros_impuestos.length > 0) {

      const datosImpuestos = {
          seleccionados: this.gasto.otros_impuestos,
          valores: this.gasto.impuestos_valores
      };

      this.gasto.otros_impuestos = datosImpuestos;
    } else if (!this.mostrar_otros_impuestos) {
        this.gasto.otros_impuestos = [];
    }

    this.apiService.store('gasto', this.gasto)
      .pipe(this.untilDestroyed())
      .subscribe(
      (gasto) => {
        if (!this.gasto.id) {
          this.alertService.success(
            'Gasto guardado',
            'El gasto fue guardado exitosamente.'
          );
        } else {
          this.alertService.success(
            'Gasto creado',
            'El gasto fue añadido exitosamente.'
          );
        }
        this.router.navigate(['/gastos']);
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  public setOtrosImpuestos() {

    if (this.mostrar_otros_impuestos) {

        if (!Array.isArray(this.gasto.otros_impuestos)) {
            this.gasto.otros_impuestos = [];
        }

        if (!this.gasto.impuestos_valores) {
            this.gasto.impuestos_valores = [];
        }
    } else {
        this.gasto.otros_impuestos = [];
        this.impuestos_seleccionados = [];
        if (this.gasto.impuestos_valores) {
            this.gasto.impuestos_valores = [];
        }
    }

    this.setTotal();
  }

  public onImpuestosChange() {
      if (!Array.isArray(this.gasto.otros_impuestos)) {
          this.gasto.otros_impuestos = [];
      }

      this.impuestos_seleccionados = [];

      if (this.gasto.otros_impuestos && this.gasto.otros_impuestos.length > 0) {
          this.gasto.otros_impuestos.forEach((impuestoId: number) => {
              const impuesto = this.impuestos.find((imp: any) => imp.id === impuestoId);
              if (impuesto) {
                  this.impuestos_seleccionados.push(impuesto);
              }
          });

          // Solo agregar valores para impuestos nuevos, no recalcular existentes
          this.agregarValoresImpuestosNuevos();
      } else {
          this.gasto.impuestos_valores = [];
      }

      this.setTotal();
  }

  private agregarValoresImpuestosNuevos() {
      if (!this.gasto.impuestos_valores) {
          this.gasto.impuestos_valores = [];
      }

      this.impuestos_seleccionados.forEach(impuesto => {
          // Solo agregar si no existe ya
          const existe = this.gasto.impuestos_valores.find((iv: any) => iv.id_impuesto === impuesto.id);

          if (!existe) {
              const subtotal = parseFloat(this.gasto.sub_total) || 0;
              const valor = (subtotal * (impuesto.porcentaje / 100)).toFixed(2);

              this.gasto.impuestos_valores.push({
                  id_impuesto: impuesto.id,
                  nombre: impuesto.nombre,
                  porcentaje: impuesto.porcentaje,
                  valor: valor
              });
          }
      });

      // Eliminar valores de impuestos deseleccionados
      this.gasto.impuestos_valores = this.gasto.impuestos_valores.filter((iv: any) =>
          this.gasto.otros_impuestos.includes(iv.id_impuesto)
      );
  }

  // public setImpuesto(impuesto:any) {
  //   this.impuestos.push(impuesto);
  //
  //   if (!Array.isArray(this.gasto.otros_impuestos)) {
  //       this.gasto.otros_impuestos = [];
  //   }
  //
  //   this.gasto.otros_impuestos.push(impuesto.id);
  //
  //   this.impuestos_seleccionados.push(impuesto);
  //
  //   this.setTotal();
  // }

  private tieneOtrosImpuestos(otrosImpuestos: any): boolean {
    if (!otrosImpuestos) return false;

    if (Array.isArray(otrosImpuestos)) {
        return otrosImpuestos.length > 0;
    }

    return otrosImpuestos !== false && otrosImpuestos !== null && otrosImpuestos !== undefined;
  }

  openJsonImport(template: TemplateRef<any>) {
    this.jsonContent = '';
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  /**
   * Maneja la selección de archivo JSON
   */
  handleFileInput(event: any) {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e: any) => {
        this.jsonContent = e.target.result;
      };
      reader.readAsText(file);
    }
  }

  processJsonData() {
    this.processingJson = true;

    try {
      // Parsear el JSON
      const jsonData = JSON.parse(this.jsonContent);

      // Mapear los datos del JSON al modelo de Gasto
      this.mapJsonToGasto(jsonData);

      // Cerrar el modal y mostrar mensaje de éxito
      this.modalRef?.hide();
      this.alertService.success(
        'Datos importados',
        'Los datos del JSON han sido importados exitosamente.'
      );
    } catch (error) {
      this.alertService.error('Error al procesar el JSON: ' + error);
    } finally {
      this.processingJson = false;
    }
  }

  /**
   * Mapea los datos del JSON al modelo de Gasto con mejor soporte para DTE
   */
  mapJsonToGasto(jsonData: any) {
    try {
      // Inicializar valores por defecto si no existe el gasto
      if (!this.gasto.id) {
        this.gasto.forma_pago = 'Efectivo';
        this.gasto.estado = 'Confirmado';
        this.gasto.tipo_documento = 'Factura';
        this.gasto.detalle_banco = '';
        this.gasto.tipo = 'Gastos varios'; // Categoría predeterminada
        this.gasto.id_categoria = '';
        this.gasto.id_proveedor = '';
        this.gasto.fecha = this.apiService.date();
        this.gasto.id_empresa = this.apiService.auth_user().id_empresa;
        this.gasto.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.gasto.id_usuario = this.apiService.auth_user().id;
      }

      // Mapear datos de identificación
      if (jsonData.identificacion) {
        // Fecha
        if (jsonData.identificacion.fecEmi) {
          this.gasto.fecha = jsonData.identificacion.fecEmi;
        }

        // Referencia
        if (jsonData.identificacion.numeroControl) {
          this.gasto.referencia = jsonData.identificacion.numeroControl
            .split('-')
            .pop();
        }

        // Tipo de documento
        if (jsonData.identificacion.tipoDte) {
          const tiposDte: { [key: string]: string } = {
            '01': 'Factura',
            '03': 'Crédito fiscal',
            '05': 'Nota de débito',
            '06': 'Nota de crédito',
            '07': 'Comprobante de retención',
            '11': 'Factura de exportación',
            '14': 'Sujeto excluido',
          };

          this.gasto.tipo_documento =
            tiposDte[jsonData.identificacion.tipoDte] || 'Factura';
        }

        // Valores para DTE
        this.gasto.codigo_generacion =
          jsonData.identificacion.codigoGeneracion || '';
        this.gasto.numero_control = jsonData.identificacion.numeroControl || '';
      }

      // Mapear datos del proveedor
      if (jsonData.emisor) {
        this.buscarCrearProveedor(jsonData.emisor);
      }

      // Mapear conceptos e ítems
      if (jsonData.cuerpoDocumento && jsonData.cuerpoDocumento.length > 0) {
        // Usar la primera descripción como concepto principal
        this.gasto.concepto = jsonData.cuerpoDocumento[0].descripcion;

        // Si hay más de un ítem, añadirlos como nota
        if (jsonData.cuerpoDocumento.length > 1) {
          const itemsAdicionales = jsonData.cuerpoDocumento
            .slice(1)
            .map(
              (item: any, index: number) =>
                `${index + 2}. ${item.descripcion} (${item.cantidad} x $${
                  item.precioUni
                })`
            )
            .join('\n');

          this.gasto.nota = `Detalle adicional:\n${itemsAdicionales}`;
        }

        // Intentar determinar categoría basada en las descripciones
        this.determinarCategoria(jsonData.cuerpoDocumento);
      }

      // Mapear totales financieros
      if (jsonData.resumen) {
        // Montos base
        if (jsonData.resumen.subTotal) {
          this.gasto.sub_total = parseFloat(jsonData.resumen.subTotal);
        } else if (jsonData.resumen.totalGravada) {
          this.gasto.sub_total = parseFloat(jsonData.resumen.totalGravada);
        }

        // IVA
        if (jsonData.resumen.tributos && jsonData.resumen.tributos.length > 0) {
          const iva = jsonData.resumen.tributos.find(
            (t: any) => t.codigo === '20'
          );
          if (iva) {
            this.gasto.iva = parseFloat(iva.valor);
            this.gasto.impuesto = true;
          }
        }

        // Retención de renta
        if (
          jsonData.resumen.reteRenta &&
          parseFloat(jsonData.resumen.reteRenta) > 0
        ) {
          this.gasto.renta_retenida = parseFloat(jsonData.resumen.reteRenta);
          this.gasto.renta = true;
        }

        // Percepción
        if (
          jsonData.resumen.ivaPerci1 &&
          parseFloat(jsonData.resumen.ivaPerci1) > 0
        ) {
          this.gasto.iva_percibido = parseFloat(jsonData.resumen.ivaPerci1);
          this.gasto.percepcion = true;
        }

        // Total
        if (jsonData.resumen.totalPagar) {
          this.gasto.total = parseFloat(jsonData.resumen.totalPagar);
        } else if (jsonData.resumen.montoTotalOperacion) {
          this.gasto.total = parseFloat(jsonData.resumen.montoTotalOperacion);
        }

        // Forma de pago
        if (jsonData.resumen.pagos && jsonData.resumen.pagos.length > 0) {
          const formaPagoCodigos: { [key: string]: string } = {
            '01': 'Efectivo',
            '02': 'Tarjeta de Crédito',
            '03': 'Tarjeta de Débito',
            '04': 'Cheque',
            '05': 'Transferencia',
            '06': 'Crédito',
            '07': 'Tarjeta de regalo',
            '08': 'Dinero electrónico',
            '99': 'Otros',
          };

          const pago = jsonData.resumen.pagos[0];
          this.gasto.forma_pago = formaPagoCodigos[pago.codigo] || 'Efectivo';

          // Manejo de crédito
          if (pago.codigo === '06') {
            this.gasto.credito = true;
            this.gasto.estado = 'Pendiente';

            // Si hay plazo, calcular fecha de pago
            if (pago.plazo) {
              const fechaPago = moment(this.gasto.fecha)
                .add(pago.plazo, 'days')
                .format('YYYY-MM-DD');
              this.gasto.fecha_pago = fechaPago;
            }
          }
        }

        // Condición de operación
        if (jsonData.resumen.condicionOperacion) {
          if (jsonData.resumen.condicionOperacion === 1) {
            // this.gasto.condicion = 'Contado';  vamos a denigrarlo porque no nos sirve en gastos ❌
            this.gasto.estado = 'Confirmado';
          } else if (jsonData.resumen.condicionOperacion === 2) {
            // this.gasto.condicion = 'Crédito'; vamos a denigrarlo porque no nos sirve en gastos ❌
            this.gasto.credito = true;
            this.gasto.estado = 'Pendiente';
          }
        }
      }

      // Actualizar los cálculos para asegurar consistencia
      this.setTotal();
    } catch (error) {
      console.error('Error al mapear JSON a gasto:', error);
      this.alertService.error('Error al procesar algunos campos del JSON');
    }
  }

  private async buscarCrearProveedor(emisorData: any) {
    // Primero buscar por NIT
    if (emisorData.nit) {
      const proveedorExistente = this.proveedores.find(
        (p: any) =>
          p.nit === emisorData.nit ||
          (p.nombre_empresa && p.nombre_empresa.includes(emisorData.nombre))
      );

      if (proveedorExistente) {
        this.gasto.id_proveedor = proveedorExistente.id;
        return;
      }

      try {
        // Intentar buscar en el backend por NIT
        const response = await       this.apiService
          .store('proveedores/buscar-nit', { nit: emisorData.nit })
          .pipe(this.untilDestroyed())
          .toPromise();
        if (response && response.id) {
          this.gasto.id_proveedor = response.id;

          // Añadir a la lista local si no existe
          if (!this.proveedores.find((p: any) => p.id === response.id)) {
            this.proveedores.push(response);
          }
          return;
        }
      } catch (error) {
        // Proveedor no encontrado, continuar para crearlo
      }

      // Si llegamos aquí, necesitamos crear un nuevo proveedor
      const nuevoProveedor = {
        tipo: 'Empresa',
        nombre_empresa: emisorData.nombre,
        nit: emisorData.nit,
        ncr: emisorData.nrc || '',
        telefono: emisorData.telefono || '',
        email: emisorData.correo || '',
        direccion:
          emisorData.direccion && emisorData.direccion.complemento
            ? emisorData.direccion.complemento
            : 'No especificada',
        id_empresa: this.apiService.auth_user().id_empresa,
        id_usuario: this.apiService.auth_user().id,
      };

      try {
        const proveedorCreado = await this.apiService
          .store('proveedor', nuevoProveedor)
          .pipe(this.untilDestroyed())
          .toPromise();
        if (proveedorCreado && proveedorCreado.id) {
          this.gasto.id_proveedor = proveedorCreado.id;
          this.proveedores.push(proveedorCreado);
          this.alertService.success(
            'Proveedor creado',
            `Se creó automáticamente el proveedor ${emisorData.nombre}`
          );
        }
      } catch (error) {
        this.alertService.error(
          'No se pudo crear el proveedor automáticamente'
        );
      }
    }
  }

  /**
   * Intenta determinar la categoría del gasto basándose en las descripciones de los ítems
   */
  private determinarCategoria(items: any[]) {
    // Palabras clave para cada categoría
    const categoriasKeywords: { [key: string]: string[] } = {
      Alquiler: ['alquiler', 'renta', 'arrendamiento', 'local'],
      Combustible: ['combustible', 'gasolina', 'diesel', 'gas'],
      'Costo de venta': ['costo', 'venta', 'producto'],
      Insumos: ['insumos', 'suministros', 'papelería', 'oficina'],
      Impuestos: ['impuesto', 'iva', 'renta', 'fiscal', 'tributario'],
      'Gastos Administrativos': ['administrativo', 'gestión', 'admin'],
      Mantenimiento: ['mantenimiento', 'reparación', 'arreglo'],
      Marketing: ['marketing', 'publicidad', 'promoción'],
      'Materia Prima': ['materia prima', 'material', 'insumo'],
      Servicios: [
        'servicio',
        'suscripción',
        'internet',
        'teléfono',
        'electricidad',
        'agua',
      ],
      Planilla: ['planilla', 'salario', 'sueldo', 'nómina'],
      Préstamos: ['préstamo', 'crédito', 'financiamiento'],
    };

    // Concatenar todas las descripciones
    const descripcionCompleta = items
      .map((item) => item.descripcion.toLowerCase())
      .join(' ');

    // Buscar coincidencias con palabras clave
    for (const [categoria, keywords] of Object.entries(categoriasKeywords)) {
      for (const keyword of keywords) {
        if (descripcionCompleta.includes(keyword.toLowerCase())) {
          this.gasto.tipo = categoria;
          return;
        }
      }
    }
  }

  public onImpuestoValorChange() {
    this.calcularTotalConImpuestosEditados();
  }

  private calcularTotalConImpuestosEditados() {
    const subtotal = parseFloat(this.gasto.sub_total) || 0;
    let total = subtotal;

    // IVA
    if (this.gasto.impuesto) {
      const ivaRate = this.apiService.auth_user().empresa.iva / 100;
      const ivaValue = subtotal * ivaRate;
      this.gasto.iva = ivaValue.toFixed(2);
      total += ivaValue;
    } else {
      this.gasto.iva = 0;
    }

    // Renta
    if (this.gasto.renta) {
      this.gasto.renta_retenida = (subtotal * 0.10).toFixed(2);
      total -= parseFloat(this.gasto.renta_retenida);
    } else {
      this.gasto.renta_retenida = 0;
    }

    // Percepción
    if (this.gasto.percepcion) {
      this.gasto.iva_percibido = (subtotal * 0.01).toFixed(2);
      total += parseFloat(this.gasto.iva_percibido);
    } else {
      this.gasto.iva_percibido = 0;
    }

    // Sumar otros impuestos (valores editados)
    if (this.gasto.impuestos_valores && this.gasto.impuestos_valores.length > 0) {
      this.gasto.impuestos_valores.forEach((impValue: any) => {
        total += parseFloat(impValue.valor) || 0;
      });
    }

    this.gasto.total = total.toFixed(2);
  }

  public isColumnEnabled(columnName: string): boolean {
      return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
  }

  private loadDepartamentos(): Promise<any> {
    return new Promise((resolve, reject) => {
      this.apiService.getAll('departamentosEmpresa/list')
        .pipe(this.untilDestroyed())
        .subscribe(departamentos => {
        this.departamentos = departamentos;
        resolve(departamentos);
      }, error => {
        this.alertService.error(error);
        reject(error);
      });
    });
  }

  public onDepartamentoChangeGasto() {
    // Limpiar área seleccionada
    this.gasto.id_area_empresa = '';
    this.areasDisponibles = [];

    if (this.gasto.id_departamento) {
      this.loadAreasPorDepartamento(this.gasto.id_departamento);
    }
  }

  private loadAreasPorDepartamento(idDepartamento: string) {
    this.loadingAreas = true;

    this.apiService.getAll('area-empresa', { id_departamento: idDepartamento, estado: 1 })
      .pipe(this.untilDestroyed())
      .subscribe(response => {
        this.areasDisponibles = response.data || response;
        this.loadingAreas = false;
      }, error => {
        this.alertService.error(error);
        this.loadingAreas = false;
        this.areasDisponibles = [];
      });
  }


  public setDepartamento(departamento: any) {
    this.departamentos.push(departamento);
    this.gasto.id_departamento = departamento.id.toString();
    // Limpiar área seleccionada y cargar nuevas áreas
    this.gasto.id_area_empresa = '';
    this.loadAreasPorDepartamento(departamento.id);
  }

  public setArea(area: any) {
    this.areasDisponibles.push(area);
    this.gasto.id_area_empresa = area.id.toString();
  }

}
