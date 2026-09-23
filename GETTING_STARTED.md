# 🚀 Getting Started with SenzDigitals Framework

Welcome to the **SenzDigitals Framework**, a lightweight and modular custom PHP framework tailored for API development. This guide will walk you through the process of setting up your local development environment and getting the framework running.

## 📋 Prerequisites

Before you begin, ensure you have the following installed:
- **PHP** $\ge 8.2$
- **Composer** (PHP dependency manager)
- **Docker & Docker Compose** (Recommended for easiest setup)
- **MySQL** & **Redis** (If not using Docker)

---

## 🛠️ Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd api
```

### 2. Install Dependencies
Use Composer to install all required PHP packages:
```bash
composer install
```

### 3. Environment Configuration
The framework uses a `.env` file for environment-specific configurations.
1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
2. Open `.env` in your preferred editor and update the following:
   - `APP_NAME`, `APP_URL`
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `JWT_SECRET`: Generate a strong key using: 
     `php -r "echo bin2hex(random_bytes(32));"`
   - Configure payment gateway keys (Stripe, PayPal, etc.) as needed.

---

## 🏃 Running the Framework

You can run the framework using either a local PHP server or Docker.

### Option A: Using Docker (Recommended)
This sets up the application, Nginx, MySQL, and Redis in isolated containers.

1. **Start the containers:**
   ```bash
   docker-compose up -d
   ```
2. **Run migrations inside the app container:**
   ```bash
   docker-compose exec app composer run migrate
   ```
3. **Access the API:** 
   The server will be available at `http://localhost:8000`.

### Option B: Local Setup (Manual)
If you prefer running services locally:
1. Ensure MySQL and Redis are running.
2. **Run Migrations:**
   ```bash
   composer run migrate
   ```
3. **Start the Server:**
   ```bash
   composer run serve
   ```
   The API will be available at the URL specified in your `.env` (default: `http://localhost:8000`).

---

## 🏗️ Project Structure

The framework follows a modular architecture:

| Directory | Description |
| :--- | :--- |
| `App/` | Application-level logic and controllers. |
| `Core/` | The framework's engine, base classes, and helpers. |
| `Database/` | Database connection and schema management. |
| `Repositories/` | Data access layer to decouple business logic from SQL. |
| `Services/` | Third-party integrations (Payments, SMS, Email). |
| `routes/` | API endpoint definitions. |
| `Support/` | Utility classes and common helper tools. |
| `jobs/` | Background task and queue definitions. |
| `public/` | Web root (entry point `index.php`). |
| `tests/` | PHPUnit test suites. |

---

## 🧪 Development Workflow

### Testing
The framework uses PHPUnit for testing. Run the test suite with:
```bash
composer run test
```

### Code Quality & Linting
Maintain code standards (PSR-12) using the built-in linting script:
```bash
composer run lint
```

### Security Audit
Check for known vulnerabilities in your dependencies:
```bash
composer run security-audit
```

---

## 📖 API Documentation
This framework integrates **Swagger (OpenAPI)**. You can generate and view API documentation by checking the `zircote/swagger-php` implementation within the code. Look for the Swagger UI endpoint (typically configured in the routes) to interact with the API.
