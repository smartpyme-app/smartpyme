import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { BoxfulApiService, BoxfulState, BoxfulCity } from '@services/boxful/boxful-api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-boxful-shipping-selector',
  templateUrl: './boxful-shipping-selector.component.html',
  styleUrls: ['./boxful-shipping-selector.component.css']
})
export class BoxfulShippingSelectorComponent implements OnInit {
  @Input() clienteId!: number;
  @Input() paqueteData: any; // peso, alto, ancho, largo
  @Output() guiaGenerada = new EventEmitter<any>();
  @Output() cerrar = new EventEmitter<void>();

  // Asistente (Wizard)
  step: number = 1;

  // Paso 1
  addressForm!: FormGroup;
  states: BoxfulState[] = [];
  filteredCities: BoxfulCity[] = [];
  addresses: any[] = []; // Direcciones del cliente de la BD local
  mode: 'saved' | 'new' = 'saved';
  selectedAddressId: number | null = null;

  // Datos del Destinatario
  clienteNombre: string = '';
  clienteApellido: string = '';
  clienteEmail: string = '';

  // Paso 2
  couriers: any[] = [];
  selectedCourierId: number | null = null;

  // Paso 3
  shipmentResult: any = null;

  // Estados comunes
  loading = false;
  loadingCouriers = false;
  generatingGuide = false;
  errorMessage: string | null = null;

  constructor(
    private fb: FormBuilder,
    private boxfulService: BoxfulApiService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    this.initForm();
    this.loadStates();
    if (this.clienteId) {
      this.loadAddresses();
      this.loadClientDetails();
    } else {
      this.mode = 'new';
    }
  }

  loadClientDetails(): void {
    if (!this.clienteId) return;
    this.boxfulService.getClientDetails(this.clienteId).subscribe({
      next: (client: any) => {
        if (client) {
          this.clienteNombre = client.nombre || '';
          this.clienteApellido = client.apellido || '';
          this.clienteEmail = client.correo || client.email || '';
        }
      },
      error: (err: any) => {
        console.error('Error cargando detalles del cliente:', err);
      }
    });
  }

  private initForm(): void {
    this.addressForm = this.fb.group({
      direccion: ['', [Validators.required, Validators.maxLength(500)]],
      referencia: ['', [Validators.maxLength(500)]],
      telefono: ['', [Validators.required, Validators.pattern(/^[0-9+ ]{8,20}$/)]],
      codigo_area: ['503', [Validators.required, Validators.maxLength(10)]],
      stateId: [null, [Validators.required]],
      cityId: [null, [Validators.required]],
      latitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      longitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      guardarDireccion: [true] // Opción para guardar la dirección en el cliente al final
    });
  }

