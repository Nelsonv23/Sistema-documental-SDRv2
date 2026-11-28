# SDR v2 — Estado Actual del Proyecto

> **Documento vivo de seguimiento diario.** Última actualización: 2026-11-28 (commit base en `main`: `5793e64`).
> Complementa las reglas arquitectónicas cerradas definidas en [CLAUDE.md](CLAUDE.md) y `.agent/rules/contexto_sdr.md`.

---

## 1. Arquitectura y decisiones cerradas

- **Backend y runtime:** Laravel 13, PHP 8.4 (local Laragon PHP 8.4.25 NTS vía FastCGI con Apache 2.4.54) y MySQL 8.0 (local MySQL 8.0.30).
- **Autenticación y autorización:**
  - Laravel Fortify para inicio de sesión, recuperación y restablecimiento de contraseña, deshabilitando el registro público (`registration` desactivado) y con validación de usuario activo (`EnsureUserIsActive`).
  - Spatie Permission para roles base (`Administrador`, `Jefatura`, `Colaborador`).
- **Frontend e interfaz:**
  - Blade + Bootstrap 5.3 + recursos base Purple (`public/assets/purple/`) + hoja de estilos corporativa `public/css/tailadmin-sdr.css` (estilo TailAdmin/Orchid con tokens `--sdr-surface-bg`, `--sdr-border-color`, `--sdr-card-shadow`, modo claro y modo oscuro) + Alpine.js + Lucide Icons.
  - *Nota técnica:* Aunque inicialmente se evaluó AdminLTE v3.15.3 en la especificación original, la aplicación en disco opera sin AdminLTE ni compilación Vite requerida en producción, utilizando exclusivamente los activos estáticos mencionados en `public/`.
  - Identidad visual adaptativa claro/oscuro en sidebar/login (`logo-version-azul.png` / `logo-sdr-blanco.png`), campana de notificaciones con badge rojo de alto contraste (`.sdr-notif-badge`) y footer institucional con enlace a LA TRIADA.
- **Almacenamiento privado y descarga segura:**
  - Archivos almacenados fuera del directorio público en el disco privado (`storage/app/private`), sin `public/storage` symlink.
  - Descarga, previsualización inline y avatares servidos exclusivamente mediante rutas y controladores autenticados tras validar políticas y permisos.
- **Control de acceso documental (herencia y `DENY` absoluto):**
  - Evaluado en `FolderPermissionService` (`canAccess`, permisos heredados por jerarquía de carpetas).
  - Precedencia **DENY absoluto**: si un usuario o su rol tiene denegación (`DENY`), el bloqueo prevalece sobre cualquier permiso heredado o superior y oculta completamente carpetas, documentos, resultados de búsqueda y notificaciones asociadas.
- **Subida asíncrona de documentos:**
  - Cola de subida gestionada en cliente (Alpine.js) con un máximo de **2 archivos concurrentes**, progreso por archivo y soporte de versionado (`DocumentVersion`).
- **Auditoría inmutable y notificaciones:**
  - Registro de acciones en `audit_logs` mediante `AuditLogger` (histórico no editable desde la aplicación, con comando de archivado/limpieza programable).
  - Bandeja interna en `notifications` con polling ligero en el layout principal y notificaciones al comentar documentos enviadas únicamente a usuarios activos con acceso efectivo (`FolderPermissionService::canAccess`), respetando el bloqueo `DENY`.

---

## 2. Entornos operativos

| Entorno | URL | Ruta en servidor / local | Base de datos / Estado |
| :--- | :--- | :--- | :--- |
| **Local** | `http://sdr-v2.test` | `C:\Users\NELSON\Desktop\laragon\www\sdr-v2` | MySQL 8.0.30 local · Rama `main` sincronizada con `origin/main` en `5793e64` |
| **Staging / Pruebas** | `https://pruebas.sdrservicios.cl` | `~/sdr-v2-staging` | `qkncwrsb_pruebas_sdr_db2026` · Migrado, con seeders y operativo para validación de usuarios |
| **Producción** | `https://intranet.sdrservicios.cl` | `~/sdr-v2` | Pendiente de despliegue tras validación final en staging |

---

## 3. Implementación verificada en el repositorio

### Modelos (`app/Models/`)
- `User`, `Folder`, `FolderPermission`, `Document`, `DocumentVersion`, `Comment`, `Notification`, `AuditLog`.

### Migraciones y Seeders (`database/`)
- **Migraciones:** tablas base (`users`, `cache`, `jobs`), `create_permission_tables`, `add_sdr_columns_to_users_table`, `create_folders_table`, `create_folder_permissions_table`, `create_documents_table`, `create_document_versions_table`, `create_comments_table`, `create_notifications_table`, `create_audit_logs_table`.
- **Seeders:** `DatabaseSeeder`, `RolesAndPermissionsSeeder`, `UsersSeeder`.

