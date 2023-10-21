import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ClientesDashComponent } from './clientes-dash.component';

describe('ClientesDashComponent', () => {
  let component: ClientesDashComponent;
  let fixture: ComponentFixture<ClientesDashComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ClientesDashComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ClientesDashComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
