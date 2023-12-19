import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearAbonoCompraComponent } from './crear-abono-compra.component';

describe('CrearAbonoCompraComponent', () => {
  let component: CrearAbonoCompraComponent;
  let fixture: ComponentFixture<CrearAbonoCompraComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearAbonoCompraComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearAbonoCompraComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
