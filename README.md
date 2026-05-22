# 🏥 Salute — Sistema de Gestió de Cites Mèdiques

Projecte de Síntesi de **Desenvolupament d'Aplicacions Web (DAW)**.

Salute és una plataforma web completa per a la gestió de cites mèdiques amb assistent conversacional intel·ligent, notificacions automàtiques per correu electrònic i sistema de rols diferenciat.

🌐 **Producció:** [http://grup11.infla.cat](http://grup11.infla.cat)

---

## 🛠️ Stack tecnològic

| Capa | Tecnologia |
|------|-----------|
| Frontend | Angular 17+ (standalone components) |
| Backend | Symfony 7 + PHP 8.4 |
| Base de dades | MySQL 8.0 |
| Autenticació | JWT (LexikJWTAuthenticationBundle) |
| IA | Groq API (Llama 3.3-70b / Llama 3.1-8b) |
| Correu | Gmail SMTP / Mailpit (desenvolupament) |
| Contenidors | Docker + Docker Compose |
| Producció | Kubernetes (clúster grup11) |

---

## 📋 Requisits previs

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Node.js 18+](https://nodejs.org/) i npm
- [Angular CLI](https://angular.io/cli): `npm install -g @angular/cli`

---

## 🚀 Instal·lació i posada en marxa (local)

### 1. Clonar el repositori

```bash
git clone https://github.com/massiiinn/SaluteApp.git
cd SaluteApp
```

### 2. Configurar variables d'entorn

Edita `salute-backend/.env` i configura:

```env
DATABASE_URL="mysql://salute_user:salute_pass@mysql:3306/salute_db?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN=gmail://EL_TEU_EMAIL%40gmail.com:EL_TEU_APP_PASSWORD@default
GROQ_API_KEY=la_teva_api_key_de_groq
JWT_PASSPHRASE=salute_jwt_secret
```

> Per obtenir una App Password de Gmail: Compte Google → Seguretat → Verificació en dos passos → Contrasenyes d'aplicació.
>
> Per obtenir una API Key de Groq: [console.groq.com](https://console.groq.com)

### 3. Arrancar el backend amb Docker

```bash
docker compose up -d --build
```

Això aixeca 4 contenidors:
- `salute_php` — PHP 8.4 FPM + Symfony
- `salute_nginx` — Nginx (port 8080)
- `salute_mysql` — MySQL 8.0 (port 3306)
- `salute_mailpit` — Servidor de correu local (port 8025)

### 4. Instal·lar dependències PHP

```bash
docker exec salute_php composer install
```

### 5. Generar claus JWT

```bash
docker exec salute_php php bin/console lexik:jwt:generate-keypair
```

### 6. Crear la base de dades i executar migracions

```bash
docker exec salute_php php bin/console doctrine:database:create --if-not-exists
docker exec salute_php php bin/console doctrine:migrations:migrate --no-interaction
```

### 7. Carregar dades de prova (fixtures)

```bash
docker exec salute_php php bin/console doctrine:fixtures:load --no-interaction
```

### 8. Arrancar el frontend

```bash
cd salute-frontend
npm install
ng serve
```

L'aplicació estarà disponible a [http://localhost:4200](http://localhost:4200)

---

## ☸️ Desplegament en producció (Kubernetes)

L'aplicació està desplegada en un clúster Kubernetes al namespace `grup11`.

### Imatges Docker

```bash
# Construir i pujar imatges a Harbor
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

### Actualitzar el frontend en producció

```bash
# 1. Compilar en local
cd salute-frontend
ng build --configuration production

# 2. Pujar al servidor
scp -r dist/ grup11@infla.cat:~/salute-app/salute-frontend/

# 3. Copiar al pod Nginx
kubectl cp ~/salute-app/salute-frontend/dist/salute-frontend/browser/. <pod-nginx>:/usr/share/nginx/html/
```

### Actualitzar el backend en producció

```bash
# Copiar codi al pod PHP
kubectl cp ~/salute-app/salute-backend/. <pod-php>:/var/www/html/

# Arreglar permisos i netejar caché
kubectl exec -it <pod-php> -- chmod -R 755 /var/www/html/vendor/
kubectl exec -it <pod-php> -- php bin/console cache:clear
```

---

## 👥 Credencials de prova

| Rol | Email | Contrasenya |
|-----|-------|-----------|
| Administrador | admin@salute.com | admin123 |
| Metge | carlos@salute.com | doctor123 |
| Pacient | juan@email.com | patient123 |

---

## ✨ Funcionalitats principals

### Gestió de cites
- Crear, editar, cancel·lar i eliminar cites mèdiques
- Validació d'horaris — no es poden solapar cites del mateix metge (±30 min)
- Filtres i ordenació per columnes
- Expiració automàtica de cites passades (cron)

### Assistent conversacional IA
- Xat flotant integrat a tota l'aplicació
- Crear nova cita amb llenguatge natural
- Cancel·lar una cita existent
- Reprogramar a una altra data/hora
- Canviar el metge d'una cita
- Consultar cites actuals

### Suggeriment d'especialitat
- El pacient descriu els seus símptomes
- La IA recomana l'especialitat adequada
- Selecció automàtica del metge corresponent

### Notificacions per correu
| Esdeveniment | Assumpte |
|--------|--------|
| Crear cita | ✅ Cita confirmada |
| Cancel·lar cita | ❌ Cita cancel·lada |
| Reprogramar cita | 📅 Cita reprogramada |
| Canviar metge | 👨‍⚕️ Metge actualitzat |
| Recordatori 24h abans | ⏰ Recordatori de cita |
| Recuperació de contrasenya | 🔐 Restablir contrasenya |

### Sistema de rols
| Funcionalitat | Pacient | Metge | Admin |
|---------------|----------|--------|-------|
| Veure cites | ✅ | ✅ | ✅ |
| Crear cites | ✅ | ✅ | ✅ |
| Editar cites | ❌ | ✅ | ✅ |
| Gestionar metges | ❌ | ❌ | ✅ |
| Gestionar pacients | ❌ | ❌ | ✅ |
| Gestionar especialitats | ❌ | ❌ | ✅ |

### Altres funcionalitats
- Mode fosc amb persistència
- Perfil d'usuari amb avatar personalitzable
- Recuperació de contrasenya per correu
- Calendari interactiu al dashboard
- Landing page explicativa
- Disseny responsive

---

## 🗂️ Estructura del projecte

```
SaluteApp/
├── salute-backend/          # Symfony 7
│   ├── src/
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Command/         # SendRemindersCommand, ExpireAppointmentsCommand
│   │   └── DataFixtures/
│   ├── templates/emails/    # Plantilles de correu (Twig)
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

## 🔧 Comandes útils

```bash
# Veure logs del backend
docker logs salute_php -f

# Executar migracions
docker exec salute_php php bin/console doctrine:migrations:migrate --no-interaction

# Netejar caché Symfony
docker exec salute_php php bin/console cache:clear

# Provar enviament de recordatoris manualment
docker exec salute_php php bin/console app:send-reminders

# Expirar cites passades manualment
docker exec salute_php php bin/console app:expire-appointments

# Veure correus de desenvolupament (Mailpit)
open http://localhost:8025
```

---

## 📧 Tasques automàtiques (Cron)

El contenidor PHP executa dues tasques automàtiques cada hora:

- `app:send-reminders` — Envia recordatoris als pacients amb cita en les pròximes 24 hores
- `app:expire-appointments` — Marca com a completades les cites la data de les quals ja ha passat

---

## 📝 Notes de desenvolupament

- Els correus en desenvolupament es capturen a **Mailpit** (`http://localhost:8025`) i no s'envien realment
- Per usar Gmail real, configurar `MAILER_DSN` amb una App Password de Google
- L'API de Groq té un pla gratuït suficient per al desenvolupament i la demo
- El frontend es connecta al backend a `http://localhost:8080`
