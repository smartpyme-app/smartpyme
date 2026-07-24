import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { TransformacionService } from './transformacion.service';
import { BuscadorProductosComponent } from '@shared/parts/buscador-productos/buscador-productos.component';
import { TranslatePipe } from '@ngx-translate/core';
import { getEmpresaCurrencySymbol } from '@helpers/currency-format.helper';

@Component({
  selector: 'app-transformacion-crear',
  templateUrl: './transformacion-crear.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, TooltipModule, BuscadorProductosComponent, TranslatePipe],
})
export class TransformacionCrearComponent implements OnInit {

  public bodegas: any[] = [];
  public id_bodega: string = '';
  public observacion: string = '';
  
  public productoOrigen: any = null;
  public cantidadOrigen: number = 0;
  
  public productosDestino: any[] = [];
  public saving: boolean = false;

  public categorias: any = [];
  public medidas: any = [];
  public impuestos: any[] = [];
  public usuario: any = {};

  get currencySymbol(): string {
    return getEmpresaCurrencySymbol(this.usuario?.empresa ?? this.apiService.auth_user()?.empresa);
  }
  public nuevoProducto: any = {
      nombre: '', codigo: '', id_categoria: null, medida: null,
      costo: null, costo_promedio: 0, precio: null, precio_final: null,
      porcentaje_impuesto: null, impuesto: 0, tipo: 'Producto'
  };
  public modalRef?: BsModalRef;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private transformacionService: TransformacionService,
    private modalService: BsModalService
  ) { }

  ngOnInit(): void {
    this.cargarBodegas();
    this.usuario = this.apiService.auth_user();
    
    this.apiService.getAll('categorias/list').subscribe(res => {
      this.categorias = res;
    });

    this.apiService.getAll('impuestos').subscribe(res => {
      this.impuestos = (res || []).filter((i: any) => i.aplica_ventas);
    });

    this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);
  }

  cargarBodegas() {
    this.apiService.getAll('bodegas/list').subscribe({
      next: (res: any) => {
        this.bodegas = res.data || res;
      },
      error: (err) => {
        this.alertService.error('Error al cargar las bodegas.');
      }
    });
  }

  seleccionarOrigen(producto: any) {
    if (!producto) return;
    
    // Validar que no esté en destino
    const existeEnDestino = this.productosDestino.find(p => p.id === producto.id);
    if (existeEnDestino) {
      this.alertService.error('El producto de origen no puede ser el mismo que un producto de destino.');
      return;
    }

    // Extraer el stock real de la bodega seleccionada
    let stockActual = 0;
    if (producto.inventarios && producto.inventarios.length > 0) {
      const inv = producto.inventarios.find((i: any) => i.id_bodega == this.id_bodega);
      if (inv) {
        stockActual = Number(inv.stock);
      }
    } else if (producto.stock != null) {
      stockActual = Number(producto.stock);
    }
    
    producto.stock = stockActual;
    
    this.productoOrigen = producto;
    this.cantidadOrigen = 1;
    this.recalcularCantidadesDestino();
  }

  quitarOrigen() {
    this.productoOrigen = null;
    this.cantidadOrigen = 0;
  }

  agregarDestino(producto: any) {
    if (!producto) return;

    if (this.productoOrigen && this.productoOrigen.id === producto.id) {
      this.alertService.error('El producto de destino no puede ser el mismo que el producto de origen.');
      return;
    }

    const existe = this.productosDestino.find(p => p.id === producto.id);
    if (existe) {
      this.alertService.warning('Atención', 'Este producto ya fue agregado al destino.');
      return;
    }

    this.productosDestino.push({
      ...producto,
      factor_conversion: 1,
      cantidad_ingreso: this.calcularCantidadIngreso(1)
    });
  }

  calcularCantidadIngreso(factor: number): number {
    const origen = Number(this.cantidadOrigen) || 0;
    const f = Number(factor) || 0;
    if (origen <= 0 || f <= 0) return 0;
    return Number((origen * f).toFixed(4));
  }

  actualizarCantidadDestino(prod: any) {
    prod.cantidad_ingreso = this.calcularCantidadIngreso(prod.factor_conversion);
  }

  recalcularCantidadesDestino() {
    this.productosDestino.forEach(p => this.actualizarCantidadDestino(p));
  }

  onCantidadOrigenChange() {
    this.recalcularCantidadesDestino();
  }

  onFactorChange(prod: any) {
    this.actualizarCantidadDestino(prod);
  }

  eliminarDestino(index: number) {
    this.productosDestino.splice(index, 1);
  }

  guardar() {
    if (!this.id_bodega) {
      this.alertService.error('Debe seleccionar una bodega.');
      return;
    }

    if (!this.productoOrigen || this.cantidadOrigen <= 0) {
      this.alertService.error('Debe seleccionar un producto de origen con una cantidad mayor a 0.');
      return;
    }

    if (this.cantidadOrigen > (this.productoOrigen.stock || 0)) {
      this.alertService.error('La cantidad a sacar no puede superar el stock del producto de origen.');
      return;
    }

    if (this.productosDestino.length === 0) {
      this.alertService.error('Debe agregar al menos un producto de destino.');
      return;
    }

    const factoresInvalidos = this.productosDestino.filter(p => !p.factor_conversion || p.factor_conversion <= 0);
    if (factoresInvalidos.length > 0) {
      this.alertService.error('Todos los productos de destino deben tener un factor de conversión mayor a 0.');
      return;
    }

    this.recalcularCantidadesDestino();

    const detallesDestinoInvalidos = this.productosDestino.filter(p => !p.cantidad_ingreso || p.cantidad_ingreso <= 0);
    if (detallesDestinoInvalidos.length > 0) {
      this.alertService.error('La cantidad a ingresar debe ser mayor a 0. Verifique la cantidad de origen y el factor de conversión.');
      return;
    }

    this.saving = true;

    const payload = {
      id_bodega: this.id_bodega,
      observacion: this.observacion,
      detalles: [
        {
          id_producto: this.productoOrigen.id,
          cantidad: this.cantidadOrigen,
          tipo: 'SALIDA'
        },
        ...this.productosDestino.map(p => ({
          id_producto: p.id,
          cantidad: p.cantidad_ingreso,
          tipo: 'ENTRADA'
        }))
      ]
    };

    this.transformacionService.guardar(payload).subscribe({
      next: (res) => {
        this.saving = false;
        this.alertService.success('Éxito', 'Conversión de productos guardada correctamente.');
        this.limpiarFormulario();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error('Ocurrió un error al guardar la conversión.');
        console.error(err);
      }
    });
  }

  limpiarFormulario() {
    this.id_bodega = '';
    this.observacion = '';
    this.productoOrigen = null;
    this.cantidadOrigen = 0;
    this.productosDestino = [];
  }

  alCambiarBodega() {
    this.productoOrigen = null;
    this.cantidadOrigen = 0;
    this.productosDestino = [];
  }

  abrirModal(template: TemplateRef<any>) {
    this.nuevoProducto = {
      nombre: '', codigo: '', id_categoria: null, medida: null,
      costo: null, costo_promedio: 0, precio: null, precio_final: null,
      porcentaje_impuesto: null, impuesto: 0, tipo: 'Producto'
    };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  public getPorcentajeProducto(): number {
    const p = this.nuevoProducto?.porcentaje_impuesto;
    if (p != null && p !== '') return Number(p);
    return Number(this.usuario?.empresa?.iva ?? 0);
  }

  calPrecioBase() {
    const pct = this.getPorcentajeProducto();
    if (pct <= 0) {
      this.nuevoProducto.precio = this.nuevoProducto.precio_final;
      return;
    }
    this.nuevoProducto.impuesto = pct / 100;
    this.nuevoProducto.precio = (this.nuevoProducto.precio_final / (1 + (this.nuevoProducto.impuesto * 1))).toFixed(4);
  }

  calPrecioFinal() {
    const pct = this.getPorcentajeProducto();
    if (pct <= 0) {
      this.nuevoProducto.precio_final = this.nuevoProducto.precio;
      return;
    }
    this.nuevoProducto.impuesto = pct / 100;
    this.nuevoProducto.precio_final = ((this.nuevoProducto.precio * 1) + (this.nuevoProducto.precio * this.nuevoProducto.impuesto)).toFixed(2);
  }

  crearProductoRapido() {
    if (!this.nuevoProducto.nombre || !this.nuevoProducto.id_categoria || !this.nuevoProducto.medida || this.nuevoProducto.costo === null) {
      this.alertService.error('Debes completar los campos obligatorios: Nombre, Categoría, U. de Medida y Costo.');
      return;
    }

    if (!this.nuevoProducto.costo_promedio) {
      this.nuevoProducto.costo_promedio = this.nuevoProducto.costo;
    }

    this.saving = true;

    // Agregar el id_empresa obligatorio
    this.nuevoProducto.id_empresa = this.usuario?.id_empresa;

    this.apiService.store('producto', this.nuevoProducto).subscribe({
      next: (res) => {
        this.saving = false;
        this.alertService.success('Éxito', 'Producto creado exitosamente.');
        this.agregarDestino(res);
        this.modalRef?.hide();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error('Error al crear producto.');
      }
    });
  }
}
