-- generate tables, privileges and index
CREATE TABLE regions(
 id BIGSERIAL,
 name VARCHAR(100)
NOT NULL,
 external_region_id INT,
 system_id VARCHAR(4)
NOT NULL,
 PRIMARY KEY(id));
SELECT AddGeometryColumn('regions', 'geom', 4326, 'POLYGON', 2);

CREATE TABLE outings_regions(
 id BIGSERIAL,
 outing_id BIGINT,
 region_id BIGINT,
 PRIMARY KEY(id));

CREATE TABLE outings(
 id BIGSERIAL,
 name VARCHAR(150)
NOT NULL,
 elevation INT,
 date TIMESTAMP without time zone NOT NULL,
 lang VARCHAR(2),
 rating VARCHAR(20),
 facing INT,
 source_id INT,
 original_outing_id VARCHAR(20),
 url VARCHAR(200)
NOT NULL,
 region_name VARCHAR(100),
 activity_ids integer[],
 created_at TIMESTAMP without time zone,
 updated_at TIMESTAMP without time zone,
 PRIMARY KEY(id));
SELECT AddGeometryColumn('outings', 'geom', 4326, 'POINT', 2);

GRANT ALL PRIVILEGES ON outings, 
 outings_regions, 
 regions, 
 regions_id_seq, 
 outings_regions_id_seq, 
 outings_id_seq, 
 geometry_columns 
 TO "www-data";

CREATE INDEX outings_id_idx ON outings (id);
CREATE INDEX outings_name_idx ON outings (name);
CREATE INDEX outings_source_id_idx ON outings (name);
CREATE INDEX outings_date_idx ON outings (name);
CREATE INDEX outings_geom_idx ON outings USING GIST (geom GIST_GEOMETRY_OPS);
CREATE INDEX outings_regions_id_idx ON outings_regions (outing_id);
CREATE INDEX regions_id_idx ON regions (id);
CREATE INDEX regions_name_idx ON regions (name);
