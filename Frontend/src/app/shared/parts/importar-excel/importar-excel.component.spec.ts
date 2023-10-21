import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ImportarExcelComponent } from './importar-excel.component';

describe('ImportarExcelComponent', () => {
  let component: ImportarExcelComponent;
  let fixture: ComponentFixture<ImportarExcelComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ImportarExcelComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ImportarExcelComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
