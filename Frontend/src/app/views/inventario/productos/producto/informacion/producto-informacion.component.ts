import {
  Component,
  OnInit,
  TemplateRef,
  Input,
  ViewChild,
  DestroyRef,
  inject,
} from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { CrearCategoriaComponent } from '@shared/modals/crear-categoria/crear-categoria.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { ChangeDetectionStrategy, ChangeDetectorRef, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TagInputModule } from 'ngx-chips';
import { NgSelectModule } from '@ng-select/ng-select';
import { finalize } from 'rxjs/operators';
import { subscriptionHelper } from '@shared/utils/subscription.helper';


@Component({
    selector: 'app-producto-informacion',
    templateUrl: './producto-informacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TagInputModule, NgSelectModule, CrearCategoriaComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class ProductoInformacionComponent extends BaseModalComponent implements OnInit {
  @ViewChild('modalAtributo') modalAtributo!: TemplateRef<any>;
  @Input() producto: any = {};
  public categorias: any = [];
  public subcategorias: any = [];
  public subcategRes: any = [];
  public proveedores: any = [];
  public usuario: any = {};
  public categoria: any = {};
  public bodegas: any = [];
  public medidas: any = [];
  public override loading = false;
  public guardar = false;
  public variants: Array<{ nombre: string; cantidad: number }> = [];
  public tallas: any = [];
  public colores: any = [];
  public materiales: any = [];

  tipoAtributoActual: string = '';
  nuevoAtributo: any = {};
  guardandoAtributo: boolean = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef,
    private zone: NgZone
  ) {
    super(modalManager, alertService);
    // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    this.addVariant();
  }

  ngOnInit() {
    this.loadAtributes();
    this.usuario = this.apiService.auth_user();

    this.apiService.getAll('categorias/padre')
      .pipe(this.untilDestroyed())
      .subscribe(
      (categorias) => {
        this.categorias = categorias;
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
        this.subcategRes = this.subcategorias.filter((cat: any) => {
          return cat.id_cate_padre == this.producto.id_categoria;
        });
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);

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

    // this.apiService.getAll('bodegas').subscribe(
    //   (bodegas) => {
    //     this.bodegas = bodegas;
    //     this.loading = false;
    //   },
    //   (error) => {
    //     this.alertService.error(error);
    //     this.loading = false;
    //   }
    // );

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
    } else {
      this.producto.tipo = 'Producto';
    }
  }

    public actualizarCostoPromedio(){
        this.producto.costo_promedio = this.producto.costo;
    }

    public actualizarCosto(){
        this.producto.costo = this.producto.costo_promedio;
    }

    // public calPrecioBase(){
    //     if(this.usuario.empresa.iva > 0){
    //         this.producto.impuesto = this.usuario.empresa.iva / 100;
    //         this.producto.precio = (this.producto.precio_final / (1 + (this.producto.impuesto * 1))).toFixed(4);
    //     }
    // }

    public calPrecioBase() {
      if (this.usuario.empresa.iva > 0) {
        this.producto.impuesto = this.usuario.empresa.iva / 100;
        this.producto.precio = (
          this.producto.precio_final /
          (1 + this.producto.impuesto * 1)
        ).toFixed(4);
      }
    }

  public calPrecioFinal() {
    if (this.usuario.empresa.iva > 0) {
      this.producto.impuesto = this.usuario.empresa.iva / 100;
      this.producto.precio_final = (
        this.producto.precio * 1 +
        this.producto.precio * this.producto.impuesto
      ).toFixed(2);
    }
  }

  public onSubmit() {
    this.guardar = true;
    this.cdr.markForCheck();
    this.apiService.store('producto', this.producto)
      .pipe(
        this.untilDestroyed(),
        finalize(() => {
          // Si tu ApiService corre fuera de Angular, asegúrate de volver a la zona:
          this.zone.run(() => {
            this.guardar = false;
            this.cdr.markForCheck();
          });
        })
      )
      .subscribe({
        next: (producto) => {
          if (!this.producto.id) this.producto = producto;

          // Navegación + alertas
          const tipo = this.producto.tipo;
          if (tipo === 'Producto' || tipo === 'Compuesto') {
            this.router.navigate(['/producto/editar/' + producto.id]);
            this.alertService.success(
              tipo === 'Compuesto' ? 'Producto compuesto guardado' : 'Producto guardado',
              `El ${tipo.toLowerCase()} fue guardado exitosamente.`
            );
          } else if (tipo === 'Servicio') {
            this.router.navigate(['/servicio/editar/' + producto.id]);
            this.alertService.success('Servicio guardado', 'El servicio fue guardado exitosamente.');
          } else if (tipo === 'Materia Prima') {
            this.router.navigate(['/materias-prima/editar/' + producto.id]);
            this.alertService.success('Materia prima guardada', 'La materia prima fue guardada exitosamente.');
          }
        },
        error: (err) => {
          this.alertService.error(err);
          // `guardar` se apaga en finalize()
        }
      });
  }

  public barcode() {
    var ventana = window.open(
      this.apiService.baseUrl +
        '/api/barcode/' +
        this.producto.barcode +
        '?token=' +
        this.apiService.auth_token(),
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
}
