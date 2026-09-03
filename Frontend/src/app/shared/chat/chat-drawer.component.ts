import {
  Component,
  OnInit,
  OnDestroy,
  ViewChild,
  ElementRef,
  DestroyRef,
  inject,
} from '@angular/core';
import { ChatService, ChatMessage, LucasConversation } from '@services/chat/chat.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { SafeHtmlPipe } from '@pipes/safe-html.pipe';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../directives/lazy-image.directive';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

@Component({
    selector: 'app-chat-drawer',
    templateUrl: './chat-drawer.component.html',
    styleUrls: ['./chat-drawer.component.css'],
    standalone: true,
    imports: [CommonModule, FormsModule, SafeHtmlPipe, LazyImageDirective, TooltipModule],
    
})
export class ChatDrawerComponent
  implements OnInit, OnDestroy
{
  @ViewChild('chatContainer') private chatContainer!: ElementRef;
  isOpen = false;
  view: 'list' | 'chat' = 'list';
  messages: ChatMessage[] = [];
  conversations: LucasConversation[] = [];
  newMessage = '';
  isLoading = false;
  conversationsLoading = false;
  loadingConversation = false;
  today = new Date();

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  private scrollTimeout: ReturnType<typeof setTimeout> | null = null;

  constructor(private chatService: ChatService) {}

  ngOnInit(): void {
    // Verificar acceso al chat
    this.chatService.verificarAcceso();

    // Suscribirse al estado del drawer
    this.chatService.loading$
      .pipe(this.untilDestroyed())
      .subscribe((isLoading) => {
        this.isLoading = isLoading;

        if (isLoading) {
          this.scrollToBottomSoon();
        }
      });

    this.chatService.drawerOpen$
      .pipe(this.untilDestroyed())
      .subscribe((isOpen) => {
        this.isOpen = isOpen;

        // Manipulación del offcanvas de Bootstrap mediante JavaScript
        if (isOpen) {
          this.showOffcanvas();
          // Al abrir, mostrar el listado de conversaciones
          this.view = 'list';
          this.chatService.loadConversations();
        } else {
          this.hideOffcanvas();
        }
      });

    // Suscribirse a los mensajes
    this.chatService.messages$
      .pipe(this.untilDestroyed())
      .subscribe((messages) => {
        this.messages = messages;
        this.scrollToBottomSoon();
      });

    // Suscribirse a la lista de conversaciones
    this.chatService.conversations$
      .pipe(this.untilDestroyed())
      .subscribe((conversations) => {
        this.conversations = conversations;
      });

    // Estado de carga de la lista de conversaciones
    this.chatService.conversationsLoading$
      .pipe(this.untilDestroyed())
      .subscribe((loading) => {
        this.conversationsLoading = loading;
      });

    // Estado de carga al abrir una conversación
    this.chatService.loadingConversation$
      .pipe(this.untilDestroyed())
      .subscribe((loading) => {
        this.loadingConversation = loading;
      });
  }

  ngOnDestroy(): void {
    // Las suscripciones se limpian automáticamente mediante DestroyRef
    if (this.scrollTimeout !== null) {
      clearTimeout(this.scrollTimeout);
      this.scrollTimeout = null;
    }
  }

  toggle() {
    this.chatService.toggleDrawer();
  }

  /**
   * Programa el scroll al fondo para después de que Angular renderice el DOM.
   * Reemplaza a AfterViewChecked, que corría en cada ciclo de detección de cambios.
   * Las llamadas múltiples se consolidan en un único timeout pendiente.
   */
  private scrollToBottomSoon(): void {
    if (this.view !== 'chat') {
      return;
    }
    if (this.scrollTimeout !== null) {
      return;
    }
    this.scrollTimeout = setTimeout(() => {
      this.scrollTimeout = null;
      this.scrollToBottom();
    }, 0);
  }

  scrollToBottom(): void {
    if (this.view !== 'chat' || !this.chatContainer) {
      return;
    }
    try {
      this.chatContainer.nativeElement.scrollTop =
        this.chatContainer.nativeElement.scrollHeight;
    } catch (err) {
      console.error('Error al hacer scroll:', err);
    }
  }

  sendMessage() {
    if (this.newMessage.trim() === '') return;

    this.chatService.sendMessage(this.newMessage);
    this.newMessage = '';
  }

  // Nuevo método para manejar clics en sugerencias
  handleSuggestionClick(suggestion: string) {
    this.chatService.sendMessage(suggestion);
  }

  /**
   * Maneja los clics dentro del contenido de mensajes (HTML sanitizado).
   * Permite descargar los gráficos SVG generados por Lucas sin recurrir a
   * JavaScript inline (que el sanitizador eliminaría y abriría un vector XSS).
   */
  onMessageContentClick(event: Event): void {
    const target = event.target as HTMLElement;
    const button = target.closest('[data-svg-action]') as HTMLElement | null;
    if (!button) {
      return;
    }

    const svgId = button.getAttribute('data-svg-id');
    if (!svgId) {
      return;
    }

    const svgEl = document.getElementById(svgId);
    if (!svgEl) {
      return;
    }

    if (button.getAttribute('data-svg-action') === 'png') {
      this.downloadSvgAsPng(button, svgEl);
    } else {
      this.downloadSvgRaw(svgEl);
    }
  }

  private downloadSvgRaw(svgEl: Element): void {
    const svgData = new XMLSerializer().serializeToString(svgEl);
    const dataUri =
      'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgData);
    this.triggerDownload(dataUri, `grafico-lucas-${Date.now()}.svg`);
  }

  private downloadSvgAsPng(button: HTMLElement, svgEl: Element): void {
    const width = parseInt(button.getAttribute('data-svg-width') || '400', 10);
    const height = parseInt(button.getAttribute('data-svg-height') || '300', 10);
    const title = button.getAttribute('data-svg-title') || 'Gráfico financiero';
    const text = button.getAttribute('data-svg-text') || '';

    const padding = 40;
    const canvasWidth = Math.max(width + padding * 2, 500);

    const svgData = new XMLSerializer().serializeToString(svgEl);
    const dataUri =
      'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgData);

    const img = new Image();
    img.onload = () => {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        return;
      }

      const wrapText = (
        context: CanvasRenderingContext2D,
        content: string,
        x: number,
        y: number,
        maxWidth: number,
        lineHeight: number
      ): number => {
        if (!content) {
          return 0;
        }
        const words = content.split(' ');
        let line = '';
        let lines = 0;
        for (let n = 0; n < words.length; n++) {
          const testLine = line + words[n] + ' ';
          if (context.measureText(testLine).width > maxWidth && n > 0) {
            context.fillText(line, x, y + lines * lineHeight);
            line = words[n] + ' ';
            lines++;
          } else {
            line = testLine;
          }
        }
        context.fillText(line, x, y + lines * lineHeight);
        return lines + 1;
      };

      ctx.font = '14px Arial';
      let textHeight = 0;
      if (text) {
        const maxTextWidth = canvasWidth - padding * 2;
        const linesUsed = Math.max(
          1,
          Math.ceil(ctx.measureText(text).width / maxTextWidth)
        );
        textHeight = linesUsed * 20 + 30;
      }

      const canvasHeight = height + padding * 2 + textHeight + 40;

      canvas.width = canvasWidth;
      canvas.height = canvasHeight;

      ctx.fillStyle = 'white';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, padding, padding + 25, width, height);

      ctx.font = 'bold 16px Arial';
      ctx.fillStyle = '#333';
      ctx.textAlign = 'center';
      ctx.fillText(title, canvasWidth / 2, padding / 2 + 16);

      if (text) {
        ctx.font = '14px Arial';
        ctx.fillStyle = '#555';
        ctx.textAlign = 'center';
        wrapText(ctx, text, canvasWidth / 2, height + padding + 45, canvasWidth - 80, 20);
      }

      ctx.font = '10px Arial';
      ctx.fillStyle = '#999';
      ctx.textAlign = 'right';
      ctx.fillText(
        `Generado por Lucas - ${new Date().toLocaleDateString()}`,
        canvasWidth - 10,
        canvasHeight - 10
      );

      this.triggerDownload(
        canvas.toDataURL('image/png'),
        `grafico-lucas-${Date.now()}.png`
      );
    };

    img.src = dataUri;
  }

  private triggerDownload(href: string, filename: string): void {
    const a = document.createElement('a');
    a.href = href;
    a.download = filename;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  openConversation(conversationId: string) {
    this.view = 'chat';
    this.chatService.openConversation(conversationId);
  }

  newConversation() {
    this.view = 'chat';
    this.chatService.startNewConversation();
  }

  backToList() {
    this.view = 'list';
    this.chatService.loadConversations();
  }

  // Mostrar el offcanvas usando la API de Bootstrap
  private showOffcanvas() {
    const offcanvasElement = document.getElementById('chatOffcanvas');
    if (offcanvasElement) {
      const bsOffcanvas = new (window as any).bootstrap.Offcanvas(
        offcanvasElement
      );
      bsOffcanvas.show();

      // Agregar listener para cuando se cierre manualmente
      offcanvasElement.addEventListener(
        'hidden.bs.offcanvas',
        () => {
          this.chatService.closeDrawer();
        },
        { once: true }
      );
    }
  }

  // Ocultar el offcanvas usando la API de Bootstrap
  private hideOffcanvas() {
    const offcanvasElement = document.getElementById('chatOffcanvas');
    if (offcanvasElement) {
      const bsOffcanvas = (window as any).bootstrap.Offcanvas.getInstance(
        offcanvasElement
      );
      if (bsOffcanvas) {
        bsOffcanvas.hide();
      }
    }
  }
}
