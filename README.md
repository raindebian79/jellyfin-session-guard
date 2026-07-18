# Jellyfin Session Guard

Un microservicio ligero desarrollado en PHP y empaquetado en Docker, diseñado para auditar, controlar y limitar de forma automatizada las sesiones simultáneas en servidores de **Jellyfin**. 

Evita que las cuentas de usuario se compartan de forma desmedida y optimiza los recursos de transcodificación y ancho de banda de tu servidor.

## 🚀 Características Principales

* **Límite Global Inteligente:** Restringe de forma masiva el número de conexiones simultáneas permitidas por defecto para todos los usuarios estándar del servidor.
* **Inmunidad Automatizada para Administradores:** Identifica dinámicamente mediante la API si una sesión pertenece a una cuenta con privilegios de administrador (`IsAdministrator`) y le otorga inmunidad total, eliminando la necesidad de gestionar listas blancas manuales.
* **Políticas de Conexión Configurables (`keep_session`):**
  * `newest`: Mantiene la sesión más reciente y desconecta de forma automática la sesión más antigua (ideal para que la reproducción "siga" al usuario si cambia de dispositivo).
  * `oldest`: Mantiene la sesión activa original y bloquea los intentos de conexión excedentes posteriores.
* **Arquitectura Manos Libres:** Opera mediante ciclos constantes de patrullaje (*polling*) configurables por tiempo (ej. cada 30 segundos) sin necesidad de instalar plugins o dependencias complejas dentro de Jellyfin.
* **Configuración Desacoplada:** Toda la lógica de negocio se gestiona a través de archivos de configuración externos (`config.yaml` y `.env`), protegiendo la seguridad de los tokens de acceso del servidor.
* **Despliegue Rápido:** Listo para correr en cualquier entorno Linux mediante Docker y Docker Compose.

## 🛠️ Requisitos del Sistema

* Docker y Docker Compose
* Un servidor Jellyfin activo con acceso a su API (Token de Administrador)
