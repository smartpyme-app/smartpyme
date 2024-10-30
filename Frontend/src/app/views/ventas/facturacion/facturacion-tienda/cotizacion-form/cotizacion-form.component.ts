
import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BsModalService } from 'ngx-bootstrap/modal';

@Component({
  selector: 'app-cotizacion-form',
  templateUrl: './cotizacion-form.component.html',
  styleUrls: ['./cotizacion-form.component.css']
})
export class CotizacionFormComponent implements OnInit {
  venta: any = {};

  usuarios: any = [];
  sucursales: any = [];
  documentos: any = [];
  clientes: any = [];
  formaPagos: any = [];
  proyectos: any = [];
  impuestos: any = [];
  loading: boolean = false;
  saving: boolean = false;

  @ViewChild('msupervisor') supervisorTemplate!: TemplateRef<any>;
  modalRefInstance!: any;

  constructor(
    public apiService: ApiService,
    private modalService: BsModalService,
    private alertService: AlertService,
    private router: Router,
    private _activeRoute: ActivatedRoute
  ) {
    let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);

    this.venta = {
      fecha: corte ? JSON.parse(sessionStorage.getItem('SP_corte')!).fecha : this.apiService.date(),
      fecha_pago: this.apiService.date(),
      forma_pago: 'Efectivo',
      tipo: 'Interna',
      estado: 'Pendiente',
      detalle_banco: '',
      id_cliente: '',
      detalles: [],
      descuento: 0,
      sub_total: 0,
      iva_percibido: 0,
      iva_retenido: 0,
      cotizacion: 1,
      iva: 0,
      total_costo: 0,
      total: 0,
      cobrar_impuestos: this.apiService.auth_user().empresa.cobra_iva == 'Si',
      id_bodega: this.apiService.auth_user().id_bodega,
      id_usuario: this.apiService.auth_user().id,
      id_vendedor: this.apiService.auth_user().id,
      id_sucursal: this.apiService.auth_user().id_sucursal,
      id_empresa: this.apiService.auth_user().id_empresa,
      caja_id: corte ? JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja : null,
      corte_id: corte ? JSON.parse(sessionStorage.getItem('SP_corte')!).id : null,
    }

  }
  ngOnInit(): void {
    Promise.all([
      this.apiService.getAll('usuarios/list').toPromise().then((_: any) => this.usuarios = _),
      this.apiService.getAll('sucursales/list').toPromise().then((_: any) => this.sucursales = _),
      this.apiService.getAll('documentos/list').toPromise().then((_: any) => this.documentos = _),
      this.apiService.getAll('clientes/list').toPromise().then((_: any) => this.clientes = _),
      this.apiService.getAll('formas-de-pago/list').toPromise().then((_: any) => this.formaPagos = _),
      this.apiService.getAll('proyectos/list').toPromise().then((_: any) => this.proyectos = _),
      this.apiService.getAll('impuestos').toPromise().then((_: any) => { this.venta.impuestos = _; this.impuestos = _ }),
    ]);

    this._activeRoute.paramMap.subscribe(params => {
      if (params.has('id')) {
        this.apiService.read('cotizacionVentas/', +params.get('id')!).subscribe((venta: any) => {
          venta.retencion = venta.aplicar_retencion;
          venta.detalles = venta.detalles.map((_detalle: any) => {

            _detalle.descripcion = _detalle.producto.nombre;
            return _detalle;
          })
          this.updateVenta(venta);
        });
      }
    });

  }
  setDocumento(id_documento: any) {
    let documento = this.documentos.find((x: any) => x.id == id_documento);
    this.venta.id_documento = documento.id;
    this.venta.correlativo = documento.correlativo;
  }
  setCliente(cliente: any) {
    if (!this.venta.id_cliente) {
      this.clientes.push(cliente);
    }
    this.venta.id_cliente = cliente.id;
    if (cliente.tipo_contribuyente == "Grande") {
      this.venta.retencion = 1;
      this.sumTotal();
    }
  }

  sumTotal() {
    this.venta.sub_total = (parseFloat(new SumPipe().transform(this.venta.detalles, 'total'))).toFixed(4);

    this.venta.exenta = (parseFloat(new SumPipe().transform(this.venta.detalles, 'exenta'))).toFixed(4);
    this.venta.no_sujeta = (parseFloat(new SumPipe().transform(this.venta.detalles, 'no_sujeta'))).toFixed(4);
    this.venta.gravada = (parseFloat(new SumPipe().transform(this.venta.detalles, 'gravada'))).toFixed(4);
    this.venta.cuenta_a_terceros = (parseFloat(new SumPipe().transform(this.venta.detalles, 'cuenta_a_terceros'))).toFixed(4);

    this.venta.iva_percibido = this.venta.percepcion ? this.venta.sub_total * 0.01 : 0;
    this.venta.iva_retenido = this.venta.retencion ? this.venta.sub_total * 0.01 : 0;

    this.venta.impuestos.forEach((impuesto: any) => {
      impuesto.monto = 0;
      if (this.venta.cobrar_impuestos)
        impuesto.monto = this.venta.sub_total * (impuesto.porcentaje / 100);
    });

    this.venta.iva = (parseFloat(new SumPipe().transform(this.venta.impuestos, 'monto'))).toFixed(4);
    this.venta.descuento = (parseFloat(new SumPipe().transform(this.venta.detalles, 'descuento'))).toFixed(4);
    this.venta.total_costo = (parseFloat(new SumPipe().transform(this.venta.detalles, 'total_costo'))).toFixed(4);
    this.venta.total = (
      parseFloat(this.venta.sub_total)
      + parseFloat(this.venta.iva)
      + parseFloat(this.venta.cuenta_a_terceros)
      + parseFloat(this.venta.exenta)
      + parseFloat(this.venta.no_sujeta)
      + parseFloat(this.venta.iva_percibido)
      - parseFloat(this.venta.iva_retenido)
    ).toFixed(4);
  }
  updateVenta(venta: any) {
    this.venta = venta;
    this.sumTotal();
  }
  onFacturar() {
    if (confirm('¿Confirma procesar la ' + (this.venta.cotizacion == 1 ? ' cotización.' : 'venta.'))) {
      if (!this.venta.recibido)
        this.venta.recibido = this.venta.total;

      if (this.venta.forma_pago == 'Wompi') {
        this.venta.estado = 'Pendiente';
      }
      this.onSubmit();
    }
  }
  limpiar() {
    this.modalRefInstance = this.modalService.show(this.supervisorTemplate, { class: 'modal-xs' });
  }
  cambioMetodoDePago() {
    if (this.venta.forma_pago != 'Multiple') {
      this.venta.metodos_de_pago = [];
      this.venta.efectivo = this.venta.total;
      this.formaPagos.forEach((item: any) => {
        item.total = null;
      });
    }
  }

  totalPorMetodoDePago() {
    // Agregar los metodos que tengan asignado un monto
    this.venta.metodos_de_pago = this.formaPagos.filter((item: any) => item.total && (item.total > 0))
    this.formaPagos.push({ 'nombre': 'Multiple' })
    this.venta.forma_pago = 'Multiple';
    this.venta.efectivo = this.formaPagos.find((item: any) => item.nombre == 'Efectivo').total;
  }

  onSubmit() {

    this.saving = true;

    this.apiService.store('cotizacionVentas', this.venta).subscribe(venta => {
      this.saving = false;
      this.router.navigate(['/cotizaciones']);
      this.alertService.success('Cotización creada', 'La cotizacion fue añadida exitosamente.');

      this.modalRefInstance?.hide()

    }, error => {
      this.alertService.error(error);
      this.saving = false;
    });

  }

}
