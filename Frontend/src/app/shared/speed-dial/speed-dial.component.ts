import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ChatService } from '@services/chat/chat.service';
import { Subscription } from 'rxjs';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-speed-dial',
    templateUrl: './speed-dial.component.html',
    styleUrls: ['./speed-dial.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class SpeedDialComponent implements OnInit, OnDestroy {
  // Variable para controlar la visibilidad del botón
  mostrarBotonChat = false;
  
  // Suscripción para liberar recursos al destruir el componente
  private subscription: Subscription = new Subscription();
  
  constructor(private chatService: ChatService, private apiService: ApiService) { }

  isAdmin() {
    return this.apiService.isAdmin();
  }
  ngOnInit(): void {
    // Verificar acceso explícitamente (esto no causará dependencia circular)
    this.chatService.verificarAcceso();
    
    // Suscribirse al observable que indica si tiene acceso al chat
    this.subscription.add(
      this.chatService.tieneAcceso$.subscribe(tieneAcceso => {
        this.mostrarBotonChat = tieneAcceso;
      })
    );
  }

  ngOnDestroy(): void {
    // Liberar recursos al destruir el componente
    if (this.subscription) {
      this.subscription.unsubscribe();
    }
  }

  openChat() {
    this.chatService.toggleDrawer();
  }
}