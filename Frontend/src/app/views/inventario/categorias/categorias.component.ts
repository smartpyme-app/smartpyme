import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { CategoriaCuentasComponent } from './cuentas/categoria-cuentas.component';
import { FuncionalidadesService } from '@services/functionalities.service';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-categorias',
    templateUrl: './categorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent, CategoriaCuentasComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
})

export class CategoriasComponent extends BaseCrudComponent<any> implements OnInit {

    public categorias: any = {};
    public categoria: any = {};
    public sucursales: any = [];
    public catalogo: any = [];
    public contabilidadHabilitada: boolean = false;

    public file: File | null = null;
    public preview = false;
    public url_img_preview = '';
    public quitarImg = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private funcionalidadesService: FuncionalidadesService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'categoria',
            itemsProperty: 'categorias',
            itemProperty: 'categoria',
            messages: {
                created: 'La categoria fue añadida exitosamente.',
                updated: 'La categoria fue guardada exitosamente.',
                createTitle: 'Categoria creada',
                updateTitle: 'Categoria guardada'
            },
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.enable = true;
                return item;
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarCategorias();
    }

    ngOnInit() {
        // Verificar si tiene contabilidad habilitada
        this.verificarAccesoContabilidad();

        // Cargar datos adicionales necesarios para el componente
        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });

        // Solo cargar catálogo si tiene contabilidad habilitada
        if (this.contabilidadHabilitada) {
            this.apiService.getAll('catalogo/list')
                .pipe(this.untilDestroyed())
                .subscribe(catalogo => {
                    this.catalogo = catalogo;
                    this.cdr.markForCheck();
                }, error => { this.alertService.error(error); });
        }

        // Cargar categorías usando el método heredado
        this.loadAll();
    }

    verificarAccesoContabilidad() {
        this.funcionalidadesService.verificarAcceso('contabilidad')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (acceso) => {
                    this.contabilidadHabilitada = acceso;
                    // Si tiene acceso y aún no se cargó el catálogo, cargarlo
                    if (acceso && !this.catalogo.length) {
                        this.apiService.getAll('catalogo/list')
                            .pipe(this.untilDestroyed())
                            .subscribe(catalogo => {
                                this.catalogo = catalogo;
                                this.cdr.markForCheck();
                            }, error => { this.alertService.error(error); });
                    }
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    console.error('Error al verificar acceso a contabilidad:', error);
                    this.contabilidadHabilitada = false;
                }
            });
    }

    public override loadAll() {
        // Resetear filtros específicos de categorías
        this.filtros.id_sucursal = '';
        // Llamar al método base que resetea filtros comunes y llama a aplicarFiltros()
        super.loadAll();
    }

    public filtrarCategorias() {
        this.loading = true;

        this.apiService.getAll('categorias', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((response: any) => {
                // El backend devuelve un objeto de paginación de Laravel
                this.categorias = response;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    // setPagination() ahora se hereda de BaseFilteredPaginatedComponent

    override openModal(template: TemplateRef<any>, categoria?: any) {
        this.resetImgState();
        // Si se está editando una categoría existente, cargar las cuentas
        if (categoria?.id) {
            this.loading = true;
            this.apiService.read('categoria/', categoria.id)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (categoriaCompleta) => {
                        this.loading = false;
                        // Usar el método heredado con la categoría completa que incluye las cuentas
                        super.openModal(template, categoriaCompleta, {
                            class: 'modal-lg',
                            backdrop: 'static'
                        });
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.loading = false;
                        this.alertService.error(error);
                    }
                });
        } else {
            // Usar el método heredado que maneja la inicialización del item
            // y pasar la configuración del modal
            super.openModal(template, categoria, {
                class: 'modal-lg',
                backdrop: 'static'
            });
        }
    }

    public setEstado(categoria: any) {
        this.categoria = categoria;
        // Usar el método heredado onSubmit
        this.onSubmit();
    }

    // Asegurar que id_empresa siempre esté presente antes de guardar
    public override async onSubmit(item?: any, isStatusChange: boolean = false): Promise<void> {
        const categoriaToSave = item || this.categoria;

        // Asegurar que id_empresa esté presente si no existe
        if (!categoriaToSave.id_empresa) {
            categoriaToSave.id_empresa = this.apiService.auth_user()?.id_empresa;
        }

        if (isStatusChange || (!this.file && !this.quitarImg)) {
            await super.onSubmit(categoriaToSave, isStatusChange);
            return;
        }

        const formData = new FormData();
        const keys = ['id', 'nombre', 'descripcion', 'id_empresa', 'enable', 'subcategoria', 'id_cate_padre'];
        for (const key of keys) {
            const val = categoriaToSave[key];
            if (val === undefined || val === null) {
                continue;
            }
            formData.append(key, val === true || val === false ? (val ? '1' : '0') : String(val));
        }
        if (this.file) {
            formData.append('file', this.file);
        }
        if (this.quitarImg) {
            formData.append('quitar_img', '1');
        }

        this.loading = true;
        this.saving = true;
        this.cdr.markForCheck();
        try {
            const isNew = !categoriaToSave.id;
            const savedItem = await this.apiService.store('categoria', formData)
                .pipe(this.untilDestroyed())
                .toPromise();
            (this as any).categoria = savedItem;
            if (isNew) {
                this.addItemToList(savedItem);
            } else {
                this.updateItemInList(savedItem);
            }
            this.alertService.success(
                isNew ? 'Categoria creada' : 'Categoria guardada',
                isNew ? 'La categoria fue añadida exitosamente.' : 'La categoria fue guardada exitosamente.'
            );
            this.resetImgState();
            if (this.modalRef) {
                this.closeModal();
            }
            this.filtrarCategorias();
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
            this.saving = false;
            this.cdr.markForCheck();
        }
    }

    setFile(event: any): void {
        const chosen = event?.target?.files?.[0];
        if (!chosen) {
            return;
        }
        if (chosen.size > 2 * 1024 * 1024) {
            this.alertService.warning('Archivo grande', 'La imagen no debe superar 2 MB.');
            event.target.value = '';
            return;
        }
        this.file = chosen;
        this.quitarImg = false;
        const reader = new FileReader();
        reader.onload = () => {
            this.url_img_preview = String(reader.result || '');
            this.preview = true;
            this.cdr.markForCheck();
        };
        reader.readAsDataURL(chosen);
        this.cdr.markForCheck();
    }

    quitarImagen(): void {
        this.file = null;
        this.preview = false;
        this.url_img_preview = '';
        this.quitarImg = true;
        this.categoria = { ...this.categoria, img: null };
        this.cdr.markForCheck();
    }

    imgSrc(img?: string | null): string {
        const path = String(img || '').replace(/^\//, '');
        return `${this.apiService.baseUrl}/img/${path}`;
    }

    private resetImgState(): void {
        this.file = null;
        this.preview = false;
        this.url_img_preview = '';
        this.quitarImg = false;
    }

    public verificarSiExiste() {
        if (this.categoria.nombre) {
            if (this.categorias.data?.filter((item: any) => item.nombre.toLowerCase() == this.categoria.nombre.toLowerCase())[0]) {
                this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                    'Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
                );
            }
        }
    }

}
