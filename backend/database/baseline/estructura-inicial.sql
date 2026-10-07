--
-- PostgreSQL database dump
--


-- Dumped from database version 17.9
-- Dumped by pg_dump version 17.9

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: adq; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA IF NOT EXISTS adq;


--
-- Name: dbo; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA IF NOT EXISTS dbo;


--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA dbo;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- Name: nom_auditoria_log_bloquear_cambio(); Type: FUNCTION; Schema: dbo; Owner: -
--

CREATE FUNCTION dbo.nom_auditoria_log_bloquear_cambio() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                RAISE EXCEPTION 'dbo.nom_auditoria_log es append-only: % no permitido (id=%)', TG_OP, OLD.id;
                RETURN NULL;
            END;
            $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: articulo; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.articulo (
    id bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    nombre character varying(200) NOT NULL,
    descripcion character varying(500),
    unidad_medida character varying(50),
    categoria character varying(100),
    stock_actual numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    stock_maximo_historico numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    marca character varying(100),
    nivel1 character varying(2),
    nivel2 character varying(6),
    precio_unitario numeric(10,5) DEFAULT 0 NOT NULL,
    iva_id integer,
    item_presupuestario character varying(150),
    estado_fisico character varying(20) DEFAULT 'BUENO'::character varying NOT NULL,
    imagen character varying(200)
);


--
-- Name: articulo_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.articulo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: articulo_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.articulo_id_seq OWNED BY adq.articulo.id;


--
-- Name: catalogo_inventario; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.catalogo_inventario (
    nivel1 character varying(2) NOT NULL,
    nivel2 character varying(6) NOT NULL,
    descripcion character varying(300) NOT NULL,
    asociacion_presupuestaria character varying(150)
);


--
-- Name: catalogo_nivel1; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.catalogo_nivel1 (
    nivel1 character varying(2) NOT NULL,
    descripcion character varying(200) NOT NULL
);


