import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { MetodosDePagoComponent } from './metodos-de-pago.component';

describe('MetodosDePagoComponent', () => {
  let component: MetodosDePagoComponent;
  let fixture: ComponentFixture<MetodosDePagoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ MetodosDePagoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(MetodosDePagoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
