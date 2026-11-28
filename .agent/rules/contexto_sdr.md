### contexto_sdr.md — Sistema de Gestión Documental (SDR v2)
Este archivo le da contexto al agente de desarrollo sobre el proyecto. Léelo por completo antes de proponer cambios de arquitectura, agregar dependencias o sugerir "mejoras" — la mayoría de las decisiones aquí ya fueron evaluadas y cerradas deliberadamente. Si algo parece subóptimo, es casi seguro una decisión consciente de simplicidad, no un descuido.

#### Cómo guiar la sesión de desarrollo (importante — Nelson está aprendiendo este flujo de trabajo)
Nelson es nuevo en desarrollo asistido por IA/agentes. Sigue siempre estas pautas de comportamiento, no solo el contenido técnico del proyecto:
* Al terminar cualquier tarea, **no te detengas solo con "listo"**. Resume brevemente qué hiciste, marca esa tarea como completada (`[x]`) en la lista de "Próximos pasos pendientes" de este archivo (edítala tú mismo), y **dime explícitamente cuál es el siguiente paso recomendado** según esa lista, preguntando si quiero continuar con él ahora o prefiero revisar algo primero.
* Si detectas un problema, riesgo o algo que se aparta de las decisiones ya cerradas en este archivo, dilo antes de continuar — no lo resuelvas en silencio ni sigas adelante asumiendo que está bien.
* No hagas más de lo que se te pidió en una sola tarea. Si "ayúdame con el paso 1" implica varias sub-acciones, está bien resolverlas todas, pero no saltes al paso 2 sin confirmar conmigo primero — prefiero avanzar de forma incremental y entender cada parte, no recibir el proyecto completo de una vez.
* Si en algún momento no sigo esta dinámica y solo te pido una tarea puntual, igual cierra recordándome cuál es el siguiente paso pendiente — es la forma en que quiero trabajar mientras aprendo.

---

#### Estado actual del proyecto (checkpoint inicial SDR v2)
La fuente viva del avance diario, inventario de módulos implementados, entornos y siguientes pasos se mantiene en `SDR_PROYECTO_ESTADO_ACTUAL.md` (y su resumen de decisiones cerradas en `CLAUDE.md`).
**Entorno local:** Laragon en Windows, PHP 8.4.25 (NTS, vía FastCGI con Apache), MySQL 8.0.30, Apache 2.4.54. Proyecto en `C:\Users\NELSON\Desktop\laragon\www\sdr-v2` (dominio local `http://sdr-v2.test`).
**Stack base a instalar (composer):**
* `laravel/laravel` v13.x
* `spatie/laravel-permission` v8.x
* `laravel/fortify` v1.x (dejar apagados: registro público, verificación de email, 2FA y passkeys)
* **Frontend:** Plantilla HTML/CSS estática **Purple Admin Free (Bootstrap 5.3)** alojada en `public/assets/purple/` + **Alpine.js** (CDN o local liviano) + hoja de estilos corporativa `public/css/sdr-orchid-theme.css`. **NO instalar `jeroennoten/laravel-adminlte` ni Laravel Orchid.**

