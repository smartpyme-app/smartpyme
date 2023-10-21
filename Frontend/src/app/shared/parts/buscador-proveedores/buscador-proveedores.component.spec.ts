import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BuscadorProveedoresComponent } from './buscador-proveedores.component';

describe('BuscadorProveedoresComponent', () => {
  let component: BuscadorProveedoresComponent;
  let fixture: ComponentFixture<BuscadorProveedoresComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BuscadorProveedoresComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BuscadorProveedoresComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
