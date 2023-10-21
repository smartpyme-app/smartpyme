import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { MateriasPrimaComponent } from './materias-prima.component';

describe('MateriasPrimaComponent', () => {
  let component: MateriasPrimaComponent;
  let fixture: ComponentFixture<MateriasPrimaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ MateriasPrimaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(MateriasPrimaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
