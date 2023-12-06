import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FormasDePagoComponent } from './formas-de-pago.component';

describe('FormasDePagoComponent', () => {
  let component: FormasDePagoComponent;
  let fixture: ComponentFixture<FormasDePagoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FormasDePagoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FormasDePagoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
