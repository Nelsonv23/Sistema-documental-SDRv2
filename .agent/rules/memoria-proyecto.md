---
trigger: always_on
---

# Protocolo Obligatorio de Contexto y Estado (SDR)

1. **Lectura inicial:** Antes de responder o modificar código en cualquier sesión nueva, lee siempre `CLAUDE.md` y `SDR_PROYECTO_ESTADO_ACTUAL.md` para conocer las decisiones arquitectónicas cerradas y el punto exacto donde quedó el proyecto.
2. **Actualización automática:** Al finalizar cualquier tarea, implementación o cambio relevante, actualiza directamente la sección de estado y pendientes en `SDR_PROYECTO_ESTADO_ACTUAL.md` (y en `CLAUDE.md` si se cerró una decisión técnica) dejando registrado:
    - Qué se completó y qué archivos se tocaron.
    - Cuál es el siguiente paso pendiente exacto.
3. **Cierre de turno:** Nunca termines una respuesta solo con "listo"; resume brevemente lo hecho y recuerda explícitamente el siguiente paso según el archivo de estado.
