import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaUsuariosComponent } from './caja-usuarios.component';

describe('CajaUsuariosComponent', () => {
  let component: CajaUsuariosComponent;
  let fixture: ComponentFixture<CajaUsuariosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaUsuariosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaUsuariosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
