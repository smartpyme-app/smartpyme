import {
  Component,
  OnInit,
  OnDestroy,
  ViewChild,
  ElementRef,
  AfterViewChecked,
  DestroyRef,
  inject,
} from '@angular/core';
import { ChatService, ChatMessage } from '@services/chat/chat.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { SafeHtmlPipe } from '@pipes/safe-html.pipe';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../directives/lazy-image.directive';

@Component({
    selector: 'app-chat-drawer',
    templateUrl: './chat-drawer.component.html',
    styleUrls: ['./chat-drawer.component.css'],
    standalone: true,
    imports: [CommonModule, FormsModule, SafeHtmlPipe, LazyImageDirective],
    
})
export class ChatDrawerComponent
  implements OnInit, OnDestroy, AfterViewChecked
{
  @ViewChild('chatContainer') private chatContainer!: ElementRef;
  isOpen = false;
  messages: ChatMessage[] = [];
  newMessage = '';
  isLoading = false;

  private shouldScrollToBottom = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

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
          this.shouldScrollToBottom = true;
        }
      });

    this.chatService.drawerOpen$
      .pipe(this.untilDestroyed())
      .subscribe((isOpen) => {
        this.isOpen = isOpen;

        // Manipulación del offcanvas de Bootstrap mediante JavaScript
        if (isOpen) {
          this.showOffcanvas();
        } else {
          this.hideOffcanvas();
        }
      });

    // Suscribirse a los mensajes
    this.chatService.messages$
      .pipe(this.untilDestroyed())
      .subscribe((messages) => {
        if (messages.length > this.messages.length) {
          this.shouldScrollToBottom = true;
        }

        this.messages = messages;
      });
  }

  ngOnDestroy(): void {
    // Las suscripciones se limpian automáticamente mediante DestroyRef
  }

  toggle() {
    this.chatService.toggleDrawer();
  }

  ngAfterViewChecked() {
    // Después de que Angular renderice la vista, scrollear si es necesario
    if (this.shouldScrollToBottom) {
      this.scrollToBottom();
      this.shouldScrollToBottom = false;
    }
  }

  scrollToBottom(): void {
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