  loadStates(): void {
    this.loading = true;
    this.boxfulService.getStates().subscribe({
      next: (res: any) => {
        this.states = res.states || res;
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
    if (!this.clienteId) return;
    this.loading = true;
    this.boxfulService.getClientAddresses(this.clienteId).subscribe({
      next: (addresses: any[]) => {
        this.addresses = addresses;
        this.loading = false;
        this.errorMessage = null;
        // Seleccionar la predeterminada si existe
        const defaultAddr = addresses.find(a => a.es_predeterminada);
        if (defaultAddr) {
          this.selectedAddressId = defaultAddr.boxful_address_id;
        } else if (addresses.length > 0) {
          this.selectedAddressId = addresses[0].boxful_address_id;
        }
      },
      error: (err: any) => {
        console.error('Error cargando direcciones de Boxful del cliente:', err);
        this.errorMessage = 'No se pudieron cargar las direcciones guardadas del cliente.';
        this.loading = false;
      }
    });
  }

  onStateChange(): void {
    const stateId = this.addressForm.get('stateId')?.value;
    const selectedState = this.states.find(s => String(s.id) === String(stateId));

    if (selectedState && selectedState.Cities) {
      this.filteredCities = selectedState.Cities;
    } else {
      this.filteredCities = [];
    }

    this.addressForm.get('cityId')?.setValue(null);
    this.addressForm.patchValue({
      latitud: null,
      longitud: null
    });
  }

  onCityChange(): void {
    const cityId = this.addressForm.get('cityId')?.value;
    const selectedCity = this.filteredCities.find(c => String(c.id) === String(cityId));

    if (selectedCity && selectedCity.latitude !== undefined && selectedCity.longitude !== undefined) {
      this.addressForm.patchValue({
        latitud: selectedCity.latitude,
        longitud: selectedCity.longitude
      });
    } else {
      this.addressForm.patchValue({
        latitud: null,
        longitud: null
      });
    }
  }

  onModeChange(newMode: 'saved' | 'new'): void {
    this.mode = newMode;
    this.errorMessage = null;
    if (newMode === 'saved') {
      if (this.addresses.length === 0) {
        this.loadAddresses();
      }
    }
  }

  private getDireccionOrigen(): any {
    // Retornamos null para dejar que el backend de Laravel autodetecte la dirección predeterminada de la empresa
    return null;
  }

  private getDireccionDestino(): any {
    const destino: any = {};
    if (this.mode === 'saved') {
      const selected = this.addresses.find(a => String(a.boxful_address_id) === String(this.selectedAddressId));
      if (selected) {
        destino.direccion = selected.direccion;
        destino.referencia = selected.referencia || '';
        destino.latitud = parseFloat(selected.latitud);
        destino.longitud = parseFloat(selected.longitud);
        destino.stateId = selected.boxful_state_id;
        destino.cityId = selected.boxful_city_id;
        destino.id = selected.boxful_address_id;
        destino.telefono = selected.telefono;
        destino.codigo_area = selected.codigo_area || '503';
      }
    } else {
      const formVal = this.addressForm.value;
      destino.direccion = formVal.direccion;
      destino.referencia = formVal.referencia || '';
      destino.latitud = parseFloat(formVal.latitud);
      destino.longitud = parseFloat(formVal.longitud);
      destino.stateId = formVal.stateId;
      destino.cityId = formVal.cityId;
      destino.telefono = formVal.telefono;
      destino.codigo_area = formVal.codigo_area || '503';
    }
    return destino;
  }

  // Paso 1: Cotizar envío
  cotizarEnvio(): void {
    this.errorMessage = null;

    // Validar dirección
    if (this.mode === 'saved') {
      if (!this.selectedAddressId) {
        this.errorMessage = 'Debe seleccionar una dirección guardada.';
        this.alertService.error(this.errorMessage);
        return;
      }
    } else {
      if (this.addressForm.invalid) {
        this.addressForm.markAllAsTouched();
        this.errorMessage = 'Por favor complete todos los campos de la dirección correctamente.';
        this.alertService.error(this.errorMessage);
        return;
      }
    }

    // Validar paqueteData
    if (!this.paqueteData || !this.paqueteData.peso) {
      this.errorMessage = 'Los datos del paquete (peso) son obligatorios para cotizar.';
      this.alertService.error(this.errorMessage);
      return;
    }

    this.loadingCouriers = true;

    // Construir payload según la nueva arquitectura normalizada
    const payload = {
      paquete: {
        peso: parseFloat(this.paqueteData.peso || 0),
        alto: parseFloat(this.paqueteData.alto || 0),
        ancho: parseFloat(this.paqueteData.ancho || 0),
        largo: parseFloat(this.paqueteData.largo || 0),
        valor: parseFloat(this.paqueteData.valor || 50)
      },
      destino: this.getDireccionDestino(),
      origen: this.getDireccionOrigen()
    };

    this.boxfulService.getCouriersAvailable(payload).subscribe({
      next: (res: any) => {
        this.couriers = Array.isArray(res) ? res : (res.data || []);
        this.loadingCouriers = false;
        if (this.couriers.length > 0) {
          this.selectedCourierId = this.couriers[0].id || this.couriers[0].courierId;
          this.step = 2;
        } else {
          this.errorMessage = 'No se encontraron paqueterías disponibles para esta dirección y dimensiones.';
          this.alertService.warning('Atención', this.errorMessage);
        }
      },
      error: (err: any) => {
        console.error('Error al cotizar mensajería:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al obtener las paqueterías disponibles de Boxful.';
        this.alertService.error(this.errorMessage);
        this.loadingCouriers = false;
      }
    });
  }

  // Paso 2: Generar Guía
  generarGuia(): void {
    if (!this.selectedCourierId) {
      this.errorMessage = 'Debe seleccionar una paquetería.';
      this.alertService.error(this.errorMessage);
      return;
    }

    this.generatingGuide = true;
    this.errorMessage = null;

    const destino = this.getDireccionDestino();

    // Construir payload final para shipment
    const payload = {
      courierId: this.selectedCourierId,
      paquete: {
        peso: parseFloat(this.paqueteData.peso || 0),
        alto: parseFloat(this.paqueteData.alto || 0),
        ancho: parseFloat(this.paqueteData.ancho || 0),
        largo: parseFloat(this.paqueteData.largo || 0),
        valor: parseFloat(this.paqueteData.valor || 50)
      },
      destino: destino,
      origen: this.getDireccionOrigen(),
      cliente: {
        nombre: this.clienteNombre || 'Cliente',
        apellido: this.clienteApellido || 'Final',
        email: this.clienteEmail || 'cliente@email.com',
        telefono: destino.telefono || '',
        codigo_area: destino.codigo_area || '503'
      },
      clienteId: this.clienteId
    };

    this.boxfulService.createShipment(payload).subscribe({
      next: (res: any) => {
        this.shipmentResult = res;
        this.generatingGuide = false;
        this.step = 3;
        this.guiaGenerada.emit(res);

        // Si es una dirección nueva y se marcó para guardar
        if (this.mode === 'new' && this.addressForm.value.guardarDireccion) {
          this.guardarNuevaDireccionEnCliente();
        }
      },
      error: (err: any) => {
        console.error('Error al generar la guía en Boxful:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al generar la guía en Boxful.';
        this.alertService.error(this.errorMessage);
        this.generatingGuide = false;
      }
    });
  }

  // Guardar dirección localmente en cliente y Boxful
  private guardarNuevaDireccionEnCliente(): void {
    const formVal = this.addressForm.value;
    const savePayload = {
      alias: 'Dirección de envío',
      direccion: formVal.direccion,
      referencia: formVal.referencia || '',
      telefono: formVal.telefono,
      codigo_area: formVal.codigo_area,
      latitud: parseFloat(formVal.latitud),
      longitud: parseFloat(formVal.longitud),
      boxful_state_id: formVal.stateId,
      boxful_city_id: formVal.cityId,
      es_predeterminada: false
    };

    this.boxfulService.storeClientAddress(this.clienteId, savePayload).subscribe({
      next: (savedAddr: any) => {
        console.log('Dirección guardada exitosamente en el cliente:', savedAddr);
        // Recargar las direcciones guardadas
        this.loadAddresses();
      },
      error: (err: any) => {
        console.error('Error al guardar dirección en el cliente en segundo plano:', err);
      }
    });
  }

  // Reiniciar Wizard
  resetWizard(): void {
    this.step = 1;
    this.couriers = [];
    this.selectedCourierId = null;
    this.shipmentResult = null;
    this.errorMessage = null;
    if (this.mode === 'new') {
      this.addressForm.reset({
        direccion: '',
        referencia: '',
        telefono: '',
        codigo_area: '503',
        stateId: null,
        cityId: null,
        latitud: null,
        longitud: null,
        guardarDireccion: true
      });
      this.filteredCities = [];
    } else {
      this.loadAddresses();
    }
  }

  closeSelector(): void {
    this.cerrar.emit();
  }
}
