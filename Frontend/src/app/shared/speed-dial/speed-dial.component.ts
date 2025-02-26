import { Component } from '@angular/core';
import { ChatService } from '@services/chat/chat.service';

@Component({
  selector: 'app-speed-dial',
  templateUrl: './speed-dial.component.html',
  styleUrls: ['./speed-dial.component.css']
})
export class SpeedDialComponent {
  constructor(private chatService: ChatService) { }

  openChat() {
    this.chatService.toggleDrawer();
  }
}