#### Próximos pasos pendientes (ejecutar estrictamente en este orden)
- [x] **Paso 1 — Creación de proyecto y paquetes base:** Crear proyecto Laravel 13 limpio en `sdr-v2`, instalar `spatie/laravel-permission` y `laravel/fortify`, configurar `.env` hacia MySQL (`DB_DATABASE=sdr_v2_db`, `APP_LOCALE=es`, límites de subida PHP), eliminar `database/database.sqlite` y configurar `Paginator::useBootstrapFive()` en `AppServiceProvider.php`.
- [x] **Paso 2 — Integración de plantilla Purple Admin Free con estética Orchid:** Instalar los assets estáticos de Purple Admin Free en `public/assets/purple/`, crear el layout maestro `resources/views/layouts/app.blade.php` (con parciales `navbar`, `sidebar`, `footer` y banner global de conexión offline/online en Alpine.js) y aplicar `public/css/sdr-orchid-theme.css` (paleta azul corporativo `#002262`, `#0049A4`, `#3B6AA4`, `#424242`, `#FFFFFF` y estética limpia minimalista estilo Orchid).
- [x] **Paso 3 — Configuración de Fortify y vistas de autenticación:** Publicar Fortify, registrar únicamente `login`, `resetPasswords` y `updatePasswords` (apagando registro público, verificación de email, 2FA y passkeys), bloquear acceso a usuarios con `is_active = false`, y crear las vistas Blade de login y recuperación/reseteo de contraseña con la identidad visual de SDR v2.
- [x] **Paso 4 — Migraciones de dominio e índices:** Publicar migración de Spatie Permission y crear las migraciones propias: actualización de `users` (`is_active`, `last_login_at`, `avatar_path`), `folders` (con `path` materializado), `folder_permissions`, `documents` (con `status`, `current_version_id`, `deleted_at`, `deleted_by` e índice FULLTEXT), `document_versions`, `comments`, `notifications` y `audit_logs` (con `actor_name`/`actor_email` snapshot). Ejecutar `php artisan migrate` en MySQL.
- [x] **Paso 5 — Modelos, relaciones, Seeders y Administración de Usuarios/Roles:** Configurar relaciones Eloquent, crear `RolesAndPermissionsSeeder` (roles: `Administrador`, `Jefatura`, `Colaborador`) y `UsersSeeder` (`admin@sdr.local` / `Sdr2026!`, más usuarios de prueba por rol), e implementar el módulo web exclusivo de Administrador para crear usuarios, cambiar su rol y activar/desactivar cuentas (`admin/users`).
- [x] **Paso 6 — Servicio de permisos jerárquicos y Observer de carpetas:** Implementar `FolderPermissionService` con regla **DENY absoluto sin excepciones hacia abajo** y `accessibleFolderIds()` sin consultas N+1, más `FolderObserver` para calcular y recalcular `path` materializado en transacciones al crear o mover carpetas. Crear pruebas unitarias exhaustivas.
- [x] **Paso 7 — Módulo "Mi Perfil" (datos, foto privada y contraseña):** Implementar `ProfileController` y `resources/views/profile/edit.blade.php` accesible para todos los roles autenticados: edición de nombre, gestión de avatar en disco privado (`storage/app/private/avatars/{uuid}` servido vía ruta autenticada `Storage::response()` sin `storage:link`, con respaldo de iniciales SVG), y cambio de contraseña exigiendo `current_password` vía `UpdateUserPassword` de Fortify.
- [x] **Paso 8 — Núcleo documental, búsqueda híbrida y subida múltiple Alpine.js:** Implementar `FolderPolicy`, `DocumentPolicy`, `DocumentStorageService` (UUID en disco privado, SHA-256 por streaming, compensación ante fallos BD/filesystem, manejo de duplicados `replace` y `keep_both`, previsualización inline vs descarga streaming), comando diario `documents:clean-orphans`, `DocumentSearchService` (híbrido FULLTEXT + LIKE + filtros), liberación temprana de sesión (`$request->session()->save()`) y explorador en Bootstrap 5.3 + Alpine.js (cola de máx. 2 subidas concurrentes, 1 archivo por petición HTTP, reintentos automáticos silenciosos).
- [x] **Paso 9 — Papelera de 60 días, auditoría inmutable, comentarios y notificaciones:** Implementar `AuditLogger` inmutable (conectado a auth, usuarios, carpetas, ciclo de vida completo de documentos, comentarios y perfil), papelera con restauración exclusiva por Administrador y comando diario `documents:purge-trash` (borra binarios de todas las `document_versions` y marca `status = 'purged'` preservando la fila y `audit_logs`), comentarios en documentos y polling liviano de notificaciones cada 60s (pausado en pestaña oculta o inactividad).
- [x] **Paso 10 — Auditoría final UI/UX y verificación completa:** Ejecutar suite completa de pruebas automatizadas (`php artisan test`) y revisar consistencia visual Bootstrap 5.3 + estética Orchid en todas las vistas antes del despliegue a cPanel.

