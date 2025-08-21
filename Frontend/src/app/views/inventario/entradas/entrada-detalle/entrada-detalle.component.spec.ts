import { ComponentFixture, TestBed } from '@angular/core/testing';

import { EntradaDetalleComponent } from './entrada-detalle.component';

describe('EntradaDetalleComponent', () => {
  let component: EntradaDetalleComponent;
  let fixture: ComponentFixture<EntradaDetalleComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ EntradaDetalleComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(EntradaDetalleComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
}); 