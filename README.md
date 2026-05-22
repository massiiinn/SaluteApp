# 🏥 Salute — Sistema de Gestión de Citas Médicas

Proyecto de fin de grado (TFG) del ciclo **Desarrollo de Aplicaciones Web (DAW)**.

Salute es una plataforma web completa para la gestión de citas médicas con asistente conversacional inteligente, notificaciones automáticas por email y sistema de roles diferenciado.

🌐 **Producción:** [http://grup11.infla.cat](http://grup11.infla.cat)

---

## 🛠️ Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Frontend | Angular 17+ (standalone components) |
| Backend | Symfony 7 + PHP 8.4 |
| Base de datos | MySQL 8.0 |
| Autenticación | JWT (LexikJWTAuthenticationBundle) |
| IA | Groq API (Llama 3.3-70b / Llama 3.1-8b) |
| Email | Gmail SMTP / Mailpit (desarrollo) |
| Contenedores | Docker + Docker Compose |
| Producción | Kubernetes (clúster grup11) |

---

## 📋 Requisitos previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Node.js 18+](https://nodejs.org/) y npm
- [Angular CLI](https://angular.io/cli): `npm install -g @angular/cli`

---

## 🚀 Instalación y puesta en marcha (local)

### 1. Clonar el repositorio

```bash
git clone <url-del-repositorio>
cd salute-app
```

### 2. Configurar variables de entorno

Edita `salute-backend/.env` y configura:

```env
DATABASE_URL="mysql://salute_user:salute_pass@mysql:3306/salute_db?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN=gmail://TU_EMAIL%40gmail.com:TU_APP_PASSWORD@default
GROQ_API_KEY=tu_api_key_de_groq
JWT_PASSPHRASE=salute_jwt_secret
```

> Para obtener una App Password de Gmail: Cuenta Google → Seguridad → Verificación en dos pasos → Contraseñas de aplicaciones.
>
> Para obtener una API Key de Groq: [console.groq.com](https://console.groq.com)

### 3. Arrancar el backend con Docker

```bash
docker compose up -d --build
```

Esto levanta 4 contenedores:
- `salute_php` — PHP 8.4 FPM + Symfony
- `salute_nginx` — Nginx (puerto 8080)
- `salute_mysql` — MySQL 8.0 (puerto 3306)
- `salute_mailpit` — Servidor de email local (puerto 8025)

### 4. Instalar dependencias PHP

```bash
docker exec salute_php composer install
```

### 5. Generar claves JWT

```bash
docker exec salute_php php bin/console lexik:jwt:generate-keypair
```

### 6. Crear la base de datos y ejecutar migraciones

```bash
docker exec salute_php php bin/console doctrine:database:create --if-not-exists
docker exec salute_php php bin/console doctrine:migrations:migrate --no-interaction
```

### 7. Cargar datos de prueba (fixtures)

```bash
docker exec salute_php php bin/console doctrine:fixtures:load --no-interaction
```

### 8. Arrancar el frontend

```bash
cd salute-frontend
npm install
ng serve
```

La aplicación estará disponible en [http://localhost:4200](http://localhost:4200)

---

## ☸️ Despliegue en producción (Kubernetes)

La aplicación está desplegada en un clúster Kubernetes en el namespace `grup11`.

### Imágenes Docker

```bash
# Construir y subir imágenes a Harbor
docker build -t kube0.lacetania.cat/grup11/salute-php:1.0 ./salute-backend/
docker push kube0.lacetania.cat/grup11/salute-php:1.0

docker build -t kube0.lacetania.cat/grup11/salute-nginx:1.0 -f - . << 'EOF'
FROM nginx:alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
EOF
docker push kube0.lacetania.cat/grup11/salute-nginx:1.0
```

### Aplicar manifests

```bash
kubectl apply -f k8s/
```

### Actualizar el frontend en producción

```bash
# 1. Compilar en local
cd salute-frontend
ng build --configuration production

# 2. Subir al servidor
scp -r dist/ grup11@infla.cat:~/salute-app/salute-frontend/

# 3. Copiar al pod Nginx
kubectl cp ~/salute-app/salute-frontend/dist/salute-frontend/browser/. <pod-nginx>:/usr/share/nginx/html/
```

### Actualizar el backend en producción

```bash
# Copiar código al pod PHP
kubectl cp ~/salute-app/salute-backend/. <pod-php>:/var/www/html/

# Arreglar permisos y limpiar caché
kubectl exec -it <pod-php> -- chmod -R 755 /var/www/html/vendor/
kubectl exec -it <pod-php> -- php bin/console cache:clear
```

---

## 👥 Credenciales de prueba

| Rol | Email | Contraseña |
|-----|-------|-----------|
| Administrador | admin@salute.com | admin123 |
| Médico | carlos@salute.com | doctor123 |
| Paciente | juan@email.com | patient123 |

---

## ✨ Funcionalidades principales

### Gestión de citas
- Crear, editar, cancelar y eliminar citas médicas
- Validación de horarios — no se pueden solapar citas del mismo médico (±30 min)
- Filtros y ordenación por columnas
- Expiración automática de citas pasadas (cron)

### Asistente conversacional IA
- Chat flotante integrado en toda la aplicación
- Crear nueva cita con lenguaje natural
- Cancelar una cita existente
- Reprogramar a otra fecha/hora
- Cambiar el médico de una cita
- Consultar citas actuales

### Sugerencia de especialidad
- El paciente describe sus síntomas
- La IA recomienda la especialidad adecuada
- Selección automática del médico correspondiente

### Notificaciones por email
| Evento | Asunto |
|--------|--------|
| Crear cita | ✅ Cita confirmada |
| Cancelar cita | ❌ Cita cancelada |
| Reprogramar cita | 📅 Cita reprogramada |
| Cambiar médico | 👨‍⚕️ Médico actualizado |
| Recordatorio 24h antes | ⏰ Recordatorio de cita |
| Recuperar contraseña | 🔐 Restablecer contraseña |

### Sistema de roles
| Funcionalidad | Paciente | Médico | Admin |
|---------------|----------|--------|-------|
| Ver sus citas | ✅ | ✅ | ✅ |
| Crear citas | ✅ | ✅ | ✅ |
| Editar citas | ❌ | ✅ | ✅ |
| Gestionar médicos | ❌ | ❌ | ✅ |
| Gestionar pacientes | ❌ | ❌ | ✅ |
| Gestionar especialidades | ❌ | ❌ | ✅ |

### Otras funcionalidades
- Modo oscuro con persistencia
- Perfil de usuario con avatar personalizable
- Recuperación de contraseña por email
- Calendario interactivo en el dashboard
- Landing page explicativa
- Diseño responsive

---

## 🗂️ Estructura del proyecto

```
salute-app/
├── salute-backend/          # Symfony 7
│   ├── src/
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Command/         # SendRemindersCommand, ExpireAppointmentsCommand
│   │   └── DataFixtures/
│   ├── templates/emails/    # Plantillas de email (Twig)
│   ├── config/
│   └── Dockerfile
├── salute-frontend/         # Angular 17
│   └── src/app/
│       ├── pages/
│       ├── components/      # navbar, chat
│       ├── services/
│       └── guards/
├── docker/nginx/
│   └── default.conf
├── k8s/                     # Manifests Kubernetes
│   ├── secret.yaml
│   ├── pvc.yaml
│   ├── mysql.yaml
│   ├── php.yaml
│   └── nginx.yaml
└── docker-compose.yml
```

---

## 🔧 Comandos útiles

```bash
# Ver logs del backend
docker logs salute_php -f

# Ejecutar migraciones
docker exec salute_php php bin/console doctrine:migrations:migrate --no-interaction

# Limpiar caché Symfony
docker exec salute_php php bin/console cache:clear

# Probar envío de recordatorios manualmente
docker exec salute_php php bin/console app:send-reminders

# Expirar citas pasadas manualmente
docker exec salute_php php bin/console app:expire-appointments

# Ver emails de desarrollo (Mailpit)
open http://localhost:8025
```

---

## 📧 Cron jobs

El contenedor PHP ejecuta dos tareas automáticas cada hora:

- `app:send-reminders` — Envía recordatorios a pacientes con cita en las próximas 24 horas
- `app:expire-appointments` — Marca como completadas las citas cuya fecha ya ha pasado

---

## 📝 Notas de desarrollo

- Los emails en desarrollo se capturan en **Mailpit** (`http://localhost:8025`) y no se envían realmente
- Para usar Gmail real, configurar `MAILER_DSN` con una App Password de Google
- La API de Groq tiene un plan gratuito suficiente para desarrollo y demo
- El frontend se conecta al backend en `http://localhost:8080`