---

#### Qué es este proyecto
Sistema de Gestión Documental (SDR v2) web para una pyme de ~30 colaboradores. Centraliza documentos corporativos hoy dispersos en correos/carpetas personales, con control de acceso por carpeta, búsqueda, comentarios, gestión de perfil de usuario y trazabilidad inmutable. Prioridad absoluta: **simplicidad, bajo costo, excelente experiencia UI/UX y resiliencia en hosting compartido**, no flexibilidad ni escalabilidad máxima.

#### Regla de oro para cualquier sugerencia
No proponer microservicios, Kubernetes, Redis, colas persistentes (Horizon), Elasticsearch/OpenSearch, WebSockets/Reverb, ni ninguna infraestructura adicional. Si una tarea "necesitaría" algo de eso, la respuesta correcta es resolverlo de forma más simple dentro del stack actual, no agregar la pieza.

---

#### Identidad Visual y Lineamientos UI/UX (Purple Admin + Estética Orchid)
* **Base visual:** Plantilla **Purple Admin Free (Bootstrap 5.3)** adaptada mediante `public/css/sdr-orchid-theme.css` para lograr una interfaz minimalista, limpia y corporativa inspirada en **Laravel Orchid** (sin instalar dependencias PHP de Orchid).
* **Paleta de colores oficial:**
  * Azul profundo: `#002262`
  * Azul corporativo: `#0049A4`
  * Azul medio: `#3B6AA4`
  * Gris grafito: `#424242`
  * Blanco: `#FFFFFF`
  * Superficie base: `#F8F9FA` (fondo de trabajo) y `#FFFFFF` (tarjetas con borde fino `1px solid #E9ECEF` y sombras muy sutiles).
  * Degradado institucional (login y acentos clave): `linear-gradient(90deg, #002262 0%, #0049A4 65%, #3B6AA4 100%)`
* **Criterios de diseño UI/UX estilo Orchid sobre Bootstrap 5.3:**
  * Reemplazar todos los acentos morados/rosados por defecto de Purple Admin con la paleta azul corporativa.
  * **Sidebar:** Ancho compacto (`240px`), fondo azul profundo `#002262` (o blanco minimalista `#FFFFFF` con borde derecho `#E9ECEF`), secciones en mayúsculas discretas (`11px`, `letter-spacing: 0.08em`) e ítems activos limpios sin degradados estridentes.
  * **Cabecera de página (Command Bar):** Breadcrumbs discretos arriba, título limpio a la izquierda y botones de acción principales alineados a la derecha (`+ Subir archivo`, `+ Nueva carpeta`) con íconos lineales finos.
  * **Tablas de datos:** Sin bordes verticales, encabezados planos con fondo suave (`#F1F3F5`), espaciado vertical generoso (`padding: 0.85rem 1rem`), texto en `#424242` y menú contextual (`⋮`) o acciones limpias por fila.
  * **Formularios y Perfil:** Inputs rectangulares limpios (`border-radius: 6px`), etiquetas claras superiores, distribución ordenada en dos columnas para pantallas de configuración/perfil y estados vacíos (*empty states*) bien centrados con ícono descriptivo.
  * **Convenciones Bootstrap 5.3:** Usar exclusivamente atributos de Bootstrap 5 (`data-bs-toggle`, `data-bs-dismiss`, `ms-*`, `me-*`, `ps-*`, `pe-*`) y `Paginator::useBootstrapFive()`.

---

