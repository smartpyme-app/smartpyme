import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionCompraComponent } from './devolucion-compra.component';

describe('DevolucionCompraComponent', () => {
  let component: DevolucionCompraComponent;
  let fixture: ComponentFixture<DevolucionCompraComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionCompraComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionCompraComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
