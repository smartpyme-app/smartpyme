import { Component, OnInit,TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PartidaDetallesComponent } from './detalles/partida-detalles.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-partida',
  templateUrl: './partida.component.html',
  providers: [ SumPipe ]
})
export class PartidaComponent implements OnInit {

    @ViewChild(PartidaDetallesComponent) detallesComponent!: PartidaDetallesComponent;
    
    // Exponer Math y parseFloat para usar en el template
    Math = Math;
    parseFloat = parseFloat;
    
    public partida:any = {};
    public catalogos:any = [];
    public proveedor: any = {};
    public cliente: any = {};
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

  constructor( 
      private apiService: ApiService, private alertService: AlertService, private sumPipe:SumPipe,
      private route: ActivatedRoute, private router: Router, private modalService: BsModalService
  ) { }

  ngOnInit() {
        this.loadAll();
        
        // Suscribirse a cambios en los parámetros de ruta para recargar cuando cambie el ID
        this.route.paramMap.subscribe(params => {
            const id = params.get('id');
            if (id && id !== 'crear') {
                console.log('Parámetro de ruta cambió, recargando partida:', id);
                this.loadAll();
            }
        });

        // this.apiService.getAll('catalogos/list').subscribe(catalogos => {
        //     this.catalogos = catalogos;
        // }, error => {this.alertService.error(error);});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('partida/', id).subscribe(partida => {
                this.partida = partida;
                
                // Inicializar paginación
                const perPage = parseInt(partida.per_page) || 100;
                const totalDetalles = parseInt(partida.total_detalles) || partida.detalles?.length || 0;
                
                if (!this.partida.pagination) {
                    this.partida.pagination = {
                        current_page: 1,
                        per_page: perPage,
                        total: totalDetalles,
                        last_page: Math.ceil(totalDetalles / perPage),
                        has_more: partida.tiene_mas_detalles === true || partida.tiene_mas_detalles === 'true' || partida.tiene_mas_detalles === 1
                    };
                }
                
                // IMPORTANTE: Usar totales del backend calculados desde TODOS los detalles en BD
                // NO recalcular desde los detalles cargados porque solo tenemos una parte
                if (partida.total_debe !== undefined && partida.total_haber !== undefined) {
                    this.partida.debe = parseFloat(partida.total_debe).toFixed(2);
                    this.partida.haber = parseFloat(partida.total_haber).toFixed(2);
                    this.partida.diferencia = (parseFloat(this.partida.debe) - parseFloat(this.partida.haber)).toFixed(2);
                    
                    console.log('Totales desde backend (TODOS los detalles):', {
                        debe: this.partida.debe,
                        haber: this.partida.haber,
                        diferencia: this.partida.diferencia,
                        total_detalles: partida.total_detalles
                    });
                } else {
                    // Si no vienen del backend, calcular desde los detalles cargados (solo para partidas nuevas)
                    console.warn('Totales no disponibles desde backend, calculando desde detalles cargados');
                    this.sumTotal();
                }
                
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.partida = {};
            this.partida.fecha = this.apiService.date();
            this.partida.estado = 'Pendiente';
            this.partida.tipo = 'Ingreso';
            this.partida.detalles = [];
            this.partida.id_usuario = this.apiService.auth_user().id;
            this.partida.id_empresa = this.apiService.auth_user().id_empresa;
            this.partida.debe = '0.00';
            this.partida.haber = '0.00';
            this.partida.diferencia = '0.00';
            this.partida.pagination = {
                current_page: 1,
                per_page: 100,
                total: 0,
                last_page: 1,
                has_more: false
            };
        }

    }
    
    public cargarMasDetalles() {
        if (!this.partida.id || !this.partida.pagination?.has_more) {
            console.warn('No se pueden cargar más detalles:', {
                has_id: !!this.partida.id,
                has_pagination: !!this.partida.pagination,
                has_more: this.partida.pagination?.has_more
            });
            return;
        }
        
        this.loading = true;
        // IMPORTANTE: Convertir a número para evitar concatenación de strings
        const currentPage = parseInt(this.partida.pagination.current_page) || 1;
        const nextPage = currentPage + 1;
        const perPage = parseInt(this.partida.pagination.per_page) || 100;
        
        console.log('Cargando más detalles:', {
            partida_id: this.partida.id,
            current_page: currentPage,
            next_page: nextPage,
            per_page: perPage,
            detalles_actuales: this.partida.detalles?.length || 0,
            total_detalles: this.partida.pagination.total
        });
        
        this.apiService.getAll(`partida/${this.partida.id}/detalles`, {
            page: nextPage,
            per_page: perPage
        })
        .subscribe({
            next: (response) => {
                console.log('Respuesta de detalles:', {
                    detalles_recibidos: response.detalles?.length || 0,
                    pagination: response.pagination
                });
                
                // Agregar los nuevos detalles a la lista existente, evitando duplicados
                if (response.detalles && response.detalles.length > 0) {
                    const detallesAnteriores = this.partida.detalles?.length || 0;
                    const detallesExistentesIds = new Set((this.partida.detalles || []).map((d: any) => d.id).filter((id: any) => id));
                    
                    // Filtrar detalles que ya existen (por ID) para evitar duplicados
                    const detallesNuevos = response.detalles.filter((detalle: any) => {
                        if (!detalle.id) return true; // Si no tiene ID, es nuevo
                        return !detallesExistentesIds.has(detalle.id);
                    });
                    
                    if (detallesNuevos.length > 0) {
                        this.partida.detalles = [...(this.partida.detalles || []), ...detallesNuevos];
                        console.log(`Detalles agregados: ${detallesAnteriores} -> ${this.partida.detalles.length} (${detallesNuevos.length} nuevos, ${response.detalles.length - detallesNuevos.length} duplicados evitados)`);
                    } else {
                        console.warn('Todos los detalles recibidos ya estaban cargados (duplicados evitados)');
                    }
                }
                
                // Actualizar información de paginación correctamente
                if (response.pagination) {
                    // Mantener el total original (no debe cambiar)
                    const totalOriginal = parseInt(this.partida.pagination?.total) || parseInt(response.pagination.total);
                    
                    // Convertir todos los valores a números para evitar problemas de tipo
                    this.partida.pagination = {
                        ...this.partida.pagination,
                        current_page: parseInt(response.pagination.current_page) || 1,
                        has_more: response.pagination.has_more === true || response.pagination.has_more === 'true' || response.pagination.has_more === 1,
                        last_page: parseInt(response.pagination.last_page) || 1,
                        per_page: parseInt(response.pagination.per_page) || 100,
                        total: totalOriginal // Mantener el total original
                    };
                    
                    console.log('Paginación actualizada:', {
                        ...this.partida.pagination,
                        detalles_cargados: this.partida.detalles?.length || 0
                    });
                    
                    // IMPORTANTE: NO recalcular totales aquí
                    // Los totales ya están calculados desde TODOS los detalles en el backend
                    // y se establecieron cuando se cargó la partida inicialmente
                }
                
                this.loading = false;
            },
            error: (error) => {
                console.error('Error al cargar más detalles:', error);
                this.alertService.error(error);
                this.loading = false;
            }
        });
    }

    public sumTotal() {
        // IMPORTANTE: Si la partida tiene totales del backend, NO recalcular
        // Solo recalcular si es una partida nueva sin totales del backend
        if (this.partida.total_debe !== undefined && this.partida.total_haber !== undefined && this.partida.id) {
            // Mantener los totales del backend hasta que se guarde
            console.log('Manteniendo totales del backend, no recalculando:', {
                debe: this.partida.debe,
                haber: this.partida.haber,
                diferencia: this.partida.diferencia
            });
            return;
        }
        
        // Solo recalcular para partidas nuevas sin totales del backend
        this.partida.debe = (parseFloat(this.sumPipe.transform(this.partida.detalles, 'debe')) || 0).toFixed(2);
        this.partida.haber = (parseFloat(this.sumPipe.transform(this.partida.detalles, 'haber')) || 0).toFixed(2);
        this.partida.diferencia = (parseFloat(this.partida.debe) - parseFloat(this.partida.haber)).toFixed(2);
    }

    public updatePartida(partida:any) {
        this.partida = partida;
        // Solo recalcular totales si es una partida nueva (sin ID)
        // Para partidas existentes, los totales vienen del backend
        if (!this.partida.id) {
            this.sumTotal();
        }
    }
    
    public onTotalesActualizados(totales: any) {
        // Los totales ya fueron actualizados en el componente de detalles
        // Solo sincronizar aquí si es necesario
        this.partida.debe = totales.debe;
        this.partida.haber = totales.haber;
        this.partida.diferencia = totales.diferencia;
    }

    public onSubmit(){
        this.saving = true;

        // Obtener detalles modificados desde el componente de detalles si está disponible
        const detallesModificadosIds = (this as any).detallesComponent?.getDetallesModificados() || [];
        
        // Filtrar solo los detalles modificados o nuevos (sin ID)
        const detallesParaEnviar = (this.partida.detalles || []).filter((detalle: any) => {
            // Incluir si no tiene ID (es nuevo) o si está en la lista de modificados
            return !detalle.id || detallesModificadosIds.includes(detalle.id);
        });

        console.log('Guardando partida:', {
            partida_id: this.partida.id,
            detalles_modificados: detallesModificadosIds.length,
            detalles_a_enviar: detallesParaEnviar.length,
            total_detalles_en_bd: this.partida.pagination?.total || 0
        });

        // Preparar datos para enviar - solo detalles modificados
        const datosParaEnviar = {
            ...this.partida,
            detalles: detallesParaEnviar,
            // Indicar al backend que solo debe actualizar estos detalles
            solo_detalles_modificados: true
        };

        this.apiService.store('partida', datosParaEnviar).subscribe(partida => {
            // Limpiar detalles modificados después de guardar
            if ((this as any).detallesComponent?.limpiarDetallesModificados) {
                (this as any).detallesComponent.limpiarDetallesModificados();
            }
            
            if (!this.partida.id) {
                this.alertService.success('Partida guardada', 'La partida fue guardada exitosamente.');
            }else{
                this.alertService.success('Partida creada', 'La partida fue añadida exitosamente.');
            }
            this.router.navigate(['/contabilidad/partidas']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    openModalProveedor(template: TemplateRef<any>) {

            this.proveedor = {};
            this.proveedor.tipo = 'Persona';
            this.proveedor.id_usuario = this.apiService.auth_user().id;
            this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
        
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.proveedor.tipo = tipo;
    }
    
    public onSubmitProveedor() {
        this.saving = true;
        this.apiService.store('proveedor', this.proveedor).subscribe(proveedor => {
            // this.update.emit(proveedor);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Proveedor creado', 'Tu proveedor fue añadido exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    openModalCliente(template: TemplateRef<any>) {
            this.cliente = {};
            this.cliente.tipo = 'Persona';
            this.cliente.id_usuario = this.apiService.auth_user().id;
            this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
        
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    openModal(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public setTipoCliente(tipo:any){
        this.cliente.tipo = tipo;
    }

    public onSubmitCliente() {
        this.saving = true;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => {
            // this.update.emit(cliente);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Cliente creado', 'El cliente ha sido agregado.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    generarPartidasDelDia(){
        this.saving = true;
        this.apiService.store('partidas/generar/' + this.partida.tipo.toLowerCase() , this.partida).subscribe({
            next: (data) => {
                // Si la respuesta tiene partida_id, significa que se guardó en la BD
                // y necesitamos cargarla desde ahí
                if (data.partida_id) {
                    console.log('Partida generada con ID:', data.partida_id);
                    console.log('Total detalles:', data.total_detalles);
                    
                    this.saving = false;
                    
                    // Guardar el ID de la partida generada para usar en la navegación
                    const partidaIdGenerada = data.partida_id;
                    
                    // Cerrar el modal primero
                    if (this.modalRef) {
                        this.modalRef.hide();
                    }
                    
                    // Mostrar mensaje de éxito
                    this.alertService.success(
                        'Partida generada exitosamente', 
                        `Se generaron ${data.total_detalles || 0} detalles. Redirigiendo a la partida en un momento...`
                    );
                    
                    // Navegar después de un breve delay para asegurar que el modal se cerró
                    setTimeout(() => {
                        console.log('=== INICIANDO NAVEGACIÓN ===');
                        console.log('Partida ID:', partidaIdGenerada);
                        console.log('Ruta destino:', `/contabilidad/partida/${partidaIdGenerada}`);
                        console.log('Ruta actual:', this.router.url);
                        
                        // Usar window.location.href para forzar recarga completa de la página
                        // Esto asegura que el componente se recargue completamente y cargue los datos
                        console.log('Forzando recarga completa de la página para cargar los datos...');
                        window.location.href = `/contabilidad/partida/${partidaIdGenerada}`;
                    }, 1000); // Delay suficiente para que el modal se cierre completamente
                } else {
                    // Respuesta directa (para compatibilidad con versiones anteriores)
                    this.partida = data.partida;
                    this.partida.id_usuario = this.apiService.auth_user().id;
                    this.partida.id_empresa = this.apiService.auth_user().id_empresa;

                    this.partida.detalles = data.detalles;
                    if(this.partida.detalles.length == 0){
                        this.alertService.info('No hay registros', 'No se encontraron transacciones.')
                    }else{
                        this.sumTotal();
                        this.modalRef?.hide();
                    }

                    this.saving = false;
                }
            },
            error: (error) => {
                console.error('Error al generar partidas:', error);
                this.alertService.error(error);
                this.saving = false;
            }
        });
      }

}
