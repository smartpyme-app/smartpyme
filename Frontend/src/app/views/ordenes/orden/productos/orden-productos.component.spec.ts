import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { OrdenProductosComponent } from './orden-productos.component';

describe('OrdenProductosComponent', () => {
  let component: OrdenProductosComponent;
  let fixture: ComponentFixture<OrdenProductosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ OrdenProductosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(OrdenProductosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
