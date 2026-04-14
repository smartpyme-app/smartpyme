import { Component, OnInit,TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { Location } from '@angular/common';
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
import { FuncionalidadesService } from '@services/functionalities.service';
import { SharedDataService } from '@services/shared-data.service';
import { HttpCacheService } from '@services/http-cache.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';
import { forkJoin } from 'rxjs';

@Component({
    selector: 'app-gasto',
    templateUrl: './gasto.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, NgSelectModule, CrearProveedorComponent, CrearProyectoComponent, CrearAreaEmpresaComponent, CrearImpuestoComponent, CrearDepartamentoComponent, CrearAbonoGastoComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
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

  public varios_items = false;
  public detalles: any[] = [];

  public opAvanzadas: boolean = false;
  public otrosImpuestos: boolean = false;
  public areasDisponibles: any[] = [];
  public loadingAreas: boolean = false;
  public departamentos: any[] = [];
  public contabilidadHabilitada: boolean = false;

  /** Catálogo de áreas (filtradas por sucursal del gasto). */
  public areas: any[] = [];
  /** Departamentos de empresa según sucursal del gasto. */
  public departamentosEmpresa: any[] = [];
  /** Solo UI: filtra áreas; el gasto guarda `id_area_empresa`. */
  public idDepartamentoSeleccionado: number | null = null;

  modalRef?: BsModalRef;

  readonly TIPOS_CATEGORIA = [
    'Alquiler', 'Combustible', 'Costo de venta', 'Gastos varios', 'Insumos',
    'Impuestos', 'Gastos Administrativos', 'Mantenimiento', 'Marketing',
    'Materia Prima', 'Servicios', 'Pago comisión', 'Planilla', 'Préstamos', 'Publicidad'
  ];

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  private cdr = inject(ChangeDetectorRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private modalService: BsModalService,
    private sharedDataService: SharedDataService,
    private cacheService: HttpCacheService,
    private location: Location,
    private funcionalidadesService: FuncionalidadesService
  ) {}

  public openAbono(template: TemplateRef<any>, gasto:any){
    this.gasto = gasto;
    this.modalRef = this.modalService.show(template);
  }

  public setEstado(abono: any){
    this.apiService.store('gasto/abono', abono).subscribe(abono => {
      this.loadAll();
      this.cdr.markForCheck();
    }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
  }

	ngOnInit(){
        this.loadAll();
        this.loadDepartamentos();
        this.verificarAccesoContabilidad();

    this.mostrar_otros_impuestos = false;
    this.impuestos_seleccionados = [];

    // Cargar datos compartidos usando SharedDataService
    this.sharedDataService.getSucursales()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (sucursales) => {
          this.sucursales = sucursales;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });

    this.sharedDataService.getUsuarios()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (usuarios) => {
          this.usuarios = usuarios;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });

    this.sharedDataService.getFormasDePago()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (formaspago) => {
          this.formaspago = formaspago;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });

    // Categorías: se cargan en cargarCategorias() tras verificar contabilidad (y si hay categorías personalizadas en empresa).

    this.sharedDataService.getProveedores()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (proveedores) => {
          this.proveedores = proveedores;
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
          this.cdr.markForCheck();
        }
      });

    this.sharedDataService.getProyectos()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (proyectos) => {
          this.proyectos = proyectos;
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
          this.cdr.markForCheck();
        }
      });


    this.apiService.getAll('impuestos')
      .pipe(this.untilDestroyed())
      .subscribe(impuestos => {
        // Filtrar solo los impuestos que aplican a gastos
        this.impuestos = impuestos.filter((impuesto: any) => impuesto.aplica_gastos !== false && impuesto.aplica_gastos !== 0);
        this.loading = false;

      if (this.gasto && this.gasto.otros_impuestos && this.gasto.otros_impuestos.length > 0) {
          this.cargarImpuestosSeleccionados();
          this.mostrar_otros_impuestos = true;
      }
      this.cdr.markForCheck();
    }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

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

          // Alineado con setCredito(): credito true = Pendiente (switch "Pendiente" encendido)
          this.syncGastoCreditoFromEstado();

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

          if (this.apiService.isGastosCategoriasPersonalizadasHabilitadas()) {
            this.cargarDepartamentosYAreas();
          }
          if (this.gasto.id_categoria != null && this.gasto.id_categoria !== '') {
            this.gasto.id_categoria = Number(this.gasto.id_categoria);
          }

          if (this.gasto.detalles && this.gasto.detalles.length > 1) {
            this.varios_items = true;
            this.detalles = this.gasto.detalles.map((d: any) => ({
              ...d,
              tipo_gravado: d.tipo_gravado || (d.aplica_iva ? 'gravada' : 'no_sujeta'),
              id_categoria:
                d.id_categoria != null && d.id_categoria !== ''
                  ? Number(d.id_categoria)
                  : null,
            }));
          } else if (this.gasto.detalles && this.gasto.detalles.length === 1) {
            this.varios_items = false;
            this.detalles = [];
          } else {
            this.varios_items = false;
            this.detalles = [];
          }

          this.loading = false;
          this.cdr.markForCheck();
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
          this.cdr.markForCheck();
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
      this.gasto.id_categoria = null;
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
      this.varios_items = false;
      this.detalles = [];

      if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
        this.gasto.id_proyecto =
          +this.route.snapshot.queryParamMap.get('id_proyecto')!;
      }
      this.setTotal();
      if (this.apiService.isGastosCategoriasPersonalizadasHabilitadas()) {
        this.cargarDepartamentosYAreas();
      }
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
            this.syncGastoCreditoFromEstado();
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

            if (this.gasto.otros_impuestos) {
              this.mostrar_otros_impuestos = true;
              this.cargarImpuestosSeleccionados();
            }
            this.cdr.markForCheck();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
            this.cdr.markForCheck();
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
        this.cdr.markForCheck();
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

  public cambioMetodoDePago() {
    if (this.apiService.isModuloBancos() && this.gasto.forma_pago && this.gasto.forma_pago !== 'Efectivo' && this.gasto.forma_pago !== 'Wompi') {
      const formaPagoSeleccionada = this.formaspago.find((fp: any) => fp.nombre === this.gasto.forma_pago);
      if (formaPagoSeleccionada?.banco?.nombre_banco) {
        this.gasto.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
      } else {
        this.gasto.detalle_banco = '';
      }
    } else if (this.gasto.forma_pago === 'Efectivo' || this.gasto.forma_pago === 'Wompi') {
      this.gasto.detalle_banco = '';
    }
  }

  public setCredito() {
    if (this.gasto.credito) {
      this.gasto.estado = 'Pendiente';
    } else {
      this.gasto.estado = 'Confirmado';
    }
  }

  private syncGastoCreditoFromEstado(): void {
    if (!this.gasto) return;
    this.gasto.credito = this.gasto.estado === 'Pendiente';
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

  public toggleVariosItems() {
    if (this.varios_items && this.detalles.length === 0) {
      this.addDetalle();
    } else if (!this.varios_items) {
      this.detalles = [];
    }
  }

  public addDetalle() {
    this.detalles.push({
      concepto: '',
      tipo: 'Gastos varios',
      id_categoria: null,
      tipo_gravado: 'gravada',
      cantidad: 1,
      precio_unitario: 0,
      sub_total: 0,
      iva: 0,
      renta_retenida: 0,
      iva_percibido: 0,
      total: 0,
      aplica_iva: true,
      aplica_renta: false,
      aplica_percepcion: false,
      area_empresa: null,
      id_proyecto: this.gasto.id_proyecto || null,
    });
  }

  public removeDetalle(idx: number) {
    this.detalles.splice(idx, 1);
    this.recalcularTotalesDetalles();
  }

  public recalcularDetalleLinea(idx: number) {
    const d = this.detalles[idx];
    const sub = parseFloat(d.sub_total) || 0;
    let total = sub;
    const ivaRate = this.apiService.auth_user()?.empresa?.iva || 13;
    const esGravada = d.tipo_gravado === 'gravada' || (!d.tipo_gravado && d.aplica_iva);
    if (esGravada) {
      d.iva = parseFloat((sub * (ivaRate / 100)).toFixed(2));
      d.aplica_iva = true;
      total += d.iva;
    } else {
      d.iva = 0;
      d.aplica_iva = false;
    }
    if (d.aplica_renta) {
      d.renta_retenida = parseFloat((sub * 0.1).toFixed(2));
      total -= d.renta_retenida;
    } else {
      d.renta_retenida = 0;
    }
    if (d.aplica_percepcion) {
      d.iva_percibido = parseFloat((sub * 0.01).toFixed(2));
      total += d.iva_percibido;
    } else {
      d.iva_percibido = 0;
    }
    d.total = parseFloat(total.toFixed(2));
    this.recalcularTotalesDetalles();
  }

  public recalcularDesdeSubtotal(idx: number) {
    const d = this.detalles[idx];
    const sub = parseFloat(d.sub_total) || 0;
    d.precio_unitario = d.cantidad ? sub / d.cantidad : sub;
    this.recalcularDetalleLinea(idx);
  }

  public recalcularDesdePrecio(idx: number) {
    const d = this.detalles[idx];
    const cant = parseFloat(d.cantidad) || 1;
    const precio = parseFloat(d.precio_unitario) || 0;
    d.sub_total = parseFloat((cant * precio).toFixed(2));
    this.recalcularDetalleLinea(idx);
  }

  public recalcularTotalesDetalles() {
    let st = 0, iv = 0, rr = 0, ip = 0, tot = 0;
    this.detalles.forEach(d => {
      st += parseFloat(d.sub_total) || 0;
      iv += parseFloat(d.iva) || 0;
      rr += parseFloat(d.renta_retenida) || 0;
      ip += parseFloat(d.iva_percibido) || 0;
      tot += parseFloat(d.total) || 0;
    });
    this.gasto.sub_total = parseFloat(st.toFixed(2));
    this.gasto.iva = parseFloat(iv.toFixed(2));
    this.gasto.renta_retenida = parseFloat(rr.toFixed(2));
    this.gasto.iva_percibido = parseFloat(ip.toFixed(2));
    this.gasto.total = parseFloat(tot.toFixed(2));
  }

  public cambioFormaPago() {
    // Limpiar banco si la forma de pago no requiere banco
    if (this.gasto.forma_pago == 'Efectivo' || this.gasto.forma_pago == 'Wompi') {
      this.gasto.detalle_banco = '';
    }
  }

  public async onSubmit() {
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

    const usarCategoriaBd = this.apiService.mostrarMenuConfigGastos(this.contabilidadHabilitada);
    const categoriasPersonalizadas = this.apiService.isGastosCategoriasPersonalizadasHabilitadas();

    // Sin selector de categoría BD: solo tipo libre; con contabilidad se prioriza id_categoria sobre tipo
    if (!usarCategoriaBd) {
      if (this.gasto.id_categoria) {
        this.gasto.id_categoria = null;
      }
      if (!this.gasto.tipo) {
        this.gasto.tipo = '';
      }
    } else if (this.contabilidadHabilitada && this.gasto.tipo && !this.gasto.id_categoria) {
      this.gasto.tipo = '';
    }

    const payload: any = { ...this.gasto };
    if (!usarCategoriaBd) {
      delete payload.id_categoria;
    } else if (payload.id_categoria != null && payload.id_categoria !== '') {
      payload.id_categoria = Number(payload.id_categoria);
    } else {
      payload.id_categoria = null;
    }

    if (!categoriasPersonalizadas) {
      delete payload.id_area_empresa;
    }
    if (this.varios_items && this.detalles.length > 0) {
      payload.varios_items = true;
      payload.detalles = this.detalles.map(d => {
        const tg = d.tipo_gravado || (d.aplica_iva ? 'gravada' : 'no_sujeta');
        const line: any = {
          concepto: d.concepto,
          tipo: (d.tipo && String(d.tipo).trim()) ? d.tipo : 'Gastos varios',
          tipo_gravado: ['gravada', 'exenta', 'no_sujeta'].includes(tg) ? tg : 'gravada',
          cantidad: parseFloat(d.cantidad) || 1,
          precio_unitario: parseFloat(d.precio_unitario) || parseFloat(d.sub_total) || 0,
          sub_total: parseFloat(d.sub_total) || 0,
          iva: parseFloat(d.iva) || 0,
          renta_retenida: parseFloat(d.renta_retenida) || 0,
          iva_percibido: parseFloat(d.iva_percibido) || 0,
          total: parseFloat(d.total) || 0,
          aplica_iva: tg === 'gravada',
          aplica_renta: !!d.aplica_renta,
          aplica_percepcion: !!d.aplica_percepcion,
          area_empresa: d.area_empresa || null,
          id_proyecto: d.id_proyecto || null,
        };
        if (usarCategoriaBd) {
          const ic = d.id_categoria;
          line.id_categoria = ic != null && ic !== '' ? Number(ic) : null;
        }
        return line;
      });
    } else {
      payload.varios_items = false;
    }

    try {
      const gastoGuardado = await this.apiService.store('gasto', payload)
        .pipe(this.untilDestroyed())
        .toPromise();

      // Invalidar cache después de guardar
      const isNew = !this.gasto.id;
      if (this.cacheService) {
        this.cacheService.invalidatePattern('/gastos');
        if (gastoGuardado?.id) {
          this.cacheService.delete(`/gasto/${gastoGuardado.id}`);
        }
      }

      const titulo = isNew ? 'Gasto creado' : 'Gasto guardado';
      const mensaje = isNew
        ? 'El gasto fue añadido exitosamente.'
        : 'El gasto fue guardado exitosamente.';

      this.alertService.success(titulo, mensaje);
      this.cdr.markForCheck();
      this.router.navigate(['/gastos']);
    } catch (error: any) {
      // Cerrar cualquier modal abierto para que se muestre el error
      if (this.modalRef) {
        this.modalRef.hide();
        this.modalRef = undefined;
      }
      // Asegurar que el alert se muestre
      this.alertService.modal = false;

      // El AlertService ya maneja los errores 422 con mensajes detallados
      this.alertService.error(error);
      this.cdr.markForCheck();
    } finally {
      this.saving = false;
      this.cdr.markForCheck();
    }
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
        this.gasto.id_categoria = null;
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

        // Si hay más de un ítem, usar modo varios ítems
        if (jsonData.cuerpoDocumento.length > 1) {
          this.varios_items = true;
          const items = jsonData.cuerpoDocumento;
          const ivaRate = (this.apiService.auth_user()?.empresa?.iva || 13) / 100;
          this.detalles = items.map((item: any) => {
            const cant = parseFloat(item.cantidad) || 1;
            const precio = parseFloat(item.precioUni) || 0;
            const compra = parseFloat(item.compra) || cant * precio;
            const sub = ivaRate > 0 ? compra / (1 + ivaRate) : compra;
            const iva = compra - sub;
            const esGravada = iva > 0;
            return {
              concepto: item.descripcion || '',
              tipo: 'Gastos varios',
              tipo_gravado: esGravada ? 'gravada' : 'no_sujeta',
              cantidad: cant,
              precio_unitario: precio,
              sub_total: parseFloat(sub.toFixed(2)),
              iva: parseFloat(iva.toFixed(2)),
              renta_retenida: 0,
              iva_percibido: 0,
              total: parseFloat(compra.toFixed(2)),
              aplica_iva: esGravada,
              aplica_renta: false,
              aplica_percepcion: false,
              area_empresa: null,
              id_proyecto: this.gasto.id_proyecto || null,
            };
          });
          this.recalcularTotalesDetalles();
          this.gasto.concepto = items[0].descripcion;
          this.gasto.tipo = this.determinarCategoria(items);
        } else {
          this.varios_items = false;
          this.detalles = [];
          this.gasto.tipo = this.determinarCategoria(jsonData.cuerpoDocumento);
        }
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
          this.cdr.markForCheck();
          return;
        }
      } catch (error) {
        // Proveedor no encontrado, continuar para crearlo
        this.cdr.markForCheck();
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
          this.cdr.markForCheck();
        }
      } catch (error) {
        this.alertService.error(
          'No se pudo crear el proveedor automáticamente'
        );
        this.cdr.markForCheck();
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
        this.cdr.markForCheck();
        resolve(departamentos);
      }, error => {
        this.alertService.error(error);
        this.cdr.markForCheck();
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
        this.cdr.markForCheck();
      }, error => {
        this.alertService.error(error);
        this.loadingAreas = false;
        this.areasDisponibles = [];
        this.cdr.markForCheck();
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

  get areasGastoSelector(): any[] {
    if (!this.areas?.length || this.idDepartamentoSeleccionado == null) {
      return [];
    }
    return this.areas.filter(
      (a: any) => String(a.id_departamento) === String(this.idDepartamentoSeleccionado)
    );
  }

  setDepartamentoCreado(dep: any) {
    this.idDepartamentoSeleccionado = dep.id;
    this.cargarDepartamentosYAreas();
  }

  setAreaEmpresaCreada(area: any) {
    this.gasto.id_area_empresa = area.id;
    if (area.id_departamento != null) {
      this.idDepartamentoSeleccionado = area.id_departamento;
    }
    this.cargarDepartamentosYAreas();
  }

  public cargarDepartamentosYAreas(): void {
    if (!this.apiService.isGastosCategoriasPersonalizadasHabilitadas() || !this.gasto?.id_sucursal) {
      this.departamentosEmpresa = [];
      this.areas = [];
      this.idDepartamentoSeleccionado = null;
      return;
    }
    forkJoin({
      deps: this.apiService.getAll('area-empresa/list_departamentos', {
        id_sucursal: this.gasto.id_sucursal,
      }),
      areas: this.apiService.getAll('area-empresa/list', {
        id_sucursal: this.gasto.id_sucursal,
      }),
    }).subscribe({
      next: ({ deps, areas }) => {
        this.departamentosEmpresa = deps;
        this.areas = areas;
        this.aplicarSeleccionDepartamentoDesdeArea();
      },
      error: (e) => this.alertService.error(e),
    });
  }

  private aplicarSeleccionDepartamentoDesdeArea(): void {
    if (!this.gasto?.id_area_empresa || !this.areas?.length) {
      return;
    }
    const a = this.areas.find(
      (x: any) => String(x.id) === String(this.gasto.id_area_empresa)
    );
    if (a) {
      this.idDepartamentoSeleccionado = a.id_departamento;
    }
  }

  public onDepartamentoGastoChange(): void {
    if (this.idDepartamentoSeleccionado == null) {
      this.gasto.id_area_empresa = null;
      return;
    }
    if (this.gasto?.id_area_empresa == null || this.gasto.id_area_empresa === '') {
      return;
    }
    const sel = this.areas.find(
      (x: any) => String(x.id) === String(this.gasto.id_area_empresa)
    );
    if (
      sel &&
      String(sel.id_departamento) !== String(this.idDepartamentoSeleccionado)
    ) {
      this.gasto.id_area_empresa = null;
    }
  }

  public onSucursalGastoChange(): void {
    if (!this.apiService.isGastosCategoriasPersonalizadasHabilitadas()) {
      return;
    }
    this.gasto.id_area_empresa = null;
    this.idDepartamentoSeleccionado = null;
    this.cargarDepartamentosYAreas();
  }

  public goBack() {
    this.location.back();
  }

  verificarAccesoContabilidad() {
    this.funcionalidadesService.verificarAcceso('contabilidad')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (acceso) => {
          this.contabilidadHabilitada = acceso;
          this.cargarBancos();
          this.cargarCategorias();
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al verificar acceso a contabilidad:', error);
          this.contabilidadHabilitada = false;
          this.cargarBancos();
          this.cargarCategorias();
          this.cdr.markForCheck();
        }
      });
  }

  cargarBancos() {
    const endpoint = this.contabilidadHabilitada ? 'banco/cuentas/list' : 'bancos';
    this.apiService.getAll(endpoint)
      .pipe(this.untilDestroyed())
      .subscribe(
        (bancos) => {
          if (!this.contabilidadHabilitada) {
            // Filtrar solo bancos activos y transformar estructura
            this.bancos = bancos
              .filter((banco: any) => banco.activo === true || banco.activo === 1)
              .map((banco: any) => ({
                nombre: banco.nombre
              }));
          } else {
            this.bancos = bancos;
          }
          this.cdr.markForCheck();
        },
        (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
  }

  cargarCategorias() {
    if (this.apiService.mostrarMenuConfigGastos(this.contabilidadHabilitada)) {
      this.apiService.getAll('gastos/categorias/list')
        .pipe(this.untilDestroyed())
        .subscribe(
          (categorias) => {
            this.categorias = categorias;
            this.loading = false;
            this.cdr.markForCheck();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
            this.cdr.markForCheck();
          }
        );
    } else {
      this.categorias = [];
      this.cdr.markForCheck();
    }
  }
}
