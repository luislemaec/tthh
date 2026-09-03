# Especificaciones — Módulo Talento Humano

Especificación dirigida (Spec-Driven Development) del módulo **Talento Humano** del Sistema de
Gestión del Consejo de Comunicación.

Estas specs son **retro-especificaciones**: documentan el comportamiento **actual** del sistema
(tal como está implementado en `backend/` y `frontend/`), no un rediseño. Sirven como fuente de
verdad para: mantenimiento, pruebas de regresión, onboarding, auditoría (Contraloría) y como base
para especificar cambios futuros.

## Cómo leer estas specs

- La **spec paraguas** (`00-...`) define alcance, actores, glosario y reglas transversales. Leerla primero.
- Cada **sub-spec** cubre un submódulo y es autocontenida salvo lo que delega explícitamente en la paraguas.
- Formato de cada sub-spec: Propósito → Actores → Historias de usuario → Modelo de datos → Reglas de
  negocio → API → UI → Criterios de aceptación → Casos borde → Dependencias → No-objetivos → Preguntas abiertas.
- Los **criterios de aceptación** usan formato Dado / Cuando / Entonces y son verificables.

## Estado de las specs

| # | Sub-spec | Estado | Archivo |
|---|---|---|---|
| 00 | Módulo Talento Humano (paraguas) | ✅ Borrador v1 | [00-modulo-talento-humano.md](00-modulo-talento-humano.md) |
| 01 | Empleados | ✅ Borrador v1 | [01-empleados.md](01-empleados.md) |
| 02 | Importación masiva de empleados | ✅ Borrador v1 | [02-importacion-masiva-empleados.md](02-importacion-masiva-empleados.md) |
| 03 | Control de asistencia (marcación web + biométrico) | ✅ Borrador v1 | [03-control-asistencia.md](03-control-asistencia.md) |
| 04 | Permisos y licencias | ✅ Borrador v1 | [04-permisos-licencias.md](04-permisos-licencias.md) |
| 05 | Vacaciones (solicitud, saldo, planificación) | ✅ Borrador v1 | [05-vacaciones.md](05-vacaciones.md) |
| 06 | Acciones de personal | ✅ Borrador v1 | [06-acciones-personal.md](06-acciones-personal.md) |
| 07 | Liquidación de vacaciones | ✅ Borrador v1 | [07-liquidacion-vacaciones.md](07-liquidacion-vacaciones.md) |
| 08 | Horas extras | ✅ Borrador v1 | [08-horas-extras.md](08-horas-extras.md) |
| 09 | Nómina (D13 / D14 / Fondos de Reserva / Rol de Pagos) | ⬜ Pendiente · orden 1 | — |
| 10 | Certificados laborales | ✅ Borrador v1 | [10-certificados-laborales.md](10-certificados-laborales.md) |
| 11 | Dashboard y reportes de TH | ✅ Borrador v1 | [11-dashboard-reportes.md](11-dashboard-reportes.md) |
| 12 | Auditoría | ⬜ Pendiente · orden 2 | — |

> **Orden de trabajo de las pendientes** (2026-09-03): quedan **09 Nómina → 12 Auditoría**. Los
> **números de spec no cambian** (son identificadores permanentes); solo cambia el orden en que se escriben.

## Convención de versionado

Cada spec lleva `Versión` y `Última actualización` en su encabezado. Al cambiar el comportamiento
del sistema, se actualiza la spec correspondiente en el mismo commit que el código.
