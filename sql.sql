ALTER TABLE clientes ADD red_social VARCHAR(255) NULL after nota;

ALTER TABLE eventos ADD frecuencia_fin date NULL after frecuencia;

ALTER TABLE notificaciones ADD referencia VARCHAR(255) NULL after leido;
ALTER TABLE notificaciones ADD id_referencia int NULL after referencia;
