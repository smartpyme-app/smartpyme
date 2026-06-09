import {
  Component,
  OnInit,
  OnChanges,
  SimpleChanges,
  TemplateRef,
  Input,
  Output,
  EventEmitter,
  ViewChild,
  inject,
} from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { HttpCacheService } from '@services/http-cache.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { ChangeDetectionStrategy, ChangeDetectorRef, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TagInputModule } from 'ngx-chips';
import { NgSelectModule } from '@ng-select/ng-select';
import { Subject, of } from 'rxjs';
import { map, debounceTime, distinctUntilChanged, switchMap, catchError, finalize } from 'rxjs/operators';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import { mapCabysApiResponseToOptions, CabysSelectOption } from '@services/facturacion-electronica/cabys-hacienda.mapper';
import { HaciendaCabysClientService } from '@services/facturacion-electronica/hacienda-cabys-client.service';
import { CrearCategoriaComponent } from '@shared/modals/crear-categoria/crear-categoria.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-producto-informacion',
    templateUrl: './producto-informacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TagInputModule, NgSelectModule, CrearCategoriaComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,

})
export class ProductoInformacionComponent extends BaseModalComponent implements OnInit, OnChanges {
  @ViewChild('modalAtributo') modalAtributo!: TemplateRef<any>;
  @Input() producto: any = {};
  /** Notifica al padre (p. ej. OnPush) cuando cambia tipo compuesto u otros datos mutados en el mismo objeto. */
  @Output() productoActualizado = new EventEmitter<void>();
  @Output() productoGuardado = new EventEmitter<any>();
  /** Evita múltiples llamadas al pedir código de barras sugerido */
  private barcodeCorrelativoPendiente = true;
  public categorias: any = [];
  public subcategorias: any = [];
  public subcategRes: any = [];
  public proveedores: any = [];
  public usuario: any = {};
  public categoria: any = {};
  public bodegas: any = [];
  public medidas: any = [];
  public impuestos: any[] = [];
  public override loading = false;
  public guardar = false;
  public variants: Array<{ nombre: string; cantidad: number }> = [];
  public tallas: any = [];
  public colores: any = [];
  public materiales: any = [];

  tipoAtributoActual: string = '';
  nuevoAtributo: any = {};
  guardandoAtributo: boolean = false;

  /** CABYS (CR): mismo patrón que búsqueda de productos en ajustes — Subject + array `cabysItems`. */
  cabysInput$ = new Subject<string>();
  cabysItems: CabysSelectOption[] = [];
  cabysLoading = false;
  cabysSeleccionado: CabysSelectOption | null = null;

