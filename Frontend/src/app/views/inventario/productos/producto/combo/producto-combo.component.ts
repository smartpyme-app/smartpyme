import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';
import { SumPipe } from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';

@Component({
  selector: 'app-producto-combo',
  templateUrl: './producto-combo.component.html',
  providers: [SumPipe]
})
export class ProductoComboComponent implements OnInit {

  producto!: FormGroup;
  categorias: any = [];
  usuario: any = {};
  bodegas: any = [];
  loading = false;
  guardar = false;
  variants: Array<{ nombre: string, cantidad: number }> = [];
  get formValue() {
    return this.producto?.getRawValue();
  }
  get detalles() {
    return this.producto?.get('detalles')?.value;
  }
  private correlativo: number = 1; // Inicialmente, este sería el primer SKU
  mode: "create" | "edit" | "show" = "create";
  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private sumPipe: SumPipe,
    private _fb: FormBuilder
  ) {
    this.addVariant();
  }

  ngOnInit() {

    this.producto = this._fb.group({
      id: [null],
      nombre: ['', Validators.required],
      codigo_combo: ['', Validators.required],
      descripcion: ['', Validators.required],
      impuesto: [null],
      precio: [null],
      costo: [{ value: null, disabled: true }],
      precio_final: [null],
      detalles: [[], [Validators.required, Validators.minLength(1)]],
    });
    this.usuario = this.apiService.auth_user();

    this.apiService.getAll('bodegas/list').subscribe(bodegas => {
      this.bodegas = bodegas;
    }, error => { this.alertService.error(error); });

    this.route.params.subscribe((params: any) => {
      if (!params.edit_id && !params.detail_id) return;

      let id = params.edit_id || params.detail_id;
      this.loading = true;
      this.apiService.read('combos/get/', id).subscribe(producto => {
        let data = {
          id: producto.id,
          nombre: producto.nombre,
          codigo_combo: producto.codigo_combo,
          descripcion: producto.descripcion,
          impuesto: producto.impuesto,
          costo: producto.costo,
          precio_final: producto.precio_total,
          precio: producto.precio,
          detalles: producto.detalles.map((detalle: any) => {
            return {
              id: detalle.id,
              cantidad: detalle.cantidad,
              nombre_producto: detalle.producto.nombre,
              descripcion: detalle.producto.descripcion,
              id_producto: detalle.id_producto,
              cantidad_combo: detalle.cantidad_combo,
              precio: detalle.precio,
              costo: detalle.costo,
              total: detalle.costo * detalle.cantidad,
              img: detalle.producto.img,
            }
          }),
        }
        this.producto.patchValue(data);
        this.sumTotal();
        this.loading = false;

        if (params.edit_id) {
          this.mode = "edit";
        }
        if (params.detail_id) {
          this.mode = "show";
          this.producto.disable();
        }
      }, error => { this.alertService.error(error); });


    });
  }

  // CALCULO DEL STOCK MULTIPLICADO
  public calCantidadenCombo() {
    if (this.formValue.stock > 0) {


      this.producto.get("detalles")?.setValue(this.detalles.map((detalle: any) => {
        detalle.cantidad_combo = detalle.cantidad * this.formValue.stock;
        return detalle;
      }));
    }
  }

  public calPrecioFinal() {
    if (this.usuario.empresa.iva > 0) {
      this.producto.get("impuesto")?.setValue(this.usuario.empresa.iva / 100);
      this.producto.get("precio_final")?.setValue(
        (
          (this.formValue.precio * 1) +
          (this.formValue.precio * this.formValue.impuesto)
        ).toFixed(2));
    }
  }


  public onSubmit() {
    this.guardar = true;
    // this.producto.codigo = "CMPKIT" + this.producto.codigo;


    if (this.mode == "create") {
      this.apiService.store('combos/crear', this.producto.getRawValue()).subscribe(producto => {
        this.guardar = false;
        if (!this.formValue.id) {
          this.producto = producto;
        }
        this.router.navigate(['/producto/combos']);

      }, error => { this.alertService.error(error); this.guardar = false; });
    }
    else {
      this.apiService.store('combos/actualizar', this.producto.getRawValue()).subscribe(producto => {
        this.guardar = false;
        if (!this.formValue.id) {
          this.producto = producto;
        }
        this.router.navigate(['/producto/combos']);

      }, error => { this.alertService.error(error); this.guardar = false; });
    }

  }

  public barcode() {
    var ventana = window.open(this.apiService.baseUrl + "/api/barcode/" + this.formValue.codigo + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
  }

  public verificarSiExiste() {
    if (this.formValue.nombre) {
      this.apiService.getAll('productos', { nombre: this.formValue.nombre, estado: 1, }).subscribe(productos => {
        if (productos.data[0]) {
          this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
            'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' + this.apiService.appUrl + '/producto/editar/' + productos.data[0].id + '">Ver producto</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
          );
        }
        this.loading = false;
      }, error => { this.alertService.error(error); this.loading = false; });
    }
  }

  public sumTotal() {
    this.producto.get("costo")?.setValue((parseFloat(this.sumPipe.transform(this.detalles, 'total'))).toFixed(2));
  }

  public updatecompra(producto: any) {

    this.producto.get("detalles")?.setValue(producto.detalles);

    this.sumTotal();
  }
  addVariant(): void {
    this.variants.push({ nombre: '', cantidad: 0 });
  }

  removeVariant(index: number): void {
    this.variants.splice(index, 1);
  }


}
