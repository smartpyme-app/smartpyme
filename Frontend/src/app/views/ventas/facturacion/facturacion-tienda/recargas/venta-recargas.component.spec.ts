import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { VentaRecargasComponent } from './venta-recargas.component';

describe('VentaRecargasComponent', () => {
  let component: VentaRecargasComponent;
  let fixture: ComponentFixture<VentaRecargasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ VentaRecargasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(VentaRecargasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
