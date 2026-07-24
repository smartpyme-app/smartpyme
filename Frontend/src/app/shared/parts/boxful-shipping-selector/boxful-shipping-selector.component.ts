import { Component, EventEmitter, Input, OnInit, Output, OnChanges, OnDestroy, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { BoxfulApiService, BoxfulState, BoxfulCity } from '@services/boxful/boxful-api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-boxful-shipping-selector',
  templateUrl: './boxful-shipping-selector.component.html',
  styleUrls: ['./boxful-shipping-selector.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, TooltipModule],
})
export class BoxfulShippingSelectorComponent implements OnInit, OnChanges, OnDestroy {
  @Input() clienteId!: number;
  @Input() paqueteData: any; // peso, alto, ancho, largo
  @Input() pedidoId: number | null = null;
  @Input() ventaId: number | null = null;
  /** Si true, premarca pago contra entrega (editable). */
  @Input() sugerirCod = false;
  /** Monto COD sugerido (p. ej. total de la venta). */
  @Input() montoCodSugerido: number | null = null;
  @Output() guiaGenerada = new EventEmitter<any>();
  @Output() cerrar = new EventEmitter<void>();

  public clientData: any = null;

  // Asistente (Wizard)
  step: number = 1;

  /** Cash on Delivery / pago contra entrega */
  cod = false;
  codAmount = 0;

