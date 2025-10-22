# EventFlow Pro

A modern full-stack event management application built with React, Laravel, and Docker.

## 🚀 Tech Stack

- **Frontend**: React 19 + Vite
- **Backend**: Laravel 10 + PHP 8.2
- **Database**: MySQL 8.0
- **Server**: Nginx (Reverse Proxy)
- **Containerization**: Docker & Docker Compose

## 📋 Prerequisites

- Docker & Docker Compose installed
- Git
- Port 8081 available on your machine

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/ElmehdiElkabia/eventflow_pro.git
   cd eventflow_pro
   ```

2. **Set up environment variables**
   ```bash
   cp .env.example .env
   ```
   
   Edit `.env` and configure your database credentials:
   ```env
   DB_DATABASE=eventflow
   DB_USERNAME=eventflow_user
   DB_PASSWORD=your_secure_password
   MYSQL_ROOT_PASSWORD=your_root_password
   ```

3. **Start the application**
   ```bash
   docker compose up -d
   ```

4. **Install backend dependencies and setup**
   ```bash
   docker compose exec backend composer install
   docker compose exec backend php artisan key:generate
   docker compose exec backend php artisan migrate
   ```

5. **Access the application**
   - Frontend: http://localhost:81
   - API: http://localhost:81/api
   - Database: localhost:3307

## 🏗️ Project Structure

```
eventflow_pro/
├── frontend/           # React application
│   ├── src/
│   ├── public/
│   ├── Dockerfile
│   └── vite.config.js
├── backend/            # Laravel application
│   ├── app/
│   ├── routes/
│   ├── database/
│   ├── Dockerfile
│   └── artisan
├── nginx/              # Nginx configuration
│   └── nginx.conf
├── docker-compose.yml  # Docker orchestration
└── .env               # Environment variables
```

## 🔧 Development

### Running the application

```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f

# Stop all services
docker compose down
```

### Backend Commands

```bash
# Run artisan commands
docker compose exec backend php artisan [command]

# Run migrations
docker compose exec backend php artisan migrate

# Create a new controller
docker compose exec backend php artisan make:controller ControllerName

# Clear cache
docker compose exec backend php artisan cache:clear
```

### Frontend Commands

```bash
# Install dependencies
docker compose exec frontend npm install

# Build for production
docker compose exec frontend npm run build
```

## 🌐 Nginx Configuration

The nginx service acts as a reverse proxy:
- Routes `/api/*` requests to Laravel backend (port 8000)
- Routes `/sanctum/*` requests to Laravel backend
- Routes all other requests to React frontend (port 3000)
- Supports WebSocket for Vite HMR (Hot Module Replacement)

## 📦 Docker Services

| Service | Port (Host:Container) | Description |
|---------|----------------------|-------------|
| nginx | 81:80 | Reverse proxy |
| frontend | 3000 (internal) | React + Vite |
| backend | 8000 (internal) | Laravel + PHP-FPM |
| db | 3307:3306 | MySQL 8.0 |

## 🔒 Security

- `.env` file is gitignored and should never be committed
- Backend and frontend are not directly exposed (only through nginx)
- Use `.env.example` as a template for environment variables

## 🐛 Troubleshooting

### Container won't start
```bash
docker compose down
docker compose up -d --build
```

### Database connection issues
```bash
# Check if database is running
docker compose ps

# Check database logs
docker compose logs db
```

### Permission issues (Linux)
```bash
# Fix storage permissions
docker compose exec backend chmod -R 775 storage bootstrap/cache
```

### Clear all caches
```bash
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan route:clear
docker compose exec backend php artisan view:clear
```

## 📝 API Documentation

API endpoints are available at `http://localhost:81/api`

### Example Endpoints
- `GET /api/user` - Get authenticated user (requires authentication)
- `POST /api/login` - User login
- `POST /api/register` - User registration

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is open-source and available under the MIT License.

## 👨‍💻 Author

**Elmehdi Elkabia**
- GitHub: [@ElmehdiElkabia](https://github.com/ElmehdiElkabia)

## 🙏 Acknowledgments

- Laravel Framework
- React Team
- Docker Community