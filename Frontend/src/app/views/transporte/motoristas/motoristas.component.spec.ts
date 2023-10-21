import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { MotoristasComponent } from './motoristas.component';

describe('MotoristasComponent', () => {
  let component: MotoristasComponent;
  let fixture: ComponentFixture<MotoristasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ MotoristasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(MotoristasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