### Controladores, Middleware, Políticas, Servicios y Comandos (`app/`)
- **Controladores (`app/Http/Controllers/`):**
  - `FolderController` (`index`, `show`, `store`, `update`, `destroy`, `permissions`, `storePermission`, `destroyPermission`, `buildAccessibleTree`, `buildBreadcrumbs` — incluyendo el explorador documental de dos paneles SGI ya implementado en `5793e64`)
  - `DocumentController`, `CommentController`, `NotificationController`, `ProfileController`, `SearchController`
  - `Admin\AdminUserController`, `Admin\TrashController`, `Admin\AuditLogController`
- **Middleware (`app/Http/Middleware/`):** `EnsureUserIsActive`
- **Servicios (`app/Services/`):** `FolderPermissionService`, `DocumentStorageService`, `DocumentSearchService`, `AuditLogger`
- **Políticas y Observer:** `FolderPolicy`, `DocumentPolicy`, `FolderObserver`
- **Comandos Artisan (`app/Console/Commands/`):** `PurgeTrashedDocuments`, `CleanOrphanDocuments`, `ArchiveOldAuditLogs`

### Vistas Blade (`resources/views/`)
- **Layouts y parciales:** `layouts/app.blade.php`, `layouts/guest.blade.php`, `partials/sidebar.blade.php`, `partials/navbar.blade.php`, `partials/footer.blade.php`
- **Autenticación:** `auth/login.blade.php`, `auth/forgot-password.blade.php`, `auth/reset-password.blade.php`
- **Explorador documental SGI y documentos:**
  - `folders/index.blade.php`, `folders/show.blade.php`, `folders/permissions.blade.php`
  - `folders/partials/tree-panel.blade.php`, `folders/partials/tree-node.blade.php`
  - `documents/show.blade.php`, `documents/search.blade.php`
- **Notificaciones y Perfil:** `notifications/index.blade.php`, `profile/edit.blade.php`
- **Administración:** `admin/users/{index,create,edit}.blade.php`, `admin/trash/index.blade.php`, `admin/audit-logs/index.blade.php`

### Pruebas automatizadas (`tests/`)
- `tests/Feature/`: `AuthTest.php`, `AdminUsersTest.php`, `FolderPermissionTest.php`, `DocumentExplorerTest.php`, `AuditTrashCommentsNotificationsTest.php`, `ProfileTest.php`, `SecurityAuditTest.php`, `ExampleTest.php`
- `tests/Unit/`: `ExampleTest.php`

### Últimos commits en `main` (`origin/main`)
- `5793e64` — Actualizar explorador documental con arbol jerarquico de carpetas (explorador de dos paneles SGI)
- `1d85d34` — Actualizar textos del footer institucional y enlace a LA TRIADA
- `3c119f4` — Eliminar redundancia de textos en tarjeta de foto de perfil
- `33035f6` — Alinear posicion y dimensiones exactas del logotipo entre modo claro y oscuro
- `cd11758` — Eliminar redundancia de textos y optimizar vista de notificaciones

---

## 4. Siguientes pasos pendientes (en orden exacto)

> **Nota sobre el Explorador documental SGI de dos paneles:** ya fue implementado localmente y subido a `origin/main` en el commit `5793e64`.

1. **Subir la documentación de seguimiento al repositorio (`origin/main`):**
   - Hacer commit y `git push` de `SDR_PROYECTO_ESTADO_ACTUAL.md`, `CLAUDE.md`, `.agent/rules/contexto_sdr.md` y `.agent/rules/memoria-proyecto.md`.
2. **Desplegar los últimos cambios en Staging (`https://pruebas.sdrservicios.cl`):**
   - En `~/sdr-v2-staging`, ejecutar `git pull origin main` (para incorporar desde `cd11758` hasta `5793e64` y la documentación) seguido de `php artisan view:clear && php artisan config:clear && php artisan route:clear`.
3. **Validación funcional con usuarios en Staging:**
   - Probar en `https://pruebas.sdrservicios.cl`: explorador jerárquico SGI de dos paneles, creación/navegación de subcarpetas, subida concurrente de documentos, permisos con `DENY` absoluto, comentarios y campana/bandeja de notificaciones, modo claro/oscuro y perfil de usuario.
4. **Paso a Producción (`https://intranet.sdrservicios.cl`):**
   - Una vez aprobada la validación en staging, desplegar `main` en `~/sdr-v2` (migraciones si aplican, limpieza de cachés y verificación operativa).
