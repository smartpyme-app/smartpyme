import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-kardex-farmacias',
  templateUrl: './kardex-farmacias.component.html',
})
export class KardexFarmaciasComponent implements OnInit {

  public producto: any = [];
  public productos: any[] = [];
  public bodegas: any[] = [];
  public lotes: any[] = [];
  public filtros: any = {};
  public loading: boolean = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit() {
    this.filtros.inicio = this.apiService.date();
    this.filtros.fin = this.apiService.date();
    this.filtros.id_inventario = this.apiService.auth_user().id_sucursal;
    this.filtros.lote_id = '';
    this.filtros.detalle = '';
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 10;

    const id = +this.route.snapshot.paramMap.get('id')!;
    if (!isNaN(id)) {
      this.filtros.id_producto = id;
      this.loadAll();
    }

    this.apiService.getAll('bodegas/list').subscribe(bodegas => {
      this.bodegas = bodegas;
      this.loading = false;
    }, error => { this.alertService.error(error); this.loading = false; });
  }

  public loadAll() {
    if (this.producto?.inventario_por_lotes && this.isLotesActivo()) {
      if (!this.filtros.id_inventario || !this.filtros.lote_id) {
        this.producto.movimientos = [];
        return;
      }
    }

    this.loading = true;
    this.apiService.getAll('productos/kardex', this.filtros).subscribe(producto => {
      this.producto = producto;
      if (!this.producto?.inventario_por_lotes || !this.isLotesActivo()) {
        this.lotes = [];
      }
      this.loading = false;
    }, error => { this.alertService.error(error); this.loading = false; });
  }

  public isLotesActivo(): boolean {
    return this.apiService.isLotesActivo();
  }

  public cargarLotes() {
    if (!this.filtros.id_producto || !this.filtros.id_inventario) {
      this.lotes = [];
      this.filtros.lote_id = '';
      return;
    }

    const loteIdActual = this.filtros.lote_id ? String(this.filtros.lote_id) : '';

    this.apiService.getAll(`lotes/producto/${this.filtros.id_producto}`, {
      id_bodega: this.filtros.id_inventario
    }).subscribe((lotes: any[]) => {
      this.lotes = Array.isArray(lotes) ? lotes : [];

      if (loteIdActual) {
        const loteExiste = this.lotes.some((l: any) => String(l.id) === loteIdActual);
        if (loteExiste) {
          this.filtros.lote_id = loteIdActual;
        } else {
          this.filtros.lote_id = '';
        }
      }
    }, error => {
      this.lotes = [];
      if (error) {
        this.filtros.lote_id = '';
      }
    });
  }

  selectProducto(producto: any) {
    this.filtros.id_producto = producto.id;
    this.filtros.lote_id = '';
    this.producto = producto;
    if (this.producto?.inventario_por_lotes && this.isLotesActivo()) {
      this.cargarLotes();
    } else {
      this.lotes = [];
      this.loadAll();
    }
  }

  public onBodegaChange() {
    if (this.producto?.inventario_por_lotes && this.isLotesActivo()) {
      this.filtros.lote_id = '';
      this.cargarLotes();
      this.producto.movimientos = [];
    } else {
      this.loadAll();
    }
  }

  /** Número del lote seleccionado (para encabezado tarjeta de control) */
  get loteSeleccionadoNumero(): string {
    if (!this.filtros.lote_id || !this.lotes?.length) {
      return '—';
    }
    const lote = this.lotes.find((l: any) => String(l.id) === String(this.filtros.lote_id));
    return lote?.numero_lote ?? '—';
  }

  public descargarKardex() {
    this.apiService.export('productos/kardex/exportar', this.filtros).subscribe((data: Blob) => {
      const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = this.apiService.slug(this.producto.nombre) + '-kardex-farmacia.xlsx';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    }, (error) => { console.error('Error al exportar kardex:', error); });
  }
}
