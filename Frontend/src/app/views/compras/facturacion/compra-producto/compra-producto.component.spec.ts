import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CompraProductoComponent } from './compra-producto.component';

describe('CompraProductoComponent', () => {
  let component: CompraProductoComponent;
  let fixture: ComponentFixture<CompraProductoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CompraProductoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CompraProductoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
