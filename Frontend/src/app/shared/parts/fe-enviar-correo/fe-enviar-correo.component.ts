import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-fe-enviar-correo',
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="fe-enviar-correo">
      <label [attr.for]="inputId">Enviar a</label>
      <input
        [id]="inputId"
        type="email"
        class="form-control"
        [ngModel]="correo"
        (ngModelChange)="correoChange.emit($event)"
        autocomplete="off"
        [placeholder]="placeholder"
      />
      <p class="hint">{{ hint }}</p>
      <button type="button" class="btn btn-primary w-100" [disabled]="sending" (click)="enviar.emit()">
        {{ sending ? 'Enviando...' : 'Enviar por correo' }}
      </button>
    </div>
  `,
  styles: [`
    :host { display: block; }
    .fe-enviar-correo {
      background: #f7f8fa;
      border: 1px solid #e6e8ec;
      border-radius: 10px;
      padding: 12px;
      margin-bottom: 12px;
      text-align: left;
    }
    label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      margin-bottom: 6px;
      color: #495057;
    }
    .hint {
      margin: 6px 0 10px;
      font-size: 0.78rem;
      line-height: 1.35;
      color: #6c757d;
      word-break: break-word;
    }
  `],
})
export class FeEnviarCorreoComponent {
  private static nextId = 0;

  readonly inputId = `fe-correo-envio-${FeEnviarCorreoComponent.nextId++}`;

  @Input() correo = '';
  @Output() correoChange = new EventEmitter<string>();
  @Input() correoDefecto: string | null = '';
  @Input() quien: 'cliente' | 'proveedor' = 'cliente';
  @Input() sending = false;
  @Output() enviar = new EventEmitter<void>();

  get placeholder(): string {
    const defecto = (this.correoDefecto ?? '').trim();
    return defecto || 'nombre@correo.com';
  }

  get hint(): string {
    const escrito = (this.correo ?? '').trim();
    const defecto = (this.correoDefecto ?? '').trim();
    if (escrito) {
      return 'Este envío irá solo a ese correo.';
    }
    if (defecto) {
      return `Si lo deja vacío, se envía a ${defecto}.`;
    }
    const quien = this.quien === 'proveedor' ? 'proveedor' : 'cliente';
    return `Este ${quien} no tiene correo. Escriba uno para enviar.`;
  }
}
