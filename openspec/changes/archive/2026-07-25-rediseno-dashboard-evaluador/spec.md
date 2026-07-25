# Spec: Rediseño Dashboard Evaluador

## DB-001 — Home panel
**WHEN** EvaluadorExterno opens `/dashboard/evaluador-externo`  
**THE SYSTEM SHALL** show a summary home panel and SHALL NOT render the full evaluation project list.

## DB-002 — Profile
**THE SYSTEM SHALL** display name, email, role label “Evaluador Externo”, and account created date when available; otherwise the corresponding `datoNoEncontrado` message.

## DB-003 — Activity KPIs
**THE SYSTEM SHALL** show assigned, pending, and completed counts from `/api/evaluador/kpis` for the authenticated user.

## DB-004 — Progress
**WHEN** assigned > 0  
**THE SYSTEM SHALL** show the percentage of completed evaluations relative to assigned.

## DB-005 — Quick access
**THE SYSTEM SHALL** provide navigation shortcuts to Evaluaciones, Recursos, and Anuncios.
