import { Component, OnInit, OnDestroy, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ChatService } from '@services/chat/chat.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

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
  
  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);
  
  constructor(private chatService: ChatService, private apiService: ApiService) { }

  isAdmin() {
    return this.apiService.isAdmin();
  }
  ngOnInit(): void {
    // Verificar acceso explícitamente (esto no causará dependencia circular)
    this.chatService.verificarAcceso();
    
    // Suscribirse al observable que indica si tiene acceso al chat
    this.chatService.tieneAcceso$
      .pipe(this.untilDestroyed())
      .subscribe(tieneAcceso => {
        this.mostrarBotonChat = tieneAcceso;
      });
  }

  ngOnDestroy(): void {
    // El DestroyRef maneja automáticamente la limpieza
  }

  openChat() {
    this.chatService.toggleDrawer();
  }
}