import { ComponentFixture, TestBed } from '@angular/core/testing';

import { OrdenCompraFormComponent } from './orden-compra-form.component';

describe('OrdenCompraFormComponent', () => {
  let component: OrdenCompraFormComponent;
  let fixture: ComponentFixture<OrdenCompraFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ OrdenCompraFormComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(OrdenCompraFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
