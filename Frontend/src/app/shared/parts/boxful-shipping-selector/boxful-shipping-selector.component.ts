import { Component, EventEmitter, OnInit, Output } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { BoxfulApiService, BoxfulAddress, BoxfulState, BoxfulCity } from '@services/boxful/boxful-api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-boxful-shipping-selector',
  templateUrl: './boxful-shipping-selector.component.html'
})
export class BoxfulShippingSelectorComponent implements OnInit {
  @Output() addressSelected = new EventEmitter<BoxfulAddress>();

  addressForm!: FormGroup;
  states: BoxfulState[] = [];
  filteredCities: BoxfulCity[] = [];
  addresses: BoxfulAddress[] = [];

  // Modo de selección: 'saved' (dirección guardada) o 'new' (nueva dirección)
  mode: 'saved' | 'new' = 'saved';

  loading = false;
  saving = false;
  selectedAddressId: number | null = null;
  errorMessage: string | null = null;

  constructor(
    private fb: FormBuilder,
    private boxfulService: BoxfulApiService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    this.initForm();
    this.loadStates();
    this.loadAddresses();
  }

  private initForm(): void {
    this.addressForm = this.fb.group({
      address: ['', [Validators.required, Validators.maxLength(500)]],
      referencePoint: ['', [Validators.maxLength(500)]],
      latitude: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      longitude: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      stateId: [null, [Validators.required]],
      cityId: [null, [Validators.required]],
      addressPhone: ['', [Validators.required, Validators.maxLength(20)]],
      addressAreaCode: ['503', [Validators.required, Validators.maxLength(10)]]
    });
  }

  loadStates(): void {
    this.loading = true;
    this.boxfulService.getStates().subscribe({
      next: (states: BoxfulState[]) => {
        this.states = states;
        this.loading = false;
        this.errorMessage = null;
      },
      error: (err: any) => {
        console.error('Error cargando estados de Boxful:', err);
        this.errorMessage = 'No se pudieron cargar los estados/departamentos de Boxful.';
        this.loading = false;
      }
    });
  }

  loadAddresses(): void {
    this.loading = true;
    this.boxfulService.getAddresses().subscribe({
      next: (addresses: BoxfulAddress[]) => {
        this.addresses = addresses;
        this.loading = false;
        this.errorMessage = null;
      },
      error: (err: any) => {
        console.error('Error cargando direcciones de Boxful:', err);
        this.errorMessage = 'No se pudieron cargar las direcciones guardadas de Boxful.';
        this.loading = false;
      }
    });
  }

  onStateChange(): void {
    const stateId = Number(this.addressForm.get('stateId')?.value);
    const selectedState = this.states.find(s => s.id === stateId);

    if (selectedState && selectedState.Cities) {
      this.filteredCities = selectedState.Cities;
    } else {
      this.filteredCities = [];
    }

    this.addressForm.get('cityId')?.setValue(null);
  }

  onModeChange(newMode: 'saved' | 'new'): void {
    this.mode = newMode;
    this.errorMessage = null;
    if (newMode === 'saved') {
      this.selectedAddressId = null;
    } else {
      this.resetNewAddressForm();
    }
  }

  onSelectSavedAddress(): void {
    if (this.selectedAddressId) {
      const address = this.addresses.find(a => a.id === Number(this.selectedAddressId));
      if (address) {
        this.addressSelected.emit(address);
      }
    }
  }

  onSubmitNewAddress(): void {
    if (this.addressForm.invalid) {
      this.alertService.error('Por favor complete todos los campos requeridos correctamente.');
      return;
    }

    this.saving = true;
    this.errorMessage = null;
    const formValue = this.addressForm.value;

    this.boxfulService.createAddress(formValue).subscribe({
      next: (createdAddress: BoxfulAddress) => {
        this.alertService.success('success', 'Dirección registrada correctamente.');
        this.saving = false;

        // Agregar a la lista local
        this.addresses.push(createdAddress);

        // Seleccionar automáticamente e intercambiar a modo "saved"
        this.selectedAddressId = createdAddress.id;
        this.mode = 'saved';

        // Emitir dirección creada al componente padre
        this.addressSelected.emit(createdAddress);

        this.resetNewAddressForm();
      },
      error: (err: any) => {
        console.error('Error guardando dirección de Boxful:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al registrar la nueva dirección en Boxful.';
        this.saving = false;
      }
    });
  }

  resetNewAddressForm(): void {
    this.addressForm.reset({
      address: '',
      referencePoint: '',
      latitude: null,
      longitude: null,
      stateId: null,
      cityId: null,
      addressPhone: '',
      addressAreaCode: '503'
    });
    this.filteredCities = [];
  }
}