  readonly compareCabys = (a: CabysSelectOption, b: CabysSelectOption): boolean =>
    !!(a && b && a.codigo === b.codigo);

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private cacheService: HttpCacheService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef,
    private zone: NgZone,
    private haciendaCabys: HaciendaCabysClientService,
  ) {
    super(modalManager, alertService);
    // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    this.addVariant();
  }

  ngOnInit() {
    this.loadAtributes();
    this.usuario = this.apiService.auth_user();

    this.cabysInput$
      .pipe(
        debounceTime(400),
        distinctUntilChanged(),
        switchMap((term: string) => {
          const t = (term ?? '').trim();
          if (t.length < 3) {
            return of([]);
          }
          this.cabysLoading = true;
          this.cdr.markForCheck();

          return this.haciendaCabys.getCabysByQuery(t, 20).pipe(
            map((body) => mapCabysApiResponseToOptions(body)),
            catchError(() => of([])),
            finalize(() => {
              this.cabysLoading = false;
              this.cdr.markForCheck();
            }),
          );
        }),
        this.untilDestroyed(),
      )
      .subscribe((items) => {
        this.cabysItems = [...items];
        this.cdr.detectChanges();
      });

    this.syncCabysSeleccionFromProducto();

    this.apiService.getAll('categorias/padre')
      .pipe(this.untilDestroyed())
      .subscribe(
      (categorias) => {
        this.categorias = categorias;
        this.normalizarCategoriaProducto();
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('subcategorias')
      .pipe(this.untilDestroyed())
      .subscribe(
      (subcategorias) => {
        this.subcategorias = subcategorias;
        if (this.producto?.id_categoria) {
          this.subcategRes = this.subcategorias.filter((cat: any) => {
            return cat.id_cate_padre == this.producto.id_categoria;
          });
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
      }
    );

        this.apiService.getAll('impuestos').subscribe(impuestos => {
            this.impuestos = (impuestos || []).filter((i: any) => i.aplica_ventas);
            this.inicializarImpuestosProducto();
        }, () => { this.impuestos = []; });

        this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);

    this.intentarCargarBarcodeSugerido();

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

    this.cdr.detectChanges();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['producto']) {
      this.normalizarCategoriaProducto();
      this.inicializarImpuestosProducto();
      this.intentarCargarBarcodeSugerido();
      this.syncCabysSeleccionFromProducto();
      if (this.subcategorias?.length && this.producto?.id_categoria) {
        this.subcategRes = this.subcategorias.filter((cat: any) => {
          return cat.id_cate_padre == this.producto.id_categoria;
        });
      }
      this.cdr.markForCheck();
    }
  }

  /** ng-select bindValue="id" requiere número; la API suele devolver string. */
  private normalizarCategoriaProducto(): void {
    if (this.producto?.id_categoria != null && this.producto.id_categoria !== '') {
      this.producto.id_categoria = Number(this.producto.id_categoria);
    }
    if (this.producto?.id_subcategoria != null && this.producto.id_subcategoria !== '') {
      this.producto.id_subcategoria = Number(this.producto.id_subcategoria);
    }
  }

    /** Sincroniza id_impuestos desde la relación o desde porcentaje_impuesto legacy. */
    private inicializarImpuestosProducto(): void {
        const p = this.producto;
        if (!p) return;

        if (Array.isArray(p.impuestos) && p.impuestos.length > 0) {
            p.id_impuestos = p.impuestos.map((i: any) => i.id);
            return;
        }

        if (Array.isArray(p.id_impuestos) && p.id_impuestos.length > 0) {
            return;
        }

        const pct = p.porcentaje_impuesto;
        if (pct != null && pct !== '' && Number(pct) > 0 && this.impuestos.length > 0) {
            const match = this.impuestos.find((i: any) => Number(i.porcentaje) === Number(pct));
            p.id_impuestos = match ? [match.id] : [];
            return;
        }

        if (pct === 0 || pct === '0') {
            p.id_impuestos = [];
            return;
        }

        const ivaEmpresa = Number(this.usuario?.empresa?.iva ?? 0);
        if (ivaEmpresa > 0 && this.impuestos.length > 0 && (pct == null || pct === '')) {
            const match = this.impuestos.find((i: any) => Number(i.porcentaje) === ivaEmpresa);
            if (match) {
                p.id_impuestos = [match.id];
            }
        }
    }

    public onImpuestosChange(): void {
        this.calPrecioFinal();
    }

    public getImpuestosSeleccionados(): any[] {
        const ids: number[] = Array.isArray(this.producto?.id_impuestos) ? this.producto.id_impuestos : [];
        return this.impuestos.filter((i: any) => ids.includes(i.id));
    }

    /** Precarga el código de barras correlativo desde la API cuando aplica (producto nuevo + opción de empresa). */
    private intentarCargarBarcodeSugerido() {
        const p = this.producto;
        if (!p || p.id || !this.apiService.isBarcodeCorrelativoAutomatico()) {
            return;
        }
        if (p.barcode) {
            this.barcodeCorrelativoPendiente = false;
            return;
        }
        if (!p.id_empresa) {
            return;
        }
        if (!this.barcodeCorrelativoPendiente) {
            return;
        }

        this.barcodeCorrelativoPendiente = false;
        this.apiService.getAll('productos/siguiente-barcode-correlativo').subscribe(
            (res: any) => {
                const valor = res?.barcode ?? res?.codigo;
                if (res?.habilitado && valor != null && p === this.producto && !this.producto.barcode) {
                    this.producto.barcode = String(valor);
                }
            },
            () => {
                this.barcodeCorrelativoPendiente = true;
            }
        );
    }

  esEmpresaCostaRica(): boolean {
    return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
  }

  onCabysSelectChange(item: CabysSelectOption | null): void {
    if (item) {
      this.producto.codigo_cabys = item.codigo;
      this.producto.descripcion_cabys = item.descripcion;
      void this.aplicarImpuestoDesdeCabys(item);
    } else {
      this.producto.codigo_cabys = null;
      this.producto.descripcion_cabys = null;
    }
    this.cdr.markForCheck();
  }

  private async aplicarImpuestoDesdeCabys(item: CabysSelectOption): Promise<void> {
    if (!this.esEmpresaCostaRica()) {
      return;
    }

    const porcentaje = item.impuestoTarifa;
    if (porcentaje == null) {
      return;
    }

    if (porcentaje <= 0) {
      this.seleccionarPorcentajeImpuesto(0);
      return;
    }

    const existente = this.buscarImpuestoPorPorcentaje(porcentaje);
    if (existente) {
      this.seleccionarPorcentajeImpuesto(porcentaje);
      return;
    }

    const nombreSugerido = this.nombreImpuestoSugerido(porcentaje);
    const result = await Swal.fire({
      title: 'Impuesto no configurado',
      html:
        `El CABYS seleccionado indica un impuesto de <strong>${porcentaje}%</strong>, ` +
        `pero no existe un impuesto con ese porcentaje en su cuenta.<br><br>` +
        `¿Desea crear el impuesto <strong>${nombreSugerido}</strong> y aplicarlo a este producto?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, crear y aplicar',
      cancelButtonText: 'No',
    });

    if (!result.isConfirmed) {
      return;
    }

    this.crearYSeleccionarImpuesto(porcentaje, nombreSugerido);
  }

  private buscarImpuestoPorPorcentaje(porcentaje: number): any | undefined {
    return this.impuestos.find((i) => Math.abs(Number(i.porcentaje) - porcentaje) < 0.001);
  }

  private nombreImpuestoSugerido(porcentaje: number): string {
    if (porcentaje <= 0) {
      return 'Exento (0%)';
    }
    return `Impuesto ${porcentaje}%`;
  }

  private seleccionarPorcentajeImpuesto(porcentaje: number): void {
    this.producto.porcentaje_impuesto = porcentaje;
    if (porcentaje > 0) {
      this.calPrecioFinal();
    }
    this.cdr.markForCheck();
  }

  private crearYSeleccionarImpuesto(porcentaje: number, nombre: string): void {
    const payload = {
      nombre,
      porcentaje,
      id_empresa: this.usuario?.id_empresa ?? this.apiService.auth_user()?.id_empresa,
      aplica_ventas: true,
      aplica_gastos: true,
      aplica_compras: true,
    };

    this.apiService.store('impuesto', payload).subscribe({
      next: (impuesto) => {
        this.impuestos = [...this.impuestos, impuesto];
        this.seleccionarPorcentajeImpuesto(porcentaje);
        this.alertService.success(
          'Impuesto creado',
          `Se creó "${impuesto.nombre}" y se aplicó al producto.`,
        );
      },
      error: (err) => this.alertService.error(err),
    });
  }

  private syncCabysSeleccionFromProducto(): void {
    const raw = this.producto?.codigo_cabys;
    const digits = raw != null && raw !== '' ? String(raw).replace(/\D/g, '') : '';
    if (digits.length === 13) {
      const desc = String(this.producto?.descripcion_cabys ?? '').trim();
      this.cabysSeleccionado = {
        codigo: digits,
        descripcion: desc,
        label: desc ? `${digits} — ${desc}` : digits,
        impuestoTarifa: null,
      };
    } else {
      this.cabysSeleccionado = null;
    }
    this.cdr.detectChanges();
  }

  public loadAtributes() {
    // console.log('color1', this.producto.color);
    this.apiService.getAll('atributos')
      .pipe(this.untilDestroyed())
      .subscribe(
      (atributos) => {
        //this.categorias = categorias;
        // console.log('color2', this.producto.color);
        //  recorrer los atributos por tipo 'talla', 'color', 'material'
        this.tallas = atributos.filter((cat: any) => {
          return cat.tipo == 'talla';
        });
        this.colores = atributos.filter((cat: any) => {
          return cat.tipo == 'color';
        });
        this.materiales = atributos.filter((cat: any) => {
          return cat.tipo == 'material';
        });

        this.cdr.detectChanges();
      },
      (error) => {
        this.alertService.error(error);
      }
    );
    //volver a setear los valores
    this.producto.talla = this.producto.talla;
    this.producto.color = this.producto.color;
    this.producto.material = this.producto.material;
  }

  public setCategoria(categoria: any) {
    this.loadCategorias();
    // console.log('entro');
    if (categoria.subcategoria) {
      this.subcategRes.push(categoria);
      this.producto.id_subcategoria = categoria.id;
      this.producto.id_categoria = categoria.subcategoria ? categoria.id_cate_padre : categoria.id;
      this.subcategorias.push(categoria);
    } else {
      this.categorias.push(categoria);
       this.producto.id_categoria = categoria.id;
    }
  }

  public opAvanzadas: boolean = false;

  toggleDiv(): void {
    this.opAvanzadas = !this.opAvanzadas;
    this.cdr.detectChanges();
  }

  public setCompuesto() {
    if (this.producto.tipo == 'Producto') {
      this.producto.tipo = 'Compuesto';
      if (!Array.isArray(this.producto.composiciones)) {
        this.producto.composiciones = [];
      }
    } else {
      this.producto.tipo = 'Producto';
    }
    this.productoActualizado.emit();
    this.cdr.markForCheck();
  }

    public actualizarCostoPromedio(){
        this.producto.costo_promedio = this.producto.costo;
    }

    public actualizarCosto(){
        this.producto.costo = this.producto.costo_promedio;
    }

    /** Suma de tasas de los impuestos seleccionados (cálculo paralelo sobre la base). */
    public getPorcentajeProducto(): number {
        const seleccionados = this.getImpuestosSeleccionados();
        if (seleccionados.length > 0) {
            return seleccionados.reduce((sum: number, i: any) => sum + Number(i.porcentaje || 0), 0);
        }
        const p = this.producto?.porcentaje_impuesto;
        if (p != null && p !== '') return Number(p);
        return Number(this.usuario?.empresa?.iva ?? 0);
    }

    public calPrecioBase(){
        const pct = this.getPorcentajeProducto();
        if (pct <= 0) return;
        this.producto.impuesto = pct / 100;
        this.producto.precio = (this.producto.precio_final / (1 + (this.producto.impuesto * 1))).toFixed(4);
    }

    public calPrecioFinal(){
        const pct = this.getPorcentajeProducto();
        if (pct <= 0) return;
        this.producto.impuesto = pct / 100;
        this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
    }


  public onInventarioPorLotesChange() {
    if (!this.producto.id) {
      return;
    }

    if (!this.producto.inventario_por_lotes) {
      this.onSubmit();
      return;
    }

    this.apiService.getAll(`producto/${this.producto.id}/preview-migracion-lotes`)
      .pipe(this.untilDestroyed())
      .subscribe(
        (preview: any) => {
          if (preview.requiere_migracion) {
            const listaHtml = (preview.bodegas || [])
              .map((b: any) => `<li>${b.nombre_bodega}: <strong>${b.stock_inventario}</strong> uds</li>`)
              .join('');
            Swal.fire({
              title: 'Inventario por lotes',
              html: `
                <p class="text-start mb-2">
                  A partir de ahora este producto manejará su stock <strong>por lotes</strong>.
                  Para no perder las existencias actuales, el sistema trasladará automáticamente
                  ese stock a un <strong>lote inicial</strong> (<em>${preview.numero_lote || 'STOCK-INICIAL'}</em>) en cada bodega.
                </p>
                <p class="text-start mb-2">
                  Después podrá editar ese lote y asignarle el <strong>código de lote</strong>
                  y la <strong>fecha de vencimiento</strong> que correspondan.
                </p>
                <p class="text-start mb-1"><strong>Stock a trasladar</strong> (${preview.total_bodegas} bodega(s), ${preview.total_unidades} uds en total):</p>
                <ul class="text-start mb-0">${listaHtml}</ul>
              `,
              icon: 'info',
              showCancelButton: true,
              confirmButtonText: 'Activar lotes',
              cancelButtonText: 'Cancelar',
            }).then((result) => {
              if (result.isConfirmed) {
                this.onSubmit();
              } else {
                this.producto.inventario_por_lotes = false;
                this.cdr.markForCheck();
              }
            });
            return;
          }
          this.onSubmit();
        },
        (error) => {
          this.producto.inventario_por_lotes = false;
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
  }

  public onSubmit() {
    if (!this.producto.id) {
      if (!this.producto.costo) {
        this.producto.costo = this.producto.costo_promedio;
      }
      if (!this.producto.costo_promedio) {
        this.producto.costo_promedio = this.producto.costo;
      }
    }

    this.guardar = true;
    this.cdr.markForCheck();
    const esNuevo = !this.producto.id;

    const payload = {
      ...this.producto,
      id_impuestos: Array.isArray(this.producto.id_impuestos) ? this.producto.id_impuestos : [],
    };

    this.apiService.store('producto', payload)
      .pipe(
        this.untilDestroyed(),
        finalize(() => {
          this.zone.run(() => {
            this.guardar = false;
            this.cdr.markForCheck();
          });
        })
      )
      .subscribe({
        next: (producto) => {
          if (!esNuevo && producto?.id) {
            this.cacheService.delete(`/producto/${producto.id}`);
          }
          this.cacheService.invalidatePattern('/productos');
          this.cacheService.invalidatePattern('/producto');

          if (esNuevo) {
            this.producto = producto;
          } else {
            Object.assign(this.producto, producto);
          }
          this.productoGuardado.emit(producto);
          this.productoActualizado.emit();

          if (producto.migracion_lotes && (producto.migracion_lotes.lotes_creados > 0 || producto.migracion_lotes.unidades_migradas > 0)) {
            const m = producto.migracion_lotes;
            this.alertService.success(
              'Stock migrado a lotes',
              `Se creó el lote STOCK-INICIAL con ${m.unidades_migradas} unidad(es) en ${m.lotes_creados} bodega(s).`
            );
          }

          const tipo = this.producto.tipo;
          if (tipo === 'Producto' || tipo === 'Compuesto') {
            if (esNuevo) {
              this.router.navigate(['/producto/editar/' + producto.id]);
            }
            this.alertService.success(
              tipo === 'Compuesto' ? 'Producto compuesto guardado' : 'Producto guardado',
              `El ${tipo.toLowerCase()} fue guardado exitosamente.`
            );
          } else if (tipo === 'Servicio') {
            if (esNuevo) {
              this.router.navigate(['/servicio/editar/' + producto.id]);
            }
            this.alertService.success('Servicio guardado', 'El servicio fue guardado exitosamente.');
          } else if (tipo === 'Materia Prima') {
            if (esNuevo) {
              this.router.navigate(['/materias-prima/editar/' + producto.id]);
            }
            this.alertService.success('Materia prima guardada', 'La materia prima fue guardada exitosamente.');
          }
        },
        error: (err) => {
          this.alertService.error(err);
          this.cdr.markForCheck();
        }
      });
  }

    public barcode() {
        const raw = String(this.producto.barcode || this.producto.codigo || '').trim();
        if (!raw) {
            return;
        }
        window.open(
            this.apiService.baseUrl + '/api/barcode/' + encodeURIComponent(raw) + '?token=' + this.apiService.auth_token(),
            '_new',
            'toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900'
        );
    }

  public verificarSiExiste() {
    if (this.producto.nombre) {
      this.apiService
        .getAll('productos', { nombre: this.producto.nombre, estado: 1 })
        .pipe(this.untilDestroyed())
        .subscribe(
          (productos) => {
            if (productos.data[0]) {
              this.alertService.warning(
                '🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' +
                  this.apiService.appUrl +
                  '/producto/editar/' +
                  productos.data[0].id +
                  '">Ver producto</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
              );
            }
            this.loading = false;
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
    }
  }

  // creacion de sku
  private correlativo: number = 1; // Inicialmente, este sería el primer SKU

  public updateInputValue(event: any): void {
    const categoriaSeleccionada = this.categorias.find(
      (c: any) => c.id == this.producto.id_categoria
    );

    const subcategoriaSeleccionada = this.subcategorias.find(
      (s: any) => s.id == this.producto.id_subcategoria
    );

    this.subcategRes = this.subcategorias.filter((cat: any) => {
      return cat.id_cate_padre == event;
    });

    this.producto.codigo = `${
      categoriaSeleccionada?.nombre
        ? categoriaSeleccionada?.nombre.slice(0, 3).toUpperCase()
        : ''
    }${
      subcategoriaSeleccionada?.nombre
        ? subcategoriaSeleccionada?.nombre.slice(0, 3).toUpperCase()
        : ''
    }${this.correlativo.toString().padStart(5, '0')}`;
  }
  //   variantes

  addVariant(): void {
    this.variants.push({ nombre: '', cantidad: 0 });
  }

  removeVariant(index: number): void {
    this.variants.splice(index, 1);
  }
    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

  addAttribute(event: any, tipo: string) {
    switch (tipo) {
      case 'talla': {
        this.producto.talla = event ? event.valor : null;
        break;
      }
      case 'color': {
        this.producto.color = event ? event.valor : null;
        break;
      }
      case 'material': {
        this.producto.material = event ? event.valor : null;
        break;
      }
    }
    this.cdr.detectChanges();
  }

  private loadCategorias() {
    this.apiService.getAll('categorias/padre')
      .pipe(this.untilDestroyed())
      .subscribe(
      (categorias) => {
        this.categorias = categorias;

        // Si ya hay una categoría seleccionada, generar el código
        if (this.producto.id_categoria) {
          this.updateInputValue(this.producto.id_categoria);
        }
        this.cdr.detectChanges();
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    // Cargar subcategorías
    this.apiService.getAll('subcategorias')
      .pipe(this.untilDestroyed())
      .subscribe(
      (subcategorias) => {
        this.subcategorias = subcategorias;
        if (this.producto.id_categoria) {
          this.subcategRes = this.subcategorias.filter((cat: any) => {
            return cat.id_cate_padre == this.producto.id_categoria;
          });

          // Si ya hay una subcategoría seleccionada, actualizar el código
          if (this.producto.id_subcategoria) {
            this.updateInputValue(this.producto.id_categoria);
          }
        }
        this.cdr.detectChanges();
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  openModalAtributo(tipo: string) {
    this.tipoAtributoActual = tipo;
    this.nuevoAtributo = {
      tipo: tipo,
      valor: '',
      // id_empresa: this.usuario.id_empresa,
    };
    this.openModal(this.modalAtributo, {
      class: 'modal-sm',
    });
  }

  guardarAtributo() {
    if (!this.nuevoAtributo.valor) return;

    this.guardandoAtributo = true;

    this.apiService.store('atributos', this.nuevoAtributo)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response) => {
       // this.loadAtributes();

        // console.log('tipo', this.tipoAtributoActual);
        // console.log('response', response);

        // Asignar el nuevo valor según el tipo
        switch (this.tipoAtributoActual) {
          case 'talla':
            //agregar el nuevo valor a la lista de tallas

            this.tallas.push(response);
            this.producto.talla = response.valor;
            break;
          case 'color':
            this.colores.push(response);
            this.producto.color = response.valor;
            break;
          case 'material':
            this.materiales.push(response);
            this.producto.material = response.valor;
            break;
        }

        this.alertService.success(
          'Atributo guardado',
          `El ${this.tipoAtributoActual} fue agregado exitosamente.`
        );

        this.closeModal();
        this.guardandoAtributo = false;
        this.cdr.detectChanges();
      },
      (error) => {
        this.alertService.error(error);
        this.guardandoAtributo = false;
      }
    );
  }

  public isComponenteQuimicoHabilitado(): boolean {
    return this.apiService.isComponenteQuimicoHabilitado();
  }

}
