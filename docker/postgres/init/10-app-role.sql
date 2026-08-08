-- Runs once on first cluster init (docker-entrypoint-initdb.d).
-- Creates the RESTRICTED role the application runtime uses. The app never
-- connects as the superuser/owner (spec #10, #41). This supports the
-- defense-in-depth around immutable posted ledgers: the app role is granted
-- table DML but is NOT a superuser and cannot disable triggers, so the
-- BEFORE UPDATE/DELETE guards on posted journal rows cannot be bypassed by
-- the application, only by an out-of-band DBA action (documented in ACCOUNTING.md).
--
-- Passwords are injected from the environment by the wrapper below; they are
-- never hard-coded here.
\set app_user `echo "$APP_DB_USER"`
\set app_pass `echo "$APP_DB_PASSWORD"`
\set db_name  `echo "$POSTGRES_DB"`

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user') THEN
        EXECUTE format('CREATE ROLE %I LOGIN PASSWORD %L', :'app_user', :'app_pass');
    END IF;
END
$$;

-- The app role may use the database and the public schema; table-level grants
-- are applied by migrations after tables exist (see DATABASE.md).
GRANT CONNECT ON DATABASE :"db_name" TO :"app_user";
GRANT USAGE ON SCHEMA public TO :"app_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO :"app_user";
