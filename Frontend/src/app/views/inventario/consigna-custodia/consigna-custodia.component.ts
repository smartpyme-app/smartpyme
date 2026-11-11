import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';

@Component({
  selector: 'app-consigna-custodia',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './consigna-custodia.component.html',
})
export class ConsignaCustodiaComponent implements OnInit {
  public filas: any[] = [];
  public loading = false;
  public moduloActivo = false;
  public guardandoConteo = false;
  public filtros: any = {
    id_cliente: '',
    id_producto: '',
    buscador: '',
  };
  public conteo: any = {
    id_cliente: '',
    id_producto: '',
    id_bodega: '',
    cantidad_fisica: '',
    nota: '',
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService
  ) {}

  ngOnInit() {
    this.funcionalidadesService.verificarAcceso('modulo-consignaciones').subscribe({
      next: (acceso) => {
        this.moduloActivo = acceso;
        if (acceso) {
          this.loadAll();
        }
      },
      error: () => {
        this.moduloActivo = false;
      },
    });
  }

  public loadAll() {
    this.loading = true;
    this.apiService.getAll('productos/consigna-custodia', this.filtros).subscribe({
      next: (data) => {
        this.filas = Array.isArray(data) ? data : data?.data ?? [];
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  public prepararConteo(fila: any) {
    this.conteo = {
      id_cliente: fila.id_cliente,
      id_producto: fila.id_producto,
      id_bodega: fila.id_bodega,
      cantidad_fisica: fila.cantidad,
      nota: '',
      _label: `${this.nombreCliente(fila)} — ${fila.producto?.nombre ?? 'Producto'}`,
    };
  }

  public enviarConteo() {
    this.guardandoConteo = true;
    this.apiService.store('productos/consigna-custodia/conteo', this.conteo).subscribe({
      next: (res) => {
        this.alertService.success('Conteo', res?.mensaje ?? 'Conteo registrado.');
        this.conteo = { id_cliente: '', id_producto: '', id_bodega: '', cantidad_fisica: '', nota: '' };
        this.loadAll();
        this.guardandoConteo = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardandoConteo = false;
      },
    });
  }

  public filasFiltradas(): any[] {
    const q = (this.filtros.buscador ?? '').trim().toLowerCase();
    if (!q) {
      return this.filas;
    }
    return this.filas.filter((f) => {
      const cliente = this.nombreCliente(f).toLowerCase();
      const producto = (f.producto?.nombre ?? '').toLowerCase();
      const codigo = (f.producto?.codigo ?? '').toLowerCase();
      return cliente.includes(q) || producto.includes(q) || codigo.includes(q);
    });
  }

  public nombreCliente(fila: any): string {
    const c = fila?.cliente;
    if (!c) {
      return '—';
    }
    const partes = [c.nombre, c.apellido, c.nombre_comercial || c.nombre_empresa].filter(
      (x) => x && String(x).trim() !== ''
    );

    return partes.length ? partes.join(' ') : '—';
  }
}