#### Stack técnico (cerrado)
* **Arquitectura:** Monolito Laravel.
* **Backend:** Laravel 13, PHP 8.4.
* **Frontend:** Blade + Bootstrap 5.3 (Purple Admin Free + `sdr-orchid-theme.css`) + Alpine.js. Sin compilación Vite/Node obligatoria en producción.
* **Auth:** Laravel Fortify (login, logout, recuperación de contraseña, cambio de contraseña, rate limiting). Registro público, verificación de email, Google OAuth, 2FA y passkeys desactivados.
* **Roles y permisos:** `spatie/laravel-permission` para RBAC global (`Administrador`, `Jefatura`, `Colaborador`).
* **Base de datos:** MySQL 8.0.
* **Hosting de destino:** V2Networks, cPanel/CloudLinux compartido, Plan Emprendedor (50GB NVMe, 2 núcleos CPU, 4GB RAM, backup diario).
* **Almacenamiento de archivos y avatares:** Disco privado del hosting vía Laravel Storage/Flysystem (`storage/app/private/documents/{uuid}` y `storage/app/private/avatars/{uuid}`) — **nunca en `public/`, nunca `storage:link`**, siempre autorización server-side antes de servir un archivo o avatar. Preparado para migrar a Google Drive/Shared Drive a futuro sin reescribir lógica de negocio.
* **Tiempo real:** Sin WebSockets/Reverb. Notificaciones vía polling liviano cada 60 segundos (endpoint que devuelve solo `unread_count` usando consulta indexada), pausado automáticamente si `document.hidden === true` o tras 3 minutos de inactividad.

---

#### Modelo de permisos (cerrado — no rediseñar)
RBAC global + permisos de alcance por carpeta con herencia. Un rol ve por defecto los directorios autorizados; se puede negar acceso a carpetas específicas para un rol completo o un usuario individual.
**Regla de precedencia: "DENY absoluto sin excepciones hacia abajo".** Una vez que algo queda denegado en un nivel de la jerarquía, ninguna subcarpeta inferior puede reabrirlo, sin importar qué tan específico sea un ALLOW más abajo.
* `folders` es autorreferencial (`parent_id`) y tiene una columna `path` materializada (ej. `/1/4/12/`) para resolver la herencia con una sola consulta indexada en vez de recorrer el árbol. **Al crear o mover una carpeta, calcular/recalcular su `path` y el de todos sus descendientes dentro de una transacción (`FolderObserver`).**
* `folder_permissions` usa `subject_type` + `subject_id` (polimórfico: `role` o `user`) y un campo `access` (`allow`/`deny`). Restricción `UNIQUE(folder_id, subject_type, subject_id)`.

#### Documentos, versiones y perfil de usuario (cerrado)
* `documents` es la entidad lógica (`folder_id`, `name`, `status` [`active`, `trashed`, `purged`], `current_version_id`, `created_by`, `deleted_at`, `deleted_by`). `document_versions` guarda los archivos físicos (`storage_key`, `storage_disk`, `original_name`, `mime_type`, `size`, `sha256`, `uploaded_by`, `is_active`).
* `documents` tiene `current_version_id` apuntando a la versión activa.
* El versionamiento **no es visible ni administrable por el usuario en la UI**, pero el backend lo usa internamente: al reemplazar un documento duplicado, crea una nueva fila en `document_versions` activa y deja la anterior inactiva sin borrar el binario previo.
* `storage_key` es siempre un UUID generado, nunca el nombre original del archivo.
* Vista previa en navegador (`Storage::response()`): `pdf`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `txt`. Descarga directa (`Storage::download()`): `docx`, `xlsx`, `pptx`, `zip` y resto.
* **Módulo Mi Perfil (`/profile`):** Disponible para todos los roles activos (`Administrador`, `Jefatura`, `Colaborador`). Permite actualizar nombre, subir/reemplazar/eliminar foto de perfil en disco privado (`avatars/{uuid}`, máx. 2 MB, formatos `jpg`, `jpeg`, `png`, `webp`, eliminando el binario previo al reemplazar) y cambiar contraseña exigiendo `current_password`. Muestra en solo lectura correo, rol asignado y `last_login_at`.

