import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { esDocumentoCompraSinIvaFiscal, FACTURA_REMISION } from '../../../../constants/documento.constants';

@Component({
  selector: 'app-facturacion-compra-consigna',
  templateUrl: './facturacion-compra-consigna.component.html',
  providers: [SumPipe],
})
export class FacturacionCompraConsignaComponent implements OnInit {
  public compra: any = {};
  public usuarios: any = [];
  public documentos: any = [];
  public documentosTodos: any = [];
  public formaPagos: any = [];
  public sucursales: any = [];
  public bancos: any = [];
  public loading = false;

  public searchTerm = '';
  public searchResults: any[] = [];
  public searchLoading = false;
  public searchProductos$ = new Subject<string>();
  public detalle: any = {};
  public detalleVendido: any = {};
  public ventasConsignaDetalle: any[] = [];
  public cantidadVendidaDetalle = 0;
  public loadingVentasDetalle = false;

  public productosModal!: BsModalRef;
  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private sumPipe: SumPipe,
    private route: ActivatedRoute,
    private router: Router
  ) {
    this.router.routeReuseStrategy.shouldReuseRoute = function () {
      return false;
    };

    this.searchProductos$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((term) => {
          if (!term || term.length < 2) {
            return of([]);
          }
          this.searchLoading = true;
          this.searchTerm = term;

          return this.apiService
            .store('productos/buscar-modal', {
              termino: term,
              id_empresa: this.apiService.auth_user().id_empresa,
              limite: 15,
            })
            .pipe(
              catchError(() => of([]))
            );
        })
      )
      .subscribe((results) => {
        this.searchResults = results || [];
        this.searchLoading = false;
      });
  }

  ngOnInit() {
    this.route.params.subscribe((params: any) => {
      if (params.id) {
        this.loading = true;
        this.apiService.read('compra/', params.id).subscribe(
          (compra) => {
            this.compra = compra;
            this.compra.cobrar_impuestos = Number(this.compra.iva) > 0;
            this.compra.cobrar_percepcion = Number(this.compra.percepcion) > 0;
            this.compra.retencion = Number(this.compra.iva_retenido) > 0 ? 1 : 0;
            this.loading = false;
            this.cargarDatos();
            this.sumTotal();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
      } else {
        this.compra = {};
        this.compra.id_empresa = this.apiService.auth_user().id_empresa;
        this.compra.id_usuario = this.apiService.auth_user().id;
      }
    });
  }

  public cargarDatos() {
    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('usuarios/list').subscribe(
      (usuarios) => {
        this.usuarios = usuarios;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    if (this.apiService.isModuloBancos()) {
      this.apiService.getAll('banco/cuentas/list').subscribe(
        (bancos) => {
          this.bancos = bancos;
        },
        (error) => {
          this.alertService.error(error);
        }
      );
    } else {
      this.apiService.getAll('bancos/list').subscribe(
        (bancos) => {
          this.bancos = bancos;
        },
        (error) => {
          this.alertService.error(error);
        }
      );
    }

    this.cargarDocumentos();

    this.apiService.getAll('formas-de-pago/list').subscribe(
      (formaPagos) => {
        this.formaPagos = formaPagos;
        if (
          this.apiService.isModuloBancos() &&
          this.compra.forma_pago &&
          this.compra.forma_pago !== 'Efectivo'
        ) {
          const formaPagoSeleccionada = formaPagos.find(
            (fp: any) => fp.nombre === this.compra.forma_pago
          );
          if (formaPagoSeleccionada?.banco?.nombre_banco && !this.compra.detalle_banco) {
            this.compra.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
          }
        }
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public cargarDocumentos() {
    this.apiService.getAll('documentos/list').subscribe(
      (documentos) => {
        this.documentosTodos = documentos.filter(
          (x: any) => x.id_sucursal == this.compra.id_sucursal
        );
        this.documentos = this.documentosTodos.filter(
          (x: any) => x.nombre !== FACTURA_REMISION
        );

        if (esDocumentoCompraSinIvaFiscal(this.compra.tipo_documento)) {
          const documentoFiscal =
            this.documentos.find((x: any) => x.nombre === 'Factura') ||
            this.documentos.find((x: any) => x.predeterminado == 1) ||
            this.documentos[0];
          if (documentoFiscal) {
            this.compra.tipo_documento = documentoFiscal.nombre;
          }
        }

        this.selectTipoDocumento();
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public selectTipoDocumento() {
    if (this.compra.tipo_documento === 'Sujeto excluido') {
      const documento = this.documentos.find(
        (x: any) => x.nombre == this.compra.tipo_documento
      );
      if (documento) {
        this.compra.referencia = documento.correlativo;
      }
    }

    if (esDocumentoCompraSinIvaFiscal(this.compra.tipo_documento)) {
      const documento = this.documentos.find(
        (x: any) => x.nombre === this.compra.tipo_documento
      );
      if (documento) {
        this.compra.referencia = documento.correlativo;
      }
      this.compra.cobrar_impuestos = false;
      this.compra.cobrar_percepcion = false;
      this.compra.retencion = 0;
      this.compra.tipo_operacion = 'No Gravada';
    } else if (!this.compra.cobrar_impuestos) {
      this.compra.cobrar_impuestos =
        this.apiService.auth_user().empresa.cobra_iva == 'Si';
    }

    this.sumTotal();
  }

  public esCompraSinIvaFiscal(): boolean {
    return esDocumentoCompraSinIvaFiscal(this.compra?.tipo_documento);
  }

  public cambioMetodoDePago() {
    if (
      this.apiService.isModuloBancos() &&
      this.compra.forma_pago &&
      this.compra.forma_pago !== 'Efectivo'
    ) {
      const formaPagoSeleccionada = this.formaPagos.find(
        (fp: any) => fp.nombre === this.compra.forma_pago
      );
      if (formaPagoSeleccionada?.banco?.nombre_banco) {
        this.compra.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
      } else {
        this.compra.detalle_banco = '';
      }
    } else if (this.compra.forma_pago === 'Efectivo') {
      this.compra.detalle_banco = '';
    }
  }

  public updateTotal(detalle: any) {
    if (!detalle.cantidad) {
      detalle.cantidad = 0;
    }
    if (detalle.descuento_porcentaje) {
      detalle.descuento =
        detalle.cantidad * (detalle.costo * (detalle.descuento_porcentaje / 100));
    } else {
      detalle.descuento = 0;
    }

    detalle.total = (
      parseFloat(detalle.cantidad) * parseFloat(detalle.costo) -
      parseFloat(detalle.descuento)
    ).toFixed(4);
    this.sumTotal();
  }

  public sumTotal() {
    if (esDocumentoCompraSinIvaFiscal(this.compra?.tipo_documento)) {
      this.compra.cobrar_impuestos = false;
      this.compra.cobrar_percepcion = false;
      this.compra.retencion = 0;
    }

    this.compra.sub_total = parseFloat(
      this.sumPipe.transform(this.compra.detalles, 'total')
    ).toFixed(2);
    this.compra.percepcion = this.compra.cobrar_percepcion
      ? this.compra.sub_total * 0.01
      : 0;
    this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0;

    if (this.compra.cobrar_impuestos) {
      this.compra.iva = (this.compra.sub_total * 0.13).toFixed(2);
    } else {
      this.compra.iva = 0;
    }

    this.compra.descuento = parseFloat(
      this.sumPipe.transform(this.compra.detalles, 'descuento')
    ).toFixed(2);
    this.compra.total = (
      parseFloat(this.compra.sub_total) +
      parseFloat(this.compra.iva) +
      parseFloat(this.compra.percepcion) -
      parseFloat(this.compra.iva_retenido)
    ).toFixed(2);
  }

  public openModalVentasDetalle(template: TemplateRef<any>, detalle: any) {
    this.detalleVendido = detalle;
    this.loadingVentasDetalle = true;
    this.ventasConsignaDetalle = [];
    this.cantidadVendidaDetalle = 0;

    this.apiService
      .getAll('productos/consigna-ventas', {
        id_producto: detalle.id_producto,
        id_bodega: this.compra.id_bodega,
      })
      .subscribe(
        (res: any) => {
          this.cantidadVendidaDetalle = res?.cantidad_vendida ?? 0;
          this.ventasConsignaDetalle = res?.ventas ?? [];
          this.loadingVentasDetalle = false;
          this.modalRef = this.modalService.show(template, {
            class: 'modal-lg',
            backdrop: 'static',
          });
        },
        (error) => {
          this.alertService.error(error);
          this.loadingVentasDetalle = false;
        }
      );
  }

  public ajustarCantidadVendida() {
    this.detalleVendido.cantidad = this.cantidadVendidaDetalle;
    this.updateTotal(this.detalleVendido);
    this.modalRef.hide();
  }

  public openModalProductos(template: TemplateRef<any>) {
    this.detalle = {};
    this.searchResults = [];
    this.searchTerm = '';
    this.productosModal = this.modalService.show(template, { class: 'modal-lg' });
  }

  public onSearchProducts(term: string) {
    this.searchProductos$.next(term);
  }

  public selectProducto(producto: any) {
    this.detalle = {
      id_producto: producto.id,
      nombre_producto: producto.nombre,
      descripcion: producto.descripcion,
      cantidad: 1,
      costo: producto.costo || 0,
      descuento: 0,
      descuento_porcentaje: 0,
      total: producto.costo || 0,
      img: producto.img || 'default-product.png',
      tipo: producto.tipo,
    };
    this.productosModal.hide();
    this.addDetalle();
  }

  public addDetalle() {
    if (!this.compra.detalles) {
      this.compra.detalles = [];
    }

    this.detalle.total = (
      parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.costo) -
      parseFloat(this.detalle.descuento)
    ).toFixed(2);

    this.compra.detalles.push({ ...this.detalle });
    this.detalle = {};
    this.sumTotal();
  }

  public delete(detalle: any) {
    const index = this.compra.detalles.indexOf(detalle);
    if (index > -1) {
      this.compra.detalles.splice(index, 1);
      this.sumTotal();
    }
  }

  public onFacturar() {
    if (
      confirm(
        '¿Confirma procesar la ' +
          (this.compra.estado == 'Pre-compra' ? ' cotización.' : 'compra.')
      )
    ) {
      if (!this.compra.recibido) {
        this.compra.recibido = this.compra.total;
      }

      if (this.compra.forma_pago == 'Wompi') {
        this.compra.estado = 'Pendiente';
      }
      this.onSubmit();
    }
  }

  public onSubmit() {
    this.loading = true;

    this.apiService.store('compra/facturacion/consigna', this.compra).subscribe(
      () => {
        this.loading = false;
        this.router.navigate(['/compras']);
        this.alertService.success('Compra creada', 'La compra fue añadida exitosamente.');
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }
}
