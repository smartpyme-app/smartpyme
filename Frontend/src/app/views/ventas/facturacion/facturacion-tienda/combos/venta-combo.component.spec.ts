import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { VentaComboComponent } from './venta-combo.component';

describe('VentaComboComponent', () => {
  let component: VentaComboComponent;
  let fixture: ComponentFixture<VentaComboComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ VentaComboComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(VentaComboComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
