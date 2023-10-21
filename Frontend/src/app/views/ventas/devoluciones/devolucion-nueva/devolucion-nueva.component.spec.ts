import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionVentaNuevaComponent } from './devolucion-nueva';

describe('DevolucionVentaNuevaComponent', () => {
  let component: DevolucionVentaNuevaComponent;
  let fixture: ComponentFixture<DevolucionVentaNuevaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionVentaNuevaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionVentaNuevaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