  private destroy$ = new Subject<void>();
  private alive = true;

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['clienteId'] && !changes['clienteId'].firstChange) {
      this.resetWizard();
      if (this.clienteId) {
        this.loadClientDetails();
      }
    }
    if (changes['sugerirCod'] || changes['montoCodSugerido']) {
      this.aplicarSugerenciaCod();
    }
  }

  ngOnDestroy(): void {
    this.alive = false;
    this.destroy$.next();
    this.destroy$.complete();
  }

  // Paso 1
  addressForm!: FormGroup;
  states: BoxfulState[] = [];
  filteredCities: BoxfulCity[] = [];
  mode: 'new' = 'new';

  /** Códigos telefónicos de país usados por BoxFul (addressAreaCode). */
  readonly codigosArea = [
    { value: '503', label: 'El Salvador (+503)' },
    { value: '502', label: 'Guatemala (+502)' },
    { value: '506', label: 'Costa Rica (+506)' },
    { value: '504', label: 'Honduras (+504)' },
  ];

  // Datos de Bodega/Origen de la Empresa
  originAddresses: any[] = [];
  selectedOriginAddressId: string | null = null;
  loadingOriginAddresses = false;
  originMode: 'saved' | 'new' = 'saved';
  originAddressForm!: FormGroup;
  savingOriginAddress = false;
  deletingOriginAddress = false;
  /** Si está set, el formulario de bodega está en modo edición (PATCH BoxFul). */
  editingOriginBoxfulId: string | null = null;
  filteredOriginCities: BoxfulCity[] = [];

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

  fechaRecoleccion: string = '';
  minFechaRecoleccion: string = '';

  constructor(
    private fb: FormBuilder,
    private boxfulService: BoxfulApiService,
    private alertService: AlertService,
    private router: Router
  ) { }

  ngOnInit(): void {
    this.initForm();
    this.loadStates();
    this.loadOriginAddresses();
    this.minFechaRecoleccion = this.getTodayString();
    this.fechaRecoleccion = this.getTodayString();
    if (!this.paqueteData) {
      this.paqueteData = { peso: 1, alto: 11, ancho: 43, largo: 47.5, es_fragil: false, id: null, contenido: '' };
    } else {
      if (this.paqueteData.peso === undefined || this.paqueteData.peso === null) this.paqueteData.peso = this.paqueteData.weight || this.paqueteData.peso || 1;
      if (this.paqueteData.alto === undefined || this.paqueteData.alto === null) this.paqueteData.alto = this.paqueteData.height || this.paqueteData.alto || 11;
      if (this.paqueteData.ancho === undefined || this.paqueteData.ancho === null) this.paqueteData.ancho = this.paqueteData.width || this.paqueteData.ancho || 43;
      if (this.paqueteData.largo === undefined || this.paqueteData.largo === null) this.paqueteData.largo = this.paqueteData.length || this.paqueteData.largo || 47.5;
    }
    
    // Ensure all parcels have contenido initialized
    const parcels = this.getParcels();
    parcels.forEach(p => {
      if (p.contenido === undefined || p.contenido === null) {
        p.contenido = p.content || '';
      }
    });

    if (this.clienteId) {
      this.loadClientDetails();
    }

    this.aplicarSugerenciaCod();
  }

  private aplicarSugerenciaCod(): void {
    this.cod = !!this.sugerirCod;
    const sugerido = this.montoCodSugerido != null && !isNaN(Number(this.montoCodSugerido))
      ? Number(this.montoCodSugerido)
      : this.sumaValorParcels();
    this.codAmount = this.cod ? Math.max(0, sugerido) : 0;
  }

  private sumaValorParcels(): number {
    const parcels = this.getParcels();
    if (!parcels.length) {
      return parseFloat(this.paqueteData?.valor || this.paqueteData?.price || 0) || 0;
    }
    return parcels.reduce((sum, p) => sum + (parseFloat(p.valor || p.price || 0) || 0), 0);
  }

  onCodToggle(): void {
    if (this.cod && (!this.codAmount || this.codAmount <= 0)) {
      this.codAmount = this.montoCodSugerido != null
        ? Number(this.montoCodSugerido)
        : this.sumaValorParcels();
    }
    if (!this.cod) {
      this.codAmount = 0;
    }
  }

  private validarCod(): boolean {
    if (!this.cod) {
      return true;
    }
    const monto = parseFloat(String(this.codAmount));
    if (!monto || monto <= 0) {
      this.errorMessage = 'Si activa pago contra entrega (COD), indique un monto mayor a 0.';
      this.alertService.error(this.errorMessage);
      return false;
    }
    return true;
  }

  getParcels(): any[] {
    if (this.paqueteData) {
      if (this.paqueteData.parcels && this.paqueteData.parcels.length > 0) {
        return this.paqueteData.parcels;
      }
      if (this.paqueteData.boxful_shipment && this.paqueteData.boxful_shipment.parcels && this.paqueteData.boxful_shipment.parcels.length > 0) {
        return this.paqueteData.boxful_shipment.parcels;
      }
    }
    return [this.paqueteData];
  }

  getTipoPaquete(p: any): 'S' | 'M' | 'L' | null {
    const a = Number(p.alto || p.height || 0);
    const w = Number(p.ancho || p.width || 0);
    const l = Number(p.largo || p.length || 0);

    if (a === 11 && w === 43 && l === 47.5) {
      return 'S';
    } else if (a === 22 && w === 43 && l === 47.5) {
      return 'M';
    } else if (a === 34 && w === 43 && l === 47.5) {
      return 'L';
    }
    return null;
  }

  seleccionarTipoPaquete(p: any, tipo: 'S' | 'M' | 'L', alto: number, ancho: number, largo: number): void {
    p.alto = alto;
    p.height = alto;
    p.ancho = ancho;
    p.width = ancho;
    p.largo = largo;
    p.length = largo;
  }

  private getTodayString(): string {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }

  loadClientDetails(): void {
    if (!this.clienteId) return;
    this.boxfulService.getClientDetails(this.clienteId).subscribe({
      next: (client: any) => {
        if (client) {
          this.clientData = client;
          this.clienteNombre = client.nombre || '';
          this.clienteApellido = client.apellido || '';
          this.clienteEmail = client.correo || client.email || '';

          this.fillAddressFormFromClient();
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

    this.originAddressForm = this.fb.group({
      alias: ['', [Validators.required, Validators.maxLength(100)]],
      direccion: ['', [Validators.required, Validators.maxLength(500)]],
      referencia: ['', [Validators.required, Validators.maxLength(500)]], // Requerido por la especificación de origen
      telefono: ['', [Validators.required, Validators.pattern(/^[0-9+ ]{8,20}$/)]],
      codigo_area: ['503', [Validators.required, Validators.maxLength(10)]],
      stateId: [null, [Validators.required]],
      cityId: [null, [Validators.required]],
      latitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      longitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]]
    });
  }

  loadStates(): void {
    this.loading = true;
    this.boxfulService.getStates().subscribe({
      next: (res: any) => {
        this.loading = false;
        if (res && Array.isArray(res.states)) {
          this.states = res.states;
          this.errorMessage = null;
        } else if (Array.isArray(res)) {
          this.states = res;
          this.errorMessage = null;
        } else {
          console.error('La respuesta de estados no es un arreglo válido:', res);
          this.states = [];
          this.errorMessage = res?.message || res?.error || 'No se pudieron cargar los estados de Boxful (respuesta inválida).';
        }
        this.tryMatchClientLocation();
      },
      error: (err: any) => {
        console.error('Error cargando estados de Boxful:', err);
        this.errorMessage = 'No se pudieron cargar los estados/departamentos de Boxful.';
        this.loading = false;
      }
    });
  }

  loadOriginAddresses(): void {
    this.loadingOriginAddresses = true;
    this.boxfulService.getLocalOriginAddresses().subscribe({
      next: (res: any) => {
        this.loadingOriginAddresses = false;

        let list: any[] = [];
        if (Array.isArray(res)) {
          list = res;
        } else if (res && Array.isArray(res.addresses)) {
          list = res.addresses;
        } else if (res && Array.isArray(res.data)) {
          list = res.data;
        }

        this.originAddresses = list;

        if (this.originAddresses.length > 0) {
          const defaultAddr = this.originAddresses.find(a => a.es_predeterminada);
          const selectedAddr = defaultAddr || this.originAddresses[0];
          this.selectedOriginAddressId = selectedAddr.boxful_address_id || selectedAddr.id || selectedAddr.addressId;
          this.originMode = 'saved';
        } else {
          this.selectedOriginAddressId = null;
          this.originMode = 'new';
        }
      },
      error: (err: any) => {
        console.error('Error cargando direcciones de origen:', err);
        this.loadingOriginAddresses = false;
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

  toggleOriginMode(): void {
    this.originMode = this.originMode === 'saved' ? 'new' : 'saved';
    this.errorMessage = null;
    this.editingOriginBoxfulId = null;
    if (this.originMode === 'new') {
      this.originAddressForm.reset({
        alias: '',
        direccion: '',
        referencia: '',
        telefono: '',
        codigo_area: '503',
        stateId: null,
        cityId: null,
        latitud: null,
        longitud: null
      });
      this.filteredOriginCities = [];
    }
  }

  cancelarFormularioOrigen(): void {
    this.editingOriginBoxfulId = null;
    this.errorMessage = null;
    if (this.originAddresses.length > 0) {
      this.originMode = 'saved';
    }
    this.originAddressForm.reset({
      alias: '',
      direccion: '',
      referencia: '',
      telefono: '',
      codigo_area: '503',
      stateId: null,
      cityId: null,
      latitud: null,
      longitud: null
    });
    this.filteredOriginCities = [];
  }

  editarDireccionOrigen(): void {
    if (!this.selectedOriginAddressId) {
      return;
    }

    const boxfulAddressId = String(this.selectedOriginAddressId);
    const selected = this.originAddresses.find(
      (a) => String(a.boxful_address_id || a.id || a.addressId) === boxfulAddressId
    );
    if (!selected) {
      this.alertService.error('No se encontró la bodega seleccionada.');
      return;
    }

    const stateId = selected.boxful_state_id || selected.stateId || null;
    const cityId = selected.boxful_city_id || selected.cityId || null;
    const selectedState = this.states.find((s) => String(s.id) === String(stateId));
    this.filteredOriginCities = selectedState?.Cities || [];

    this.editingOriginBoxfulId = selected.boxful_address_id
      ? String(selected.boxful_address_id)
      : boxfulAddressId;
    this.originMode = 'new';
    this.errorMessage = null;

    this.originAddressForm.reset({
      alias: selected.alias || '',
      direccion: selected.direccion || selected.address || '',
      referencia: selected.referencia || selected.referencePoint || '',
      telefono: selected.telefono || selected.addressPhone || '',
      codigo_area: this.normalizarCodigoArea(selected.codigo_area || selected.addressAreaCode || '503'),
      stateId,
      cityId,
      latitud: selected.latitud ?? selected.latitude ?? null,
      longitud: selected.longitud ?? selected.longitude ?? null
    });
  }

  /** Normaliza "+503" / "503" al value del select. */
  private normalizarCodigoArea(codigo: string | number | null | undefined): string {
    const raw = String(codigo ?? '503').replace(/^\+/, '').trim();
    const known = this.codigosArea.find((c) => c.value === raw);
    return known ? known.value : '503';
  }

  onOriginStateChange(): void {
    const stateId = this.originAddressForm.get('stateId')?.value;
    const selectedState = this.states.find(s => String(s.id) === String(stateId));

    if (selectedState && selectedState.Cities) {
      this.filteredOriginCities = selectedState.Cities;
    } else {
      this.filteredOriginCities = [];
    }

    this.originAddressForm.get('cityId')?.setValue(null);
    this.originAddressForm.patchValue({
      latitud: null,
      longitud: null
    });
  }

  onOriginCityChange(): void {
    const cityId = this.originAddressForm.get('cityId')?.value;
    const selectedCity = this.filteredOriginCities.find(c => String(c.id) === String(cityId));

    if (selectedCity && selectedCity.latitude !== undefined && selectedCity.longitude !== undefined) {
      this.originAddressForm.patchValue({
        latitud: selectedCity.latitude,
        longitud: selectedCity.longitude
      });
    } else {
      this.originAddressForm.patchValue({
        latitud: null,
        longitud: null
      });
    }
  }

  eliminarDireccionOrigen(): void {
    if (!this.selectedOriginAddressId || this.deletingOriginAddress) {
      return;
    }

    const boxfulAddressId = String(this.selectedOriginAddressId);
    const selected = this.originAddresses.find(
      (a) => String(a.boxful_address_id || a.id || a.addressId) === boxfulAddressId
    );
    const label = selected?.alias || selected?.direccion || selected?.address || boxfulAddressId;

    if (!confirm(`¿Eliminar la bodega/origen "${label}"? Esta acción no se puede deshacer.`)) {
      return;
    }

    this.deletingOriginAddress = true;
    this.errorMessage = null;

    this.boxfulService.deleteAddress(boxfulAddressId).subscribe({
      next: () => {
        this.deletingOriginAddress = false;
        this.originAddresses = this.originAddresses.filter(
          (a) => String(a.boxful_address_id || a.id || a.addressId) !== boxfulAddressId
        );

        if (this.originAddresses.length > 0) {
          const next = this.originAddresses[0];
          this.selectedOriginAddressId = next.boxful_address_id || next.id || next.addressId;
        } else {
          this.selectedOriginAddressId = null;
          this.originMode = 'new';
        }

        this.alertService.success('Éxito', 'Dirección de origen eliminada.');
      },
      error: (err: any) => {
        this.deletingOriginAddress = false;
        this.errorMessage = err?.error?.message || 'No se pudo eliminar la dirección de origen.';
        this.alertService.error(this.errorMessage);
      }
    });
  }

  guardarNuevaDireccionOrigen(): void {
    if (this.originAddressForm.invalid) {
      this.originAddressForm.markAllAsTouched();
      this.alertService.error('Por favor complete todos los campos de la dirección de origen correctamente.');
      return;
    }

    this.savingOriginAddress = true;
    this.errorMessage = null;

    const formVal = this.originAddressForm.value;
    const lat = parseFloat(formVal.latitud);
    const lng = parseFloat(formVal.longitud);

    if (this.editingOriginBoxfulId) {
      const patchPayload = {
        alias: formVal.alias || 'Bodega',
        address: formVal.direccion,
        referencePoint: formVal.referencia,
        latitude: lat,
        longitude: lng,
        stateId: formVal.stateId,
        cityId: formVal.cityId,
        addressPhone: formVal.telefono,
        addressAreaCode: formVal.codigo_area
      };

      this.boxfulService.updateAddress(this.editingOriginBoxfulId, patchPayload).subscribe({
        next: (res: any) => {
          this.savingOriginAddress = false;
          const updated = res?.address || res;
          const idKey = String(this.editingOriginBoxfulId);
          const idx = this.originAddresses.findIndex(
            (a) => String(a.boxful_address_id || a.id || a.addressId) === idKey
          );
          if (idx >= 0 && updated) {
            this.originAddresses[idx] = { ...this.originAddresses[idx], ...updated };
          } else {
            this.loadOriginAddresses();
          }
          this.selectedOriginAddressId = idKey;
          this.editingOriginBoxfulId = null;
          this.originMode = 'saved';
          this.alertService.success('Éxito', 'Dirección de origen actualizada.');
        },
        error: (err: any) => {
          this.savingOriginAddress = false;
          this.errorMessage = err?.error?.message || 'No se pudo actualizar la dirección de origen.';
          this.alertService.error(this.errorMessage);
        }
      });
      return;
    }

    const payload = {
      alias: formVal.alias || 'Bodega',
      direccion: formVal.direccion,
      referencia: formVal.referencia,
      latitude: lat,
      longitude: lng,
      latitud: lat,
      longitud: lng,
      stateId: formVal.stateId,
      cityId: formVal.cityId,
      boxful_state_id: formVal.stateId,
      boxful_city_id: formVal.cityId,
      telefono: formVal.telefono,
      addressPhone: formVal.telefono,
      codigo_area: formVal.codigo_area,
      addressAreaCode: formVal.codigo_area,
      es_predeterminada: false
    };

    this.boxfulService.storeLocalOriginAddress(payload).subscribe({
      next: (res: any) => {
        this.savingOriginAddress = false;
        this.alertService.success('Éxito', 'Dirección de origen guardada exitosamente.');

        const createdAddr = res.address || res;
        if (createdAddr) {
          this.originAddresses.push(createdAddr);
          this.selectedOriginAddressId = createdAddr.boxful_address_id || createdAddr.id || createdAddr.addressId;
          this.originMode = 'saved';
          this.editingOriginBoxfulId = null;
          this.originAddressForm.reset({
            alias: '',
            direccion: '',
            referencia: '',
            telefono: '',
            codigo_area: '503',
            stateId: null,
            cityId: null,
            latitud: null,
            longitud: null
          });
          this.filteredOriginCities = [];
        } else {
          this.loadOriginAddresses();
        }
      },
      error: (err: any) => {
        console.error('Error al guardar dirección de origen:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al guardar la dirección de origen.';
        this.alertService.error(this.errorMessage);
        this.savingOriginAddress = false;
      }
    });
  }

  private getDireccionOrigen(): any {
    if (this.selectedOriginAddressId) {
      const selected = this.originAddresses.find(a => String(a.boxful_address_id || a.id || a.addressId) === String(this.selectedOriginAddressId));
      if (selected) {
        return {
          id: selected.boxful_address_id || selected.id || selected.addressId,
          direccion: selected.direccion || selected.address,
          referencia: selected.referencia || selected.referencePoint || '',
          latitud: selected.latitud || selected.latitude,
          longitud: selected.longitud || selected.longitude,
          stateId: selected.boxful_state_id || selected.stateId,
          cityId: selected.boxful_city_id || selected.cityId,
          telefono: selected.telefono || selected.addressPhone,
          codigo_area: selected.codigo_area || selected.addressAreaCode
        };
      }
    }
    return null;
  }

  private getDireccionDestino(): any {
    const formVal = this.addressForm.value;
    return {
      direccion: formVal.direccion,
      referencia: formVal.referencia || '',
      latitud: parseFloat(formVal.latitud),
      longitud: parseFloat(formVal.longitud),
      stateId: formVal.stateId,
      cityId: formVal.cityId,
      telefono: formVal.telefono,
      codigo_area: formVal.codigo_area || '503'
    };
  }

  // Paso 1: Cotizar envío
  cotizarEnvio(): void {
    this.errorMessage = null;

    // Validar dirección de origen
    if (!this.selectedOriginAddressId) {
      this.errorMessage = 'Debe seleccionar una dirección de origen.';
      this.alertService.error(this.errorMessage);
      return;
    }

    // Validar dirección
    if (this.addressForm.invalid) {
      this.addressForm.markAllAsTouched();
      this.errorMessage = 'Por favor complete todos los campos de la dirección correctamente.';
      this.alertService.error(this.errorMessage);
      return;
    }

    // Validar paqueteData
    if (!this.paqueteData) {
      this.errorMessage = 'Los datos del paquete son obligatorios para cotizar.';
      this.alertService.error(this.errorMessage);
      return;
    }

    const parcelsList = this.getParcels();
    for (let j = 0; j < parcelsList.length; j++) {
      const p = parcelsList[j];
      if (!p.contenido || !p.contenido.trim()) {
        this.errorMessage = `El contenido del paquete ${parcelsList.length > 1 ? '#' + (j + 1) : ''} es obligatorio.`;
        this.alertService.error(this.errorMessage);
        return;
      }
    }

    if (!this.validarCod()) {
      return;
    }

    this.loadingCouriers = true;

    const paquetesPayload = parcelsList.map(p => ({
      id: p.id || null,
      peso: parseFloat(p.weight || p.peso || 1.0),
      alto: parseFloat(p.height || p.alto || 11.0),
      ancho: parseFloat(p.width || p.ancho || 43.0),
      largo: parseFloat(p.length || p.largo || 47.5),
      valor: parseFloat(p.price || p.valor || 50.0),
      es_fragil: !!(p.es_fragil || p.isFragile),
      isFragile: !!(p.es_fragil || p.isFragile),
      contenido: p.contenido || p.content || 'Productos varios'
    }));

    // Construir payload según la nueva arquitectura normalizada
    const payload = {
      paquetes: paquetesPayload,
      destino: this.getDireccionDestino(),
      origen: this.getDireccionOrigen(),
      paqueteId: this.paqueteData?.id || null,
      fecha_recoleccion: this.fechaRecoleccion,
      cod: !!this.cod,
      codAmount: this.cod ? parseFloat(String(this.codAmount)) : 0,
    };

    this.boxfulService.getCouriersAvailable(payload).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res: any) => {
        if (!this.alive) {
          return;
        }
        this.loadingCouriers = false;

        let couriersList: any[] = [];
        if (Array.isArray(res)) {
          couriersList = res;
        } else if (res && Array.isArray(res.data)) {
          couriersList = res.data;
        } else if (res && Array.isArray(res.couriers)) {
          couriersList = res.couriers;
        } else if (res && (res.message || res.error)) {
          this.errorMessage = res.message || res.error;
          this.alertService.error(this.errorMessage);
          return;
        }

        this.couriers = couriersList;

        if (this.couriers.length > 0) {
          this.selectedCourierId = this.couriers[0].id || this.couriers[0].courierId;
          this.step = 2;
        } else {
          this.errorMessage = this.errorMessage || 'No se encontraron paqueterías disponibles para esta dirección y dimensiones.';
          this.alertService.warning('Atención', this.errorMessage);
        }
      },
      error: (err: any) => {
        if (!this.alive) {
          return;
        }
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

    const parcelsList = this.getParcels();
    for (let j = 0; j < parcelsList.length; j++) {
      const p = parcelsList[j];
      if (!p.contenido || !p.contenido.trim()) {
        this.errorMessage = `El contenido del paquete ${parcelsList.length > 1 ? '#' + (j + 1) : ''} es obligatorio.`;
        this.alertService.error(this.errorMessage);
        return;
      }
    }

    if (!this.validarCod()) {
      return;
    }

    this.generatingGuide = true;
    this.errorMessage = null;

    const destino = this.getDireccionDestino();

    const paquetesPayload = parcelsList.map(p => ({
      id: p.id || null,
      peso: parseFloat(p.weight || p.peso || 1.0),
      alto: parseFloat(p.height || p.alto || 11.0),
      ancho: parseFloat(p.width || p.ancho || 43.0),
      largo: parseFloat(p.length || p.largo || 47.5),
      valor: parseFloat(p.price || p.valor || 50.0),
      es_fragil: !!(p.es_fragil || p.isFragile),
      isFragile: !!(p.es_fragil || p.isFragile),
      contenido: p.contenido || p.content || 'Productos varios'
    }));

    const orderRef = this.pedidoId
      ? String(this.pedidoId)
      : (this.ventaId ? String(this.ventaId) : undefined);

    // Construir payload final para shipment
    const payload = {
      courierId: this.selectedCourierId,
      storeOrderNumber: orderRef,
      orderNumber: orderRef,
      paquetes: paquetesPayload,
      destino: destino,
      origen: this.getDireccionOrigen(),
      cliente: {
        nombre: this.clienteNombre || 'Cliente',
        apellido: this.clienteApellido || 'Final',
        email: this.clienteEmail || 'cliente@email.com',
        telefono: destino.telefono || '',
        codigo_area: destino.codigo_area || '503'
      },
      clienteId: this.clienteId,
      paqueteId: this.paqueteData?.id || null,
      pedidoId: this.pedidoId || null,
      ventaId: this.ventaId || null,
      fecha_recoleccion: this.fechaRecoleccion,
      cod: !!this.cod,
      codAmount: this.cod ? parseFloat(String(this.codAmount)) : 0,
    };

    this.boxfulService.createShipment(payload).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res: any) => {
        if (!this.alive) {
          return;
        }
        this.shipmentResult = this.normalizarShipmentResult(res);
        if (Array.isArray(res?.warnings)) {
          this.shipmentResult.warnings = res.warnings;
        }
        this.generatingGuide = false;
        this.step = 3;
        if (Array.isArray(res?.warnings) && res.warnings.length) {
          this.alertService.warning('Atención', res.warnings.join(' '));
        }
        this.guiaGenerada.emit(this.shipmentResult);
      },
      error: (err: any) => {
        if (!this.alive) {
          return;
        }
        // Guía ya existente: mostrar confirmación con datos locales
        if (err?.status === 409 && err?.error?.shipmentNumber) {
          this.shipmentResult = this.normalizarShipmentResult(err.error);
          this.generatingGuide = false;
          this.step = 3;
          this.alertService.warning('Atención', err.error?.message || 'Este envío ya tiene guía BoxFul.');
          this.guiaGenerada.emit(this.shipmentResult);
          return;
        }
        console.error('Error al generar la guía en Boxful:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al generar la guía en Boxful.';
        this.alertService.error(this.errorMessage);
        this.generatingGuide = false;
      }
    });
  }

  /**
   * BoxFul / nuestro proxy pueden anidar el envío en shipmentData|data|response.
   * Aplana campos usados por el paso 3 (guía, PDF, tracking, precio).
   */
  private normalizarShipmentResult(res: any): any {
    const candidates = [
      res?.shipmentData,
      res?.data,
      res?.response,
      res?.shipment,
      res,
    ].filter((x) => x && typeof x === 'object');

    let nested: any = {};
    for (const c of candidates) {
      if (c.shipmentNumber || c.labelUrl || c.trackingUrl || c.shipment_number || c.id) {
        nested = c;
        break;
      }
      if (c.data && (c.data.shipmentNumber || c.data.labelUrl)) {
        nested = c.data;
        break;
      }
    }

    const selectedCourier = this.couriers.find(
      (x) => String(x.id || x.courierId) === String(this.selectedCourierId)
    );

    return {
      shipmentNumber:
        nested.shipmentNumber ||
        nested.shipment_number ||
        nested.trackingNumber ||
        nested.tracking_number ||
        nested.id ||
        null,
      labelUrl:
        nested.labelUrl ||
        nested.label_url ||
        nested.label ||
        nested.pdfUrl ||
        nested.pdf_url ||
        null,
      trackingUrl:
        nested.trackingUrl ||
        nested.tracking_url ||
        nested.tracking ||
        nested.trackUrl ||
        null,
      courierName:
        nested.courierName ||
        nested.courier_name ||
        nested.Courier?.name ||
        nested.courier?.name ||
        selectedCourier?.name ||
        selectedCourier?.courierName ||
        null,
      price:
        nested.price ??
        nested.Price ??
        nested.cost ??
        nested.total ??
        selectedCourier?.price ??
        0,
      cod: nested.cod ?? this.cod,
      codAmount: nested.codAmount ?? nested.cod_monto ?? (this.cod ? this.codAmount : 0),
      raw: res,
    };
  }

  // Reiniciar Wizard
  resetWizard(): void {
    this.step = 1;
    this.couriers = [];
    this.selectedCourierId = null;
    this.shipmentResult = null;
    this.errorMessage = null;
    this.aplicarSugerenciaCod();
    this.loadOriginAddresses();
    this.originMode = 'saved';
    this.fechaRecoleccion = this.getTodayString();
    this.originAddressForm.reset({
      alias: '',
      direccion: '',
      referencia: '',
      telefono: '',
      codigo_area: '503',
      stateId: null,
      cityId: null,
      latitud: null,
      longitud: null
    });
    this.filteredOriginCities = [];
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
  }

  getRecolectionDateInfo(courier: any): { label: string, time: string } {
    const now = new Date();
    const isEarly = now.getHours() < 11;
    return {
      label: isEarly ? 'Hoy' : 'Mañana',
      time: '08:00 - 18:00'
    };
  }

  getDeliveryDateInfo(courier: any): { dateFormatted: string, relativeLabel: string } {
    const deliveryStr = courier.estimatedDelivery || courier.deliveryTime;
    if (!deliveryStr) {
      return { dateFormatted: 'N/A', relativeLabel: 'N/A' };
    }

    const normalizedStr = deliveryStr.replace(' ', 'T');
    const deliveryDate = new Date(normalizedStr);

    if (isNaN(deliveryDate.getTime())) {
      return { dateFormatted: deliveryStr, relativeLabel: 'Próximamente' };
    }

    const day = String(deliveryDate.getDate()).padStart(2, '0');
    const month = String(deliveryDate.getMonth() + 1).padStart(2, '0');
    const year = deliveryDate.getFullYear();
    const dateFormatted = `${day} / ${month} / ${year}`;

    const now = new Date();
    const d1 = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const d2 = new Date(deliveryDate.getFullYear(), deliveryDate.getMonth(), deliveryDate.getDate());
    const diffTime = d2.getTime() - d1.getTime();
    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

    let relativeLabel = '';
    if (diffDays <= 0) {
      relativeLabel = 'Hoy mismo';
    } else if (diffDays === 1) {
      relativeLabel = 'En 1 día';
    } else {
      relativeLabel = `En ${diffDays} días`;
    }

    return { dateFormatted, relativeLabel };
  }

  closeSelector(): void {
    this.cerrar.emit();
  }

  /**
   * Tras generar guía: cierra el wizard y abre facturación.
   * /venta/crear → FacturacionVersionGuard redirige a v1 o ventas-v2/crear.
   */
  crearOtroEnvio(): void {
    this.cerrar.emit();
    this.router.navigate(['/venta/crear']);
  }

  /** Cierra el wizard y lleva al listado de pedidos (seguimiento BoxFul). */
  irAPedidos(): void {
    this.cerrar.emit();
    this.router.navigate(['/pedidos']);
  }

  private fillAddressFormFromClient(): void {
    if (!this.clientData) return;

    const clientAddress = this.clientData.tipo === 'Empresa' ? this.clientData.empresa_direccion : this.clientData.direccion;
    const clientPhone = this.clientData.tipo === 'Empresa' ? this.clientData.empresa_telefono : this.clientData.telefono;

    if (clientAddress && !this.addressForm.get('direccion')?.value) {
      //pre-fill shipping selector address from client profile
      this.addressForm.patchValue({
        direccion: clientAddress
      });
    }

    if (clientPhone && !this.addressForm.get('telefono')?.value) {
      //clean phone format and pre-fill phone
      const cleanedPhone = String(clientPhone).replace(/[^0-9+ ]/g, '');
      this.addressForm.patchValue({
        telefono: cleanedPhone
      });
    }

    this.tryMatchClientLocation();
  }

  private tryMatchClientLocation(): void {
    if (!this.clientData || !this.states || this.states.length === 0) return;

    const clientState = this.clientData.departamento;
    const clientCity = this.clientData.municipio;

    if (!clientState) return;

    //case-insensitive match for department name to auto-fill select dropdown
    const matchedState = this.states.find(s =>
      s.name.toLowerCase().trim() === clientState.toLowerCase().trim()
    );

    if (matchedState) {
      this.addressForm.patchValue({
        stateId: matchedState.id
      });

      this.onStateChange();

      if (clientCity && this.filteredCities.length > 0) {
        //case-insensitive match for municipality name to auto-fill select dropdown
        const matchedCity = this.filteredCities.find(c =>
          c.name.toLowerCase().trim() === clientCity.toLowerCase().trim()
        );
        if (matchedCity) {
          this.addressForm.patchValue({
            cityId: matchedCity.id
          });
          this.onCityChange();
        }
      }
    }
  }
}
