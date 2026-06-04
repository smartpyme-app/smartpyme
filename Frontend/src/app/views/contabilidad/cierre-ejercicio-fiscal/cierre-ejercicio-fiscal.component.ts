import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-cierre-ejercicio-fiscal',
  templateUrl: './cierre-ejercicio-fiscal.component.html',
  styleUrls: ['./cierre-ejercicio-fiscal.component.scss'],
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
})
export class CierreEjercicioFiscalComponent implements OnInit {
  public selectedAnio: number = new Date().getFullYear();
  public anios: number[] = [];
  public cargando = false;
  public guardandoConfig = false;
  public estado: any = null;
  public catalogo: any[] = [];

  public mesInicioEjercicio: number = 1;
  public idCuentaCierre: number | null = null;
  private empresaId: number | null = null;
  private empresa: any = null;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router
  ) {}

  ngOnInit(): void {
    const y = new Date().getFullYear();
    this.anios = [];
    for (let i = y - 5; i <= y + 1; i++) {
      this.anios.push(i);
    }
    this.loadCatalogo();
    this.loadEmpresa();
  }

  puedeReabrirContabilidad(): boolean {
    const usuario = this.apiService.auth_user();
    if (usuario?.tipo === 'Administrador') {
      return true;
    }
    if (this.apiService.verifyRoleAdmin()) {
      return true;
    }
    try {
      const raw = localStorage.getItem('SP_user_permissions');
      if (raw) {
        const role = JSON.parse(raw).role;
        return role === 'admin' || role === 'super_admin';
      }
    } catch {
      return false;
    }
    return false;
  }

  /**
   * Mismo origen que partidas, configuración contable, etc.: lista plana de cuentas.
   * No usar catalogo/cuentas aquí: es index paginado { data, total, ... }, no un array.
   */
  loadCatalogo(): void {
    this.apiService.getAll('catalogo/list').subscribe(
      (rows: any) => {
        const list = Array.isArray(rows) ? rows : [];
        this.catalogo = list.slice().sort((a: any, b: any) =>
          String(a.codigo ?? '').localeCompare(String(b.codigo ?? ''), undefined, { numeric: true })
        );
      },
      (err: any) => {
        this.catalogo = [];
        this.alertService.error(err);
      }
    );
  }

  loadEmpresa(): void {
    const u = this.apiService.auth_user();
    if (!u?.id_empresa) {
      return;
    }
    this.empresaId = u.id_empresa;
    this.apiService.read('empresa/', u.id_empresa).subscribe(
      (e: any) => {
        this.empresa = e;
        this.mesInicioEjercicio = Number(e.mes_inicio_ejercicio_fiscal) || 1;
        this.idCuentaCierre = e.id_cuenta_cierre_resultados
          ? Number(e.id_cuenta_cierre_resultados)
          : null;
        this.cargarEstado();
      },
      () => this.alertService.error('No se pudo cargar la empresa')
    );
  }

  guardarConfiguracionFiscal(): void {
    if (!this.empresa || !this.empresaId) {
      return;
    }
    this.guardandoConfig = true;
    const payload = {
      ...this.empresa,
      id: this.empresaId,
      mes_inicio_ejercicio_fiscal: this.mesInicioEjercicio,
      id_cuenta_cierre_resultados: this.idCuentaCierre
    };
    this.apiService.store('empresa', payload).subscribe(
      (e: any) => {
        this.empresa = e;
        this.guardandoConfig = false;
        this.alertService.success('Listo', 'Configuración guardada');
        this.cargarEstado();
        this.actualizarEmpresaEnSesionDesdeApi(e);
      },
      (err: any) => {
        this.guardandoConfig = false;
        this.alertService.error(err);
      }
    );
  }

  cargarEstado(): void {
    this.cargando = true;
    this.apiService
      .getAll(`partidas/ejercicio-fiscal/estado?anio_referencia=${this.selectedAnio}`)
      .subscribe(
        (data: any) => {
          this.estado = data;
          this.cargando = false;
        },
        (err: any) => {
          this.cargando = false;
          this.alertService.error(err);
        }
      );
  }

  nombreMes(m: number): string {
    const meses = [
      'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    return meses[m - 1] || String(m);
  }

  /**
   * Requisitos: ejercicio no cerrado fiscalmente, cuenta configurada, 11 primeros meses cerrados.
   * El último mes puede estar cerrado (cierre mensual previo); el servidor lo reabre si hace falta.
   */
  puedeCerrarEjercicio(): boolean {
    if (!this.estado || this.estado.ejercicio_cerrado) {
      return false;
    }
    if (this.estado.tiene_partida_cierre_ejercicio) {
      return false;
    }
    if (!this.estado.configuracion_ok) {
      return false;
    }
    if (!this.estado.once_primeros_meses_cerrados) {
      return false;
    }
    return true;
  }

  /** Texto para deshabilitar el botón (solo casos raros). */
  mensajeBloqueoCierre(): string | null {
    if (!this.estado || this.estado.ejercicio_cerrado) {
      return null;
    }
    if (this.estado.tiene_partida_cierre_ejercicio) {
      return 'Ya existe partida de cierre de ejercicio para este año; si el sistema no marca el ejercicio como cerrado, contacte soporte.';
    }
    return null;
  }

  cerrarEjercicio(): void {
    if (!this.puedeCerrarEjercicio()) {
      return;
    }
    Swal.fire({
      title: 'Cerrar ejercicio fiscal',
      html:
        `<p>Se generará el <strong>asiento de cierre de resultados</strong> y se cerrará el último mes del ejercicio <strong>${this.selectedAnio}</strong> ` +
        `(${this.nombreMes(this.estado?.ultimo_mes?.month)} ${this.estado?.ultimo_mes?.year}).</p>` +
        (this.estado?.ultimo_mes_cerrado
          ? `<p class="text-start small text-warning"><strong>Nota:</strong> si ese mes (y meses posteriores) figuran cerrados por cierre mensual, el sistema los reabrirá en cadena solo lo necesario, registrará el asiento de cierre y volverá a cerrar el último mes del ejercicio; los meses auxiliares reabiertos pueden quedar abiertos hasta que los cierre de nuevo si hace falta.</p>`
          : '') +
        `<p>¿Continuar?</p>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cerrar',
      cancelButtonText: 'Cancelar'
    }).then((res) => {
      if (!res.isConfirmed) {
        return;
      }
      this.cargando = true;
      this.apiService
        .store('partidas/ejercicio-fiscal/cerrar', {
          anio_referencia: this.selectedAnio
        })
        .subscribe(
          (out: any) => {
            this.cargando = false;
            const base = out.message || 'Ejercicio cerrado';
            const extra =
              out.id_partida_cierre != null
                ? ` Asiento registrado: partida #${out.id_partida_cierre} (enlace debajo en esta pantalla).`
                : '';
            this.alertService.success('Ejercicio fiscal', base + extra);
            this.cargarEstado();
          },
          (err: any) => {
            this.cargando = false;
            this.alertService.error(err);
          }
        );
    });
  }

  reabrirEjercicio(): void {
    if (!this.puedeReabrirContabilidad() || !this.estado?.ejercicio_cerrado) {
      return;
    }
    Swal.fire({
      title: 'Reabrir ejercicio fiscal',
      html: `
        <p class="text-start">Elija cómo deshacer el cierre:</p>
        <ul class="text-start small">
          <li><strong>Partida de reversa:</strong> mantiene el asiento original y crea uno que lo invierte.</li>
          <li><strong>Eliminar partida:</strong> borra el asiento de cierre (use solo si procede en auditoría).</li>
        </ul>
      `,
      icon: 'question',
      showDenyButton: true,
      showCancelButton: true,
      confirmButtonText: 'Reversar',
      denyButtonText: 'Eliminar partida',
      cancelButtonText: 'Cancelar'
    }).then((res) => {
      let modo: 'reversa' | 'eliminar' | null = null;
      if (res.isConfirmed) {
        modo = 'reversa';
      } else if (res.isDenied) {
        modo = 'eliminar';
      }
      if (!modo) {
        return;
      }
      this.cargando = true;
      this.apiService
        .store('partidas/ejercicio-fiscal/reabrir', {
          anio_referencia: this.selectedAnio,
          modo
        })
        .subscribe(
          (out: any) => {
            this.cargando = false;
            this.alertService.success('Ejercicio fiscal', out.message || 'Ejercicio reabierto');
            this.cargarEstado();
          },
          (err: any) => {
            this.cargando = false;
            this.alertService.error(err);
          }
        );
    });
  }

  volverPartidas(): void {
    this.router.navigate(['/contabilidad/partidas']);
  }

  /** Sincroniza `empresa` en localStorage sin depender de métodos opcionales de ApiService. */
  private actualizarEmpresaEnSesionDesdeApi(empresa: any): void {
    try {
      const u = this.apiService.auth_user();
      if (u) {
        u.empresa = empresa;
        localStorage.setItem('SP_auth_user', JSON.stringify(u));
      }
    } catch {
      /* ignore */
    }
  }
}
