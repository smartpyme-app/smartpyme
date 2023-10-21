import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionCompraNuevaComponent } from './devolucion-compra-nueva.component';

describe('DevolucionCompraNuevaComponent', () => {
  let component: DevolucionCompraNuevaComponent;
  let fixture: ComponentFixture<DevolucionCompraNuevaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionCompraNuevaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionCompraNuevaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
