import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BonoRegla, BonoReglaPayload, BonoTramo, BonosService } from '@services/bonos.service';

@Component({
  selector: 'app-bonos-reglas',
  standalone: false,
  templateUrl: './reglas.component.html'
})
export class ReglasComponent implements OnInit {
  reglas: BonoRegla[] = [];
  vendedores: { id: number; name: string }[] = [];
  loading = false;
  saving = false;
  filtroActivo: '' | 'true' | 'false' = '';
  mostrarFormulario = false;
  editandoId: number | null = null;

  form: {
    nombre: string;
    tipo: 'meta_fija' | 'escalonado';
    ventana: string;
    activo: boolean;
    alcance: 'global' | 'vendedores';
    id_vendedores: number[];
    meta: number | null;
    bono: number | null;
    tramos: BonoTramo[];
  } = this.formularioVacio();

  constructor(
    private bonosService: BonosService,
    private alertService: AlertService,
    private apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.loadVendedores();
    this.loadReglas();
  }

  loadVendedores(): void {
    this.apiService.getAll('usuarios/list').subscribe({
      next: (usuarios: any) => {
        this.vendedores = Array.isArray(usuarios) ? usuarios : (usuarios?.data ?? []);
      },
      error: () => {
        this.vendedores = [];
      }
    });
  }

  loadReglas(): void {
    this.loading = true;
    const activo = this.filtroActivo === '' ? undefined : this.filtroActivo === 'true';
    this.bonosService.getReglas(activo).subscribe({
      next: (response) => {
        this.reglas = response.data ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  nuevaRegla(): void {
    this.editandoId = null;
    this.form = this.formularioVacio();
    this.mostrarFormulario = true;
  }

  editarRegla(regla: BonoRegla): void {
    this.editandoId = regla.id;
    this.form = {
      nombre: regla.nombre,
      tipo: regla.tipo,
      ventana: regla.ventana || 'mensual',
      activo: regla.activo,
      alcance: regla.alcance || 'global',
      id_vendedores: regla.id_vendedores?.length ? [...regla.id_vendedores] : [],
      meta: regla.config?.meta ?? null,
      bono: regla.config?.bono ?? null,
      tramos: regla.config?.tramos?.length
        ? regla.config.tramos.map((t) => ({ meta: t.meta, bono: t.bono }))
        : [{ meta: 0, bono: 0 }]
    };
    this.mostrarFormulario = true;
  }

  cancelarFormulario(): void {
    this.mostrarFormulario = false;
    this.editandoId = null;
    this.form = this.formularioVacio();
  }

  agregarTramo(): void {
    this.form.tramos.push({ meta: 0, bono: 0 });
  }

  quitarTramo(index: number): void {
    if (this.form.tramos.length > 1) {
      this.form.tramos.splice(index, 1);
    }
  }

  toggleVendedor(id: number, checked: boolean): void {
    if (checked) {
      if (!this.form.id_vendedores.includes(id)) {
        this.form.id_vendedores = [...this.form.id_vendedores, id];
      }
      return;
    }
    this.form.id_vendedores = this.form.id_vendedores.filter((v) => v !== id);
  }

  vendedorSeleccionado(id: number): boolean {
    return this.form.id_vendedores.includes(id);
  }

  guardarRegla(): void {
    if (!this.form.nombre.trim()) {
      this.alertService.warning('Atención', 'Ingrese un nombre para la regla.');
      return;
    }

    const payload = this.buildPayload();
    if (!payload) {
      return;
    }

    this.saving = true;
    const request = this.editandoId
      ? this.bonosService.actualizarRegla(this.editandoId, payload)
      : this.bonosService.crearRegla(payload);

    request.subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Regla guardada.');
        this.saving = false;
        this.cancelarFormulario();
        this.loadReglas();
      },
      error: (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    });
  }

  desactivarRegla(regla: BonoRegla): void {
    if (!confirm(`¿Desactivar la regla "${regla.nombre}"?`)) {
      return;
    }

    this.bonosService.eliminarRegla(regla.id).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Regla desactivada.');
        this.loadReglas();
      },
      error: (error) => {
        this.alertService.error(error);
      }
    });
  }

  tipoLabel(tipo: string): string {
    return tipo === 'meta_fija' ? 'Meta fija' : 'Escalonado';
  }

  alcanceLabel(regla: BonoRegla): string {
    if (regla.alcance === 'vendedores') {
      const n = regla.id_vendedores?.length ?? 0;
      return `Por vendedor (${n})`;
    }
    return 'Global';
  }

  configResumen(regla: BonoRegla): string {
    if (regla.tipo === 'meta_fija') {
      return `Meta ${regla.config?.meta ?? 0} → Bono ${regla.config?.bono ?? 0}`;
    }
    const tramos = regla.config?.tramos?.length ?? 0;
    return `${tramos} tramo(s)`;
  }

  private buildPayload(): BonoReglaPayload | null {
    if (this.form.alcance === 'vendedores' && !this.form.id_vendedores.length) {
      this.alertService.warning('Atención', 'Seleccione al menos un vendedor.');
      return null;
    }

    const base: Pick<BonoReglaPayload, 'nombre' | 'tipo' | 'ventana' | 'activo' | 'alcance' | 'id_vendedores'> = {
      nombre: this.form.nombre.trim(),
      tipo: this.form.tipo,
      ventana: this.form.ventana || 'mensual',
      activo: this.form.activo,
      alcance: this.form.alcance,
      id_vendedores: this.form.alcance === 'vendedores' ? this.form.id_vendedores : null
    };

    if (this.form.tipo === 'meta_fija') {
      if (this.form.meta === null || this.form.bono === null) {
        this.alertService.warning('Atención', 'Meta y bono son requeridos para meta fija.');
        return null;
      }
      return {
        ...base,
        config: { meta: this.form.meta, bono: this.form.bono }
      };
    }

    const tramos = this.form.tramos.filter((t) => t.meta !== null && t.bono !== null);
    if (!tramos.length) {
      this.alertService.warning('Atención', 'Agregue al menos un tramo válido.');
      return null;
    }

    return {
      ...base,
      config: { tramos }
    };
  }

  private formularioVacio() {
    return {
      nombre: '',
      tipo: 'meta_fija' as const,
      ventana: 'mensual',
      activo: true,
      alcance: 'global' as const,
      id_vendedores: [] as number[],
      meta: null as number | null,
      bono: null as number | null,
      tramos: [{ meta: 0, bono: 0 }] as BonoTramo[]
    };
  }
}
