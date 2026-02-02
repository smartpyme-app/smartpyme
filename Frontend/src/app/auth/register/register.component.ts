import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgxMaskDirective, provideNgxMask } from 'ngx-mask';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { PromocionalService, CodigoPromocional } from '@services/promocional.service';


@Component({
    selector: 'app-register',
    templateUrl: './register.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgxMaskDirective, NotificacionesContainerComponent],
    providers: [provideNgxMask()]
})
export class RegisterComponent implements OnInit {

    public user: any = {};
    public paises: any = [];
    public loading = false;
    public saludo:string = '';
    public anio:any = '';
    public showpassword:boolean = false;
    public totalOriginal: number = 0;
    public tieneDescuento: boolean = false;
    public codigoPromocionalValido: boolean = false;
    public codigoPromocionalValidado: CodigoPromocional | null = null;
    public mensajeErrorCodigo: string = '';
    public planes: any[] = [];
    public planesMap: Map<number, any> = new Map(); // Mapa de planes por ID

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
        public promocionalService: PromocionalService
    ) { }

    ngOnInit() {
        this.user = this.apiService.register_user();

        this.apiService.getToUrl('https://restcountries.com/v3.1/all?order=name')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (data) => {
                    this.paises = data;
                    // console.log(data);
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });

        if(!this.user){
            this.user = {};
            this.user.empresa = {};

            this.user.empresa.industria = '';
            this.user.empresa.iva = 13;
            this.user.empresa.plan = '';
            this.user.empresa.tipo_plan = 'Mensual';
            this.user.empresa.frecuencia_pago = '';
            this.user.empresa.pais = 'El Salvador';
            this.user.empresa.moneda = 'USD';
            this.user.empresa.total = 0;

            if (this.route.snapshot.queryParamMap.get('plan')!) {
                this.user.empresa.plan = this.route.snapshot.queryParamMap.get('plan')!;
            }

            if (this.route.snapshot.queryParamMap.get('tipo_plan')!) {
                this.user.empresa.tipo_plan = this.route.snapshot.queryParamMap.get('tipo_plan')!;
            }

            if (this.route.snapshot.queryParamMap.get('referido')!) {
                this.user.empresa.referido = this.route.snapshot.queryParamMap.get('referido')!;
            }

            if (this.route.snapshot.queryParamMap.get('campania')!) {
                this.user.empresa.campania = this.route.snapshot.queryParamMap.get('campania')!;
            }

            if (this.route.snapshot.queryParamMap.get('promo')!) {
                this.user.empresa.codigo_promocional = this.route.snapshot.queryParamMap.get('promo')!;
            }

            this.setPlan();
            this.aplicarDescuento();

            // Cargar planes desde el backend y luego inicializar precios
            this.cargarPlanes();

            // Inicializar tieneDescuento y codigoPromocionalValido si ya hay un código promocional
            if (this.user.empresa.codigo_promocional) {
                this.promocionalService.validarCodigo(
                    this.user.empresa.codigo_promocional,
                    this.user.empresa.tipo_plan
                ).subscribe(codigoPromo => {
                    if (codigoPromo) {
                        this.codigoPromocionalValidado = codigoPromo;
                        this.tieneDescuento = true;
                        this.codigoPromocionalValido = true;
                    }
                });
            }

        } else {
            // Si ya hay un usuario, también cargar planes por si acaso
            this.cargarPlanes();
        }

    }

    /**
     * Carga los planes desde el backend
     */
    public cargarPlanes() {
        this.apiService.getToUrl(this.apiService.apiUrl + 'planes/publicos').subscribe(
            (response: any) => {
                this.planes = response.planes || response;
                // Crear mapa de planes por ID para acceso rápido
                this.planesMap.clear();
                this.planes.forEach((plan: any) => {
                    this.planesMap.set(plan.id, plan);
                });

                // Si ya hay un plan seleccionado, recalcular precios y aplicar descuentos
                if (this.user?.empresa?.plan) {
                    this.setPlan();
                    this.aplicarDescuento();
                }
            },
            error => {
                console.error('Error al cargar planes:', error);
                this.alertService.error('Error al cargar los planes disponibles');
            }
        );
    }

    public setPlan(){
        // Determinar tipo_plan basado en frecuencia_pago si no está definido
        if (!this.user.empresa.tipo_plan && this.user.empresa.frecuencia_pago) {
            if (this.user.empresa.frecuencia_pago === 'Anual') {
                this.user.empresa.tipo_plan = 'Anual';
            } else {
                this.user.empresa.tipo_plan = 'Mensual';
            }
        }

        // Si no hay planes cargados o no hay plan seleccionado, salir
        if (!this.user.empresa.plan || this.planes.length === 0) {
            this.totalOriginal = 0;
            this.user.empresa.total = 0;
            return;
        }

        // Obtener el plan seleccionado desde el mapa o buscar en la lista
        const planId = typeof this.user.empresa.plan === 'number' ? this.user.empresa.plan : parseInt(this.user.empresa.plan);
        const planSeleccionado = this.planesMap.get(planId) || this.planes.find((p: any) => p.id === planId);
        
        if (!planSeleccionado) {
            this.totalOriginal = 0;
            this.user.empresa.total = 0;
            return;
        }

        const planNombre = planSeleccionado.nombre || '';
        const tipoPlan = this.user.empresa.frecuencia_pago || this.user.empresa.tipo_plan || 'Mensual';
        const precioBase = parseFloat(planSeleccionado.precio) || 0;

        // Verificar si hay un código promocional válido para el plan anual
        const tieneCodigoPromocionalValidoAnual =
            this.codigoPromocionalValidado &&
            tipoPlan === 'Anual' &&
            this.esPlanPermitido(this.codigoPromocionalValidado, 'Anual');

        if(this.user.empresa.plan == 1){//emprendedor
            this.user.empresa.user_limit = 1;
            this.user.empresa.sucursal_limit = 1;
        } else if (planNombre.includes('estándar') || planNombre.includes('estandar') || planId === 2) {
            this.user.empresa.user_limit = 2;
            this.user.empresa.sucursal_limit = 1;
        } else if (planNombre.includes('avanzado') || planId === 3) {
            this.user.empresa.user_limit = 5;
            this.user.empresa.sucursal_limit = 2;
        } else if (planNombre.includes('pro') || planId === 4) {
            this.user.empresa.user_limit = 5;
            this.user.empresa.sucursal_limit = 2;
        }

        // Calcular total original según la frecuencia de pago
        if (tipoPlan === 'Mensual' || this.user.empresa.frecuencia_pago === 'Mensual') {
            this.totalOriginal = precioBase;
        } else if (this.user.empresa.frecuencia_pago === 'Trimestral') {
            // Para trimestral, buscar el precio trimestral o multiplicar el mensual por 3
            const planTrimestral = this.planes.find((p: any) =>
                p.duracion_dias === 90 &&
                (p.nombre === planSeleccionado.nombre ||
                 p.nombre.toLowerCase().includes(planSeleccionado.nombre.toLowerCase()))
            );
            if (planTrimestral) {
                this.totalOriginal = parseFloat(planTrimestral.precio) || 0;
            } else {
                // Si no hay plan trimestral específico, usar precio mensual * 3
                const planMensual = this.planes.find((p: any) =>
                    p.duracion_dias === 30 &&
                    (p.nombre === planSeleccionado.nombre ||
                     p.nombre.toLowerCase().includes(planSeleccionado.nombre.toLowerCase()))
                );
                const precioMensual = planMensual ? parseFloat(planMensual.precio) : precioBase;
                this.totalOriginal = precioMensual * 3;
            }
        } else if (tipoPlan === 'Anual' || this.user.empresa.frecuencia_pago === 'Anual') {
            if (tieneCodigoPromocionalValidoAnual) {
                // Si hay código promocional válido para anual, usar precio mensual (sin 20% descuento)
                // El descuento del código promocional se aplicará después
                const planMensual = this.planes.find((p: any) =>
                    p.duracion_dias === 30 &&
                    (p.nombre === planSeleccionado.nombre ||
                     p.nombre.toLowerCase().includes(planSeleccionado.nombre.toLowerCase()))
                );
                this.totalOriginal = planMensual ? parseFloat(planMensual.precio) : precioBase;
            } else {
                // Sin código promocional, buscar plan anual o calcular con 20% de descuento
                const planAnual = this.planes.find((p: any) =>
                    (p.duracion_dias === 365 || p.duracion_dias === 360) &&
                    (p.nombre === planSeleccionado.nombre ||
                     p.nombre.toLowerCase().includes(planSeleccionado.nombre.toLowerCase()))
                );
                if (planAnual) {
                    this.totalOriginal = parseFloat(planAnual.precio) || 0;
                } else {
                    // Si no hay plan anual específico, calcular con 20% de descuento
                    const planMensual = this.planes.find((p: any) =>
                        p.duracion_dias === 30 &&
                        (p.nombre === planSeleccionado.nombre ||
                         p.nombre.toLowerCase().includes(planSeleccionado.nombre.toLowerCase()))
                    );
                    const precioMensual = planMensual ? parseFloat(planMensual.precio) : precioBase;
                    this.totalOriginal = precioMensual * 12 * 0.8; // 20% de descuento anual
                }
            }
        } else {
            this.totalOriginal = precioBase;
        }

        this.aplicarDescuento();
    }

    public onFrecuenciaPagoChange() {
        // Revalidar código promocional cuando cambia la frecuencia de pago
        if (this.user.empresa.codigo_promocional) {
            this.aplicarDescuento();
        }
        this.setPlan();
    }

    public aplicarDescuento(){
        const codigo = this.user.empresa.codigo_promocional;

        if (!codigo) {
            this.codigoPromocionalValidado = null;
            this.codigoPromocionalValido = false;
            this.tieneDescuento = false;
            this.mensajeErrorCodigo = '';
            if(this.totalOriginal > 0){
                this.user.empresa.total = this.totalOriginal;
            }
            return;
        }

        // Usar frecuencia_pago como tipo_plan para la validación
        const tipoPlan = this.user.empresa.frecuencia_pago || this.user.empresa.tipo_plan;

        this.promocionalService.validarCodigo(codigo, tipoPlan).subscribe(
            codigoPromo => {
                if(codigoPromo){
                    // Si el backend retorna el código, significa que pasó todas las validaciones
                    // incluyendo la validación de planes permitidos
                    this.codigoPromocionalValidado = codigoPromo;
                    this.codigoPromocionalValido = true;
                    this.mensajeErrorCodigo = '';

                    if(this.totalOriginal > 0){
                        // Aplicar descuento según el código promocional
                        this.user.empresa.total = this.promocionalService.calcularPrecioConDescuento(
                            this.totalOriginal,
                            codigoPromo
                        );
                        this.tieneDescuento = true;
                    }
                }else{
                    this.codigoPromocionalValidado = null;
                    this.codigoPromocionalValido = false;
                    this.tieneDescuento = false;
                    this.mensajeErrorCodigo = 'Código promocional no válido';

                    if(this.totalOriginal > 0){
                        this.user.empresa.total = this.totalOriginal;
                    }
                }
            },
            error => {
                console.error('Error al validar código promocional:', error);
                this.codigoPromocionalValidado = null;
                this.codigoPromocionalValido = false;
                this.tieneDescuento = false;

                // Extraer mensaje de error del backend si está disponible
                if (error?.error?.mensaje) {
                    this.mensajeErrorCodigo = error.error.mensaje;
                } else if (error?.error?.error?.mensaje) {
                    // Algunos errores pueden venir anidados
                    this.mensajeErrorCodigo = error.error.error.mensaje;
                } else {
                    this.mensajeErrorCodigo = 'Error al validar el código promocional';
                }

                if(this.totalOriginal > 0){
                    this.user.empresa.total = this.totalOriginal;
                }
            }
        );
    }

    /**
     * Verifica si el plan actual está permitido para el código promocional
     * @param codigoPromo Código promocional validado
     * @param tipoPlan Tipo de plan actual
     * @returns true si el plan está permitido
     */
    private esPlanPermitido(codigoPromo: CodigoPromocional, tipoPlan: string | null): boolean {
        // Si no hay planes_permitidos definidos, el código es válido para todos los planes
        if (!codigoPromo.planes_permitidos || codigoPromo.planes_permitidos.length === 0) {
            return true;
        }

        // Si no hay tipo_plan, no se puede validar
        if (!tipoPlan) {
            return false;
        }

        // Comparar en minúsculas para evitar problemas de mayúsculas/minúsculas
        const tipoPlanLower = tipoPlan.toLowerCase();
        const planesPermitidosLower = codigoPromo.planes_permitidos.map(p => p.toLowerCase());

        return planesPermitidosLower.includes(tipoPlanLower);
    }

    setModeda(){
        if(this.user.empresa.pais == 'El Salvador'){
            this.user.empresa.moneda = 'USD';
            this.user.empresa.iva = 13;
        }
        if(this.user.empresa.pais == 'Belice'){
            this.user.empresa.moneda = 'BZD';
            this.user.empresa.iva = 12.5;
        }
        if(this.user.empresa.pais == 'Guatemala'){
            this.user.empresa.moneda = 'GTQ';
            this.user.empresa.iva = 12;
        }
        if(this.user.empresa.pais == 'Honduras'){
            this.user.empresa.moneda = 'HNL';
            this.user.empresa.iva = 15;
        }
        if(this.user.empresa.pais == 'Nicaragua'){
            this.user.empresa.moneda = 'NIO';
            this.user.empresa.iva = 15;
        }
        if(this.user.empresa.pais == 'Costa Rica'){
            this.user.empresa.moneda = 'CRC';
            this.user.empresa.iva = 13;
        }
        if(this.user.empresa.pais == 'Panamá'){
            this.user.empresa.moneda = 'PAB';
            this.user.empresa.iva = 7;
        }
        console.log(this.user);
    }

    submit() {
        this.loading = true;

        // Calcular el total a pagar según la frecuencia de pago
        // Para trimestral y anual, enviar el total a pagar completo
        const totalAPagar = this.getTotalAPagar();

        // Guardar el total mensual con descuento para mostrar después (no modificar hasta enviar)
        const totalMensualConDescuento = this.user.empresa.total;

        // Asegurar que tipo_plan esté sincronizado con frecuencia_pago
        if (this.user.empresa.frecuencia_pago) {
            this.user.empresa.tipo_plan = this.user.empresa.frecuencia_pago;
        }

        // Crear una copia del objeto user para enviar, sin modificar el original
        const userToSend = JSON.parse(JSON.stringify(this.user));
        userToSend.empresa.total = totalAPagar;

        this.apiService.register(userToSend)
        .subscribe(
            data => {
                // No es necesario restaurar porque nunca modificamos this.user.empresa.total

                // Si hay código promocional, agregarlo a la URL
                const navigationExtras: any = {};
                if (this.user.empresa.codigo_promocional) {
                    navigationExtras.queryParams = { promo: this.user.empresa.codigo_promocional };
                }
                this.router.navigate(['/pago'], navigationExtras);
                this.loading = false;
            },
            error => {
                // No es necesario restaurar porque nunca modificamos this.user.empresa.total
                this.alertService.error(error);
                this.loading = false;
            });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }

    public getFrecuenciaPago(){
        if(this.user.empresa.frecuencia_pago == 'Mensual'){
            return 'pago mensual';
        }
        if(this.user.empresa.frecuencia_pago == 'Trimestral'){
            return 'pago trimestral';
        };
        if(this.user.empresa.frecuencia_pago == 'Anual'){
            return 'pago anual';
        }
        return '';
    }

    public getTotalAPagar(){
        if(this.user.empresa.frecuencia_pago == 'Mensual'){
            // Para mensual, el total ya es el precio mensual (con descuentos aplicados si hay)
            return this.user.empresa.total;
        }
        if(this.user.empresa.frecuencia_pago == 'Trimestral'){
            // Para trimestral, el total ya es el precio trimestral (con descuentos aplicados si hay)
            // No multiplicar por 3 porque ya está calculado en setPlan()
            return this.user.empresa.total;
        }
        if(this.user.empresa.frecuencia_pago == 'Anual'){
            // Verificar si hay un código promocional válido para el plan anual
            const tieneCodigoPromocionalValido =
                this.codigoPromocionalValidado &&
                this.esPlanPermitido(this.codigoPromocionalValidado, 'Anual');

            if(tieneCodigoPromocionalValido){
                // Si hay código promocional válido para anual, usar ese descuento
                // El total tiene el descuento del código promocional aplicado (precio mensual)
                // Multiplicar por 12 para obtener el total anual
                return this.user.empresa.total * 12;
            } else {
                // Si no hay código promocional válido, el totalOriginal ya tiene el 20% de descuento anual aplicado
                // y user.empresa.total es igual a totalOriginal (sin descuento de código promocional)
                // Por lo tanto, devolver directamente el totalOriginal que ya incluye el descuento anual
                return this.totalOriginal;
            }
        }
        return this.user.empresa.total;
    }

    public esPlanAnual(): boolean {
        return this.user.empresa.frecuencia_pago === 'Anual';
    }

    public obtenerPorcentajeDescuentoCodigo(): number {
        let promocional = this.promocionalService.obtenerPorcentajeDescuento(this.codigoPromocionalValidado);
        // Redondear a número entero para mostrar "50%" en vez de "50.00%"
        return Math.round(promocional);
    }

}

