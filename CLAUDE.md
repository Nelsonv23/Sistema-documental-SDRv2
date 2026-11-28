# CLAUDE.md — Guía de Arquitectura y Contexto SDR v2

Este archivo concentra las decisiones técnicas y arquitectónicas cerradas del proyecto **SDR v2** (`C:\Users\NELSON\Desktop\laragon\www\sdr-v2`).

## Decisiones arquitectónicas cerradas

- **Stack principal:** Laravel 13, PHP 8.4, MySQL 8.0, Laravel Fortify (sin registro público), Spatie Laravel Permission (`Administrador`, `Jefatura`, `Colaborador`).
- **Interfaz:** Blade + Bootstrap 5.3 + assets Purple (`public/assets/purple/`) + `public/css/tailadmin-sdr.css` (diseño TailAdmin/Orchid con soporte modo claro/oscuro) + Alpine.js + Lucide Icons. No se utiliza AdminLTE ni compilación Vite en producción.
- **Almacenamiento privado:** Todos los documentos, versiones y avatares residen en `storage/app/private` sin `storage:link` público; se entregan exclusivamente mediante controladores autenticados y validados por policy/permisos.
- **Permisos con precedencia `DENY` absoluto:** Evaluados por `App\Services\FolderPermissionService`. Un `DENY` explícito (por usuario o rol) bloquea el acceso a la carpeta, sus descendientes, documentos, búsquedas y notificaciones.
- **Subida asíncrona y versionado:** Cola en cliente con un máximo de 2 subidas simultáneas, barra de progreso y control de versiones (`DocumentVersion`).
- **Auditoría inmutable y notificaciones internas:** Toda acción crítica queda en `audit_logs` vía `AuditLogger`. Las notificaciones de comentarios y novedades se envían únicamente a usuarios activos (`is_active = true`) con acceso efectivo (`FolderPermissionService::canAccess`).
- **Protocolo de memoria del proyecto:** Toda sesión debe leer este archivo y `SDR_PROYECTO_ESTADO_ACTUAL.md` antes de modificar código, y actualizar `SDR_PROYECTO_ESTADO_ACTUAL.md` al concluir cada cambio relevante (ver `.agent/rules/memoria-proyecto.md`).

#### Estado actual del proyecto (checkpoint)

El avance diario detallado, inventario de módulos implementados, commits recientes, estado de entornos y lista ordenada de pendientes se mantienen de forma viva en **[SDR_PROYECTO_ESTADO_ACTUAL.md](SDR_PROYECTO_ESTADO_ACTUAL.md)**.
Consúltalo siempre como fuente principal del estado actual antes de planificar o ejecutar la siguiente tarea.
