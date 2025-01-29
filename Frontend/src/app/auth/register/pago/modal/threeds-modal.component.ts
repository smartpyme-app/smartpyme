import { Component, Input, Output, EventEmitter } from '@angular/core';
import { SafeResourceUrl } from '@angular/platform-browser';

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
        <iframe [src]="authUrl" width="100%" height="600px" frameborder="0"></iframe>
      </div>
    </div>
  `,
  styles: [`
    .modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1050;
    }
    .modal-container {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      padding: 20px;
      border-radius: 8px;
      z-index: 1051;
      width: 90%;
      max-width: 600px;
    }
  `]
})
export class ThreedsModalComponent {
    @Input() authUrl!: SafeResourceUrl;
    @Output() close = new EventEmitter<void>();
   
    onClose() {
      this.close.emit();
    }
}