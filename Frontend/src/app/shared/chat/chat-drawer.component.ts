import { Component, OnInit, OnDestroy } from '@angular/core';
import { Subscription } from 'rxjs';
import { ChatService, ChatMessage } from '@services/chat/chat.service';

@Component({
  selector: 'app-chat-drawer',
  templateUrl: './chat-drawer.component.html',
  styleUrls: ['./chat-drawer.component.css']
})
export class ChatDrawerComponent implements OnInit, OnDestroy {
  isOpen = false;
  messages: ChatMessage[] = [];
  newMessage = '';
  
  private subscriptions: Subscription[] = [];
  
  constructor(private chatService: ChatService) { }

  ngOnInit(): void {
    // Suscribirse al estado del drawer
    this.subscriptions.push(
      this.chatService.drawerOpen$.subscribe(isOpen => {
        this.isOpen = isOpen;
        
        // Manipulación del offcanvas de Bootstrap mediante JavaScript
        if (isOpen) {
          this.showOffcanvas();
        } else {
          this.hideOffcanvas();
        }
      })
    );
    
    // Suscribirse a los mensajes
    this.subscriptions.push(
      this.chatService.messages$.subscribe(messages => {
        this.messages = messages;
      })
    );
  }
  
  ngOnDestroy(): void {
    // Limpiar suscripciones
    this.subscriptions.forEach(sub => sub.unsubscribe());
  }

  toggle() {
    this.chatService.toggleDrawer();
  }

  sendMessage() {
    if (this.newMessage.trim() === '') return;
    
    this.chatService.sendMessage(this.newMessage);
    this.newMessage = '';
  }

  // Mostrar el offcanvas usando la API de Bootstrap
  private showOffcanvas() {
    const offcanvasElement = document.getElementById('chatOffcanvas');
    if (offcanvasElement) {
      const bsOffcanvas = new (window as any).bootstrap.Offcanvas(offcanvasElement);
      bsOffcanvas.show();
      
      // Agregar listener para cuando se cierre manualmente
      offcanvasElement.addEventListener('hidden.bs.offcanvas', () => {
        this.chatService.closeDrawer();
      }, { once: true });
    }
  }

  // Ocultar el offcanvas usando la API de Bootstrap
  private hideOffcanvas() {
    const offcanvasElement = document.getElementById('chatOffcanvas');
    if (offcanvasElement) {
      const bsOffcanvas = (window as any).bootstrap.Offcanvas.getInstance(offcanvasElement);
      if (bsOffcanvas) {
        bsOffcanvas.hide();
      }
    }
  }
}