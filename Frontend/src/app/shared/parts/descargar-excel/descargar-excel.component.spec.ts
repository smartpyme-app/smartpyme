import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DescargarExcelComponent } from './descargar-excel.component';

describe('DescargarExcelComponent', () => {
  let component: DescargarExcelComponent;
  let fixture: ComponentFixture<DescargarExcelComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DescargarExcelComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DescargarExcelComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
