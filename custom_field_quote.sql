DROP TABLE IF EXISTS product_custom_fields;
DROP TABLE IF EXISTS custom_field_values;
DROP TABLE IF EXISTS custom_fields;



CREATE TABLE custom_fields (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 name VARCHAR(100) NOT NULL,
 empresa_id int unsigned NOT NULL,
 field_type VARCHAR(50) NOT NULL,
 is_required BOOLEAN DEFAULT false,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP on update current_timestamp,
 PRIMARY KEY (id),
 key custom_fields_empresa_id_foreign (empresa_id),
 constraint custom_fields_empresa_id_foreign foreign key (empresa_id) references empresas (id) on delete cascade
);



CREATE TABLE custom_field_values (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 custom_field_id bigint unsigned not null,
 value VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at timestamp default current_timestamp on update current_timestamp,
 primary key (id),
 key custom_field_values_custom_field_id_foreign (custom_field_id),
 constraint custom_field_values_custom_field_id_foreign foreign key (custom_field_id) references custom_fields (id) on delete cascade
);


CREATE TABLE product_custom_fields (
  id bigint unsigned not null auto_increment,
  custom_field_id bigint unsigned not null,
  custom_field_value_id bigint unsigned,
  cotizacion_venta_detalle_id int,
  value VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP on update current_timestamp,
  primary key (id),
  key product_custom_fields_custom_field_id_foreign (custom_field_id),
  key product_custom_fields_value_id_foreign (custom_field_value_id),
  key product_custom_fields_cotizacion_venta_detalle_id_foreign (cotizacion_venta_detalle_id),
  constraint product_custom_fields_custom_field_id_foreign foreign key (custom_field_id) references custom_fields (id) on delete cascade,
  constraint product_custom_fields_value_id_foreign foreign key (custom_field_value_id) references custom_field_values (id) on delete set null,
  constraint product_custom_fields_cotizacion_venta_detalle_id_foreign foreign key (cotizacion_venta_detalle_id) references detalles_cotizacion_ventas (id) on delete set null
);