#### Subida de archivos (cerrado)
* **Un archivo por petición HTTP**, nunca todo el lote en una sola petición.
* Cola de subida en Alpine.js con **2 subidas concurrentes máximo por usuario**, sin límite artificial en la cantidad total de archivos seleccionados.
* Rate limiting de respaldo en el servidor (`60/min`) sobre la ruta de subida.
* Reintento automático silencioso (hasta 2 veces) ante fallo de red/5xx antes de marcar error definitivo y mostrar botón de reintentar manual.
* Límites de PHP: `upload_max_filesize=60M`, `post_max_size=64M`, `memory_limit=512M`, `max_execution_time=3600`, `max_input_time=3600`.
* Descargas siempre vía streaming (`Storage::download()` / `Storage::response()`), nunca `file_get_contents()`.
* Liberar la sesión temprano (`$request->session()->save()`) apenas se valida autorización en subida, vista previa, descarga, avatar y polling de notificaciones.
* Compensación entre filesystem y MySQL: guardar archivo → confirmar existencia → insertar en BD → si falla BD, borrar archivo huérfano. Cron diario `documents:clean-orphans` limpia archivos sin registro en BD con más de 24 horas.

#### Papelera y auditoría (cerrado)
* Papelera: purga automática a los 60 días (`documents:purge-trash`). Al purgar, elimina los binarios físicos de todas sus `document_versions` pero **no elimina la fila en `documents`** — la marca como `status = 'purged'` para preservar integridad con `audit_logs`.
* La restauración de un documento eliminado es **exclusiva del Administrador**.
* `audit_logs` es inmutable (bloquea `update` y `delete` en el modelo vía `LogicException`). Registra: `login`, `login_failed`, `logout`, gestión de usuarios/roles, creación/permisos de carpetas, ciclo de vida completo de documentos (`uploaded`, `replaced`, `viewed`, `downloaded`, `deleted`, `restored`, `purged`), `comment.created`, `comment.deleted` y cambios de perfil (`profile.updated`, `profile.avatar_updated`, `profile.password_changed`).
* Guardar `actor_name` y `actor_email` como snapshot en `audit_logs` además de `actor_id`.

#### Búsqueda (cerrado)
Modelo híbrido en MySQL, sin motores externos: índice `FULLTEXT` en `documents.name` (`MATCH() AGAINST() IN BOOLEAN MODE`) combinado con `LIKE` para códigos cortos/números y filtros SQL normales por carpeta (con subcarpetas opcionales), extensión, autor y rango de fechas, restringido siempre a las carpetas accesibles del usuario.

#### Explícitamente fuera de alcance — no implementar
App móvil, Elasticsearch/OpenSearch, API REST completa, edición de documentos, OCR, IA, firma electrónica, workflow de aprobación, multiempresa, colaboración en tiempo real avanzada, versionamiento visible en la UI, Google Drive como almacenamiento inicial, notificaciones por correo, autenticación de dos factores (2FA), WebSockets/Reverb, Redis, AdminLTE o paquetes PHP de Orchid.

#### Convenciones de desarrollo
* Comandos estándar: `composer install`, `php artisan migrate`, `php artisan test`, `php artisan schedule:list`.
* Cualquier acceso a un archivo o avatar debe pasar por autorización explícita antes de servir el binario desde el disco privado.
* Índices obligatorios en migraciones: `(folder_id, status, deleted_at, name)` y `FULLTEXT(name)` en `documents`; `UNIQUE(folder_id, subject_type, subject_id)` en `folder_permissions`; `(created_at, actor_id, action)` en `audit_logs`; `(user_id, read_at)` en `notifications`.
* Priorizar pruebas automatizadas en: matriz de permisos (`ALLOW`/`DENY`/herencia), subida/descarga/duplicados con versiones, papelera/purga, auditoría inmutable y gestión de perfil/avatar privado.