--
-- Name: configuracion; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.configuracion (
    concepto character varying(50) NOT NULL,
    valor character varying(200) NOT NULL,
    descripcion character varying(300),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: egreso; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.egreso (
    id integer NOT NULL,
    numero_secuencial integer,
    anio integer,
    direccion character varying(200),
    empleado_id character varying(20),
    empleado_nombre character varying(200),
    estado character varying(20) DEFAULT 'BORRADOR'::character varying NOT NULL,
    observacion text,
    subtotal numeric(12,2) DEFAULT 0 NOT NULL,
    iva_valor numeric(12,2) DEFAULT 0 NOT NULL,
    total numeric(12,2) DEFAULT 0 NOT NULL,
    usuario_registro character varying(20),
    usuario_despacho character varying(20),
    fecha_despacho timestamp without time zone,
    motivo_reverso text,
    usuario_reverso character varying(20),
    fecha_reverso timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: egreso_det; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.egreso_det (
    id integer NOT NULL,
    egreso_id integer NOT NULL,
    articulo_id integer NOT NULL,
    cantidad numeric(12,2) NOT NULL,
    precio_unitario numeric(10,5) DEFAULT 0 NOT NULL,
    precio_anterior numeric(10,5),
    iva_id integer,
    iva_porcentaje numeric(5,2) DEFAULT 0 NOT NULL,
    subtotal numeric(12,2) DEFAULT 0 NOT NULL,
    iva_valor numeric(12,2) DEFAULT 0 NOT NULL,
    total_linea numeric(12,2) DEFAULT 0 NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: egreso_det_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.egreso_det_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: egreso_det_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.egreso_det_id_seq OWNED BY adq.egreso_det.id;


--
-- Name: egreso_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.egreso_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: egreso_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.egreso_id_seq OWNED BY adq.egreso.id;


--
-- Name: iva; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.iva (
    id integer NOT NULL,
    descripcion character varying(50) NOT NULL,
    porcentaje numeric(5,2) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    fecha_vigencia date
);


--
-- Name: iva_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.iva_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: iva_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.iva_id_seq OWNED BY adq.iva.id;


--
-- Name: kardex; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.kardex (
    id integer NOT NULL,
    articulo_id integer NOT NULL,
    fecha timestamp without time zone NOT NULL,
    tipo_movimiento character varying(20) NOT NULL,
    referencia_tipo character varying(20) NOT NULL,
    referencia_id integer NOT NULL,
    referencia_det_id integer NOT NULL,
    numero_documento character varying(50),
    cantidad_entrada numeric(12,4) DEFAULT 0,
    cantidad_salida numeric(12,4) DEFAULT 0,
    stock_antes numeric(12,4) NOT NULL,
    stock_despues numeric(12,4) NOT NULL,
    precio_antes numeric(10,5) NOT NULL,
    precio_despues numeric(10,5) NOT NULL,
    precio_movimiento numeric(10,5) NOT NULL,
    subtotal numeric(12,2) DEFAULT 0 NOT NULL,
    iva_valor numeric(12,2) DEFAULT 0 NOT NULL,
    total_linea numeric(12,2) DEFAULT 0 NOT NULL,
    usuario character varying(20) NOT NULL,
    observacion text,
    created_at timestamp without time zone DEFAULT now(),
    valor_saldo numeric(14,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: kardex_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.kardex_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kardex_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.kardex_id_seq OWNED BY adq.kardex.id;


--
-- Name: orden_compra; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.orden_compra (
    id bigint NOT NULL,
    proveedor_id bigint,
    fecha date NOT NULL,
    estado character varying(20) DEFAULT 'BORRADOR'::character varying NOT NULL,
    observacion text,
    usuario_registro character varying(20) NOT NULL,
    usuario_recepcion character varying(20),
    fecha_recepcion timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tipo_ingreso character varying(20) DEFAULT 'COMPRA'::character varying NOT NULL,
    proceso_contratacion character varying(60),
    tipo_documento character varying(30),
    numero_documento character varying(50),
    fecha_documento date,
    subtotal numeric(12,2) DEFAULT 0 NOT NULL,
    iva_valor numeric(12,2) DEFAULT 0 NOT NULL,
    total numeric(12,2) DEFAULT 0 NOT NULL,
    numero_secuencial integer,
    anio integer,
    motivo_reverso text,
    usuario_reverso character varying(20),
    fecha_reverso timestamp without time zone,
    descuento numeric(12,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: orden_compra_det; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.orden_compra_det (
    id bigint NOT NULL,
    orden_id bigint NOT NULL,
    articulo_id bigint NOT NULL,
    cantidad numeric(12,2) NOT NULL,
    precio_unitario numeric(10,5) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    iva_id integer,
    iva_porcentaje numeric(5,2) DEFAULT 0 NOT NULL,
    subtotal numeric(12,2) DEFAULT 0 NOT NULL,
    iva_valor numeric(12,2) DEFAULT 0 NOT NULL,
    total_linea numeric(12,2) DEFAULT 0 NOT NULL,
    precio_anterior numeric(10,4)
);


--
-- Name: orden_compra_det_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.orden_compra_det_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: orden_compra_det_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.orden_compra_det_id_seq OWNED BY adq.orden_compra_det.id;


--
-- Name: orden_compra_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.orden_compra_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: orden_compra_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.orden_compra_id_seq OWNED BY adq.orden_compra.id;


--
-- Name: proceso_contratacion; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.proceso_contratacion (
    id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: proceso_contratacion_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.proceso_contratacion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proceso_contratacion_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.proceso_contratacion_id_seq OWNED BY adq.proceso_contratacion.id;


--
-- Name: proveedor; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.proveedor (
    id bigint NOT NULL,
    ruc character varying(20) NOT NULL,
    nombre character varying(200) NOT NULL,
    direccion character varying(300),
    contacto character varying(100),
    email character varying(100),
    telefono character varying(20),
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    es_proveedor_bienes boolean DEFAULT true NOT NULL,
    es_taller boolean DEFAULT false NOT NULL,
    orden_compra character varying(50)
);


--
-- Name: proveedor_catalogo; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.proveedor_catalogo (
    id bigint NOT NULL,
    proveedor_id bigint NOT NULL,
    descripcion character varying(300) NOT NULL,
    unidad_medida character varying(50),
    precio_referencial numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proveedor_catalogo_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.proveedor_catalogo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedor_catalogo_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.proveedor_catalogo_id_seq OWNED BY adq.proveedor_catalogo.id;


--
-- Name: proveedor_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.proveedor_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedor_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.proveedor_id_seq OWNED BY adq.proveedor.id;


--
-- Name: solicitud_material; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.solicitud_material (
    id bigint NOT NULL,
    id_emp character varying(10) NOT NULL,
    id_depto integer NOT NULL,
    fecha date NOT NULL,
    justificacion character varying(500),
    estado character varying(30) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    usuario_aprobacion character varying(20),
    fecha_aprobacion timestamp(0) without time zone,
    usuario_despacho character varying(20),
    fecha_despacho timestamp(0) without time zone,
    observacion_despacho text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    id_depto_beneficiario integer
);


--
-- Name: solicitud_material_det; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.solicitud_material_det (
    id bigint NOT NULL,
    solicitud_id bigint NOT NULL,
    articulo_id bigint NOT NULL,
    cantidad_solicitada numeric(12,2) NOT NULL,
    cantidad_autorizada numeric(12,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: solicitud_material_det_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.solicitud_material_det_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: solicitud_material_det_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.solicitud_material_det_id_seq OWNED BY adq.solicitud_material_det.id;


--
-- Name: solicitud_material_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.solicitud_material_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: solicitud_material_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.solicitud_material_id_seq OWNED BY adq.solicitud_material.id;


--
-- Name: unidad_medida; Type: TABLE; Schema: adq; Owner: -
--

CREATE TABLE adq.unidad_medida (
    id integer NOT NULL,
    nombre character varying(60) NOT NULL,
    abreviatura character varying(15) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now()
);


--
-- Name: unidad_medida_id_seq; Type: SEQUENCE; Schema: adq; Owner: -
--

CREATE SEQUENCE adq.unidad_medida_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: unidad_medida_id_seq; Type: SEQUENCE OWNED BY; Schema: adq; Owner: -
--

ALTER SEQUENCE adq.unidad_medida_id_seq OWNED BY adq.unidad_medida.id;


--
-- Name: acc_accion_personal; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.acc_accion_personal (
    id_accion integer NOT NULL,
    numero_accion character varying(20),
    tipo_accion character varying(30) NOT NULL,
    fecha_elaboracion date NOT NULL,
    id_emp character varying(20) NOT NULL,
    id_emp_titular character varying(20),
    fecha_inicio date NOT NULL,
    fecha_fin date,
    motivacion text,
    actual_cargo character varying(200),
    actual_grupo_ocup character varying(100),
    actual_grado integer,
    actual_remuneracion numeric(10,2),
    actual_partida character varying(60),
    actual_proceso_inst character varying(30),
    propuesto_cargo character varying(200),
    propuesto_grupo_ocup character varying(100),
    propuesto_grado integer,
    propuesto_remuneracion numeric(10,2),
    propuesto_partida character varying(60),
    propuesto_proceso_inst character varying(30),
    diferencial numeric(10,2) DEFAULT 0,
    estado character varying(20) DEFAULT 'ACTIVO'::character varying,
    creado_por character varying(20),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    pdf_firmado character varying(255),
    firmante_th_nombre character varying(200),
    firmante_th_cargo character varying(200),
    firmante_autoridad_nombre character varying(200),
    firmante_autoridad_cargo character varying(200),
    medio character varying(10) DEFAULT 'DIGITAL'::character varying,
    especificacion character varying(300)
);


--
-- Name: acc_accion_personal_id_accion_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.acc_accion_personal_id_accion_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: acc_accion_personal_id_accion_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.acc_accion_personal_id_accion_seq OWNED BY dbo.acc_accion_personal.id_accion;


--
-- Name: ad_departamento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_departamento (
    id_depto integer NOT NULL,
    nombre_depto character varying(120),
    transmitio character varying(2),
    centro_de_costo character varying(5),
    padre_id integer,
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20)
);


--
-- Name: TABLE ad_departamento; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.ad_departamento IS 'Departamentos / Áreas de la institución';


--
-- Name: ad_empleado; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_empleado (
    id_emp character varying(5) NOT NULL,
    identificacion character varying(15) NOT NULL,
    nombre_emp character varying(240),
    apellido_emp character varying(240),
    clave integer,
    id_depto integer NOT NULL,
    estado character varying(10),
    password character varying(255),
    foto character varying(500),
    administrador character varying(2),
    calle_y_numero character varying(50),
    telefono character varying(10),
    validacion character varying(10),
    factor numeric(5,2),
    jornada_id integer,
    fecha_ingreso timestamp without time zone,
    posicion integer,
    valida_huella character varying(2),
    tipo_contrato character varying(20),
    transmitio character varying(2),
    ubicacion character varying(120),
    gana_hora character varying(2),
    sueldo numeric(10,2),
    referencia character varying(5),
    fecha_salida timestamp without time zone,
    nivel integer,
    origen character varying(120),
    clave_marcar character varying(10),
    metodo_calculo integer,
    periodo_horas integer,
    autorizado character varying(2),
    campo_supervisor character varying(3),
    cargo_empleado character varying(250),
    id_localidad bigint,
    campo1 character varying(120),
    modalidad_laboral character varying(60),
    id_jornada integer,
    motivo_inactividad character varying(20),
    partida_individual integer,
    partida_presupuestaria character varying(60),
    estado_puesto character varying(20) DEFAULT 'OCUPADO'::character varying,
    grupo_ocupacional character varying(100),
    proceso_institucional character varying(30),
    acumula_fondos_reserva smallint DEFAULT 0,
    acumula_decimo_tercero boolean DEFAULT false,
    acumula_decimo_cuarto boolean DEFAULT false,
    modalidad_marcacion character varying(15) DEFAULT 'PRESENCIAL'::character varying,
    puede_solicitar_vehiculo boolean DEFAULT false NOT NULL,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20),
    programa character varying(4),
    actividad character varying(6),
    sexo character varying(10),
    tipo_sangre character varying(5),
    num_sercop character varying(50),
    fecha_vence_sercop date,
    grupo_vulnerable_id integer,
    grupo_prioritario_id integer,
    tiene_discapacidad boolean DEFAULT false NOT NULL,
    tipo_discapacidad_id integer,
    porcentaje_discapacidad smallint,
    tiene_enfermedad_catastrofica boolean DEFAULT false NOT NULL,
    enfermedad_catastrofica_id integer,
    tiene_persona_sustituta boolean DEFAULT false NOT NULL,
    sustituta_alfresco_id character varying(100),
    sustituta_nombre_archivo character varying(200),
    sustituta_fecha_caducidad date,
    num_hijos_mayores smallint DEFAULT 0 NOT NULL,
    motivo_salida character varying(50),
    motivo_reactivacion character varying(50),
    institucion_comision character varying(200),
    banco character varying(100),
    tipo_cuenta character varying(50),
    numero_cuenta character varying(50),
    es_externo boolean DEFAULT false NOT NULL,
    extension character varying(10),
    es_comisionado_entrante boolean DEFAULT false
);


--
-- Name: TABLE ad_empleado; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.ad_empleado IS 'Tabla principal de empleados';


--
-- Name: COLUMN ad_empleado.motivo_inactividad; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON COLUMN dbo.ad_empleado.motivo_inactividad IS 'DESVINCULACION | COMISION_SALIDA | COMISION_RETORNO | NUEVO_INGRESO';


--
-- Name: ad_empleado_hijo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_empleado_hijo (
    id integer NOT NULL,
    id_emp character varying(20) NOT NULL,
    nombre character varying(200),
    fecha_nacimiento date NOT NULL,
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: ad_empleado_hijo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ad_empleado_hijo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ad_empleado_hijo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ad_empleado_hijo_id_seq OWNED BY dbo.ad_empleado_hijo.id;


--
-- Name: ad_empleado_mail; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_empleado_mail (
    secuencial bigint NOT NULL,
    id_emp character varying(5) NOT NULL,
    mail character varying(120) NOT NULL,
    estado character varying(10) NOT NULL
);


--
-- Name: ad_empleado_mail_secuencial_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ad_empleado_mail_secuencial_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ad_empleado_mail_secuencial_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ad_empleado_mail_secuencial_seq OWNED BY dbo.ad_empleado_mail.secuencial;


--
-- Name: ad_empleado_teletrabajo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_empleado_teletrabajo (
    id bigint NOT NULL,
    id_emp character varying(20) NOT NULL,
    fecha_desde date NOT NULL,
    fecha_hasta date NOT NULL,
    created_by character varying(20),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: ad_empleado_teletrabajo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ad_empleado_teletrabajo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ad_empleado_teletrabajo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ad_empleado_teletrabajo_id_seq OWNED BY dbo.ad_empleado_teletrabajo.id;


--
-- Name: ad_enfermedad_catastrofica; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_enfermedad_catastrofica (
    id integer NOT NULL,
    nombre character varying(200) NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: ad_enfermedad_catastrofica_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ad_enfermedad_catastrofica_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ad_enfermedad_catastrofica_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ad_enfermedad_catastrofica_id_seq OWNED BY dbo.ad_enfermedad_catastrofica.id;


--
-- Name: ad_grupo_prioritario; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_grupo_prioritario (
    id integer NOT NULL,
    nombre character varying(150) NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: ad_grupo_prioritario_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ad_grupo_prioritario_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ad_grupo_prioritario_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ad_grupo_prioritario_id_seq OWNED BY dbo.ad_grupo_prioritario.id;


--
-- Name: ad_grupo_vulnerable; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_grupo_vulnerable (
    id integer NOT NULL,
    nombre character varying(150) NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: ad_grupo_vulnerable_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ad_grupo_vulnerable_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ad_grupo_vulnerable_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ad_grupo_vulnerable_id_seq OWNED BY dbo.ad_grupo_vulnerable.id;


--
-- Name: ad_tipo_discapacidad; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ad_tipo_discapacidad (
    id integer NOT NULL,
    nombre character varying(150) NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: ad_tipo_discapacidad_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ad_tipo_discapacidad_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ad_tipo_discapacidad_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ad_tipo_discapacidad_id_seq OWNED BY dbo.ad_tipo_discapacidad.id;


--
-- Name: admin_opcion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.admin_opcion (
    id character varying(10) NOT NULL,
    descripcion character varying(50) NOT NULL,
    url character varying(50) NOT NULL,
    categoria character varying(20) NOT NULL,
    orden_categoria integer NOT NULL,
    secuencia integer NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    padre character varying(10)
);


--
-- Name: TABLE admin_opcion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.admin_opcion IS 'Opciones/menús del sistema';


--
-- Name: admin_rol; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.admin_rol (
    id integer NOT NULL,
    descripcion character varying(50) NOT NULL,
    estado boolean DEFAULT true NOT NULL
);


--
-- Name: TABLE admin_rol; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.admin_rol IS 'Roles del sistema';


--
-- Name: admin_rol_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.admin_rol_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: admin_rol_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.admin_rol_id_seq OWNED BY dbo.admin_rol.id;


--
-- Name: admin_rol_opcion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.admin_rol_opcion (
    id integer NOT NULL,
    id_rol integer NOT NULL,
    id_opcion character varying(10) NOT NULL
);


--
-- Name: TABLE admin_rol_opcion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.admin_rol_opcion IS 'Relación Rol - Opción';


--
-- Name: admin_rol_opcion_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.admin_rol_opcion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: admin_rol_opcion_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.admin_rol_opcion_id_seq OWNED BY dbo.admin_rol_opcion.id;


--
-- Name: admin_usuario_rol; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.admin_usuario_rol (
    id_emp character varying(5) NOT NULL,
    identificacion character varying(20) NOT NULL,
    id_rol integer NOT NULL
);


--
-- Name: TABLE admin_usuario_rol; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.admin_usuario_rol IS 'Asignación de roles a usuarios';


--
-- Name: cache; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: com_anticipo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_anticipo (
    id bigint NOT NULL,
    solicitud_id bigint NOT NULL,
    monto_solicitado numeric(10,2) DEFAULT 0 NOT NULL,
    estado character varying(30) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    cur_compromiso character varying(50),
    cur_devengado character varying(50),
    fecha_pago date,
    observacion text,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20)
);


--
-- Name: com_anticipo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_anticipo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_anticipo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_anticipo_id_seq OWNED BY dbo.com_anticipo.id;


--
-- Name: com_ciudad; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_ciudad (
    id integer NOT NULL,
    provincia_id integer NOT NULL,
    nombre character varying(100) NOT NULL
);


--
-- Name: com_ciudad_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_ciudad_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_ciudad_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_ciudad_id_seq OWNED BY dbo.com_ciudad.id;


--
-- Name: com_coeficiente_pais; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_coeficiente_pais (
    id bigint NOT NULL,
    pais character varying(100) NOT NULL,
    region character varying(50) NOT NULL,
    coeficiente numeric(6,4) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: com_coeficiente_pais_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_coeficiente_pais_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_coeficiente_pais_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_coeficiente_pais_id_seq OWNED BY dbo.com_coeficiente_pais.id;


--
-- Name: com_ficha_liquidacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_ficha_liquidacion (
    id bigint NOT NULL,
    solicitud_id bigint NOT NULL,
    valor_por_dia numeric(10,2) DEFAULT 0 NOT NULL,
    dias_viaticos integer DEFAULT 0 NOT NULL,
    total_viatico numeric(10,2) DEFAULT 0 NOT NULL,
    anticipo_viatico numeric(10,2) DEFAULT 0 NOT NULL,
    anticipo_combustible numeric(10,2),
    total_anticipo numeric(10,2) DEFAULT 0 NOT NULL,
    justif_alimentacion numeric(10,2) DEFAULT 0 NOT NULL,
    justif_alojamiento numeric(10,2) DEFAULT 0 NOT NULL,
    total_justificacion numeric(10,2) DEFAULT 0 NOT NULL,
    movilizacion numeric(10,2) DEFAULT 0 NOT NULL,
    peajes_parqueaderos numeric(10,2) DEFAULT 0 NOT NULL,
    combustibles numeric(10,2),
    otros_gastos numeric(10,2) DEFAULT 0 NOT NULL,
    viaticos_por_pagar numeric(10,2) DEFAULT 0 NOT NULL,
    devolucion_movilizacion numeric(10,2) DEFAULT 0 NOT NULL,
    total_a_pagar numeric(10,2) DEFAULT 0 NOT NULL,
    tipo_resultado character varying(15),
    cur_compromiso character varying(50),
    cur_devengado character varying(50),
    comprobante_devolucion character varying(100),
    fecha_devolucion date,
    estado character varying(30) DEFAULT 'BORRADOR'::character varying NOT NULL,
    observacion text,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20),
    pais_destino character varying(100),
    coeficiente_pais numeric(6,4)
);


--
-- Name: com_ficha_liquidacion_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_ficha_liquidacion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_ficha_liquidacion_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_ficha_liquidacion_id_seq OWNED BY dbo.com_ficha_liquidacion.id;


--
-- Name: com_funcionario_externo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_funcionario_externo (
    id bigint NOT NULL,
    cedula character varying(20) NOT NULL,
    nombres character varying(200) NOT NULL,
    cargo character varying(200) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    banco character varying(100),
    tipo_cuenta character varying(50),
    numero_cuenta character varying(50),
    programa character varying(4),
    actividad character varying(6)
);


--
-- Name: com_funcionario_externo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_funcionario_externo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_funcionario_externo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_funcionario_externo_id_seq OWNED BY dbo.com_funcionario_externo.id;


--
-- Name: com_informe; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_informe (
    id bigint NOT NULL,
    solicitud_id bigint NOT NULL,
    fecha_informe date NOT NULL,
    actividades text,
    productos text,
    fecha_salida date,
    hora_salida time without time zone,
    fecha_llegada date,
    hora_llegada time without time zone,
    estado character varying(30) DEFAULT 'BORRADOR'::character varying NOT NULL,
    observacion text,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20),
    pdf_firmado_id character varying(100),
    pdf_firmado_nombre character varying(200),
    destino character varying(200)
);


--
-- Name: com_informe_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_informe_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_informe_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_informe_id_seq OWNED BY dbo.com_informe.id;


--
-- Name: com_informe_transporte; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_informe_transporte (
    id bigint NOT NULL,
    informe_id bigint NOT NULL,
    tipo character varying(50),
    nombre character varying(200),
    ruta character varying(300),
    salida_fecha date,
    salida_hora time without time zone,
    llegada_fecha date,
    llegada_hora time without time zone,
    orden integer DEFAULT 1 NOT NULL
);


--
-- Name: com_informe_transporte_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_informe_transporte_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_informe_transporte_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_informe_transporte_id_seq OWNED BY dbo.com_informe_transporte.id;


--
-- Name: com_provincia; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_provincia (
    id integer NOT NULL,
    nombre character varying(100) NOT NULL
);


--
-- Name: com_provincia_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_provincia_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_provincia_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_provincia_id_seq OWNED BY dbo.com_provincia.id;


--
-- Name: com_solicitud; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_solicitud (
    id bigint NOT NULL,
    numero_solicitud character varying(50),
    tipo character varying(10) NOT NULL,
    id_emp character varying(20) NOT NULL,
    id_depto integer NOT NULL,
    fecha_solicitud date NOT NULL,
    tiene_viaticos boolean DEFAULT true NOT NULL,
    tiene_movilizaciones boolean DEFAULT false NOT NULL,
    tiene_anticipo boolean DEFAULT false NOT NULL,
    destino character varying(200) NOT NULL,
    unidad_nombre character varying(200),
    fecha_salida date NOT NULL,
    hora_salida time without time zone NOT NULL,
    fecha_llegada date NOT NULL,
    hora_llegada time without time zone NOT NULL,
    descripcion_actividades text NOT NULL,
    banco character varying(100),
    tipo_cuenta character varying(50),
    numero_cuenta character varying(50),
    estado character varying(40) DEFAULT 'BORRADOR'::character varying NOT NULL,
    observacion text,
    num_sistema_exterior character varying(100),
    resolucion_juridica character varying(100),
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20),
    observacion_devolucion character varying(500)
);


--
-- Name: com_solicitud_documento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_solicitud_documento (
    id bigint NOT NULL,
    solicitud_id bigint NOT NULL,
    tipo_doc character varying(30) NOT NULL,
    alfresco_id character varying(100),
    nombre_archivo character varying(200),
    created_by character varying(20),
    created_at timestamp(0) without time zone
);


--
-- Name: com_solicitud_documento_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_solicitud_documento_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_solicitud_documento_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_solicitud_documento_id_seq OWNED BY dbo.com_solicitud_documento.id;


--
-- Name: com_solicitud_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_solicitud_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_solicitud_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_solicitud_id_seq OWNED BY dbo.com_solicitud.id;


--
-- Name: com_solicitud_servidor; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_solicitud_servidor (
    id bigint NOT NULL,
    solicitud_id bigint NOT NULL,
    id_emp character varying(20) NOT NULL,
    unidad character varying(200),
    puesto character varying(200),
    orden integer DEFAULT 1 NOT NULL,
    banco character varying(100),
    tipo_cuenta character varying(50),
    numero_cuenta character varying(50)
);


--
-- Name: com_solicitud_servidor_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_solicitud_servidor_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_solicitud_servidor_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_solicitud_servidor_id_seq OWNED BY dbo.com_solicitud_servidor.id;


--
-- Name: com_solicitud_transporte; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_solicitud_transporte (
    id bigint NOT NULL,
    solicitud_id bigint NOT NULL,
    tipo character varying(50),
    nombre character varying(200),
    ruta character varying(300),
    salida_fecha date,
    salida_hora time without time zone,
    llegada_fecha date,
    llegada_hora time without time zone,
    orden integer DEFAULT 1 NOT NULL
);


--
-- Name: com_solicitud_transporte_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_solicitud_transporte_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_solicitud_transporte_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_solicitud_transporte_id_seq OWNED BY dbo.com_solicitud_transporte.id;


--
-- Name: com_tarifa_viatico; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.com_tarifa_viatico (
    id bigint NOT NULL,
    descripcion character varying(200) NOT NULL,
    valor_dia numeric(10,2) NOT NULL,
    tipo character varying(10) DEFAULT 'AMBOS'::character varying NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    aplica_jerarquico boolean DEFAULT false NOT NULL
);


--
-- Name: com_tarifa_viatico_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.com_tarifa_viatico_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: com_tarifa_viatico_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.com_tarifa_viatico_id_seq OWNED BY dbo.com_tarifa_viatico.id;


--
-- Name: d2_aportes_iess; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_aportes_iess (
    id_aporte integer NOT NULL,
    modalidad character varying(30) NOT NULL,
    aporte_individual numeric(5,2) NOT NULL,
    aporte_patronal numeric(5,2) NOT NULL,
    fecha_desde date NOT NULL,
    fecha_hasta date,
    created_at timestamp without time zone DEFAULT now(),
    iece_patronal numeric(5,2) DEFAULT 0 NOT NULL,
    iece_personal numeric(5,2) DEFAULT 0 NOT NULL,
    secap_patronal numeric(5,2) DEFAULT 0 NOT NULL,
    secap_personal numeric(5,2) DEFAULT 0 NOT NULL,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20)
);


--
-- Name: d2_aportes_iess_id_aporte_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_aportes_iess_id_aporte_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_aportes_iess_id_aporte_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_aportes_iess_id_aporte_seq OWNED BY dbo.d2_aportes_iess.id_aporte;


--
-- Name: d2_auditoria; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_auditoria (
    secuencial integer NOT NULL,
    fecha_hora timestamp without time zone NOT NULL,
    usuario integer NOT NULL,
    concepto character varying(241),
    id_emp character varying(5),
    ip character varying(30) NOT NULL
);


--
-- Name: TABLE d2_auditoria; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_auditoria IS 'Log de auditoría de acciones en el sistema';


--
-- Name: d2_auditoria_secuencial_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_auditoria_secuencial_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_auditoria_secuencial_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_auditoria_secuencial_seq OWNED BY dbo.d2_auditoria.secuencial;


--
-- Name: d2_aviso_ticker; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_aviso_ticker (
    id integer NOT NULL,
    texto text NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    orden smallint DEFAULT 0 NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


--
-- Name: d2_aviso_ticker_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_aviso_ticker_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_aviso_ticker_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_aviso_ticker_id_seq OWNED BY dbo.d2_aviso_ticker.id;


--
-- Name: d2_cab_prog; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_cab_prog (
    id integer NOT NULL,
    descripcion character varying(50) NOT NULL
);


--
-- Name: TABLE d2_cab_prog; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_cab_prog IS 'Cabecera de programación de turnos';


--
-- Name: d2_cab_turno; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_cab_turno (
    id_turno integer NOT NULL,
    descripcion character varying(30) NOT NULL,
    color character varying(20),
    transmitio character varying(2),
    horas_normales numeric(5,2),
    horas_25 numeric(5,2),
    origen character varying(120)
);


--
-- Name: TABLE d2_cab_turno; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_cab_turno IS 'Catálogo de turnos';


--
-- Name: d2_cabecera_vacacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_cabecera_vacacion (
    id_emp character varying(5) NOT NULL,
    dias_adicionales numeric(5,2) NOT NULL,
    fecha_proceso timestamp without time zone,
    total_dias_tomados numeric(6,2),
    total_fin_semana numeric(6,2),
    total_tomados numeric(6,2),
    dias_x_tomar_normal numeric(6,2),
    dias_x_tomar_fin_semana numeric(6,2),
    dias_totales numeric(6,2),
    venta_normal numeric(6,2),
    venta_adicional numeric(6,2)
);


--
-- Name: TABLE d2_cabecera_vacacion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_cabecera_vacacion IS 'Resumen de saldo de vacaciones por empleado';


--
-- Name: d2_certificado_laboral; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_certificado_laboral (
    id bigint NOT NULL,
    numero character varying(25) NOT NULL,
    id_emp character varying(20) NOT NULL,
    fecha_emision date NOT NULL,
    alfresco_id character varying(100),
    nombre_archivo character varying(200),
    usuario_emision character varying(20) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    estado character varying(20) DEFAULT 'EMITIDO'::character varying NOT NULL,
    observacion_anulacion character varying(300),
    anulado_en timestamp without time zone,
    anulado_por character varying(20)
);


--
-- Name: d2_certificado_laboral_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_certificado_laboral_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_certificado_laboral_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_certificado_laboral_id_seq OWNED BY dbo.d2_certificado_laboral.id;


--
-- Name: d2_configuracion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_configuracion (
    concepto character varying(120) NOT NULL,
    valor character varying(150) NOT NULL,
    descripcion text,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20)
);


--
-- Name: TABLE d2_configuracion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_configuracion IS 'Parámetros de configuración global del sistema';


--
-- Name: d2_cuadre_marcacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_cuadre_marcacion (
    id_emp character varying(5) NOT NULL,
    identificacion character varying(15),
    fecha timestamp without time zone NOT NULL,
    apellido character varying(70),
    nombre character varying(70),
    hora_turno_entrada numeric(5,2),
    hora_real_entrada numeric(5,2),
    atraso_entrada numeric(5,2),
    hora_turno_sal_lunch numeric(5,2),
    hora_real_sal_lunch numeric(5,2),
    hora_turno_ent_lunch numeric(5,2),
    hora_real_ent_lunch numeric(5,2),
    atraso_lunch numeric(5,2),
    hora_turno_sal numeric(5,2),
    hora_real_sal numeric(5,2),
    atraso_salida numeric(5,2),
    horas_totales numeric(5,2),
    horas_decto numeric(5,2),
    totalusd numeric(5,2),
    factor numeric(5,2),
    horaspermiso numeric(5,2),
    horaspermiso_pag numeric(5,2),
    horas_adicionales numeric(5,2),
    horas_adicionales_no numeric(5,2),
    ip character varying(20) NOT NULL,
    falta character varying(2),
    extra0 numeric(5,2),
    extra25 numeric(5,2),
    extra50 numeric(5,2),
    extra100 numeric(5,2),
    extra150 numeric(5,2),
    permiso25 numeric(5,2),
    permiso50 numeric(5,2),
    permiso100 numeric(5,2),
    permiso150 numeric(5,2),
    area character varying(120),
    jornada integer,
    motivo_entrada character varying(120),
    motivo_lunch character varying(120),
    motivo_salida character varying(120),
    horas_autorizadas numeric(5,2),
    tiempo_lunch integer,
    horas_permiso_entrada numeric(5,2),
    horas_permiso_salida numeric(5,2),
    horas_real_entrada numeric(5,2),
    horas_real_salida numeric(5,2),
    horas_permiso_real numeric(5,2),
    cambio_turno character varying(1),
    centro_costo_cuadre character varying(120),
    t1 integer,
    e1 numeric(5,2),
    s1 numeric(5,2),
    t2 integer,
    e2 numeric(5,2),
    s2 numeric(5,2),
    t3 integer,
    e3 numeric(5,2),
    s3 numeric(5,2),
    d1 numeric(5,2),
    d2 numeric(5,2),
    d3 numeric(5,2),
    mail character varying(2),
    d0 numeric(5,2),
    t0 integer
);


--
-- Name: TABLE d2_cuadre_marcacion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_cuadre_marcacion IS 'Cuadre diario de asistencia por empleado';


--
-- Name: d2_detalle_vacacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_detalle_vacacion (
    secuencial integer NOT NULL,
    id_emp character varying(5) NOT NULL,
    numero_periodo integer NOT NULL,
    periodo character varying(20) NOT NULL,
    observaciones character varying(120) NOT NULL,
    dias_por_tomar numeric(5,2) NOT NULL,
    tomados_normal numeric(6,2),
    tomados_fin_semana numeric(6,2),
    disponible_normal numeric(6,2),
    disponible_fin_semana numeric(6,2),
    acumulado numeric(5,2) NOT NULL
);


--
-- Name: TABLE d2_detalle_vacacion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_detalle_vacacion IS 'Detalle de vacaciones por período';


--
-- Name: d2_detalle_vacacion_secuencial_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_detalle_vacacion_secuencial_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_detalle_vacacion_secuencial_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_detalle_vacacion_secuencial_seq OWNED BY dbo.d2_detalle_vacacion.secuencial;


--
-- Name: d2_jornada; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_jornada (
    id_jornada integer NOT NULL,
    descripcion character varying(50) NOT NULL,
    jornada_ordinaria_maxima integer,
    recargo numeric(5,2),
    normal integer,
    porc_25 integer,
    porc_extraordinaria numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    porc_suplementaria numeric(5,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: TABLE d2_jornada; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_jornada IS 'Tipos de jornada laboral';


--
-- Name: d2_lista_fecha; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_lista_fecha (
    fecha timestamp without time zone NOT NULL,
    factor numeric(5,2) NOT NULL,
    tipo character varying(10) NOT NULL,
    color character varying(10) NOT NULL,
    hora_desde timestamp without time zone NOT NULL,
    hora_hasta timestamp without time zone NOT NULL,
    ubicacion character varying(120) NOT NULL,
    hora25 integer,
    transmitio character varying(2),
    hora_25 numeric(5,2)
);


--
-- Name: TABLE d2_lista_fecha; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_lista_fecha IS 'Calendario laboral: feriados, fines de semana, etc.';


--
-- Name: d2_modalidad_laboral; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_modalidad_laboral (
    id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    orden smallint DEFAULT 0 NOT NULL,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20),
    codigo character varying(40)
);


--
-- Name: d2_modalidad_laboral_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_modalidad_laboral_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_modalidad_laboral_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_modalidad_laboral_id_seq OWNED BY dbo.d2_modalidad_laboral.id;


--
-- Name: d2_permiso; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_permiso (
    fecha_hora timestamp without time zone NOT NULL,
    id_emp character varying(5) NOT NULL,
    razon character varying(100) NOT NULL,
    fecha_desde timestamp without time zone NOT NULL,
    fecha_hasta timestamp without time zone NOT NULL,
    terminal character varying(20),
    hora_desde timestamp without time zone NOT NULL,
    hora_hasta timestamp without time zone NOT NULL,
    usuario character varying(5) NOT NULL,
    cargo integer NOT NULL,
    dias_pendientes integer,
    dias_totales integer,
    dias_por_gozar integer,
    dias_adicionales integer,
    dias_tomados integer,
    dias_a_tomar integer,
    disminuir_dias integer,
    secuencial integer,
    observaciones character varying(250),
    todo_dia character varying(2),
    concepto character varying(20),
    sec_permiso integer,
    procedencia integer,
    estado_permiso character varying(10),
    observacion_negacion character varying(120),
    transmitio character varying(2),
    origen character varying(120),
    centro_costo_cuadre character varying(120),
    sec_migrar integer,
    sec_migracion integer,
    s1 integer,
    s2 integer,
    s3 integer,
    s4 integer,
    s5 integer,
    s6 integer,
    s7 integer,
    s8 integer,
    s9 integer,
    s10 integer,
    s11 integer,
    s12 integer,
    s13 integer,
    s14 integer,
    s15 integer,
    s16 integer,
    s17 integer,
    s18 integer,
    s19 integer,
    s20 integer,
    s21 integer,
    s22 integer,
    s23 integer,
    s24 integer,
    s25 integer,
    s26 integer,
    s27 integer,
    s28 integer,
    s29 integer,
    s30 integer,
    principal integer,
    secuencial_clave integer NOT NULL,
    descontable character varying(2),
    tipo_horario character varying(20),
    aprobado_en timestamp without time zone,
    dias_descuento_efectivo numeric(10,4)
);


--
-- Name: TABLE d2_permiso; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_permiso IS 'Permisos y justificaciones del personal';


--
-- Name: d2_permiso_documento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_permiso_documento (
    id integer NOT NULL,
    permiso_id integer NOT NULL,
    tipo_doc character varying(60) NOT NULL,
    nombre_archivo character varying(200) NOT NULL,
    alfresco_id character varying(200) NOT NULL,
    created_by character varying(50),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: d2_permiso_documento_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_permiso_documento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_permiso_documento_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_permiso_documento_id_seq OWNED BY dbo.d2_permiso_documento.id;


--
-- Name: d2_permiso_secuencial_clave_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_permiso_secuencial_clave_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_permiso_secuencial_clave_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_permiso_secuencial_clave_seq OWNED BY dbo.d2_permiso.secuencial_clave;


--
-- Name: d2_programacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_programacion (
    secuencial integer NOT NULL,
    id integer NOT NULL,
    fecha timestamp without time zone NOT NULL,
    id_turno integer NOT NULL,
    id_emp character varying(5),
    horas_extras integer,
    tipo_fecha character varying(120),
    sancion_lunch integer,
    desayuno character varying(2),
    almuerzo character varying(2),
    merienda character varying(2),
    cena character varying(2),
    usuario_resp integer,
    fecha_hora_resp timestamp without time zone,
    descanso character varying(2),
    transmitio character varying(2),
    id_ubicacion integer,
    id_ruta integer,
    origen character varying(120),
    estado_programacion character varying(10),
    s1 integer,
    s2 integer,
    s3 integer,
    s4 integer,
    s5 integer,
    s6 integer,
    s7 integer,
    s8 integer,
    s9 integer,
    s10 integer,
    s11 integer,
    s12 integer,
    s13 integer,
    s14 integer,
    s15 integer,
    s16 integer,
    s17 integer,
    s18 integer,
    s19 integer,
    s20 integer,
    s21 integer,
    s22 integer,
    s23 integer,
    s24 integer,
    s25 integer,
    s26 integer,
    s27 integer,
    s28 integer,
    s29 integer,
    s30 integer
);


--
-- Name: TABLE d2_programacion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_programacion IS 'Programación de turnos por empleado y fecha';


--
-- Name: d2_programacion_secuencial_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_programacion_secuencial_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_programacion_secuencial_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_programacion_secuencial_seq OWNED BY dbo.d2_programacion.secuencial;


--
-- Name: d2_razon; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_razon (
    secuencial integer NOT NULL,
    descripcion character varying(100) NOT NULL,
    descontable character varying(2) NOT NULL,
    tipo_razon character varying(20),
    nomina character varying(2),
    tipo_certificado character varying(2),
    nomenclatura character varying(10),
    leyenda_justificacion character varying(250),
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20)
);


--
-- Name: TABLE d2_razon; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_razon IS 'Razones/motivos de permisos';


--
-- Name: d2_turno; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_turno (
    id_turno integer NOT NULL,
    concepto character varying(30) NOT NULL,
    hora timestamp without time zone NOT NULL,
    id_jornada integer,
    transmitio character varying(2),
    origen character varying(120)
);


--
-- Name: TABLE d2_turno; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_turno IS 'Detalle de horarios por turno (ENTRADA, SALIDA, LUNCH)';


--
-- Name: d2_vacacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_vacacion (
    id_emp character varying(5) NOT NULL,
    fecha_hora timestamp without time zone,
    nombre_emp character varying(240) NOT NULL,
    fecha_inicial timestamp without time zone NOT NULL,
    fecha_final timestamp without time zone NOT NULL,
    hora_desde timestamp without time zone NOT NULL,
    hora_hasta timestamp without time zone NOT NULL,
    observaciones character varying(250),
    todo_dia character varying(2),
    estado_permiso character varying(10),
    ip character varying(30),
    secuencial_clave integer NOT NULL,
    observacion_negacion character varying(120),
    aprobado_en timestamp without time zone,
    aprobado_por character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20),
    backup_id character varying(20),
    backup_nombre character varying(300),
    requiere_informe boolean DEFAULT false NOT NULL,
    informe_estado character varying(20),
    informe_fecha date,
    informe_por character varying(20)
);


--
-- Name: TABLE d2_vacacion; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.d2_vacacion IS 'Solicitudes de vacaciones';


--
-- Name: d2_vacacion_secuencial_clave_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_vacacion_secuencial_clave_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_vacacion_secuencial_clave_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_vacacion_secuencial_clave_seq OWNED BY dbo.d2_vacacion.secuencial_clave;


--
-- Name: d2_zkteco_dispositivo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.d2_zkteco_dispositivo (
    id integer NOT NULL,
    serial character varying(50) NOT NULL,
    nombre character varying(100),
    ip character varying(45),
    ultimo_push timestamp(0) without time zone,
    activo boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: d2_zkteco_dispositivo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.d2_zkteco_dispositivo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: d2_zkteco_dispositivo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.d2_zkteco_dispositivo_id_seq OWNED BY dbo.d2_zkteco_dispositivo.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.failed_jobs_id_seq OWNED BY dbo.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.jobs_id_seq OWNED BY dbo.jobs.id;


--
-- Name: nom_auditoria_log; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_auditoria_log (
    id bigint NOT NULL,
    tabla character varying(60) NOT NULL,
    registro_id integer NOT NULL,
    accion character varying(40) NOT NULL,
    datos_anteriores jsonb,
    datos_nuevos jsonb,
    usuario_id character varying(20) NOT NULL,
    nombre_usuario character varying(150) NOT NULL,
    ip_origen character varying(45),
    descripcion character varying(200),
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: nom_auditoria_log_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_auditoria_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_auditoria_log_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_auditoria_log_id_seq OWNED BY dbo.nom_auditoria_log.id;


--
-- Name: nom_decimo_cuarto; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_decimo_cuarto (
    id bigint NOT NULL,
    anio smallint NOT NULL,
    mes smallint NOT NULL,
    id_emp character varying(20) NOT NULL,
    sbu numeric(10,2) NOT NULL,
    dias smallint NOT NULL,
    valor numeric(10,2) NOT NULL,
    estado character varying(10) DEFAULT 'BORRADOR'::character varying NOT NULL,
    creado_por character varying(20) NOT NULL,
    fecha_calculo timestamp(0) without time zone NOT NULL,
    cerrado_por character varying(20),
    fecha_cierre timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nom_decimo_cuarto_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_decimo_cuarto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_decimo_cuarto_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_decimo_cuarto_id_seq OWNED BY dbo.nom_decimo_cuarto.id;


--
-- Name: nom_decimo_tercero; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_decimo_tercero (
    id bigint NOT NULL,
    anio smallint NOT NULL,
    mes smallint NOT NULL,
    id_emp character varying(20) NOT NULL,
    sueldo_base numeric(10,2) NOT NULL,
    dias smallint NOT NULL,
    valor numeric(10,2) NOT NULL,
    estado character varying(10) DEFAULT 'BORRADOR'::character varying NOT NULL,
    creado_por character varying(20) NOT NULL,
    fecha_calculo timestamp(0) without time zone NOT NULL,
    cerrado_por character varying(20),
    fecha_cierre timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nom_decimo_tercero_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_decimo_tercero_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_decimo_tercero_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_decimo_tercero_id_seq OWNED BY dbo.nom_decimo_tercero.id;


--
-- Name: nom_fondos_reserva; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_fondos_reserva (
    id bigint NOT NULL,
    anio smallint NOT NULL,
    mes smallint NOT NULL,
    id_emp character varying(20) NOT NULL,
    sueldo_base numeric(10,2) NOT NULL,
    dias smallint NOT NULL,
    porcentaje numeric(5,2) DEFAULT 8.33 NOT NULL,
    valor numeric(10,2) NOT NULL,
    tipo character varying(10) NOT NULL,
    estado character varying(10) DEFAULT 'BORRADOR'::character varying NOT NULL,
    creado_por character varying(20) NOT NULL,
    fecha_calculo timestamp(0) without time zone NOT NULL,
    cerrado_por character varying(20),
    fecha_cierre timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nom_fondos_reserva_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_fondos_reserva_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_fondos_reserva_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_fondos_reserva_id_seq OWNED BY dbo.nom_fondos_reserva.id;


--
-- Name: nom_he_planificacion_cab; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_he_planificacion_cab (
    id bigint NOT NULL,
    id_emp character varying(10) NOT NULL,
    anio integer NOT NULL,
    mes integer NOT NULL,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    total_extraordinarias numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    total_suplementarias numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    observacion character varying(250),
    usuario_registro character varying(20),
    fecha_registro timestamp(0) without time zone,
    usuario_decision character varying(20),
    fecha_decision timestamp(0) without time zone,
    pdf_aprobado character varying(100),
    memorando character varying(300),
    usuario_autorizacion character varying(20),
    fecha_autorizacion timestamp(0) without time zone
);


--
-- Name: nom_he_planificacion_cab_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_he_planificacion_cab_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_he_planificacion_cab_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_he_planificacion_cab_id_seq OWNED BY dbo.nom_he_planificacion_cab.id;


--
-- Name: nom_he_planificacion_det; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_he_planificacion_det (
    id bigint NOT NULL,
    cab_id bigint NOT NULL,
    actividad character varying(300) NOT NULL,
    horas_extraordinarias numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    horas_suplementarias numeric(8,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: nom_he_planificacion_det_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_he_planificacion_det_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_he_planificacion_det_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_he_planificacion_det_id_seq OWNED BY dbo.nom_he_planificacion_det.id;


--
-- Name: nom_he_registro; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_he_registro (
    id bigint NOT NULL,
    cab_id bigint NOT NULL,
    id_emp character varying(10) NOT NULL,
    fecha date NOT NULL,
    horas_extraordinarias numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    horas_suplementarias numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    descripcion character varying(300),
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    usuario_decision character varying(20),
    fecha_decision timestamp(0) without time zone,
    observacion character varying(250),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    hora_inicio character varying(5),
    hora_fin character varying(5),
    devuelto_count smallint DEFAULT 0 NOT NULL
);


--
-- Name: nom_he_registro_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_he_registro_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_he_registro_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_he_registro_id_seq OWNED BY dbo.nom_he_registro.id;


--
-- Name: nom_rol_pago_cab; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_rol_pago_cab (
    id bigint NOT NULL,
    anio smallint NOT NULL,
    mes smallint NOT NULL,
    estado character varying(10) DEFAULT 'BORRADOR'::character varying NOT NULL,
    total_empleados integer DEFAULT 0 NOT NULL,
    total_bruto numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_patronal numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_descuentos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_liquido numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    creado_por character varying(20) NOT NULL,
    fecha_calculo timestamp(0) without time zone NOT NULL,
    cerrado_por character varying(20),
    fecha_cierre timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nom_rol_pago_cab_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_rol_pago_cab_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_rol_pago_cab_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_rol_pago_cab_id_seq OWNED BY dbo.nom_rol_pago_cab.id;


--
-- Name: nom_rol_pago_det; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_rol_pago_det (
    id bigint NOT NULL,
    cab_id bigint NOT NULL,
    id_emp character varying(20) NOT NULL,
    tipo_contrato character varying(30),
    rmu_puesto numeric(10,2) NOT NULL,
    dias smallint NOT NULL,
    valor_rmu numeric(10,2) NOT NULL,
    aporte_patronal_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    aporte_patronal numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    aporte_personal_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    aporte_personal numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    quirografario numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    hipotecario numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    impuesto_renta numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    supa numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_descuentos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    liquido numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    iece_pct numeric(5,2) DEFAULT 0 NOT NULL,
    iece numeric(10,2) DEFAULT 0 NOT NULL,
    secap_pct numeric(5,2) DEFAULT 0 NOT NULL,
    secap numeric(10,2) DEFAULT 0 NOT NULL,
    programa character varying(4),
    actividad character varying(6),
    poliza_blanket numeric(10,2) DEFAULT 0 NOT NULL,
    otros_descuentos numeric(10,2) DEFAULT 0 NOT NULL,
    observaciones character varying(300),
    sanciones numeric(10,2) DEFAULT 0 NOT NULL
);


--
-- Name: nom_rol_pago_det_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_rol_pago_det_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_rol_pago_det_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_rol_pago_det_id_seq OWNED BY dbo.nom_rol_pago_det.id;


--
-- Name: nom_sbu_historico; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.nom_sbu_historico (
    id bigint NOT NULL,
    anio smallint NOT NULL,
    valor numeric(10,2) NOT NULL,
    usuario_registro character varying(20) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nom_sbu_historico_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.nom_sbu_historico_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nom_sbu_historico_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.nom_sbu_historico_id_seq OWNED BY dbo.nom_sbu_historico.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: sessions; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: sg_control_persona; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.sg_control_persona (
    secuencial integer NOT NULL,
    identificador integer NOT NULL,
    clasificacion character varying(20) NOT NULL,
    nro_documento character varying(5),
    lugar character varying(40) NOT NULL,
    fecha_hora timestamp without time zone,
    concepto character varying(20),
    motivo character varying(120),
    transmitio character varying(2),
    penalizado integer,
    tipo_marcacion character varying(10),
    sec_permiso integer,
    ip character varying(30),
    ubicacion character varying(120),
    cantidad numeric(5,2),
    costo numeric(5,2),
    id_ubicacion integer,
    id_ruta integer,
    criterio_calculo integer,
    hora_turno numeric(5,2),
    hora_salida_lunch timestamp without time zone,
    hora_regreso_lunch timestamp without time zone,
    centro_costo character varying(20),
    bandera_centro_costo integer,
    tiempo_lunch numeric(5,2),
    funcion character varying(2),
    fecha_especial timestamp without time zone,
    clasificacion_especial character varying(20),
    relacionado_con integer,
    jornada_especial integer,
    turno_especial integer,
    programado integer,
    origen character varying(120),
    s1 integer,
    s2 integer,
    s3 integer,
    s4 integer,
    s5 integer,
    s6 integer,
    s7 integer,
    s8 integer,
    s9 integer,
    s10 integer,
    s11 integer,
    s12 integer,
    s13 integer,
    s14 integer,
    s15 integer,
    s16 integer,
    s17 integer,
    s18 integer,
    s19 integer,
    s20 integer,
    s21 integer,
    s22 integer,
    s23 integer,
    s24 integer,
    s25 integer,
    s26 integer,
    s27 integer,
    s28 integer,
    s29 integer,
    s30 integer,
    n_event_log_idn integer,
    procesado character varying(2)
);


--
-- Name: TABLE sg_control_persona; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.sg_control_persona IS 'Tabla principal de marcaciones (timbradas) del personal';


--
-- Name: sg_control_persona_secuencial_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.sg_control_persona_secuencial_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sg_control_persona_secuencial_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.sg_control_persona_secuencial_seq OWNED BY dbo.sg_control_persona.secuencial;


--
-- Name: supervisor_area; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.supervisor_area (
    id integer NOT NULL,
    id_depto integer NOT NULL,
    id_supervisor character varying(5) NOT NULL,
    fecha_registro timestamp without time zone DEFAULT now(),
    usuario character varying(5)
);


--
-- Name: TABLE supervisor_area; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON TABLE dbo.supervisor_area IS 'Supervisor asignado por área/departamento';


--
-- Name: supervisor_area_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.supervisor_area_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: supervisor_area_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.supervisor_area_id_seq OWNED BY dbo.supervisor_area.id;


--
-- Name: ti_actividad_mantenimiento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_actividad_mantenimiento (
    id integer NOT NULL,
    nombre character varying(150) NOT NULL,
    orden smallint DEFAULT 1 NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: ti_actividad_mantenimiento_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_actividad_mantenimiento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_actividad_mantenimiento_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_actividad_mantenimiento_id_seq OWNED BY dbo.ti_actividad_mantenimiento.id;


--
-- Name: ti_asignacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_asignacion (
    id integer NOT NULL,
    equipo_id integer NOT NULL,
    id_emp character varying(20) NOT NULL,
    fecha_asignacion date NOT NULL,
    fecha_devolucion date,
    motivo_devolucion character varying(30),
    observacion text,
    usuario_asigna character varying(20),
    usuario_devolucion character varying(20),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: ti_asignacion_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_asignacion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_asignacion_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_asignacion_id_seq OWNED BY dbo.ti_asignacion.id;


--
-- Name: ti_equipo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_equipo (
    id integer NOT NULL,
    codigo_bien character varying(50) NOT NULL,
    tipo_equipo_id integer,
    marca character varying(150),
    modelo character varying(300),
    descripcion character varying(300),
    serie character varying(100),
    estado character varying(20) DEFAULT 'DISPONIBLE'::character varying NOT NULL,
    condicion character varying(20),
    fecha_ingreso date,
    vida_util_anios smallint,
    ubicacion character varying(100),
    ultimo_mantenimiento date,
    observaciones text,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20),
    motivo_baja character varying(50),
    detalle_baja text,
    fecha_baja date
);


--
-- Name: ti_equipo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_equipo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_equipo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_equipo_id_seq OWNED BY dbo.ti_equipo.id;


--
-- Name: ti_mantenimiento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_mantenimiento (
    id integer NOT NULL,
    equipo_id integer NOT NULL,
    anio smallint NOT NULL,
    fecha_mantenimiento date NOT NULL,
    hora_inicio time without time zone,
    hora_fin time without time zone,
    tipo character varying(20) DEFAULT 'PREVENTIVO'::character varying NOT NULL,
    id_emp_tecnico character varying(20) NOT NULL,
    id_emp_custodio character varying(20),
    observaciones text,
    acta_alfresco_id character varying(100),
    acta_nombre_archivo character varying(200),
    created_by character varying(20),
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    origen character varying(20) DEFAULT 'INTERNO'::character varying NOT NULL,
    proveedor character varying(150),
    proceso_contratacion character varying(50),
    numero_orden_compra character varying(50),
    lote_externo character varying(50)
);


--
-- Name: ti_mantenimiento_detalle; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_mantenimiento_detalle (
    id integer NOT NULL,
    mantenimiento_id integer NOT NULL,
    actividad_id integer NOT NULL,
    realizado boolean DEFAULT false NOT NULL
);


--
-- Name: ti_mantenimiento_detalle_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_mantenimiento_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_mantenimiento_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_mantenimiento_detalle_id_seq OWNED BY dbo.ti_mantenimiento_detalle.id;


--
-- Name: ti_mantenimiento_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_mantenimiento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_mantenimiento_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_mantenimiento_id_seq OWNED BY dbo.ti_mantenimiento.id;


--
-- Name: ti_pieza; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_pieza (
    id integer NOT NULL,
    codigo character varying(50),
    serie character varying(100),
    descripcion character varying(300) NOT NULL,
    fecha_entrega date,
    estado character varying(20) DEFAULT 'DISPONIBLE'::character varying NOT NULL,
    equipo_id integer,
    observaciones text,
    created_at timestamp without time zone,
    created_by character varying(20),
    updated_at timestamp without time zone,
    updated_by character varying(20)
);


--
-- Name: ti_pieza_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_pieza_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_pieza_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_pieza_id_seq OWNED BY dbo.ti_pieza.id;


--
-- Name: ti_pieza_movimiento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_pieza_movimiento (
    id integer NOT NULL,
    pieza_id integer NOT NULL,
    equipo_id integer NOT NULL,
    mantenimiento_id integer,
    fecha_instalacion date NOT NULL,
    fecha_retiro date,
    motivo_retiro character varying(50),
    observacion text,
    usuario_instala character varying(20),
    usuario_retira character varying(20),
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: ti_pieza_movimiento_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_pieza_movimiento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_pieza_movimiento_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_pieza_movimiento_id_seq OWNED BY dbo.ti_pieza_movimiento.id;


--
-- Name: ti_tipo_equipo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.ti_tipo_equipo (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: ti_tipo_equipo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.ti_tipo_equipo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ti_tipo_equipo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.ti_tipo_equipo_id_seq OWNED BY dbo.ti_tipo_equipo.id;


--
-- Name: trans_mantenimiento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_mantenimiento (
    id integer NOT NULL,
    vehiculo_id integer NOT NULL,
    tipo character varying(30) NOT NULL,
    descripcion text NOT NULL,
    id_emp_conductor character varying(20) NOT NULL,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    taller character varying(100),
    fecha_orden date,
    numero_orden character varying(20),
    observacion_responsable text,
    fecha_finalizacion date,
    id_emp_responsable character varying(20),
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    motivo_negacion text,
    fecha_negacion timestamp without time zone,
    usuario_negacion character varying(20),
    km_actual integer,
    taller_id integer,
    tipo_mantenimiento_id integer,
    plan_preventivo_id integer,
    km_finalizacion integer
);


--
-- Name: trans_mantenimiento_actividad; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_mantenimiento_actividad (
    id integer NOT NULL,
    mantenimiento_id integer NOT NULL,
    tipo character varying(10) NOT NULL,
    actividad text NOT NULL,
    orden smallint DEFAULT 1 NOT NULL,
    tipo_actividad character varying(2),
    cantidad smallint
);


--
-- Name: trans_mantenimiento_actividad_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_mantenimiento_actividad_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_mantenimiento_actividad_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_mantenimiento_actividad_id_seq OWNED BY dbo.trans_mantenimiento_actividad.id;


--
-- Name: trans_mantenimiento_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_mantenimiento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_mantenimiento_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_mantenimiento_id_seq OWNED BY dbo.trans_mantenimiento.id;


--
-- Name: trans_plan_preventivo_cab; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_plan_preventivo_cab (
    id integer NOT NULL,
    vehiculo_id integer NOT NULL,
    km_hito integer NOT NULL,
    nombre character varying(100),
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: trans_plan_preventivo_cab_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_plan_preventivo_cab_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_plan_preventivo_cab_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_plan_preventivo_cab_id_seq OWNED BY dbo.trans_plan_preventivo_cab.id;


--
-- Name: trans_plan_preventivo_det; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_plan_preventivo_det (
    id integer NOT NULL,
    cab_id integer NOT NULL,
    orden smallint DEFAULT 1 NOT NULL,
    actividad text NOT NULL,
    tipo_actividad character varying(2) DEFAULT 'MO'::character varying NOT NULL,
    cantidad smallint DEFAULT 1 NOT NULL
);


--
-- Name: trans_plan_preventivo_det_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_plan_preventivo_det_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_plan_preventivo_det_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_plan_preventivo_det_id_seq OWNED BY dbo.trans_plan_preventivo_det.id;


--
-- Name: trans_solicitud_mov; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_solicitud_mov (
    id integer NOT NULL,
    id_emp_solicitante character varying(20) NOT NULL,
    motivo text NOT NULL,
    fecha_movilizacion date NOT NULL,
    hora_salida time without time zone NOT NULL,
    hora_retorno time without time zone NOT NULL,
    lugar_salida character varying(100),
    lugar_destino character varying(200),
    num_personas smallint DEFAULT 1 NOT NULL,
    estado character varying(15) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    vehiculo_id integer,
    id_emp_conductor character varying(20),
    observacion text,
    km_salida integer,
    km_retorno integer,
    hoja_ruta_observacion text,
    fecha_completado timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    id_emp_responsable character varying(20),
    fecha_aprobacion timestamp without time zone,
    fecha_negacion timestamp without time zone,
    direccion_salida character varying(200),
    direccion_destino character varying(200),
    pasajeros text
);


--
-- Name: trans_solicitud_mov_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_solicitud_mov_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_solicitud_mov_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_solicitud_mov_id_seq OWNED BY dbo.trans_solicitud_mov.id;


--
-- Name: trans_tipo_mantenimiento; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_tipo_mantenimiento (
    id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: trans_tipo_mantenimiento_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_tipo_mantenimiento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_tipo_mantenimiento_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_tipo_mantenimiento_id_seq OWNED BY dbo.trans_tipo_mantenimiento.id;


--
-- Name: trans_vale_combustible; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_vale_combustible (
    id integer NOT NULL,
    numero integer NOT NULL,
    gasolinera character varying(200),
    id_emp_conductor character varying(20) NOT NULL,
    vehiculo_id integer,
    kilometraje integer,
    fecha date NOT NULL,
    glns_extra numeric(8,2),
    pu_extra numeric(8,4),
    valor_extra numeric(10,2),
    glns_super numeric(8,2),
    pu_super numeric(8,4),
    valor_super numeric(10,2),
    glns_diesel numeric(8,2),
    pu_diesel numeric(8,4),
    valor_diesel numeric(10,2),
    estado character varying(15) DEFAULT 'EMITIDO'::character varying NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    fecha_comprobante date
);


--
-- Name: trans_vale_combustible_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_vale_combustible_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_vale_combustible_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_vale_combustible_id_seq OWNED BY dbo.trans_vale_combustible.id;


--
-- Name: trans_vehiculo; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.trans_vehiculo (
    id integer NOT NULL,
    placa character varying(10) NOT NULL,
    marca character varying(50),
    modelo character varying(50),
    anio smallint,
    chasis character varying(50),
    color character varying(30),
    kilometraje_actual integer DEFAULT 0 NOT NULL,
    estado character varying(15) DEFAULT 'ACTIVO'::character varying NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    numero_motor character varying(50)
);


--
-- Name: trans_vehiculo_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.trans_vehiculo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trans_vehiculo_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.trans_vehiculo_id_seq OWNED BY dbo.trans_vehiculo.id;


--
-- Name: users; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.users_id_seq OWNED BY dbo.users.id;


--
-- Name: vac_liquidacion_historico; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.vac_liquidacion_historico (
    id integer NOT NULL,
    id_emp character varying(10) NOT NULL,
    motivo character varying(20) NOT NULL,
    fecha_evento date NOT NULL,
    fecha_corte_usada date NOT NULL,
    saldo_inicial numeric(8,2) DEFAULT 0 NOT NULL,
    acumulado numeric(8,2) DEFAULT 0 NOT NULL,
    tomados numeric(8,2) DEFAULT 0 NOT NULL,
    saldo_liquidado numeric(8,2) DEFAULT 0 NOT NULL,
    observacion character varying(500),
    usuario_proceso character varying(10) NOT NULL,
    fecha_registro timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: COLUMN vac_liquidacion_historico.motivo; Type: COMMENT; Schema: dbo; Owner: -
--

COMMENT ON COLUMN dbo.vac_liquidacion_historico.motivo IS 'DESVINCULACION | COMISION_SALIDA | COMISION_RETORNO | NUEVO_INGRESO';


--
-- Name: vac_liquidacion_historico_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.vac_liquidacion_historico_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vac_liquidacion_historico_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.vac_liquidacion_historico_id_seq OWNED BY dbo.vac_liquidacion_historico.id;


--
-- Name: vac_periodo_planificacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.vac_periodo_planificacion (
    id integer NOT NULL,
    anio integer NOT NULL,
    fecha_inicio date NOT NULL,
    fecha_fin date NOT NULL,
    estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL
);


--
-- Name: vac_periodo_planificacion_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.vac_periodo_planificacion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vac_periodo_planificacion_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.vac_periodo_planificacion_id_seq OWNED BY dbo.vac_periodo_planificacion.id;


--
-- Name: vac_planificacion_cab; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.vac_planificacion_cab (
    id integer NOT NULL,
    id_emp character varying(10) NOT NULL,
    anio integer NOT NULL,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    total_dias_planificados numeric(8,4) DEFAULT 0 NOT NULL,
    fecha_registro timestamp without time zone NOT NULL,
    usuario_registro character varying(20) NOT NULL,
    fecha_decision timestamp without time zone,
    usuario_decision character varying(20),
    observacion character varying(250),
    replanificada character varying(2) DEFAULT 'NO'::character varying NOT NULL
);


--
-- Name: vac_planificacion_cab_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.vac_planificacion_cab_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vac_planificacion_cab_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.vac_planificacion_cab_id_seq OWNED BY dbo.vac_planificacion_cab.id;


--
-- Name: vac_planificacion_det; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.vac_planificacion_det (
    id integer NOT NULL,
    cab_id integer NOT NULL,
    numero_periodo integer NOT NULL,
    fecha_inicial date,
    fecha_final date,
    dias_calculados numeric(8,4)
);


--
-- Name: vac_planificacion_det_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.vac_planificacion_det_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vac_planificacion_det_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.vac_planificacion_det_id_seq OWNED BY dbo.vac_planificacion_det.id;


--
-- Name: vac_reporte_planificacion; Type: TABLE; Schema: dbo; Owner: -
--

CREATE TABLE dbo.vac_reporte_planificacion (
    id integer NOT NULL,
    anio integer NOT NULL,
    alfresco_node_id character varying(100) NOT NULL,
    nombre_archivo character varying(255) NOT NULL,
    fecha_subida timestamp without time zone DEFAULT now() NOT NULL,
    subido_por character varying(20) NOT NULL
);


--
-- Name: vac_reporte_planificacion_id_seq; Type: SEQUENCE; Schema: dbo; Owner: -
--

CREATE SEQUENCE dbo.vac_reporte_planificacion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vac_reporte_planificacion_id_seq; Type: SEQUENCE OWNED BY; Schema: dbo; Owner: -
--

ALTER SEQUENCE dbo.vac_reporte_planificacion_id_seq OWNED BY dbo.vac_reporte_planificacion.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id character varying(255) NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: articulo id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.articulo ALTER COLUMN id SET DEFAULT nextval('adq.articulo_id_seq'::regclass);


--
-- Name: egreso id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.egreso ALTER COLUMN id SET DEFAULT nextval('adq.egreso_id_seq'::regclass);


--
-- Name: egreso_det id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.egreso_det ALTER COLUMN id SET DEFAULT nextval('adq.egreso_det_id_seq'::regclass);


--
-- Name: iva id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.iva ALTER COLUMN id SET DEFAULT nextval('adq.iva_id_seq'::regclass);


--
-- Name: kardex id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.kardex ALTER COLUMN id SET DEFAULT nextval('adq.kardex_id_seq'::regclass);


--
-- Name: orden_compra id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra ALTER COLUMN id SET DEFAULT nextval('adq.orden_compra_id_seq'::regclass);


--
-- Name: orden_compra_det id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra_det ALTER COLUMN id SET DEFAULT nextval('adq.orden_compra_det_id_seq'::regclass);


--
-- Name: proceso_contratacion id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proceso_contratacion ALTER COLUMN id SET DEFAULT nextval('adq.proceso_contratacion_id_seq'::regclass);


--
-- Name: proveedor id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proveedor ALTER COLUMN id SET DEFAULT nextval('adq.proveedor_id_seq'::regclass);


--
-- Name: proveedor_catalogo id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proveedor_catalogo ALTER COLUMN id SET DEFAULT nextval('adq.proveedor_catalogo_id_seq'::regclass);


--
-- Name: solicitud_material id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.solicitud_material ALTER COLUMN id SET DEFAULT nextval('adq.solicitud_material_id_seq'::regclass);


--
-- Name: solicitud_material_det id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.solicitud_material_det ALTER COLUMN id SET DEFAULT nextval('adq.solicitud_material_det_id_seq'::regclass);


--
-- Name: unidad_medida id; Type: DEFAULT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.unidad_medida ALTER COLUMN id SET DEFAULT nextval('adq.unidad_medida_id_seq'::regclass);


--
-- Name: acc_accion_personal id_accion; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.acc_accion_personal ALTER COLUMN id_accion SET DEFAULT nextval('dbo.acc_accion_personal_id_accion_seq'::regclass);


--
-- Name: ad_empleado_hijo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_hijo ALTER COLUMN id SET DEFAULT nextval('dbo.ad_empleado_hijo_id_seq'::regclass);


--
-- Name: ad_empleado_mail secuencial; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_mail ALTER COLUMN secuencial SET DEFAULT nextval('dbo.ad_empleado_mail_secuencial_seq'::regclass);


--
-- Name: ad_empleado_teletrabajo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_teletrabajo ALTER COLUMN id SET DEFAULT nextval('dbo.ad_empleado_teletrabajo_id_seq'::regclass);


--
-- Name: ad_enfermedad_catastrofica id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_enfermedad_catastrofica ALTER COLUMN id SET DEFAULT nextval('dbo.ad_enfermedad_catastrofica_id_seq'::regclass);


--
-- Name: ad_grupo_prioritario id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_grupo_prioritario ALTER COLUMN id SET DEFAULT nextval('dbo.ad_grupo_prioritario_id_seq'::regclass);


--
-- Name: ad_grupo_vulnerable id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_grupo_vulnerable ALTER COLUMN id SET DEFAULT nextval('dbo.ad_grupo_vulnerable_id_seq'::regclass);


--
-- Name: ad_tipo_discapacidad id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_tipo_discapacidad ALTER COLUMN id SET DEFAULT nextval('dbo.ad_tipo_discapacidad_id_seq'::regclass);


--
-- Name: admin_rol id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_rol ALTER COLUMN id SET DEFAULT nextval('dbo.admin_rol_id_seq'::regclass);


--
-- Name: admin_rol_opcion id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_rol_opcion ALTER COLUMN id SET DEFAULT nextval('dbo.admin_rol_opcion_id_seq'::regclass);


--
-- Name: com_anticipo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_anticipo ALTER COLUMN id SET DEFAULT nextval('dbo.com_anticipo_id_seq'::regclass);


--
-- Name: com_ciudad id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_ciudad ALTER COLUMN id SET DEFAULT nextval('dbo.com_ciudad_id_seq'::regclass);


--
-- Name: com_coeficiente_pais id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_coeficiente_pais ALTER COLUMN id SET DEFAULT nextval('dbo.com_coeficiente_pais_id_seq'::regclass);


--
-- Name: com_ficha_liquidacion id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_ficha_liquidacion ALTER COLUMN id SET DEFAULT nextval('dbo.com_ficha_liquidacion_id_seq'::regclass);


--
-- Name: com_funcionario_externo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_funcionario_externo ALTER COLUMN id SET DEFAULT nextval('dbo.com_funcionario_externo_id_seq'::regclass);


--
-- Name: com_informe id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_informe ALTER COLUMN id SET DEFAULT nextval('dbo.com_informe_id_seq'::regclass);


--
-- Name: com_informe_transporte id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_informe_transporte ALTER COLUMN id SET DEFAULT nextval('dbo.com_informe_transporte_id_seq'::regclass);


--
-- Name: com_provincia id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_provincia ALTER COLUMN id SET DEFAULT nextval('dbo.com_provincia_id_seq'::regclass);


--
-- Name: com_solicitud id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud ALTER COLUMN id SET DEFAULT nextval('dbo.com_solicitud_id_seq'::regclass);


--
-- Name: com_solicitud_documento id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_documento ALTER COLUMN id SET DEFAULT nextval('dbo.com_solicitud_documento_id_seq'::regclass);


--
-- Name: com_solicitud_servidor id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_servidor ALTER COLUMN id SET DEFAULT nextval('dbo.com_solicitud_servidor_id_seq'::regclass);


--
-- Name: com_solicitud_transporte id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_transporte ALTER COLUMN id SET DEFAULT nextval('dbo.com_solicitud_transporte_id_seq'::regclass);


--
-- Name: com_tarifa_viatico id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_tarifa_viatico ALTER COLUMN id SET DEFAULT nextval('dbo.com_tarifa_viatico_id_seq'::regclass);


--
-- Name: d2_aportes_iess id_aporte; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_aportes_iess ALTER COLUMN id_aporte SET DEFAULT nextval('dbo.d2_aportes_iess_id_aporte_seq'::regclass);


--
-- Name: d2_auditoria secuencial; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_auditoria ALTER COLUMN secuencial SET DEFAULT nextval('dbo.d2_auditoria_secuencial_seq'::regclass);


--
-- Name: d2_aviso_ticker id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_aviso_ticker ALTER COLUMN id SET DEFAULT nextval('dbo.d2_aviso_ticker_id_seq'::regclass);


--
-- Name: d2_certificado_laboral id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_certificado_laboral ALTER COLUMN id SET DEFAULT nextval('dbo.d2_certificado_laboral_id_seq'::regclass);


--
-- Name: d2_detalle_vacacion secuencial; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_detalle_vacacion ALTER COLUMN secuencial SET DEFAULT nextval('dbo.d2_detalle_vacacion_secuencial_seq'::regclass);


--
-- Name: d2_modalidad_laboral id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_modalidad_laboral ALTER COLUMN id SET DEFAULT nextval('dbo.d2_modalidad_laboral_id_seq'::regclass);


--
-- Name: d2_permiso secuencial_clave; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_permiso ALTER COLUMN secuencial_clave SET DEFAULT nextval('dbo.d2_permiso_secuencial_clave_seq'::regclass);


--
-- Name: d2_permiso_documento id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_permiso_documento ALTER COLUMN id SET DEFAULT nextval('dbo.d2_permiso_documento_id_seq'::regclass);


--
-- Name: d2_programacion secuencial; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_programacion ALTER COLUMN secuencial SET DEFAULT nextval('dbo.d2_programacion_secuencial_seq'::regclass);


--
-- Name: d2_vacacion secuencial_clave; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_vacacion ALTER COLUMN secuencial_clave SET DEFAULT nextval('dbo.d2_vacacion_secuencial_clave_seq'::regclass);


--
-- Name: d2_zkteco_dispositivo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_zkteco_dispositivo ALTER COLUMN id SET DEFAULT nextval('dbo.d2_zkteco_dispositivo_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.failed_jobs ALTER COLUMN id SET DEFAULT nextval('dbo.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.jobs ALTER COLUMN id SET DEFAULT nextval('dbo.jobs_id_seq'::regclass);


--
-- Name: nom_auditoria_log id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_auditoria_log ALTER COLUMN id SET DEFAULT nextval('dbo.nom_auditoria_log_id_seq'::regclass);


--
-- Name: nom_decimo_cuarto id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_cuarto ALTER COLUMN id SET DEFAULT nextval('dbo.nom_decimo_cuarto_id_seq'::regclass);


--
-- Name: nom_decimo_tercero id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_tercero ALTER COLUMN id SET DEFAULT nextval('dbo.nom_decimo_tercero_id_seq'::regclass);


--
-- Name: nom_fondos_reserva id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_fondos_reserva ALTER COLUMN id SET DEFAULT nextval('dbo.nom_fondos_reserva_id_seq'::regclass);


--
-- Name: nom_he_planificacion_cab id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_planificacion_cab ALTER COLUMN id SET DEFAULT nextval('dbo.nom_he_planificacion_cab_id_seq'::regclass);


--
-- Name: nom_he_planificacion_det id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_planificacion_det ALTER COLUMN id SET DEFAULT nextval('dbo.nom_he_planificacion_det_id_seq'::regclass);


--
-- Name: nom_he_registro id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_registro ALTER COLUMN id SET DEFAULT nextval('dbo.nom_he_registro_id_seq'::regclass);


--
-- Name: nom_rol_pago_cab id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_cab ALTER COLUMN id SET DEFAULT nextval('dbo.nom_rol_pago_cab_id_seq'::regclass);


--
-- Name: nom_rol_pago_det id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_det ALTER COLUMN id SET DEFAULT nextval('dbo.nom_rol_pago_det_id_seq'::regclass);


--
-- Name: nom_sbu_historico id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_sbu_historico ALTER COLUMN id SET DEFAULT nextval('dbo.nom_sbu_historico_id_seq'::regclass);


--
-- Name: sg_control_persona secuencial; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.sg_control_persona ALTER COLUMN secuencial SET DEFAULT nextval('dbo.sg_control_persona_secuencial_seq'::regclass);


--
-- Name: supervisor_area id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.supervisor_area ALTER COLUMN id SET DEFAULT nextval('dbo.supervisor_area_id_seq'::regclass);


--
-- Name: ti_actividad_mantenimiento id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_actividad_mantenimiento ALTER COLUMN id SET DEFAULT nextval('dbo.ti_actividad_mantenimiento_id_seq'::regclass);


--
-- Name: ti_asignacion id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_asignacion ALTER COLUMN id SET DEFAULT nextval('dbo.ti_asignacion_id_seq'::regclass);


--
-- Name: ti_equipo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_equipo ALTER COLUMN id SET DEFAULT nextval('dbo.ti_equipo_id_seq'::regclass);


--
-- Name: ti_mantenimiento id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento ALTER COLUMN id SET DEFAULT nextval('dbo.ti_mantenimiento_id_seq'::regclass);


--
-- Name: ti_mantenimiento_detalle id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento_detalle ALTER COLUMN id SET DEFAULT nextval('dbo.ti_mantenimiento_detalle_id_seq'::regclass);


--
-- Name: ti_pieza id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza ALTER COLUMN id SET DEFAULT nextval('dbo.ti_pieza_id_seq'::regclass);


--
-- Name: ti_pieza_movimiento id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza_movimiento ALTER COLUMN id SET DEFAULT nextval('dbo.ti_pieza_movimiento_id_seq'::regclass);


--
-- Name: ti_tipo_equipo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_tipo_equipo ALTER COLUMN id SET DEFAULT nextval('dbo.ti_tipo_equipo_id_seq'::regclass);


--
-- Name: trans_mantenimiento id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento ALTER COLUMN id SET DEFAULT nextval('dbo.trans_mantenimiento_id_seq'::regclass);


--
-- Name: trans_mantenimiento_actividad id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento_actividad ALTER COLUMN id SET DEFAULT nextval('dbo.trans_mantenimiento_actividad_id_seq'::regclass);


--
-- Name: trans_plan_preventivo_cab id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_plan_preventivo_cab ALTER COLUMN id SET DEFAULT nextval('dbo.trans_plan_preventivo_cab_id_seq'::regclass);


--
-- Name: trans_plan_preventivo_det id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_plan_preventivo_det ALTER COLUMN id SET DEFAULT nextval('dbo.trans_plan_preventivo_det_id_seq'::regclass);


--
-- Name: trans_solicitud_mov id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_solicitud_mov ALTER COLUMN id SET DEFAULT nextval('dbo.trans_solicitud_mov_id_seq'::regclass);


--
-- Name: trans_tipo_mantenimiento id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_tipo_mantenimiento ALTER COLUMN id SET DEFAULT nextval('dbo.trans_tipo_mantenimiento_id_seq'::regclass);


--
-- Name: trans_vale_combustible id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_vale_combustible ALTER COLUMN id SET DEFAULT nextval('dbo.trans_vale_combustible_id_seq'::regclass);


--
-- Name: trans_vehiculo id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_vehiculo ALTER COLUMN id SET DEFAULT nextval('dbo.trans_vehiculo_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.users ALTER COLUMN id SET DEFAULT nextval('dbo.users_id_seq'::regclass);


--
-- Name: vac_liquidacion_historico id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_liquidacion_historico ALTER COLUMN id SET DEFAULT nextval('dbo.vac_liquidacion_historico_id_seq'::regclass);


--
-- Name: vac_periodo_planificacion id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_periodo_planificacion ALTER COLUMN id SET DEFAULT nextval('dbo.vac_periodo_planificacion_id_seq'::regclass);


--
-- Name: vac_planificacion_cab id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_planificacion_cab ALTER COLUMN id SET DEFAULT nextval('dbo.vac_planificacion_cab_id_seq'::regclass);


--
-- Name: vac_planificacion_det id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_planificacion_det ALTER COLUMN id SET DEFAULT nextval('dbo.vac_planificacion_det_id_seq'::regclass);


--
-- Name: vac_reporte_planificacion id; Type: DEFAULT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_reporte_planificacion ALTER COLUMN id SET DEFAULT nextval('dbo.vac_reporte_planificacion_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: articulo adq_articulo_codigo_unique; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.articulo
    ADD CONSTRAINT adq_articulo_codigo_unique UNIQUE (codigo);


--
-- Name: proveedor adq_proveedor_ruc_unique; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proveedor
    ADD CONSTRAINT adq_proveedor_ruc_unique UNIQUE (ruc);


--
-- Name: articulo articulo_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.articulo
    ADD CONSTRAINT articulo_pkey PRIMARY KEY (id);


--
-- Name: catalogo_inventario catalogo_inventario_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.catalogo_inventario
    ADD CONSTRAINT catalogo_inventario_pkey PRIMARY KEY (nivel2);


--
-- Name: catalogo_nivel1 catalogo_nivel1_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.catalogo_nivel1
    ADD CONSTRAINT catalogo_nivel1_pkey PRIMARY KEY (nivel1);


--
-- Name: configuracion configuracion_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.configuracion
    ADD CONSTRAINT configuracion_pkey PRIMARY KEY (concepto);


--
-- Name: egreso_det egreso_det_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.egreso_det
    ADD CONSTRAINT egreso_det_pkey PRIMARY KEY (id);


--
-- Name: egreso egreso_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.egreso
    ADD CONSTRAINT egreso_pkey PRIMARY KEY (id);


--
-- Name: iva iva_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.iva
    ADD CONSTRAINT iva_pkey PRIMARY KEY (id);


--
-- Name: kardex kardex_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.kardex
    ADD CONSTRAINT kardex_pkey PRIMARY KEY (id);


--
-- Name: orden_compra_det orden_compra_det_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra_det
    ADD CONSTRAINT orden_compra_det_pkey PRIMARY KEY (id);


--
-- Name: orden_compra orden_compra_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra
    ADD CONSTRAINT orden_compra_pkey PRIMARY KEY (id);


--
-- Name: proceso_contratacion proceso_contratacion_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proceso_contratacion
    ADD CONSTRAINT proceso_contratacion_pkey PRIMARY KEY (id);


--
-- Name: proveedor_catalogo proveedor_catalogo_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proveedor_catalogo
    ADD CONSTRAINT proveedor_catalogo_pkey PRIMARY KEY (id);


--
-- Name: proveedor proveedor_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proveedor
    ADD CONSTRAINT proveedor_pkey PRIMARY KEY (id);


--
-- Name: solicitud_material_det solicitud_material_det_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.solicitud_material_det
    ADD CONSTRAINT solicitud_material_det_pkey PRIMARY KEY (id);


--
-- Name: solicitud_material solicitud_material_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.solicitud_material
    ADD CONSTRAINT solicitud_material_pkey PRIMARY KEY (id);


--
-- Name: unidad_medida unidad_medida_pkey; Type: CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.unidad_medida
    ADD CONSTRAINT unidad_medida_pkey PRIMARY KEY (id);


--
-- Name: acc_accion_personal acc_accion_personal_numero_accion_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.acc_accion_personal
    ADD CONSTRAINT acc_accion_personal_numero_accion_key UNIQUE (numero_accion);


--
-- Name: acc_accion_personal acc_accion_personal_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.acc_accion_personal
    ADD CONSTRAINT acc_accion_personal_pkey PRIMARY KEY (id_accion);


--
-- Name: ad_empleado_hijo ad_empleado_hijo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_hijo
    ADD CONSTRAINT ad_empleado_hijo_pkey PRIMARY KEY (id);


--
-- Name: ad_empleado_teletrabajo ad_empleado_teletrabajo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_teletrabajo
    ADD CONSTRAINT ad_empleado_teletrabajo_pkey PRIMARY KEY (id);


--
-- Name: ad_enfermedad_catastrofica ad_enfermedad_catastrofica_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_enfermedad_catastrofica
    ADD CONSTRAINT ad_enfermedad_catastrofica_pkey PRIMARY KEY (id);


--
-- Name: ad_grupo_prioritario ad_grupo_prioritario_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_grupo_prioritario
    ADD CONSTRAINT ad_grupo_prioritario_pkey PRIMARY KEY (id);


--
-- Name: ad_grupo_vulnerable ad_grupo_vulnerable_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_grupo_vulnerable
    ADD CONSTRAINT ad_grupo_vulnerable_pkey PRIMARY KEY (id);


--
-- Name: ad_tipo_discapacidad ad_tipo_discapacidad_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_tipo_discapacidad
    ADD CONSTRAINT ad_tipo_discapacidad_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: com_anticipo com_anticipo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_anticipo
    ADD CONSTRAINT com_anticipo_pkey PRIMARY KEY (id);


--
-- Name: com_anticipo com_anticipo_solicitud_id_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_anticipo
    ADD CONSTRAINT com_anticipo_solicitud_id_key UNIQUE (solicitud_id);


--
-- Name: com_ciudad com_ciudad_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_ciudad
    ADD CONSTRAINT com_ciudad_pkey PRIMARY KEY (id);


--
-- Name: com_coeficiente_pais com_coeficiente_pais_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_coeficiente_pais
    ADD CONSTRAINT com_coeficiente_pais_pkey PRIMARY KEY (id);


--
-- Name: com_ficha_liquidacion com_ficha_liquidacion_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_ficha_liquidacion
    ADD CONSTRAINT com_ficha_liquidacion_pkey PRIMARY KEY (id);


--
-- Name: com_ficha_liquidacion com_ficha_liquidacion_solicitud_id_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_ficha_liquidacion
    ADD CONSTRAINT com_ficha_liquidacion_solicitud_id_key UNIQUE (solicitud_id);


--
-- Name: com_funcionario_externo com_funcionario_externo_cedula_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_funcionario_externo
    ADD CONSTRAINT com_funcionario_externo_cedula_key UNIQUE (cedula);


--
-- Name: com_funcionario_externo com_funcionario_externo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_funcionario_externo
    ADD CONSTRAINT com_funcionario_externo_pkey PRIMARY KEY (id);


--
-- Name: com_informe com_informe_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_informe
    ADD CONSTRAINT com_informe_pkey PRIMARY KEY (id);


--
-- Name: com_informe com_informe_solicitud_id_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_informe
    ADD CONSTRAINT com_informe_solicitud_id_key UNIQUE (solicitud_id);


--
-- Name: com_informe_transporte com_informe_transporte_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_informe_transporte
    ADD CONSTRAINT com_informe_transporte_pkey PRIMARY KEY (id);


--
-- Name: com_provincia com_provincia_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_provincia
    ADD CONSTRAINT com_provincia_pkey PRIMARY KEY (id);


--
-- Name: com_solicitud_documento com_solicitud_documento_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_documento
    ADD CONSTRAINT com_solicitud_documento_pkey PRIMARY KEY (id);


--
-- Name: com_solicitud com_solicitud_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud
    ADD CONSTRAINT com_solicitud_pkey PRIMARY KEY (id);


--
-- Name: com_solicitud_servidor com_solicitud_servidor_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_servidor
    ADD CONSTRAINT com_solicitud_servidor_pkey PRIMARY KEY (id);


--
-- Name: com_solicitud_transporte com_solicitud_transporte_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_transporte
    ADD CONSTRAINT com_solicitud_transporte_pkey PRIMARY KEY (id);


--
-- Name: com_tarifa_viatico com_tarifa_viatico_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_tarifa_viatico
    ADD CONSTRAINT com_tarifa_viatico_pkey PRIMARY KEY (id);


--
-- Name: d2_aportes_iess d2_aportes_iess_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_aportes_iess
    ADD CONSTRAINT d2_aportes_iess_pkey PRIMARY KEY (id_aporte);


--
-- Name: d2_aviso_ticker d2_aviso_ticker_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_aviso_ticker
    ADD CONSTRAINT d2_aviso_ticker_pkey PRIMARY KEY (id);


--
-- Name: d2_certificado_laboral d2_certificado_laboral_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_certificado_laboral
    ADD CONSTRAINT d2_certificado_laboral_pkey PRIMARY KEY (id);


--
-- Name: d2_modalidad_laboral d2_modalidad_laboral_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_modalidad_laboral
    ADD CONSTRAINT d2_modalidad_laboral_pkey PRIMARY KEY (id);


--
-- Name: d2_permiso_documento d2_permiso_documento_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_permiso_documento
    ADD CONSTRAINT d2_permiso_documento_pkey PRIMARY KEY (id);


--
-- Name: d2_vacacion d2_vacacion_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_vacacion
    ADD CONSTRAINT d2_vacacion_pkey PRIMARY KEY (secuencial_clave);


--
-- Name: d2_zkteco_dispositivo d2_zkteco_dispositivo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_zkteco_dispositivo
    ADD CONSTRAINT d2_zkteco_dispositivo_pkey PRIMARY KEY (id);


--
-- Name: d2_certificado_laboral dbo_d2_certificado_laboral_numero_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_certificado_laboral
    ADD CONSTRAINT dbo_d2_certificado_laboral_numero_unique UNIQUE (numero);


--
-- Name: d2_zkteco_dispositivo dbo_d2_zkteco_dispositivo_serial_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_zkteco_dispositivo
    ADD CONSTRAINT dbo_d2_zkteco_dispositivo_serial_unique UNIQUE (serial);


--
-- Name: nom_decimo_cuarto dbo_nom_decimo_cuarto_anio_mes_id_emp_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_cuarto
    ADD CONSTRAINT dbo_nom_decimo_cuarto_anio_mes_id_emp_unique UNIQUE (anio, mes, id_emp);


--
-- Name: nom_decimo_tercero dbo_nom_decimo_tercero_anio_mes_id_emp_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_tercero
    ADD CONSTRAINT dbo_nom_decimo_tercero_anio_mes_id_emp_unique UNIQUE (anio, mes, id_emp);


--
-- Name: nom_fondos_reserva dbo_nom_fondos_reserva_anio_mes_id_emp_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_fondos_reserva
    ADD CONSTRAINT dbo_nom_fondos_reserva_anio_mes_id_emp_unique UNIQUE (anio, mes, id_emp);


--
-- Name: nom_he_planificacion_cab dbo_nom_he_planificacion_cab_id_emp_anio_mes_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_planificacion_cab
    ADD CONSTRAINT dbo_nom_he_planificacion_cab_id_emp_anio_mes_unique UNIQUE (id_emp, anio, mes);


--
-- Name: nom_rol_pago_cab dbo_nom_rol_pago_cab_anio_mes_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_cab
    ADD CONSTRAINT dbo_nom_rol_pago_cab_anio_mes_unique UNIQUE (anio, mes);


--
-- Name: nom_rol_pago_det dbo_nom_rol_pago_det_cab_id_id_emp_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_det
    ADD CONSTRAINT dbo_nom_rol_pago_det_cab_id_id_emp_unique UNIQUE (cab_id, id_emp);


--
-- Name: nom_sbu_historico dbo_nom_sbu_historico_anio_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_sbu_historico
    ADD CONSTRAINT dbo_nom_sbu_historico_anio_unique UNIQUE (anio);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: nom_auditoria_log nom_auditoria_log_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_auditoria_log
    ADD CONSTRAINT nom_auditoria_log_pkey PRIMARY KEY (id);


--
-- Name: nom_decimo_cuarto nom_decimo_cuarto_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_cuarto
    ADD CONSTRAINT nom_decimo_cuarto_pkey PRIMARY KEY (id);


--
-- Name: nom_decimo_tercero nom_decimo_tercero_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_tercero
    ADD CONSTRAINT nom_decimo_tercero_pkey PRIMARY KEY (id);


--
-- Name: nom_fondos_reserva nom_fondos_reserva_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_fondos_reserva
    ADD CONSTRAINT nom_fondos_reserva_pkey PRIMARY KEY (id);


--
-- Name: nom_he_planificacion_cab nom_he_planificacion_cab_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_planificacion_cab
    ADD CONSTRAINT nom_he_planificacion_cab_pkey PRIMARY KEY (id);


--
-- Name: nom_he_planificacion_det nom_he_planificacion_det_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_planificacion_det
    ADD CONSTRAINT nom_he_planificacion_det_pkey PRIMARY KEY (id);


--
-- Name: nom_he_registro nom_he_registro_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_registro
    ADD CONSTRAINT nom_he_registro_pkey PRIMARY KEY (id);


--
-- Name: nom_rol_pago_cab nom_rol_pago_cab_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_cab
    ADD CONSTRAINT nom_rol_pago_cab_pkey PRIMARY KEY (id);


--
-- Name: nom_rol_pago_det nom_rol_pago_det_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_det
    ADD CONSTRAINT nom_rol_pago_det_pkey PRIMARY KEY (id);


--
-- Name: nom_sbu_historico nom_sbu_historico_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_sbu_historico
    ADD CONSTRAINT nom_sbu_historico_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: ad_departamento pk_ad_departamento; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_departamento
    ADD CONSTRAINT pk_ad_departamento PRIMARY KEY (id_depto);


--
-- Name: ad_empleado pk_ad_empleado; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado
    ADD CONSTRAINT pk_ad_empleado PRIMARY KEY (id_emp);


--
-- Name: ad_empleado_mail pk_ad_empleado_mail; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_mail
    ADD CONSTRAINT pk_ad_empleado_mail PRIMARY KEY (secuencial);


--
-- Name: admin_opcion pk_admin_opcion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_opcion
    ADD CONSTRAINT pk_admin_opcion PRIMARY KEY (id);


--
-- Name: admin_rol pk_admin_rol; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_rol
    ADD CONSTRAINT pk_admin_rol PRIMARY KEY (id);


--
-- Name: admin_rol_opcion pk_admin_rol_opcion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_rol_opcion
    ADD CONSTRAINT pk_admin_rol_opcion PRIMARY KEY (id);


--
-- Name: admin_usuario_rol pk_admin_usuario_rol; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_usuario_rol
    ADD CONSTRAINT pk_admin_usuario_rol PRIMARY KEY (id_emp, identificacion, id_rol);


--
-- Name: d2_auditoria pk_d2_auditoria; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_auditoria
    ADD CONSTRAINT pk_d2_auditoria PRIMARY KEY (secuencial);


--
-- Name: d2_cab_prog pk_d2_cab_prog; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_cab_prog
    ADD CONSTRAINT pk_d2_cab_prog PRIMARY KEY (id);


--
-- Name: d2_cab_turno pk_d2_cab_turno; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_cab_turno
    ADD CONSTRAINT pk_d2_cab_turno PRIMARY KEY (id_turno);


--
-- Name: d2_cabecera_vacacion pk_d2_cabecera_vacacion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_cabecera_vacacion
    ADD CONSTRAINT pk_d2_cabecera_vacacion PRIMARY KEY (id_emp);


--
-- Name: d2_configuracion pk_d2_configuracion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_configuracion
    ADD CONSTRAINT pk_d2_configuracion PRIMARY KEY (concepto);


--
-- Name: d2_cuadre_marcacion pk_d2_cuadre_marcacion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_cuadre_marcacion
    ADD CONSTRAINT pk_d2_cuadre_marcacion PRIMARY KEY (id_emp, fecha, ip);


--
-- Name: d2_detalle_vacacion pk_d2_detalle_vacacion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_detalle_vacacion
    ADD CONSTRAINT pk_d2_detalle_vacacion PRIMARY KEY (secuencial);


--
-- Name: d2_jornada pk_d2_jornada; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_jornada
    ADD CONSTRAINT pk_d2_jornada PRIMARY KEY (id_jornada);


--
-- Name: d2_lista_fecha pk_d2_lista_fecha; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_lista_fecha
    ADD CONSTRAINT pk_d2_lista_fecha PRIMARY KEY (fecha, ubicacion);


--
-- Name: d2_permiso pk_d2_permiso; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_permiso
    ADD CONSTRAINT pk_d2_permiso PRIMARY KEY (fecha_hora, id_emp);


--
-- Name: d2_programacion pk_d2_programacion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_programacion
    ADD CONSTRAINT pk_d2_programacion PRIMARY KEY (secuencial);


--
-- Name: d2_razon pk_d2_razon; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_razon
    ADD CONSTRAINT pk_d2_razon PRIMARY KEY (secuencial);


--
-- Name: d2_turno pk_d2_turno; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_turno
    ADD CONSTRAINT pk_d2_turno PRIMARY KEY (id_turno, concepto);


--
-- Name: sg_control_persona pk_sg_control_persona; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.sg_control_persona
    ADD CONSTRAINT pk_sg_control_persona PRIMARY KEY (secuencial);


--
-- Name: supervisor_area pk_supervisor_area; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.supervisor_area
    ADD CONSTRAINT pk_supervisor_area PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: ti_actividad_mantenimiento ti_actividad_mantenimiento_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_actividad_mantenimiento
    ADD CONSTRAINT ti_actividad_mantenimiento_pkey PRIMARY KEY (id);


--
-- Name: ti_asignacion ti_asignacion_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_asignacion
    ADD CONSTRAINT ti_asignacion_pkey PRIMARY KEY (id);


--
-- Name: ti_equipo ti_equipo_codigo_bien_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_equipo
    ADD CONSTRAINT ti_equipo_codigo_bien_key UNIQUE (codigo_bien);


--
-- Name: ti_equipo ti_equipo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_equipo
    ADD CONSTRAINT ti_equipo_pkey PRIMARY KEY (id);


--
-- Name: ti_mantenimiento_detalle ti_mantenimiento_detalle_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento_detalle
    ADD CONSTRAINT ti_mantenimiento_detalle_pkey PRIMARY KEY (id);


--
-- Name: ti_mantenimiento ti_mantenimiento_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento
    ADD CONSTRAINT ti_mantenimiento_pkey PRIMARY KEY (id);


--
-- Name: ti_pieza_movimiento ti_pieza_movimiento_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza_movimiento
    ADD CONSTRAINT ti_pieza_movimiento_pkey PRIMARY KEY (id);


--
-- Name: ti_pieza ti_pieza_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza
    ADD CONSTRAINT ti_pieza_pkey PRIMARY KEY (id);


--
-- Name: ti_tipo_equipo ti_tipo_equipo_nombre_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_tipo_equipo
    ADD CONSTRAINT ti_tipo_equipo_nombre_key UNIQUE (nombre);


--
-- Name: ti_tipo_equipo ti_tipo_equipo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_tipo_equipo
    ADD CONSTRAINT ti_tipo_equipo_pkey PRIMARY KEY (id);


--
-- Name: trans_mantenimiento_actividad trans_mantenimiento_actividad_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento_actividad
    ADD CONSTRAINT trans_mantenimiento_actividad_pkey PRIMARY KEY (id);


--
-- Name: trans_mantenimiento trans_mantenimiento_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_pkey PRIMARY KEY (id);


--
-- Name: trans_plan_preventivo_cab trans_plan_preventivo_cab_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_plan_preventivo_cab
    ADD CONSTRAINT trans_plan_preventivo_cab_pkey PRIMARY KEY (id);


--
-- Name: trans_plan_preventivo_det trans_plan_preventivo_det_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_plan_preventivo_det
    ADD CONSTRAINT trans_plan_preventivo_det_pkey PRIMARY KEY (id);


--
-- Name: trans_solicitud_mov trans_solicitud_mov_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_solicitud_mov
    ADD CONSTRAINT trans_solicitud_mov_pkey PRIMARY KEY (id);


--
-- Name: trans_tipo_mantenimiento trans_tipo_mantenimiento_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_tipo_mantenimiento
    ADD CONSTRAINT trans_tipo_mantenimiento_pkey PRIMARY KEY (id);


--
-- Name: trans_vale_combustible trans_vale_combustible_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_vale_combustible
    ADD CONSTRAINT trans_vale_combustible_pkey PRIMARY KEY (id);


--
-- Name: trans_vehiculo trans_vehiculo_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_vehiculo
    ADD CONSTRAINT trans_vehiculo_pkey PRIMARY KEY (id);


--
-- Name: trans_vehiculo trans_vehiculo_placa_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_vehiculo
    ADD CONSTRAINT trans_vehiculo_placa_key UNIQUE (placa);


--
-- Name: ad_empleado uq_ad_empleado_identificacion; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado
    ADD CONSTRAINT uq_ad_empleado_identificacion UNIQUE (identificacion);


--
-- Name: vac_planificacion_cab uq_planificacion_emp_anio; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_planificacion_cab
    ADD CONSTRAINT uq_planificacion_emp_anio UNIQUE (id_emp, anio);


--
-- Name: supervisor_area uq_supervisor_area; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.supervisor_area
    ADD CONSTRAINT uq_supervisor_area_depto_sup UNIQUE (id_depto, id_supervisor);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vac_liquidacion_historico vac_liquidacion_historico_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_liquidacion_historico
    ADD CONSTRAINT vac_liquidacion_historico_pkey PRIMARY KEY (id);


--
-- Name: vac_periodo_planificacion vac_periodo_planificacion_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_periodo_planificacion
    ADD CONSTRAINT vac_periodo_planificacion_pkey PRIMARY KEY (id);


--
-- Name: vac_planificacion_cab vac_planificacion_cab_id_emp_anio_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_planificacion_cab
    ADD CONSTRAINT vac_planificacion_cab_id_emp_anio_key UNIQUE (id_emp, anio);


--
-- Name: vac_planificacion_cab vac_planificacion_cab_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_planificacion_cab
    ADD CONSTRAINT vac_planificacion_cab_pkey PRIMARY KEY (id);


--
-- Name: vac_planificacion_det vac_planificacion_det_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_planificacion_det
    ADD CONSTRAINT vac_planificacion_det_pkey PRIMARY KEY (id);


--
-- Name: vac_reporte_planificacion vac_reporte_planificacion_anio_key; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_reporte_planificacion
    ADD CONSTRAINT vac_reporte_planificacion_anio_key UNIQUE (anio);


--
-- Name: vac_reporte_planificacion vac_reporte_planificacion_pkey; Type: CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_reporte_planificacion
    ADD CONSTRAINT vac_reporte_planificacion_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: idx_catalogo_nivel1; Type: INDEX; Schema: adq; Owner: -
--

CREATE INDEX idx_catalogo_nivel1 ON adq.catalogo_inventario USING btree (nivel1);


--
-- Name: idx_kardex_articulo_fecha; Type: INDEX; Schema: adq; Owner: -
--

CREATE INDEX idx_kardex_articulo_fecha ON adq.kardex USING btree (articulo_id, fecha);


--
-- Name: idx_kardex_ref; Type: INDEX; Schema: adq; Owner: -
--

CREATE INDEX idx_kardex_ref ON adq.kardex USING btree (referencia_tipo, referencia_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX cache_expiration_index ON dbo.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON dbo.cache_locks USING btree (expiration);


--
-- Name: idx_ad_empleado_depto; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_ad_empleado_depto ON dbo.ad_empleado USING btree (id_depto);


--
-- Name: idx_ad_empleado_estado; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_ad_empleado_estado ON dbo.ad_empleado USING btree (estado);


--
-- Name: idx_ad_empleado_extension; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_ad_empleado_extension ON dbo.ad_empleado USING btree (tipo_contrato);


--
-- Name: idx_d2_cuadre_marcacion_emp; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_d2_cuadre_marcacion_emp ON dbo.d2_cuadre_marcacion USING btree (id_emp);


--
-- Name: idx_d2_cuadre_marcacion_fecha; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_d2_cuadre_marcacion_fecha ON dbo.d2_cuadre_marcacion USING btree (fecha);


--
-- Name: idx_d2_permiso_emp; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_d2_permiso_emp ON dbo.d2_permiso USING btree (id_emp);


--
-- Name: idx_d2_permiso_estado; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_d2_permiso_estado ON dbo.d2_permiso USING btree (estado_permiso);


--
-- Name: idx_d2_permiso_fechas; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_d2_permiso_fechas ON dbo.d2_permiso USING btree (fecha_desde, fecha_hasta);


--
-- Name: idx_d2_programacion_emp_fecha; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_d2_programacion_emp_fecha ON dbo.d2_programacion USING btree (id_emp, fecha);


--
-- Name: idx_empleado_hijo_emp; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_empleado_hijo_emp ON dbo.ad_empleado_hijo USING btree (id_emp);


--
-- Name: idx_nom_audit_fecha; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_nom_audit_fecha ON dbo.nom_auditoria_log USING btree (created_at);


--
-- Name: idx_nom_audit_tabla_reg; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_nom_audit_tabla_reg ON dbo.nom_auditoria_log USING btree (tabla, registro_id);


--
-- Name: idx_nom_audit_usuario; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_nom_audit_usuario ON dbo.nom_auditoria_log USING btree (usuario_id);


--
-- Name: idx_sg_control_persona_clasif; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_sg_control_persona_clasif ON dbo.sg_control_persona USING btree (clasificacion);


--
-- Name: idx_sg_control_persona_emp; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_sg_control_persona_emp ON dbo.sg_control_persona USING btree (nro_documento);


--
-- Name: idx_sg_control_persona_fecha; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX idx_sg_control_persona_fecha ON dbo.sg_control_persona USING btree (fecha_hora);


--
-- Name: jobs_queue_reserved_at_available_at_index; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX jobs_queue_reserved_at_available_at_index ON dbo.jobs USING btree (queue, reserved_at, available_at);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX sessions_last_activity_index ON dbo.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: dbo; Owner: -
--

CREATE INDEX sessions_user_id_index ON dbo.sessions USING btree (user_id);


--
-- Name: ti_mantenimiento_preventivo_anio_uq; Type: INDEX; Schema: dbo; Owner: -
--

CREATE UNIQUE INDEX ti_mantenimiento_preventivo_anio_uq ON dbo.ti_mantenimiento USING btree (equipo_id, anio) WHERE ((tipo)::text = 'PREVENTIVO'::text);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: nom_auditoria_log trg_nom_auditoria_log_bloquear_delete; Type: TRIGGER; Schema: dbo; Owner: -
--

CREATE TRIGGER trg_nom_auditoria_log_bloquear_delete BEFORE DELETE ON dbo.nom_auditoria_log FOR EACH ROW EXECUTE FUNCTION dbo.nom_auditoria_log_bloquear_cambio();


--
-- Name: nom_auditoria_log trg_nom_auditoria_log_bloquear_update; Type: TRIGGER; Schema: dbo; Owner: -
--

CREATE TRIGGER trg_nom_auditoria_log_bloquear_update BEFORE UPDATE ON dbo.nom_auditoria_log FOR EACH ROW EXECUTE FUNCTION dbo.nom_auditoria_log_bloquear_cambio();


--
-- Name: orden_compra_det adq_orden_compra_det_articulo_id_foreign; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra_det
    ADD CONSTRAINT adq_orden_compra_det_articulo_id_foreign FOREIGN KEY (articulo_id) REFERENCES adq.articulo(id);


--
-- Name: orden_compra_det adq_orden_compra_det_orden_id_foreign; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra_det
    ADD CONSTRAINT adq_orden_compra_det_orden_id_foreign FOREIGN KEY (orden_id) REFERENCES adq.orden_compra(id) ON DELETE CASCADE;


--
-- Name: orden_compra adq_orden_compra_proveedor_id_foreign; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra
    ADD CONSTRAINT adq_orden_compra_proveedor_id_foreign FOREIGN KEY (proveedor_id) REFERENCES adq.proveedor(id);


--
-- Name: proveedor_catalogo adq_proveedor_catalogo_proveedor_id_foreign; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.proveedor_catalogo
    ADD CONSTRAINT adq_proveedor_catalogo_proveedor_id_foreign FOREIGN KEY (proveedor_id) REFERENCES adq.proveedor(id) ON DELETE CASCADE;


--
-- Name: solicitud_material_det adq_solicitud_material_det_articulo_id_foreign; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.solicitud_material_det
    ADD CONSTRAINT adq_solicitud_material_det_articulo_id_foreign FOREIGN KEY (articulo_id) REFERENCES adq.articulo(id);


--
-- Name: solicitud_material_det adq_solicitud_material_det_solicitud_id_foreign; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.solicitud_material_det
    ADD CONSTRAINT adq_solicitud_material_det_solicitud_id_foreign FOREIGN KEY (solicitud_id) REFERENCES adq.solicitud_material(id) ON DELETE CASCADE;


--
-- Name: solicitud_material adq_solicitud_material_id_emp_foreign; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.solicitud_material
    ADD CONSTRAINT adq_solicitud_material_id_emp_foreign FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: articulo articulo_iva_id_fkey; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.articulo
    ADD CONSTRAINT articulo_iva_id_fkey FOREIGN KEY (iva_id) REFERENCES adq.iva(id);


--
-- Name: egreso_det egreso_det_articulo_id_fkey; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.egreso_det
    ADD CONSTRAINT egreso_det_articulo_id_fkey FOREIGN KEY (articulo_id) REFERENCES adq.articulo(id);


--
-- Name: egreso_det egreso_det_egreso_id_fkey; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.egreso_det
    ADD CONSTRAINT egreso_det_egreso_id_fkey FOREIGN KEY (egreso_id) REFERENCES adq.egreso(id) ON DELETE CASCADE;


--
-- Name: egreso_det egreso_det_iva_id_fkey; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.egreso_det
    ADD CONSTRAINT egreso_det_iva_id_fkey FOREIGN KEY (iva_id) REFERENCES adq.iva(id);


--
-- Name: articulo fk_articulo_nivel2; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.articulo
    ADD CONSTRAINT fk_articulo_nivel2 FOREIGN KEY (nivel2) REFERENCES adq.catalogo_inventario(nivel2) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: catalogo_inventario fk_catalogo_nivel1; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.catalogo_inventario
    ADD CONSTRAINT fk_catalogo_nivel1 FOREIGN KEY (nivel1) REFERENCES adq.catalogo_nivel1(nivel1) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: kardex kardex_articulo_id_fkey; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.kardex
    ADD CONSTRAINT kardex_articulo_id_fkey FOREIGN KEY (articulo_id) REFERENCES adq.articulo(id);


--
-- Name: orden_compra_det orden_compra_det_iva_id_fkey; Type: FK CONSTRAINT; Schema: adq; Owner: -
--

ALTER TABLE ONLY adq.orden_compra_det
    ADD CONSTRAINT orden_compra_det_iva_id_fkey FOREIGN KEY (iva_id) REFERENCES adq.iva(id);


--
-- Name: d2_configuracion d2_configuracion_created_by_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_configuracion
    ADD CONSTRAINT d2_configuracion_created_by_fkey FOREIGN KEY (created_by) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: d2_configuracion d2_configuracion_updated_by_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_configuracion
    ADD CONSTRAINT d2_configuracion_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: com_ciudad dbo_com_ciudad_provincia_id_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_ciudad
    ADD CONSTRAINT dbo_com_ciudad_provincia_id_foreign FOREIGN KEY (provincia_id) REFERENCES dbo.com_provincia(id);


--
-- Name: com_solicitud_documento dbo_com_solicitud_documento_solicitud_id_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_documento
    ADD CONSTRAINT dbo_com_solicitud_documento_solicitud_id_foreign FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE;


--
-- Name: nom_decimo_cuarto dbo_nom_decimo_cuarto_id_emp_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_cuarto
    ADD CONSTRAINT dbo_nom_decimo_cuarto_id_emp_foreign FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: nom_decimo_tercero dbo_nom_decimo_tercero_id_emp_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_decimo_tercero
    ADD CONSTRAINT dbo_nom_decimo_tercero_id_emp_foreign FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: nom_fondos_reserva dbo_nom_fondos_reserva_id_emp_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_fondos_reserva
    ADD CONSTRAINT dbo_nom_fondos_reserva_id_emp_foreign FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: nom_he_planificacion_cab dbo_nom_he_planificacion_cab_id_emp_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_planificacion_cab
    ADD CONSTRAINT dbo_nom_he_planificacion_cab_id_emp_foreign FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: nom_he_planificacion_det dbo_nom_he_planificacion_det_cab_id_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_planificacion_det
    ADD CONSTRAINT dbo_nom_he_planificacion_det_cab_id_foreign FOREIGN KEY (cab_id) REFERENCES dbo.nom_he_planificacion_cab(id) ON DELETE CASCADE;


--
-- Name: nom_he_registro dbo_nom_he_registro_cab_id_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_registro
    ADD CONSTRAINT dbo_nom_he_registro_cab_id_foreign FOREIGN KEY (cab_id) REFERENCES dbo.nom_he_planificacion_cab(id);


--
-- Name: nom_he_registro dbo_nom_he_registro_id_emp_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_he_registro
    ADD CONSTRAINT dbo_nom_he_registro_id_emp_foreign FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: nom_rol_pago_det dbo_nom_rol_pago_det_cab_id_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_det
    ADD CONSTRAINT dbo_nom_rol_pago_det_cab_id_foreign FOREIGN KEY (cab_id) REFERENCES dbo.nom_rol_pago_cab(id) ON DELETE CASCADE;


--
-- Name: nom_rol_pago_det dbo_nom_rol_pago_det_id_emp_foreign; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.nom_rol_pago_det
    ADD CONSTRAINT dbo_nom_rol_pago_det_id_emp_foreign FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: ad_empleado fk_ad_empleado_depto; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado
    ADD CONSTRAINT fk_ad_empleado_depto FOREIGN KEY (id_depto) REFERENCES dbo.ad_departamento(id_depto);


--
-- Name: ad_empleado fk_ad_empleado_jornada; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado
    ADD CONSTRAINT fk_ad_empleado_jornada FOREIGN KEY (jornada_id) REFERENCES dbo.d2_cab_prog(id);


--
-- Name: ad_empleado fk_ad_empleado_jornada_laboral; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado
    ADD CONSTRAINT fk_ad_empleado_jornada_laboral FOREIGN KEY (id_jornada) REFERENCES dbo.d2_jornada(id_jornada);


--
-- Name: ad_empleado_mail fk_ad_empleado_mail_emp; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_mail
    ADD CONSTRAINT fk_ad_empleado_mail_emp FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: admin_rol_opcion fk_admin_rol_opcion_rol; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_rol_opcion
    ADD CONSTRAINT fk_admin_rol_opcion_rol FOREIGN KEY (id_rol) REFERENCES dbo.admin_rol(id);


--
-- Name: admin_usuario_rol fk_admin_usuario_rol_rol; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.admin_usuario_rol
    ADD CONSTRAINT fk_admin_usuario_rol_rol FOREIGN KEY (id_rol) REFERENCES dbo.admin_rol(id);


--
-- Name: com_anticipo fk_com_ant_sol; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_anticipo
    ADD CONSTRAINT fk_com_ant_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE;


--
-- Name: com_ficha_liquidacion fk_com_fic_sol; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_ficha_liquidacion
    ADD CONSTRAINT fk_com_fic_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE;


--
-- Name: com_informe fk_com_inf_sol; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_informe
    ADD CONSTRAINT fk_com_inf_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE;


--
-- Name: com_informe_transporte fk_com_itrn_inf; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_informe_transporte
    ADD CONSTRAINT fk_com_itrn_inf FOREIGN KEY (informe_id) REFERENCES dbo.com_informe(id) ON DELETE CASCADE;


--
-- Name: com_solicitud fk_com_sol_emp; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud
    ADD CONSTRAINT fk_com_sol_emp FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: com_solicitud_servidor fk_com_srv_sol; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_servidor
    ADD CONSTRAINT fk_com_srv_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE;


--
-- Name: com_solicitud_transporte fk_com_trn_sol; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.com_solicitud_transporte
    ADD CONSTRAINT fk_com_trn_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE;


--
-- Name: d2_cabecera_vacacion fk_d2_cabecera_vacacion_emp; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_cabecera_vacacion
    ADD CONSTRAINT fk_d2_cabecera_vacacion_emp FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: d2_detalle_vacacion fk_d2_detalle_vacacion_cab; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_detalle_vacacion
    ADD CONSTRAINT fk_d2_detalle_vacacion_cab FOREIGN KEY (id_emp) REFERENCES dbo.d2_cabecera_vacacion(id_emp);


--
-- Name: d2_permiso fk_d2_permiso_emp; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_permiso
    ADD CONSTRAINT fk_d2_permiso_emp FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: d2_programacion fk_d2_programacion_cab; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_programacion
    ADD CONSTRAINT fk_d2_programacion_cab FOREIGN KEY (id) REFERENCES dbo.d2_cab_prog(id);


--
-- Name: d2_programacion fk_d2_programacion_turno; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_programacion
    ADD CONSTRAINT fk_d2_programacion_turno FOREIGN KEY (id_turno) REFERENCES dbo.d2_cab_turno(id_turno);


--
-- Name: d2_turno fk_d2_turno_cab; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.d2_turno
    ADD CONSTRAINT fk_d2_turno_cab FOREIGN KEY (id_turno) REFERENCES dbo.d2_cab_turno(id_turno);


--
-- Name: ad_departamento fk_depto_padre; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_departamento
    ADD CONSTRAINT fk_depto_padre FOREIGN KEY (padre_id) REFERENCES dbo.ad_departamento(id_depto);


--
-- Name: ad_empleado_hijo fk_hijo_emp; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ad_empleado_hijo
    ADD CONSTRAINT fk_hijo_emp FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp) ON DELETE CASCADE;


--
-- Name: sg_control_persona fk_sg_control_persona_emp; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.sg_control_persona
    ADD CONSTRAINT fk_sg_control_persona_emp FOREIGN KEY (nro_documento) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: supervisor_area fk_supervisor_area_depto; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.supervisor_area
    ADD CONSTRAINT fk_supervisor_area_depto FOREIGN KEY (id_depto) REFERENCES dbo.ad_departamento(id_depto);


--
-- Name: supervisor_area fk_supervisor_area_emp; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.supervisor_area
    ADD CONSTRAINT fk_supervisor_area_emp FOREIGN KEY (id_supervisor) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: ti_asignacion ti_asignacion_equipo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_asignacion
    ADD CONSTRAINT ti_asignacion_equipo_id_fkey FOREIGN KEY (equipo_id) REFERENCES dbo.ti_equipo(id);


--
-- Name: ti_asignacion ti_asignacion_id_emp_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_asignacion
    ADD CONSTRAINT ti_asignacion_id_emp_fkey FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: ti_equipo ti_equipo_tipo_equipo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_equipo
    ADD CONSTRAINT ti_equipo_tipo_equipo_id_fkey FOREIGN KEY (tipo_equipo_id) REFERENCES dbo.ti_tipo_equipo(id);


--
-- Name: ti_mantenimiento_detalle ti_mantenimiento_detalle_actividad_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento_detalle
    ADD CONSTRAINT ti_mantenimiento_detalle_actividad_id_fkey FOREIGN KEY (actividad_id) REFERENCES dbo.ti_actividad_mantenimiento(id);


--
-- Name: ti_mantenimiento_detalle ti_mantenimiento_detalle_mantenimiento_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento_detalle
    ADD CONSTRAINT ti_mantenimiento_detalle_mantenimiento_id_fkey FOREIGN KEY (mantenimiento_id) REFERENCES dbo.ti_mantenimiento(id) ON DELETE CASCADE;


--
-- Name: ti_mantenimiento ti_mantenimiento_equipo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento
    ADD CONSTRAINT ti_mantenimiento_equipo_id_fkey FOREIGN KEY (equipo_id) REFERENCES dbo.ti_equipo(id);


--
-- Name: ti_mantenimiento ti_mantenimiento_id_emp_custodio_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento
    ADD CONSTRAINT ti_mantenimiento_id_emp_custodio_fkey FOREIGN KEY (id_emp_custodio) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: ti_mantenimiento ti_mantenimiento_id_emp_tecnico_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_mantenimiento
    ADD CONSTRAINT ti_mantenimiento_id_emp_tecnico_fkey FOREIGN KEY (id_emp_tecnico) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: ti_pieza ti_pieza_equipo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza
    ADD CONSTRAINT ti_pieza_equipo_id_fkey FOREIGN KEY (equipo_id) REFERENCES dbo.ti_equipo(id);


--
-- Name: ti_pieza_movimiento ti_pieza_movimiento_equipo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza_movimiento
    ADD CONSTRAINT ti_pieza_movimiento_equipo_id_fkey FOREIGN KEY (equipo_id) REFERENCES dbo.ti_equipo(id);


--
-- Name: ti_pieza_movimiento ti_pieza_movimiento_mantenimiento_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza_movimiento
    ADD CONSTRAINT ti_pieza_movimiento_mantenimiento_id_fkey FOREIGN KEY (mantenimiento_id) REFERENCES dbo.ti_mantenimiento(id);


--
-- Name: ti_pieza_movimiento ti_pieza_movimiento_pieza_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.ti_pieza_movimiento
    ADD CONSTRAINT ti_pieza_movimiento_pieza_id_fkey FOREIGN KEY (pieza_id) REFERENCES dbo.ti_pieza(id);


--
-- Name: trans_mantenimiento_actividad trans_mantenimiento_actividad_mantenimiento_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento_actividad
    ADD CONSTRAINT trans_mantenimiento_actividad_mantenimiento_id_fkey FOREIGN KEY (mantenimiento_id) REFERENCES dbo.trans_mantenimiento(id) ON DELETE CASCADE;


--
-- Name: trans_mantenimiento trans_mantenimiento_id_emp_conductor_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_id_emp_conductor_fkey FOREIGN KEY (id_emp_conductor) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: trans_mantenimiento trans_mantenimiento_id_emp_responsable_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_id_emp_responsable_fkey FOREIGN KEY (id_emp_responsable) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: trans_mantenimiento trans_mantenimiento_plan_preventivo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_plan_preventivo_id_fkey FOREIGN KEY (plan_preventivo_id) REFERENCES dbo.trans_plan_preventivo_cab(id);


--
-- Name: trans_mantenimiento trans_mantenimiento_taller_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_taller_id_fkey FOREIGN KEY (taller_id) REFERENCES adq.proveedor(id);


--
-- Name: trans_mantenimiento trans_mantenimiento_tipo_mantenimiento_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_tipo_mantenimiento_id_fkey FOREIGN KEY (tipo_mantenimiento_id) REFERENCES dbo.trans_tipo_mantenimiento(id);


--
-- Name: trans_mantenimiento trans_mantenimiento_usuario_negacion_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_usuario_negacion_fkey FOREIGN KEY (usuario_negacion) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: trans_mantenimiento trans_mantenimiento_vehiculo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_mantenimiento
    ADD CONSTRAINT trans_mantenimiento_vehiculo_id_fkey FOREIGN KEY (vehiculo_id) REFERENCES dbo.trans_vehiculo(id);


--
-- Name: trans_plan_preventivo_cab trans_plan_preventivo_cab_vehiculo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_plan_preventivo_cab
    ADD CONSTRAINT trans_plan_preventivo_cab_vehiculo_id_fkey FOREIGN KEY (vehiculo_id) REFERENCES dbo.trans_vehiculo(id);


--
-- Name: trans_plan_preventivo_det trans_plan_preventivo_det_cab_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_plan_preventivo_det
    ADD CONSTRAINT trans_plan_preventivo_det_cab_id_fkey FOREIGN KEY (cab_id) REFERENCES dbo.trans_plan_preventivo_cab(id) ON DELETE CASCADE;


--
-- Name: trans_solicitud_mov trans_solicitud_mov_id_emp_conductor_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_solicitud_mov
    ADD CONSTRAINT trans_solicitud_mov_id_emp_conductor_fkey FOREIGN KEY (id_emp_conductor) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: trans_solicitud_mov trans_solicitud_mov_id_emp_responsable_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_solicitud_mov
    ADD CONSTRAINT trans_solicitud_mov_id_emp_responsable_fkey FOREIGN KEY (id_emp_responsable) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: trans_solicitud_mov trans_solicitud_mov_id_emp_solicitante_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_solicitud_mov
    ADD CONSTRAINT trans_solicitud_mov_id_emp_solicitante_fkey FOREIGN KEY (id_emp_solicitante) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: trans_solicitud_mov trans_solicitud_mov_vehiculo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_solicitud_mov
    ADD CONSTRAINT trans_solicitud_mov_vehiculo_id_fkey FOREIGN KEY (vehiculo_id) REFERENCES dbo.trans_vehiculo(id);


--
-- Name: trans_vale_combustible trans_vale_combustible_id_emp_conductor_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_vale_combustible
    ADD CONSTRAINT trans_vale_combustible_id_emp_conductor_fkey FOREIGN KEY (id_emp_conductor) REFERENCES dbo.ad_empleado(id_emp);


--
-- Name: trans_vale_combustible trans_vale_combustible_vehiculo_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.trans_vale_combustible
    ADD CONSTRAINT trans_vale_combustible_vehiculo_id_fkey FOREIGN KEY (vehiculo_id) REFERENCES dbo.trans_vehiculo(id);


--
-- Name: vac_planificacion_det vac_planificacion_det_cab_id_fkey; Type: FK CONSTRAINT; Schema: dbo; Owner: -
--

ALTER TABLE ONLY dbo.vac_planificacion_det
    ADD CONSTRAINT vac_planificacion_det_cab_id_fkey FOREIGN KEY (cab_id) REFERENCES dbo.vac_planificacion_cab(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--


