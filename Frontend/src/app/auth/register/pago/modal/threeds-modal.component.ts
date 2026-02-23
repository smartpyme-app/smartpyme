import {
  Component,
  Input,
  Output,
  EventEmitter,
  OnInit,
  OnDestroy,
  ViewChild,
  ElementRef,
  AfterViewInit,
} from '@angular/core';
import { SafeResourceUrl, DomSanitizer } from '@angular/platform-browser';

@Component({
  selector: 'app-threeds-modal',
  template: `
    <div class="modal-backdrop"></div>
    <div class="modal-container">
      <div class="modal-header">
        <h5>Autenticación 3D Secure</h5>
        <!-- <button class="btn-close" (click)="onClose()"></button> -->
      </div>
      <div class="modal-body">
        <iframe
          #threedsIframe
          [src]="authUrl"
          width="100%"
          height="600px"
          frameborder="0"
        >
        </iframe>
      </div>
    </div>
  `,
  styles: [
    `
      .modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
      }
      .modal-container {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        z-index: 10000;
        width: 90%;
        max-width: 600px;
      }
    `,
  ],
})
export class ThreedsModalComponent implements OnInit, OnDestroy, AfterViewInit {
  @Input() authUrl!: SafeResourceUrl;
  @Output() close = new EventEmitter<void>();
  @Output() messageReceived = new EventEmitter<any>();
  @ViewChild('threedsIframe', { static: false })
  iframeRef!: ElementRef<HTMLIFrameElement>;

  private messageHandler?: (event: MessageEvent) => void;
  private trustedOrigins: string[] = [];

  constructor(private sanitizer: DomSanitizer) {}

  ngOnInit() {
    // Obtener el string del URL desde SafeResourceUrl
    // SafeResourceUrl tiene una propiedad interna que contiene el string
    const urlString =
      (this.authUrl as any)?.changingThisBreaksApplicationSecurity || '';
    try {
      if (urlString) {
        const initialOrigin = new URL(urlString).origin;
        this.trustedOrigins.push(initialOrigin);
      }
      // Agregar los orígenes del iframe 3DS (sandbox y producción)
      this.trustedOrigins.push('https://front-3ds-sandbox.n1co.com');
      this.trustedOrigins.push('https://front-3ds.n1co.com');
      this.trustedOrigins.push('https://front-3ds.h4b.dev');
      // Agregar también el dominio de n1co.com que es donde se hacen las llamadas
      this.trustedOrigins.push('https://3ds-payments-sandbox.n1co.com');
      this.trustedOrigins.push('https://3ds-payments.n1co.com');
      // console.log('Orígenes confiables configurados:', this.trustedOrigins);
    } catch (e) {
      console.warn('No se pudo obtener el origen del URL:', e);
      // Agregar los orígenes de todas formas
      this.trustedOrigins.push('https://front-3ds-sandbox.n1co.com');
      this.trustedOrigins.push('https://front-3ds.n1co.com');
      this.trustedOrigins.push('https://front-3ds.h4b.dev');
    }

    // Crear el handler para los mensajes del iframe
    this.messageHandler = (event: MessageEvent) => {
      // Verificar si es de un origen confiable
      const isTrusted = this.trustedOrigins.some(
        (origin) => event.origin === origin
      );

      if (isTrusted) {
        try {
          // Parsear el JSON data recibido del iframe
          const dataMessage =
            typeof event.data === 'string'
              ? JSON.parse(event.data)
              : event.data;

          // Acceder a las propiedades del mensaje
          const messageType = dataMessage.MessageType;
          const status = dataMessage.Status;
          const authenticationId = dataMessage.AuthenticationId;
          const orderId = dataMessage.OrderId;
          const orderAmount = dataMessage.OrderAmount;

          // console.log('Datos completos:', dataMessage);
          // console.log('MessageType recibido:', messageType);
          // console.log('Status recibido:', status);
          // console.log('Tipo de MessageType:', typeof messageType);
          // console.log('Tipo de Status:', typeof status);

          // Procesar si la autenticación se completó exitosamente
          if (
            messageType === 'authentication.complete' &&
            status === 'SUCCESS'
          ) {
            // console.log('Autenticación completada exitosamente');
            // console.log('AuthenticationId:', authenticationId);
            // console.log('OrderId:', orderId);
            // console.log('OrderAmount:', orderAmount);

            // Emitir evento con los datos al componente padre para procesar el pago
            this.messageReceived.emit({
              messageType,
              status,
              authenticationId,
              orderId,
              orderAmount,
              ...dataMessage,
            });
          }
          // Procesar si la autenticación falló
          else if (
            messageType === 'authentication.failed' &&
            status === 'FAILED'
          ) {
            // console.log('Autenticación fallida');
            // console.log('AuthenticationId:', authenticationId);
            // console.log('OrderId:', orderId);

            // Emitir evento con los datos al componente padre para manejar el fallo
            this.messageReceived.emit({
              messageType,
              status,
              authenticationId,
              orderId,
              orderAmount,
              ...dataMessage,
            });
          } else {
            console.log(
              'Mensaje recibido pero no es autenticación completa o fallida:',
              messageType,
              status
            );
          }
        } catch (e) {
          console.error('Error parseando mensaje del iframe:', e);
          // console.log('Datos recibidos (sin parsear):', event.data);
        }
      } else {
        // Log de mensajes de otros orígenes para debug (opcional)
        console.log(
          'Mensaje recibido de origen no confiable:',
          event.origin,
          event.data
        );
      }
    };

    // Agregar el listener
    window.addEventListener('message', this.messageHandler);
    // console.log('Listener de mensajes configurado para iframe 3DS');
  }

  ngAfterViewInit() {
    // Esperar un poco para que el iframe se cargue
    setTimeout(() => {
      if (this.iframeRef?.nativeElement) {
        // console.log('Iframe cargado:', this.iframeRef.nativeElement);
        // console.log('URL del iframe:', this.iframeRef.nativeElement.src);
      }
    }, 1000);
  }

  ngOnDestroy() {
    // Remover el listener cuando el componente se destruya
    if (this.messageHandler) {
      window.removeEventListener('message', this.messageHandler);
      // console.log('Listener de mensajes removido');
    }
  }

  onClose() {
    this.close.emit();
  }
}
