import { Component, OnInit, ViewChild, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';
import { firstValueFrom } from 'rxjs';
import { NgForm } from '@angular/forms';
import { HttpParams } from '@angular/common/http';
import {
  DomSanitizer,
  SafeResourceUrl,
  SafeHtml,
} from '@angular/platform-browser';
import { Estado } from '../../../models/estado.interface';

@Component({
  selector: 'app-suscripcion',
  templateUrl: './suscripcion.component.html',
})
export class SuscripcionComponent implements OnInit {
  
  public suscripcion: any = {};
  public usuario: any = {};
  public loading = false;
  public saving = false;
  public showUpdateForm = false;
  public showPaymentForm = false;
  public updatePaymentMethod = false;
  public checkboxUpdate = true;
  public estadoSeleccionado: any = null;
  public paises = [];
  public estados: Estado[] = [];

  public processingPayment = false;
  public mostrar3DSModal = false;
  public urlAutenticacion!: SafeResourceUrl;

  @ViewChild('updatePaymentForm') updatePaymentForm!: NgForm;

  public paymentData = {
    cardNumber: '',
    cardHolder: '',
    expirationMonth: '',
    expirationYear: '',
    cvv: '',
  };

  public pagoRecurrente = this.apiService.auth_user().empresa.pago_recurrente || false;

  public billingInfo = {
    countryCode: '',
    stateCode: '',
    zipCode: '',
  };

  public cancelarSuscripcion = {
    password: '',
    id: 0,
    id_empresa: 0,
    motivo_cancelacion: '',
  };

  modalRef?: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private modalService: BsModalService,
    private sanitizer: DomSanitizer,
    private n1coPaymentService: N1coPaymentService
  ) {}

  ngOnInit() {
    this.loadAll();
  }

  public loadAll() {
    this.loading = true;
    this.usuario = this.apiService.auth_user();
    this.apiService.getAll('suscripcion').subscribe(
      (suscripcion) => {
        this.suscripcion = suscripcion;
        this.loading = false;
      },
      (error) => {
        const authUser = JSON.parse(
          localStorage.getItem('SP_auth_user') || '{}'
        );

        // console.log('authUser', authUser.tiene_suscripcion);
        // console.log('error', error.status);
        if (
          error.status === 404 ||
          (error.status === 500 && !authUser.tiene_suscripcion)
        ) {
        } else {
          this.alertService.error(
            'Ha ocurrido un error, por favor contactar con soporte técnico'
          );
        }
        this.loading = false;
      }
    );

    this.getPaises();

  }

  getPaises() {
    this.apiService.getAll('paises-suscripcion', this.paises).subscribe(paises => { 
      this.paises = paises;
    }, error => {this.alertService.error(error); });
  }

  getEstados(countryCode: string) {
    this.apiService.getAll(`estados-por-pais/${countryCode}`, []).subscribe(
      estados => { 
        this.estados = estados;
      }, 
      error => {
        this.alertService.error(error);
      }
    );
  }

  onPaisChange() {
    if (this.billingInfo.countryCode) {
      this.getEstados(this.billingInfo.countryCode);      
      this.billingInfo.stateCode = '';      
      this.billingInfo.zipCode = '';
    }
  }

  onEstadoChange() {
    if (this.billingInfo.stateCode) {
      const estadoSeleccionado = this.estados.find(estado => estado.codigo === this.billingInfo.stateCode);
      
      if (estadoSeleccionado && estadoSeleccionado.codigo_postal) {
        this.billingInfo.zipCode = estadoSeleccionado.codigo_postal;
      }
    }
  }

  public async payWithSavedMethod() {
    this.saving = true;
    try {
      const chargeData = {
        metodo_pago_id: this.suscripcion.metodoPago.id,
        id_usuario: this.usuario.id,
        empresa_id: this.usuario.empresa.id,
        plan_id: this.suscripcion.plan.id,
        amount: this.suscripcion.suscripcion.monto,
        customer_name: this.usuario.name,
        customer_email: this.usuario.email,
        customer_phone: this.usuario.telefono || this.usuario.empresa.telefono,
        description: `Suscripción plan ${this.usuario.plan}`,
      };

      const chargeResult = await firstValueFrom(
        this.n1coPaymentService.createChargewithMethodPayment(chargeData)
      );

      if (chargeResult.requires_3ds) {
        this.handleThreeDSAuthentication(chargeResult);
        return;
      }

      if (chargeResult.success) {
        await this.refreshUserData();

        this.alertService.success('Éxito', 'Suscripción pagada exitosamente');
        this.modalRef?.hide();
        this.loadAll();

        window.location.reload();
      } else {
        this.alertService.error(
          chargeResult.message || 'Error al procesar el pago'
        );
      }
    } catch (error: any) {
      this.alertService.error(
        'Error: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
    } finally {
      this.saving = false;
    }
  }

  // Método para pagar con nueva tarjeta
  public async onPaySubscription() {
    setTimeout(() => {
      if (!this.isFormValid()) {
        this.alertService.error('Complete todos los campos correctamente');
        return;
      }

      this.saving = true;
      this.createNewSubscriptionPayment();
    });
  }

  public onSubmit() {
    this.saving = true;
    this.apiService.store('suscripcion', this.suscripcion).subscribe(
      (suscripcion) => {
        this.suscripcion = suscripcion;
        this.alertService.success(
          'Suscripción guardada',
          'Los datos de tu plan fueron guardados exitosamente.'
        );
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  public openCancelarSuscripcion(template: TemplateRef<any>) {
    const usuario = this.apiService.auth_user();
    this.cancelarSuscripcion = {
      password: '',
      id: usuario.id,
      id_empresa: usuario.id_empresa,
      motivo_cancelacion: '',
    };
    this.modalRef = this.modalService.show(template);
  }

  public onCancelar() {
    if (!this.cancelarSuscripcion.motivo_cancelacion) {
      this.alertService.error('Por favor, indique el motivo de la cancelación');
      return;
    }

    this.saving = true;
    this.apiService
      .store('cancelar-suscripcion', this.cancelarSuscripcion)
      .subscribe(
        (response) => {
          this.saving = false;
          if (response.success) {
            this.modalRef!.hide();
            this.alertService.success(
              'Suscripción cancelada',
              `Tu suscripción ha sido cancelada exitosamente. Podrás seguir utilizando el sistema hasta ${response.fecha_desactivacion}.`
            );
            // No redirigimos al login, ya que el usuario puede seguir usando el sistema
          }
        },
        (error) => {
          this.saving = false;
          this.alertService.error(
            error.error?.error || 'Error al procesar la solicitud'
          );
        }
      );
  }
  
  public async onUpdatePaymentMethod() {
    setTimeout(() => {
      if (!this.isFormValid()) {
        this.alertService.error('Complete todos los campos correctamente');
        return;
      }

      this.saving = true;
      // if (this.suscripcion.metodoPago) {
      this.updatePayment();
      // } else {

      // }
    });
  }

  private async createNewSubscriptionPayment() {
    try {
      const cardNumber = this.paymentData.cardNumber.replace(/\s/g, '');
      const expirationMonth = this.paymentData.expirationMonth.padStart(2, '0');

      const paymentMethodData = {
        customer: {
          id: this.usuario.id.toString(),
          name: this.usuario.name,
          email: this.usuario.email,
          phoneNumber: this.usuario.telefono || this.usuario.empresa.telefono,
        },
        card: {
          number: cardNumber,
          cardHolder: this.paymentData.cardHolder.trim(),
          expirationMonth,
          expirationYear: this.paymentData.expirationYear,
          cvv: this.paymentData.cvv,
        },
        plan: {
          id_plan: this.usuario.plan_id,
          plan_name: this.usuario.plan,
        },
        billingInfo: this.billingInfo,
        updatePaymentMethod: this.showPaymentForm,
        showPaymentForm: this.showPaymentForm,
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.createPaymentMethod(paymentMethodData)
      );

      if (result.requires_3ds) {
        this.handleThreeDSAuthentication(result);
        return;
      }

      if (result.success) {
        await this.processInitialPayment(result.data.id);
        await this.refreshUserData();
        this.alertService.success('Éxito', 'Suscripción creada exitosamente');
        this.showUpdateForm = false;
        this.modalRef?.hide();
        this.loadAll();
        window.location.reload();
      }
    } catch (error: any) {
      this.alertService.error(
        'Error: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
    } finally {
      this.saving = false;
    }
  }

  private async processInitialPayment(cardId: string) {
    const chargeData = {
      empresa_id: this.usuario.empresa.id,
      card_id: cardId,
      amount: this.usuario.monto_plan,
      customer_name: this.usuario.name,
      customer_email: this.usuario.email,
      customer_phone: this.usuario.telefono || '',
      description: `Suscripción plan ${this.usuario.plan}`,
    };

    const chargeResult = await firstValueFrom(
      this.n1coPaymentService.createMethodPaymentWithCharge(chargeData)
    );

    if (!chargeResult.success) {
      throw new Error(
        chargeResult.message || 'Error al procesar el pago inicial'
      );
    }

    return chargeResult;
  }

  private async handleThreeDSAuthentication(result: any) {
    this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
      result.authentication_url
    );
    this.mostrar3DSModal = true;

    // Configurar el intervalo para verificar la autenticación
    setTimeout(() => {
      const interval = setInterval(async () => {
        try {
          const authStatus = await firstValueFrom(
            this.n1coPaymentService.checkAuthenticationStatus({
              authentication_id: result.authentication_id,
              order_id: result.order_id,
            })
          );

          if (authStatus.estado === 'autenticacion_exitosa') {
            clearInterval(interval);
            this.mostrar3DSModal = false;
            await this.processThreeDSPayment(result);
          } else if (
            [
              'autenticacion_rechazada',
              'autenticacion_cancelada',
              'autenticacion_fallida',
            ].includes(authStatus.estado)
          ) {
            clearInterval(interval);
            this.mostrar3DSModal = false;
            this.alertService.error('La autenticación ha fallado');
            this.saving = false;
          }
        } catch (error) {
          clearInterval(interval);
          this.mostrar3DSModal = false;
          this.alertService.error('Error en la autenticación');
          this.saving = false;
        }
      }, 3000);
    }, 10000);
  }

  private async processThreeDSPayment(result: any) {
    try {
      const response = await firstValueFrom(
        this.n1coPaymentService.processDirectPayment3DS({
          authentication_id: result.authentication_id,
          order_id: result.order_id,
        })
      );

      if (response.success) {
        await this.refreshUserData();
        this.alertService.success('Éxito', 'Suscripción creada exitosamente');
        this.showUpdateForm = false;
        this.modalRef?.hide();
        this.loadAll();

        window.location.reload();
      } else {
        this.alertService.error(
          response.message || 'Error al procesar el pago'
        );
      }
    } catch (error: any) {
      this.alertService.error(
        'Error al procesar el pago: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
    } finally {
      this.saving = false;
    }
  }

  private async updatePayment() {
    try {
      const cardNumber = this.paymentData.cardNumber.replace(/\s/g, '');
      const expirationMonth = this.paymentData.expirationMonth.padStart(2, '0');

      const paymentMethodData = {
        customer: {
          id: this.usuario.id.toString(),
          name: this.usuario.name,
          email: this.usuario.email,
          phoneNumber: this.usuario.telefono || this.usuario.empresa.telefono,
        },
        card: {
          number: cardNumber,
          cardHolder: this.paymentData.cardHolder.trim(),
          expirationMonth,
          expirationYear: this.paymentData.expirationYear,
          cvv: this.paymentData.cvv,
        },
        billingInfo: this.billingInfo,
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.updateMethodPayment(paymentMethodData)
      );

      if (result.success) {
        await this.refreshUserData();
        this.alertService.success(
          'Éxito',
          'Método de pago actualizado exitosamente'
        );
        this.showUpdateForm = false;
        this.modalRef?.hide();
        this.loadAll();

        window.location.reload();
      }
    } catch (error: any) {
      this.alertService.error(
        'Error: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
    } finally {
      this.saving = false;
    }
  }

  // public imprimirDoc(recibo: any) {
  //   window.open(
  //     this.apiService.baseUrl +
  //       '/api/suscripcion/recibo/pdf/' +
  //       recibo.id +
  //       '?token=' +
  //       this.apiService.auth_token()
  //   );
  // }

  public imprimirRecibo(pago: any) {

    if (!pago) {
      console.error('Error: El objeto pago es nulo o indefinido');
      this.alertService.error('No se puede imprimir el recibo: Datos de pago no disponibles');
      return;
    }
    
    if (!this.suscripcion) {
      console.error('Error: El objeto suscripcion es nulo o indefinido');
      this.alertService.error('No se puede imprimir el recibo: Datos de suscripción no disponibles');
      return;
    }
    
    const idSuscripcion = this.suscripcion.suscripcion?.id;
    
    if (!idSuscripcion) {
      console.error('Error: El ID de suscripción no está definido');
      this.alertService.error('No se puede imprimir el recibo: ID de suscripción no disponible');
      return;
    }
  
    const queryParams = [
      `fecha=${encodeURIComponent(pago.fecha_transaccion || '')}`,
      `monto=${encodeURIComponent(pago.monto || '')}`,
      `estado=${encodeURIComponent(pago.estado || '')}`,
      `plan=${encodeURIComponent(pago.plan || '')}`
    ].join('&');
    
    const url = this.apiService.baseUrl +
      '/api/suscripcion/' + 
      idSuscripcion + 
      '/recibo-suscripcion?' +
      queryParams +
      '&token=' +
      this.apiService.auth_token();
    
    // console.log('URL generada:', url);
    window.open(url);
  }

  public isFormValid(): boolean {
    const cardNumber = this.paymentData.cardNumber.replace(/\s/g, '');
    const cardNumberValid = cardNumber.length >= 13 && cardNumber.length <= 16;
    const cardHolderValid = this.paymentData.cardHolder.trim().length > 0;
    const monthValid = /^(0[1-9]|1[0-2])$/.test(
      this.paymentData.expirationMonth
    );
    const yearValid = /^\d{2}$/.test(this.paymentData.expirationYear);
    const cvvValid = /^\d{3,4}$/.test(this.paymentData.cvv);
    const billingValid =
      Boolean(this.billingInfo.countryCode) &&
      this.billingInfo.stateCode?.length > 0 &&
      /^\d{4,5}$/.test(this.billingInfo.zipCode?.toString() || '');

    return (
      cardNumberValid && cardHolderValid && monthValid && yearValid && cvvValid
    );
  }

  openModal(template: TemplateRef<any>) {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-md',
      backdrop: 'static',
    });
  }

  loadPayments() {
    this.loading = true;
    this.apiService.getAll('suscripcion').subscribe({
      next: (data) => {
        this.suscripcion = data;
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al cargar los pagos');
        this.loading = false;
        console.error('Error:', error);
      },
    });
  }

  getConcepto(pago: any): string {
    return `Pago de suscripción del plan ${pago.plan}`;
  }

  getCardSvg(metodoPago: any): SafeHtml {
    const svgString = this.getCardSvgString(metodoPago);
    return this.sanitizer.bypassSecurityTrustHtml(svgString);
  }

  private getCardSvgString(metodoPago: any): string {
    switch (metodoPago.marca_tarjeta) {
      case 'Visa':
        return `<svg width="25px" height="25px" viewBox="0 -140 780 780" enable-background="new 0 0 780 500" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><rect width="780" height="500" fill="#0E4595"/><path d="m293.2 348.73l33.361-195.76h53.36l-33.385 195.76h-53.336zm246.11-191.54c-10.57-3.966-27.137-8.222-47.822-8.222-52.725 0-89.865 26.55-90.18 64.603-0.299 28.13 26.514 43.822 46.752 53.186 20.771 9.595 27.752 15.714 27.654 24.283-0.131 13.121-16.586 19.116-31.922 19.116-21.357 0-32.703-2.967-50.227-10.276l-6.876-3.11-7.489 43.823c12.463 5.464 35.51 10.198 59.438 10.443 56.09 0 92.5-26.246 92.916-66.882 0.199-22.269-14.016-39.216-44.801-53.188-18.65-9.055-30.072-15.099-29.951-24.268 0-8.137 9.668-16.839 30.557-16.839 17.449-0.27 30.09 3.535 39.938 7.5l4.781 2.26 7.232-42.429m137.31-4.223h-41.232c-12.773 0-22.332 3.487-27.941 16.234l-79.244 179.4h56.031s9.16-24.123 11.232-29.418c6.125 0 60.555 0.084 68.338 0.084 1.596 6.853 6.49 29.334 6.49 29.334h49.514l-43.188-195.64zm-65.418 126.41c4.412-11.279 21.26-54.723 21.26-54.723-0.316 0.522 4.379-11.334 7.074-18.684l3.605 16.879s10.219 46.729 12.354 56.528h-44.293zm-363.3-126.41l-52.24 133.5-5.567-27.13c-9.725-31.273-40.025-65.155-73.898-82.118l47.766 171.2 56.456-0.064 84.004-195.39h-56.521" fill="#ffffff"/><path d="m146.92 152.96h-86.041l-0.681 4.073c66.938 16.204 111.23 55.363 129.62 102.41l-18.71-89.96c-3.23-12.395-12.597-16.094-24.186-16.527" fill="#F2AE14"/></svg>`;
      case 'MasterCard':
        return `<svg  width="25px" height="25px" viewBox="0 -140 780 780" enable-background="new 0 0 780 500" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><rect width="780" height="500" fill="#16366F"/><path d="m449.01 250c0 99.143-80.37 179.5-179.51 179.5s-179.5-80.361-179.5-179.5c0-99.133 80.362-179.5 179.5-179.5 99.137 0 179.51 80.37 179.51 179.5" fill="#D9222A"/><path d="m510.49 70.496c-46.38 0-88.643 17.596-120.5 46.466-6.49 5.889-12.548 12.237-18.125 18.996h36.266c4.966 6.037 9.536 12.388 13.685 19.013h-63.635c-3.827 6.121-7.28 12.469-10.341 19.008h84.312c2.893 6.185 5.431 12.53 7.6 19.004h-99.512c-2.091 6.235-3.832 12.581-5.217 19.009h109.94c2.689 12.49 4.044 25.231 4.041 38.008 0 19.934-3.254 39.113-9.254 57.02h-99.512c2.164 6.479 4.7 12.825 7.595 19.01h84.317c-3.064 6.54-6.52 12.889-10.347 19.013h-63.625c4.154 6.629 8.73 12.979 13.685 18.996h36.258c-5.57 6.772-11.63 13.126-18.13 19.012 31.86 28.867 74.118 46.454 120.5 46.454 99.138-1e-3 179.51-80.362 179.51-179.5 0-99.13-80.37-179.5-179.51-179.5" fill="#EE9F2D"/><path d="m666.08 350.06c0-3.201 2.592-5.801 5.796-5.801s5.796 2.6 5.796 5.801c0 3.199-2.592 5.799-5.796 5.799-3.202-1e-3 -5.797-2.598-5.796-5.799zm5.796 4.408c2.435-1e-3 4.407-1.975 4.408-4.408 0-2.433-1.972-4.404-4.404-4.404h-4e-3c-2.429-4e-3 -4.4 1.963-4.404 4.392v0.013c-3e-3 2.432 1.967 4.406 4.399 4.408 1e-3 -1e-3 3e-3 -1e-3 5e-3 -1e-3zm-0.783-1.86h-1.188v-5.094h2.149c0.45 0 0.908 0 1.305 0.254 0.413 0.278 0.646 0.77 0.646 1.278 0 0.57-0.337 1.104-0.883 1.312l0.937 2.25h-1.315l-0.78-2.016h-0.87v2.016h-1e-3zm0-2.89h0.658c0.246 0 0.504 0.02 0.725-0.1 0.196-0.125 0.296-0.359 0.296-0.584 0-0.195-0.12-0.42-0.288-0.516-0.207-0.131-0.536-0.101-0.758-0.101h-0.633v1.301zm-443.5-80.063c-2.045-0.237-2.945-0.301-4.35-0.301-11.045 0-16.637 3.789-16.637 11.268 0 4.611 2.73 7.546 6.987 7.546 7.938 0 13.659-7.56 14-18.513zm14.171 32.996h-16.146l0.371-7.676c-4.925 6.067-11.496 8.95-20.425 8.95-10.562 0-17.804-8.25-17.804-20.229 0-18.024 12.596-28.54 34.217-28.54 2.208 0 5.041 0.2 7.941 0.569 0.605-2.441 0.763-3.486 0.763-4.8 0-4.908-3.396-6.738-12.5-6.738-9.533-0.108-17.396 2.271-20.625 3.334 0.204-1.23 2.7-16.658 2.7-16.658 9.712-2.846 16.117-3.917 23.325-3.917 16.733 0 25.596 7.512 25.58 21.712 0.032 3.805-0.597 8.5-1.58 14.671-1.692 10.731-5.32 33.718-5.817 39.322zm-62.158 0h-19.488l11.163-69.997-24.925 69.997h-13.28l-1.64-69.597-11.734 69.597h-18.242l15.238-91.054h28.02l1.7 50.966 17.092-50.966h31.167l-15.071 91.054m354.98-32.996c-2.037-0.237-2.942-0.301-4.342-0.301-11.041 0-16.634 3.789-16.634 11.268 0 4.611 2.726 7.546 6.983 7.546 7.939 0 13.664-7.56 13.993-18.513zm14.183 32.996h-16.145l0.365-7.676c-4.925 6.067-11.5 8.95-20.42 8.95-10.566 0-17.8-8.25-17.8-20.229 0-18.024 12.587-28.54 34.212-28.54 2.208 0 5.037 0.2 7.934 0.569 0.604-2.441 0.763-3.486 0.763-4.8 0-4.908-3.392-6.738-12.496-6.738-9.533-0.108-17.388 2.271-20.63 3.334 0.205-1.23 2.709-16.658 2.709-16.658 9.713-2.846 16.113-3.917 23.312-3.917 16.741 0 25.604 7.512 25.588 21.712 0.032 3.805-0.597 8.5-1.58 14.671-1.682 10.731-5.32 33.718-5.812 39.322zm-220.39-1.125c-5.334 1.68-9.492 2.399-14 2.399-9.963 0-15.4-5.725-15.4-16.267-0.142-3.27 1.433-11.879 2.67-19.737 1.125-6.917 8.45-50.53 8.45-50.53h19.371l-2.262 11.209h11.7l-2.643 17.796h-11.742c-2.25 14.083-5.454 31.625-5.491 33.95 0 3.817 2.037 5.483 6.67 5.483 2.221 0 3.941-0.226 5.255-0.7l-2.578 16.397m59.391-0.6c-6.654 2.033-13.075 3.017-19.879 3-21.683-0.021-32.987-11.346-32.987-33.032 0-25.313 14.38-43.947 33.9-43.947 15.97 0 26.17 10.433 26.17 26.796 0 5.429-0.7 10.729-2.387 18.212h-38.575c-1.304 10.742 5.57 15.217 16.837 15.217 6.935 0 13.188-1.43 20.142-4.663l-3.221 18.417zm-10.887-43.9c0.107-1.543 2.054-13.217-9.013-13.217-6.171 0-10.583 4.704-12.38 13.217h21.393zm-123.42-5.017c0 9.367 4.541 15.825 14.841 20.676 7.892 3.709 9.113 4.809 9.113 8.17 0 4.617-3.48 6.7-11.192 6.7-5.812 0-11.22-0.907-17.458-2.92 0 0-2.563 16.32-2.68 17.101 4.43 0.966 8.38 1.861 20.28 2.19 20.562 0 30.058-7.829 30.058-24.75 0-10.175-3.975-16.146-13.737-20.633-8.171-3.75-9.109-4.588-9.109-8.046 0-4.004 3.238-6.046 9.538-6.046 3.825 0 9.05 0.408 14 1.113l2.775-17.175c-5.046-0.8-12.696-1.442-17.15-1.442-21.8 0-29.346 11.387-29.279 25.062m229.09-23.116c5.413 0 10.459 1.42 17.413 4.92l3.187-19.762c-2.854-1.12-12.904-7.7-21.416-7.7-13.042 0-24.066 6.47-31.82 17.15-11.31-3.746-15.959 3.825-21.659 11.367l-5.062 1.179c0.383-2.483 0.73-4.95 0.613-7.446h-17.896c-2.445 22.917-6.779 46.13-10.171 69.075l-0.884 4.976h19.496c3.254-21.143 5.038-34.681 6.121-43.842l7.342-4.084c1.096-4.08 4.529-5.458 11.416-5.292-0.926 5.008-1.389 10.09-1.383 15.184 0 24.225 13.071 39.308 34.05 39.308 5.404 0 10.042-0.712 17.221-2.657l3.431-20.76c-6.46 3.18-11.761 4.676-16.561 4.676-11.328 0-18.183-8.362-18.183-22.184-1e-3 -20.05 10.195-34.108 24.745-34.108"/><path d="m185.21 297.24h-19.491l11.17-69.988-24.925 69.988h-13.282l-1.642-69.588-11.733 69.588h-18.243l15.238-91.042h28.02l0.788 56.362 18.904-56.362h30.267l-15.071 91.042" fill="#ffffff"/><path d="m647.52 211.6l-4.319 26.308c-5.33-7.012-11.054-12.087-18.612-12.087-9.834 0-18.784 7.454-24.642 18.425-8.158-1.692-16.597-4.563-16.597-4.563l-4e-3 0.067c0.658-6.133 0.92-9.875 0.862-11.146h-17.9c-2.437 22.917-6.77 46.13-10.157 69.075l-0.893 4.976h19.492c2.633-17.097 4.65-31.293 6.133-42.551 6.659-6.017 9.992-11.267 16.721-10.917-2.979 7.206-4.725 15.504-4.725 24.017 0 18.513 9.367 30.725 23.534 30.725 7.141 0 12.62-2.462 17.966-8.17l-0.912 6.884h18.433l14.842-91.043h-19.222zm-24.37 73.942c-6.634 0-9.983-4.909-9.983-14.597 0-14.553 6.271-24.875 15.112-24.875 6.695 0 10.32 5.104 10.32 14.508 1e-3 14.681-6.369 24.964-15.449 24.964z"/><path d="m233.19 264.26c-2.042-0.236-2.946-0.3-4.346-0.3-11.046 0-16.634 3.788-16.634 11.267 0 4.604 2.73 7.547 6.98 7.547 7.945-1e-3 13.666-7.559 14-18.514zm14.179 32.984h-16.146l0.367-7.663c-4.921 6.054-11.5 8.95-20.421 8.95-10.567 0-17.804-8.25-17.804-20.229 0-18.032 12.591-28.542 34.216-28.542 2.209 0 5.042 0.2 7.938 0.571 0.604-2.442 0.762-3.487 0.762-4.808 0-4.908-3.391-6.73-12.496-6.73-9.537-0.108-17.395 2.272-20.629 3.322 0.204-1.226 2.7-16.638 2.7-16.638 9.709-2.858 16.121-3.93 23.321-3.93 16.738 0 25.604 7.518 25.588 21.705 0.029 3.82-0.605 8.512-1.584 14.675-1.687 10.725-5.32 33.725-5.812 39.317zm261.38-88.592l-3.192 19.767c-6.95-3.496-12-4.921-17.407-4.921-14.551 0-24.75 14.058-24.75 34.107 0 13.821 6.857 22.181 18.183 22.181 4.8 0 10.096-1.492 16.554-4.677l-3.42 20.75c-7.184 1.959-11.816 2.672-17.226 2.672-20.976 0-34.05-15.084-34.05-39.309 0-32.55 18.059-55.3 43.888-55.3 8.507 1e-3 18.562 3.609 21.42 4.73m31.442 55.608c-2.041-0.236-2.941-0.3-4.346-0.3-11.042 0-16.634 3.788-16.634 11.267 0 4.604 2.729 7.547 6.984 7.547 7.937-1e-3 13.662-7.559 13.996-18.514zm14.179 32.984h-16.15l0.37-7.663c-4.924 6.054-11.5 8.95-20.42 8.95-10.563 0-17.804-8.25-17.804-20.229 0-18.032 12.595-28.542 34.212-28.542 2.213 0 5.042 0.2 7.941 0.571 0.601-2.442 0.763-3.487 0.763-4.808 0-4.908-3.392-6.73-12.496-6.73-9.533-0.108-17.396 2.272-20.629 3.322 0.204-1.226 2.704-16.638 2.704-16.638 9.709-2.858 16.116-3.93 23.316-3.93 16.742 0 25.604 7.518 25.583 21.705 0.034 3.82-0.595 8.512-1.579 14.675-1.682 10.725-5.324 33.725-5.811 39.317zm-220.39-1.122c-5.338 1.68-9.496 2.409-14 2.409-9.963 0-15.4-5.726-15.4-16.266-0.138-3.281 1.437-11.881 2.675-19.738 1.12-6.926 8.446-50.533 8.446-50.533h19.367l-2.259 11.212h9.942l-2.646 17.788h-9.975c-2.25 14.091-5.463 31.619-5.496 33.949 0 3.83 2.042 5.483 6.671 5.483 2.22 0 3.938-0.217 5.254-0.692l-2.579 16.388m59.392-0.591c-6.65 2.033-13.08 3.013-19.88 3-21.684-0.021-32.987-11.346-32.987-33.033 0-25.321 14.38-43.95 33.9-43.95 15.97 0 26.17 10.429 26.17 26.8 0 5.433-0.7 10.733-2.382 18.212h-38.575c-1.306 10.741 5.569 15.221 16.837 15.221 6.93 0 13.188-1.434 20.137-4.676l-3.22 18.426zm-10.892-43.912c0.117-1.538 2.059-13.217-9.013-13.217-6.166 0-10.579 4.717-12.375 13.217h21.388zm-123.42-5.004c0 9.365 4.542 15.816 14.842 20.675 7.891 3.708 9.112 4.812 9.112 8.17 0 4.617-3.483 6.7-11.187 6.7-5.817 0-11.225-0.908-17.467-2.92 0 0-2.554 16.32-2.67 17.1 4.42 0.967 8.374 1.85 20.274 2.191 20.567 0 30.059-7.829 30.059-24.746 0-10.18-3.971-16.15-13.738-20.637-8.167-3.758-9.112-4.583-9.112-8.046 0-4 3.245-6.058 9.541-6.058 3.821 0 9.046 0.42 14.004 1.125l2.771-17.18c-5.041-0.8-12.691-1.441-17.146-1.441-21.804 0-29.345 11.379-29.283 25.067m398.45 50.629h-18.437l0.917-6.893c-5.347 5.717-10.825 8.18-17.967 8.18-14.168 0-23.53-12.213-23.53-30.725 0-24.63 14.521-45.393 31.709-45.393 7.558 0 13.28 3.088 18.604 10.096l4.325-26.308h19.221l-14.842 91.043zm-28.745-17.109c9.075 0 15.45-10.283 15.45-24.953 0-9.405-3.63-14.509-10.325-14.509-8.838 0-15.116 10.317-15.116 24.875-1e-3 9.686 3.357 14.587 9.991 14.587zm-56.843-56.929c-2.439 22.917-6.773 46.13-10.162 69.063l-0.891 4.975h19.491c6.971-45.275 8.658-54.117 19.588-53.009 1.742-9.266 4.982-17.383 7.399-21.479-8.163-1.7-12.721 2.913-18.688 11.675 0.471-3.787 1.334-7.466 1.163-11.225h-17.9m-160.42 0c-2.446 22.917-6.78 46.13-10.167 69.063l-0.887 4.975h19.5c6.962-45.275 8.646-54.117 19.569-53.009 1.75-9.266 4.992-17.383 7.4-21.479-8.154-1.7-12.716 2.913-18.678 11.675 0.47-3.787 1.325-7.466 1.162-11.225h-17.899m254.57 68.242c0-3.214 2.596-5.8 5.796-5.8 3.197-3e-3 5.792 2.587 5.795 5.785v0.015c-1e-3 3.2-2.595 5.794-5.795 5.796-3.2-2e-3 -5.794-2.596-5.796-5.796zm5.796 4.404c2.432 1e-3 4.403-1.97 4.403-4.401v-2e-3c3e-3 -2.433-1.968-4.406-4.399-4.408h-4e-3c-2.435 1e-3 -4.408 1.974-4.409 4.408 3e-3 2.432 1.976 4.403 4.409 4.403zm-0.784-1.87h-1.188v-5.084h2.154c0.446 0 0.908 8e-3 1.296 0.254 0.416 0.283 0.654 0.767 0.654 1.274 0 0.575-0.338 1.113-0.888 1.317l0.941 2.236h-1.319l-0.78-2.008h-0.87v2.008 3e-3zm0-2.88h0.654c0.245 0 0.513 0.018 0.729-0.1 0.195-0.125 0.295-0.361 0.295-0.587-9e-3 -0.21-0.115-0.404-0.287-0.524-0.204-0.117-0.542-0.085-0.763-0.085h-0.629v1.296h1e-3z" fill="#ffffff"/></svg>`;
      default:
        return `<svg  width="25px" height="25px" viewBox="0 -140 780 780" enable-background="new 0 0 780 500" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><path d="M40,0h700c22.092,0,40,17.909,40,40v420c0,22.092-17.908,40-40,40H40c-22.091,0-40-17.908-40-40V40  C0,17.909,17.909,0,40,0z" fill="#000C9D"/><rect x="66.908" y="106.91" width="110.32" height="99.679" fill="#9D9400"/><path d="m94.714 284.15v-3.795h-5.117v-11.27h-4.198l-0.402 11.27h-11.443l10.58-25.07-3.967-1.725-11.673 27.141v3.449h16.445v9.66h4.658v-9.66h5.117zm19.586-30.589c-4.025 0-7.878 1.381-11.213 4.6l2.588 2.934c2.645-2.473 5.002-3.68 8.395-3.68 4.197 0 7.532 2.357 7.532 6.727 0 4.773-3.737 6.959-7.532 6.959h-2.358l-0.575 3.795h3.335c4.658 0 8.223 1.84 8.223 7.531 0 4.945-3.278 8.107-8.855 8.107-3.22 0-6.555-1.322-8.798-3.967l-3.22 2.645c2.99 3.68 7.705 5.232 12.133 5.232 8.165 0 13.742-5.174 13.742-12.018 0-6.152-4.37-9.371-9.027-9.717 4.197-0.807 7.762-4.43 7.762-9.199 0-5.406-4.715-9.949-12.132-9.949zm34.477 0c-5.347 0-8.912 1.896-12.075 5.693l3.335 2.529c2.53-2.934 4.658-4.197 8.568-4.197 4.427 0 7.072 2.76 7.072 7.188 0 6.496-3.22 10.809-18.17 25.127v3.908h23.518l0.575-4.08h-18.63c13.052-11.904 17.71-17.826 17.71-25.07 0-6.325-4.428-11.098-11.903-11.098zm48.738 36.339h-8.28v-35.648h-4.198l-11.73 7.244 2.07 3.393 9.085-5.463v30.476h-9.775v3.908h22.828v-3.91zm69.587-5.75v-3.795h-5.117v-11.27h-4.198l-0.402 11.27h-11.443l10.58-25.07-3.967-1.725-11.673 27.141v3.449h16.445v9.66h4.658v-9.66h5.117zm19.585-30.589c-4.025 0-7.877 1.381-11.212 4.6l2.587 2.934c2.645-2.473 5.003-3.68 8.395-3.68 4.198 0 7.533 2.357 7.533 6.727 0 4.773-3.738 6.959-7.533 6.959h-2.357l-0.575 3.795h3.335c4.657 0 8.222 1.84 8.222 7.531 0 4.945-3.277 8.107-8.855 8.107-3.22 0-6.555-1.322-8.797-3.967l-3.22 2.645c2.99 3.68 7.705 5.232 12.132 5.232 8.165 0 13.743-5.174 13.743-12.018 0-6.152-4.37-9.371-9.028-9.717 4.198-0.807 7.763-4.43 7.763-9.199 0-5.406-4.715-9.949-12.133-9.949zm34.478 0c-5.348 0-8.913 1.896-12.075 5.693l3.335 2.529c2.53-2.934 4.657-4.197 8.567-4.197 4.428 0 7.073 2.76 7.073 7.188 0 6.496-3.221 10.809-18.171 25.127v3.908h23.518l0.575-4.08h-18.63c13.053-11.904 17.71-17.826 17.71-25.07 0-6.325-4.427-11.098-11.902-11.098zm48.737 36.339h-8.28v-35.648h-4.196l-11.729 7.244 2.069 3.393 9.085-5.463v30.476h-9.774v3.908h22.827l-2e-3 -3.91zm69.588-5.75v-3.795h-5.119v-11.27h-4.197l-0.401 11.27h-11.443l10.58-25.07-3.969-1.725-11.672 27.141v3.449h16.445v9.66h4.656v-9.66h5.12zm19.584-30.589c-4.023 0-7.877 1.381-11.213 4.6l2.588 2.934c2.646-2.473 5.002-3.68 8.396-3.68 4.195 0 7.531 2.357 7.531 6.727 0 4.773-3.736 6.959-7.531 6.959h-2.358l-0.574 3.795h3.334c4.658 0 8.225 1.84 8.225 7.531 0 4.945-3.278 8.107-8.854 8.107-3.222 0-6.556-1.322-8.799-3.967l-3.22 2.645c2.988 3.68 7.703 5.232 12.134 5.232 8.163 0 13.741-5.174 13.741-12.018 0-6.152-4.371-9.371-9.026-9.717 4.196-0.807 7.762-4.43 7.762-9.199-3e-3 -5.406-4.718-9.949-12.136-9.949zm34.479 0c-5.348 0-8.912 1.896-12.076 5.693l3.337 2.529c2.528-2.934 4.657-4.197 8.565-4.197 4.428 0 7.072 2.76 7.072 7.188 0 6.496-3.219 10.809-18.17 25.127v3.908h23.518l0.576-4.08h-18.631c13.053-11.904 17.711-17.826 17.711-25.07 0-6.326-4.428-11.098-11.904-11.098h2e-3zm48.736 36.339h-8.279v-35.648h-4.197l-11.729 7.244 2.07 3.393 9.084-5.463v30.476h-9.775v3.908h22.828l-2e-3 -3.91zm69.588-5.75v-3.795h-5.117v-11.27h-4.197l-0.401 11.27h-11.443l10.58-25.07-3.967-1.725-11.672 27.141v3.449h16.445v9.66h4.655v-9.66h5.117zm19.584-30.589c-4.023 0-7.877 1.381-11.211 4.6l2.588 2.934c2.646-2.473 5.002-3.68 8.396-3.68 4.196 0 7.532 2.357 7.532 6.727 0 4.773-3.737 6.959-7.532 6.959h-2.357l-0.574 3.795h3.334c4.658 0 8.224 1.84 8.224 7.531 0 4.945-3.277 8.107-8.855 8.107-3.219 0-6.555-1.322-8.797-3.967l-3.221 2.645c2.99 3.68 7.705 5.232 12.133 5.232 8.166 0 13.742-5.174 13.742-12.018 0-6.152-4.369-9.371-9.027-9.717 4.197-0.807 7.764-4.43 7.764-9.199 0-5.406-4.715-9.949-12.133-9.949h-6e-3zm34.478 0c-5.347 0-8.912 1.896-12.074 5.693l3.334 2.529c2.531-2.934 4.658-4.197 8.567-4.197 4.429 0 7.072 2.76 7.072 7.188 0 6.496-3.221 10.809-18.17 25.127v3.908h23.519l0.575-4.08h-18.631c13.054-11.904 17.711-17.826 17.711-25.07 2e-3 -6.325-4.428-11.098-11.903-11.098zm48.739 36.339h-8.278v-35.648h-4.2l-11.729 7.244 2.068 3.393 9.086-5.463v30.476h-9.775v3.908h22.828v-3.91z" fill="#ffffff"/><path d="m72.219 389.2h6.445v-22.246l-7.012 1.406v-3.594l6.973-1.406h3.945v25.84h6.446v3.32h-16.797v-3.32zm25.468 0h6.446v-22.246l-7.012 1.406v-3.594l6.973-1.406h3.945v25.84h6.445v3.32h-16.797v-3.32zm40.918 0h13.77v3.32h-18.516v-3.32c1.497-1.549 3.535-3.625 6.114-6.229 2.591-2.617 4.218-4.305 4.882-5.061 1.263-1.42 2.142-2.617 2.637-3.594 0.508-0.988 0.762-1.959 0.762-2.91 0-1.549-0.547-2.811-1.64-3.789-1.081-0.977-2.495-1.465-4.24-1.465-1.237 0-2.545 0.215-3.925 0.646-1.367 0.43-2.831 1.08-4.394 1.953v-3.986c1.588-0.637 3.072-1.119 4.453-1.443 1.38-0.326 2.643-0.486 3.789-0.486 3.021 0 5.429 0.754 7.226 2.264 1.797 1.512 2.696 3.529 2.696 6.055 0 1.199-0.228 2.338-0.684 3.418-0.442 1.068-1.256 2.332-2.441 3.789-0.326 0.377-1.361 1.471-3.106 3.281-1.745 1.796-4.206 4.315-7.383 7.557zm34.024-12.402c1.888 0.404 3.359 1.244 4.414 2.521 1.067 1.275 1.601 2.852 1.601 4.727 0 2.877-0.99 5.104-2.968 6.682-1.979 1.574-4.792 2.361-8.438 2.361-1.224 0-2.487-0.125-3.789-0.371-1.289-0.234-2.623-0.594-4.004-1.074v-3.809c1.093 0.639 2.292 1.119 3.594 1.445 1.302 0.324 2.663 0.488 4.082 0.488 2.474 0 4.356-0.488 5.645-1.465 1.302-0.979 1.953-2.396 1.953-4.26 0-1.719-0.605-3.061-1.817-4.021-1.198-0.979-2.872-1.465-5.02-1.465h-3.398v-3.242h3.555c1.94 0 3.425-0.385 4.453-1.152 1.029-0.781 1.543-1.9 1.543-3.359 0-1.496-0.534-2.645-1.601-3.438-1.055-0.809-2.572-1.211-4.551-1.211-1.081 0-2.24 0.117-3.477 0.354-1.237 0.232-2.597 0.598-4.082 1.092v-3.514c1.498-0.418 2.897-0.73 4.2-0.939 1.314-0.207 2.551-0.311 3.71-0.311 2.995 0 5.365 0.682 7.11 2.049 1.745 1.354 2.617 3.189 2.617 5.508 0 1.615-0.462 2.98-1.387 4.102-0.925 1.105-2.24 1.872-3.945 2.302zm26.289-13.438h15.488v3.32h-11.874v7.148c0.573-0.195 1.146-0.34 1.719-0.43 0.573-0.105 1.146-0.156 1.719-0.156 3.255 0 5.833 0.893 7.734 2.676s2.852 4.199 2.852 7.246c0 3.139-0.977 5.578-2.93 7.324-1.954 1.732-4.708 2.598-8.262 2.598-1.224 0-2.474-0.104-3.75-0.312-1.263-0.207-2.571-0.521-3.926-0.938v-3.965c1.172 0.639 2.383 1.113 3.633 1.426s2.572 0.469 3.965 0.469c2.253 0 4.037-0.594 5.352-1.777s1.972-2.793 1.972-4.824-0.657-3.639-1.972-4.824-3.099-1.775-5.352-1.775c-1.055 0-2.109 0.117-3.164 0.352-1.041 0.234-2.109 0.6-3.203 1.092l-1e-3 -14.65zm33.867 15.313c-1.875 0-3.353 0.502-4.434 1.506-1.067 1.002-1.601 2.383-1.601 4.141s0.534 3.137 1.601 4.139c1.082 1.004 2.56 1.506 4.434 1.506 1.875 0 3.354-0.502 4.434-1.504 1.08-1.016 1.62-2.396 1.62-4.141 0-1.758-0.54-3.139-1.62-4.141-1.068-1.004-2.546-1.506-4.434-1.506zm-3.945-1.68c-1.693-0.416-3.015-1.203-3.965-2.363-0.938-1.158-1.406-2.57-1.406-4.236 0-2.332 0.827-4.174 2.48-5.527 1.667-1.354 3.945-2.029 6.836-2.029 2.904 0 5.183 0.676 6.836 2.027 1.653 1.355 2.48 3.197 2.48 5.529 0 1.666-0.475 3.078-1.425 4.236-0.938 1.16-2.247 1.947-3.926 2.363 1.9 0.443 3.378 1.311 4.434 2.598 1.067 1.289 1.601 2.865 1.601 4.729 0 2.824-0.866 4.992-2.598 6.504-1.719 1.51-4.186 2.266-7.402 2.266s-5.69-0.756-7.422-2.266c-1.719-1.512-2.578-3.68-2.578-6.504 0-1.863 0.534-3.439 1.602-4.729 1.067-1.289 2.551-2.154 4.453-2.598zm-1.446-6.228c0 1.51 0.469 2.688 1.407 3.533 0.95 0.848 2.278 1.271 3.984 1.271 1.693 0 3.015-0.424 3.965-1.271 0.963-0.848 1.445-2.023 1.445-3.535 0-1.51-0.481-2.688-1.445-3.535-0.951-0.846-2.272-1.271-3.965-1.271-1.706 0-3.034 0.426-3.984 1.271-0.938 0.847-1.407 2.025-1.407 3.537z" fill="#ffffff" fill-opacity=".784"/><path d="m325.45 388.23h6.444v-22.246l-7.012 1.406v-3.594l6.973-1.406h3.946v25.84h6.444v3.32h-16.797l2e-3 -3.32zm36.738-12.404c1.889 0.404 3.359 1.244 4.415 2.521 1.067 1.275 1.602 2.852 1.602 4.727 0 2.877-0.989 5.105-2.969 6.68-1.979 1.576-4.791 2.363-8.438 2.363-1.224 0-2.487-0.123-3.79-0.371-1.288-0.232-2.622-0.592-4.003-1.074v-3.809c1.094 0.639 2.292 1.121 3.595 1.445 1.303 0.326 2.662 0.488 4.082 0.488 2.474 0 4.354-0.488 5.645-1.465 1.302-0.977 1.953-2.396 1.953-4.258 0-1.719-0.605-3.061-1.816-4.023-1.197-0.977-2.871-1.465-5.02-1.465h-3.398v-3.242h3.556c1.939 0 3.425-0.385 4.453-1.152 1.028-0.781 1.543-1.9 1.543-3.359 0-1.496-0.533-2.643-1.603-3.438-1.055-0.807-2.571-1.209-4.55-1.209-1.081 0-2.24 0.115-3.479 0.35-1.236 0.234-2.598 0.6-4.081 1.096v-3.518c1.497-0.416 2.896-0.729 4.198-0.938 1.315-0.209 2.553-0.312 3.71-0.312 2.996 0 5.365 0.686 7.11 2.053 1.744 1.354 2.616 3.189 2.616 5.508 0 1.613-0.462 2.98-1.386 4.102-0.923 1.103-2.239 1.872-3.945 2.3zm16.915 12.404h13.771v3.32h-18.518v-3.32c1.498-1.551 3.536-3.627 6.114-6.23 2.59-2.617 4.218-4.305 4.883-5.059 1.264-1.42 2.143-2.617 2.637-3.594 0.508-0.99 0.762-1.961 0.762-2.91 0-1.549-0.547-2.812-1.64-3.789-1.081-0.977-2.494-1.465-4.239-1.465-1.236 0-2.545 0.215-3.926 0.645-1.367 0.43-2.831 1.08-4.396 1.953v-3.984c1.589-0.639 3.073-1.119 4.453-1.445s2.645-0.488 3.789-0.488c3.021 0 5.43 0.756 7.227 2.268 1.797 1.51 2.695 3.527 2.695 6.055 0 1.195-0.227 2.336-0.684 3.418-0.441 1.066-1.256 2.33-2.441 3.789-0.325 0.377-1.36 1.471-3.104 3.279-1.746 1.796-4.206 4.315-7.383 7.557zm22.753 0h6.447v-22.246l-7.014 1.406v-3.594l6.975-1.406h3.943v25.84h6.445v3.32h-16.798l2e-3 -3.32zm49.472-12.404c1.889 0.404 3.358 1.244 4.414 2.521 1.067 1.275 1.604 2.852 1.604 4.727 0 2.877-0.99 5.105-2.971 6.68-1.979 1.576-4.791 2.363-8.438 2.363-1.223 0-2.485-0.123-3.788-0.371-1.289-0.232-2.623-0.592-4.005-1.074v-3.809c1.095 0.639 2.293 1.121 3.595 1.445 1.303 0.326 2.664 0.488 4.082 0.488 2.475 0 4.354-0.488 5.645-1.465 1.303-0.977 1.953-2.396 1.953-4.258 0-1.719-0.605-3.061-1.814-4.023-1.198-0.977-2.873-1.465-5.021-1.465h-3.396v-3.242h3.554c1.94 0 3.424-0.385 4.453-1.152 1.028-0.781 1.543-1.9 1.543-3.359 0-1.496-0.533-2.643-1.603-3.438-1.055-0.807-2.569-1.209-4.551-1.209-1.08 0-2.238 0.115-3.477 0.35-1.236 0.234-2.599 0.6-4.082 1.096v-3.518c1.497-0.416 2.897-0.729 4.2-0.938 1.314-0.209 2.552-0.312 3.709-0.312 2.996 0 5.365 0.686 7.109 2.053 1.746 1.354 2.617 3.189 2.617 5.508 0 1.613-0.461 2.98-1.387 4.102-0.923 1.103-2.238 1.872-3.945 2.3zm24.356-10l-9.959 15.568h9.959v-15.568zm-1.036-3.435h4.961v19.004h4.159v3.281h-4.159v6.875h-3.925v-6.875h-13.166v-3.811l12.132-18.477h-2e-3v3e-3zm15.706 0h15.486v3.32h-11.875v7.146c0.574-0.195 1.146-0.338 1.721-0.43 0.572-0.104 1.146-0.156 1.718-0.156 3.256 0 5.834 0.893 7.735 2.676 1.9 1.785 2.851 4.199 2.851 7.246 0 3.139-0.978 5.58-2.931 7.324-1.953 1.73-4.707 2.598-8.262 2.598-1.223 0-2.473-0.104-3.75-0.311-1.262-0.209-2.57-0.521-3.926-0.939v-3.965c1.172 0.639 2.383 1.113 3.633 1.426 1.25 0.314 2.572 0.469 3.967 0.469 2.252 0 4.035-0.592 5.351-1.775 1.315-1.186 1.973-2.793 1.973-4.824s-0.656-3.641-1.973-4.824c-1.314-1.186-3.099-1.777-5.351-1.777-1.055 0-2.108 0.117-3.164 0.352-1.043 0.234-2.11 0.6-3.203 1.094v-14.65zm25.466 0h15.489v3.32h-11.877v7.146c0.572-0.195 1.146-0.338 1.72-0.43 0.571-0.104 1.146-0.156 1.719-0.156 3.256 0 5.832 0.893 7.733 2.676 1.9 1.785 2.853 4.199 2.853 7.246 0 3.139-0.978 5.58-2.93 7.324-1.953 1.73-4.707 2.598-8.263 2.598-1.225 0-2.475-0.104-3.75-0.311-1.264-0.209-2.571-0.521-3.926-0.939v-3.965c1.172 0.639 2.383 1.113 3.633 1.426 1.25 0.314 2.572 0.469 3.965 0.469 2.252 0 4.037-0.592 5.353-1.775 1.313-1.186 1.973-2.793 1.973-4.824s-0.658-3.641-1.973-4.824c-1.316-1.186-3.101-1.777-5.353-1.777-1.055 0-2.108 0.117-3.164 0.352-1.043 0.234-2.108 0.6-3.202 1.094v-14.65z" fill="#ffffff" fill-opacity=".784"/></svg>`;
    }
  }

  private async handle3DSAuth(paymentResult: any) {
    try {
      const auth = await firstValueFrom(
        this.n1coPaymentService.handle3DSAuth(paymentResult.authentication.url)
      );

      if (auth.success) {
        const validationResult = await firstValueFrom(
          this.n1coPaymentService.validatePayment(paymentResult.orderId)
        );

        if (validationResult.success) {
          this.alertService.success('Éxito', 'Pago procesado exitosamente');
          this.router.navigate(['/dashboard']);
        } else {
          this.alertService.error('Error en la validación del pago');
        }
      } else {
        this.alertService.error('Error en la autenticación del pago');
      }
    } catch (error: any) {
      this.alertService.error(
        'Error en la autenticación: ' + (error.message || '')
      );
    }
  }

  public async createMethodPaymentWithCharge() {
    if (this.processingPayment) return;

    this.processingPayment = true;

    try {
      // Formatear el número de tarjeta: eliminar espacios y validar longitud
      const cardNumber = this.paymentData.cardNumber.replace(/\s/g, '');
      if (cardNumber.length < 13 || cardNumber.length > 16) {
        this.alertService.error(
          'El número de tarjeta debe tener entre 13 y 16 dígitos'
        );
        return;
      }

      // Formatear el mes con padding si es necesario
      const expirationMonth = this.paymentData.expirationMonth.padStart(2, '0');

      const paymentMethodData = {
        customer: {
          id: this.usuario.id.toString(),
          name: this.usuario.name,
          email: this.usuario.email,
          phoneNumber: this.usuario.telefono || '',
        },
        card: {
          number: cardNumber,
          cardHolder: this.paymentData.cardHolder.trim(),
          expirationMonth: expirationMonth,
          expirationYear: this.paymentData.expirationYear,
          cvv: this.paymentData.cvv,
        },
        plan: {
          id_plan: this.usuario.plan_id,
          plan_name: this.usuario.plan,
        },
        billingInfo: {
          countryCode: this.billingInfo.countryCode,
          stateCode: this.billingInfo.stateCode,
          zipCode: this.billingInfo.zipCode,
        },
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.createPaymentMethod(paymentMethodData)
      );

      if (result.requires_3ds) {
        // console.log('Resultado 3DS:', result);
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
          result.authentication_url
        );
        this.mostrar3DSModal = true;

        const checkAuthentication = async () => {
          try {
            const authStatus = await firstValueFrom(
              this.n1coPaymentService.checkAuthenticationStatus({
                authentication_id: result.authentication_id,
                order_id: result.order_id,
              })
            );

            // Usar los estados definidos en tus constantes
            switch (authStatus.estado) {
              case 'autenticacion_exitosa':
                this.mostrar3DSModal = false;
                const response = await firstValueFrom(
                  this.n1coPaymentService.processDirectPayment3DS({
                    authentication_id: result.authentication_id,
                    order_id: result.order_id,
                  })
                );

                if (response.success) {
                  this.alertService.success(
                    'Éxito',
                    'Pago procesado exitosamente'
                  );
                  this.n1coPaymentService.setPaymentResponse(response);
                  this.router.navigate(['/pago-exitoso']);
                }
                return true;

              case 'autenticacion_rechazada':
              case 'autenticacion_cancelada':
              case 'autenticacion_fallida':
                this.mostrar3DSModal = false;
                this.alertService.error(
                  `La autenticación ha fallado, intentalo nuevamente o contacta con nosotros`
                );
                return true;

              case 'autenticacion_pendiente':
                return false;

              default:
                return false;
            }
          } catch (error) {
            console.error('Error verificando autenticación:', error);
            this.alertService.error(
              'Error verificando el estado de la autenticación'
            );
            this.mostrar3DSModal = false;
            return true;
          }
        };

        // Esperar 10 segundos antes de empezar a verificar
        setTimeout(async () => {
          const interval = setInterval(async () => {
            const shouldStop = await checkAuthentication();
            if (shouldStop) {
              clearInterval(interval);
              this.processingPayment = false;
            }
          }, 3000);

          // Tiempo máximo de espera
          setTimeout(() => {
            clearInterval(interval);
            if (this.mostrar3DSModal) {
              this.mostrar3DSModal = false;
              this.alertService.error('El tiempo de autenticación ha expirado');
              this.processingPayment = false;
            }
          }, 120000);
        }, 10000);

        return;
      }

      if (result.success) {
        // Proceder con el cargo usando el ID del método de pago
        const chargeData = {
          empresa_id: this.usuario.empresa.id,
          card_id: result.data.id,
          amount: this.usuario.empresa.plan.precio,
          customer_name: this.usuario.name,
          customer_email: this.usuario.email,
          customer_phone: this.usuario.telefono || '',
          description: `Pago plan ${this.usuario.empresa.plan.nombre}`,
        };

        const chargeResult = await firstValueFrom(
          this.n1coPaymentService.createMethodPaymentWithCharge(chargeData)
        );

        // Manejar la respuesta del cargo
        if (chargeResult.success) {
          this.alertService.success('Éxito', 'Pago procesado exitosamente');
          this.router.navigate(['/dashboard']);
        } else {
          this.alertService.error(
            chargeResult.message || 'Error al procesar el pago'
          );
        }
      }
    } catch (error: any) {
      console.error('Error en createMethodPaymentWithCharge:', error);
      this.alertService.error(
        'Error al procesar el pago: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
    } finally {
      this.processingPayment = false;
    }
  }

  abrirModal3DS() {
    this.mostrar3DSModal = true;
    this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
      'https://front-3ds-sandbox.n1co.com/authentication/test'
    );
  }

  private async refreshUserData() {
    try {
      const currentUser = this.apiService.auth_user();
      if (currentUser && currentUser.id) {
        await firstValueFrom(this.apiService.getUserData(currentUser.id));

        this.usuario = this.apiService.auth_user();
        this.loadAll();
      }
    } catch (error) {
      console.error('Error al actualizar datos de usuario:', error);
    }
  }

  public onRecurrentPaymentChange(event: any): void {
    this.saving = true;
    const isRecurrent = event.target.checked;
    
    this.apiService.store('suscripcion/pago-recurrente', {
      id_empresa: this.usuario.empresa.id,
      pago_recurrente: isRecurrent
    }).subscribe(
      (response) => {
        this.suscripcion.pago_recurrente = isRecurrent;
        this.alertService.success(
          'Éxito', 
          `Pago recurrente ${isRecurrent ? 'activado' : 'desactivado'} exitosamente.`
        );
        this.saving = false;
        this.loadAll(); // Para actualizar los datos desde el servidor
      },
      (error) => {
        this.alertService.error(
          error.error?.message || 'Error al actualizar el pago recurrente'
        );
        this.saving = false;
        // Restaurar el estado anterior del interruptor
        event.target.checked = !isRecurrent;
      }
    );
  }
}
