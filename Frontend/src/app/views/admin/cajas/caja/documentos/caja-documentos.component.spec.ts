import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaDocumentosComponent } from './caja-documentos.component';

describe('CajaDocumentosComponent', () => {
  let component: CajaDocumentosComponent;
  let fixture: ComponentFixture<CajaDocumentosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaDocumentosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaDocumentosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
