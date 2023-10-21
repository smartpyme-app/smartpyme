import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BuscadorMateriasPrimasComponent } from './buscador-materias-primas.component';

describe('BuscadorMateriasPrimasComponent', () => {
  let component: BuscadorMateriasPrimasComponent;
  let fixture: ComponentFixture<BuscadorMateriasPrimasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BuscadorMateriasPrimasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BuscadorMateriasPrimasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
