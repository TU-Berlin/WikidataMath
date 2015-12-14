-- property info table --

CREATE TABLE IF NOT EXISTS /*_*/wb_property_info (
  pi_property_id    INT unsigned        PRIMARY KEY NOT NULL,
  pi_type           VARBINARY(32)       NOT NULL,
  pi_info           BLOB                NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX IF NOT EXISTS /*i*/pi_type ON /*_*/wb_property_info (pi_type);

