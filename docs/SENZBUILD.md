# SenzBuild — Global CLI Setup

## What is SenzBuild?

`senzbuild` is the global CLI for scaffolding and managing SENZ Framework projects — just like `laravel new` or `rails new`.

## Install (One-Time Setup)

The scripts are already placed in `D:\composer\` which is on your PATH.
If you ever move the framework, update the config:

```powershell
# Update the framework path config
"D:\Soft Projx\API Template\framework" | Out-File -Encoding utf8 "D:\composer\.senzbuild-config"
```

## Quick Start

```powershell
# From anywhere — see all commands
senzbuild

# Create a new project
senzbuild new my-app

# Skip composer install (faster, run manually later)
senzbuild new my-app --no-install

# Initialize an existing project
cd my-app
senzbuild init

# Initialize with database creation
senzbuild init --db

# Build a controller/model/migration
senzbuild make:controller UserController
senzbuild make:model User
senzbuild make:migration create_users_table
```

## All Commands

| Command | Description |
|---------|-------------|
| `senzbuild new <name>` | Scaffold a new project |
| `senzbuild new <name> --no-install` | Scaffold without running composer |
| `senzbuild init` | Run composer install + create storage dirs |
| `senzbuild init --db` | Also create the database from .env |
| `senzbuild serve` | Start the dev server |
| `senzbuild migrate` | Run pending migrations |
| `senzbuild make:controller <Name>` | Generate a controller |
| `senzbuild make:model <Name>` | Generate a model |
| `senzbuild make:migration <name>` | Generate a migration |
| `senzbuild queue:work` | Start the queue worker |
| `senzbuild db:seed` | Run database seeders |

## Multi-Database Support

Set the driver in your project's `.env`:

```env
# MySQL (default)
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=myapp
DB_USER=root
DB_PASS=

# PostgreSQL
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_NAME=myapp
DB_USER=postgres
DB_PASS=

# SQLite (file-based, no server needed)
DB_DRIVER=sqlite
DB_NAME=database.sqlite

# SQL Server
DB_DRIVER=sqlsrv
DB_HOST=localhost\SQLEXPRESS
DB_NAME=myapp
DB_USER=sa
DB_PASS=
```

Then create the database:

```powershell
senzbuild init --db
```

## Troubleshooting

**"SENZ Framework not found"**

Create the config file manually:
```powershell
echo "C:\path\to\framework" > "$env:USERPROFILE\.senzbuild-config"
```

Or set the environment variable permanently:
```powershell
setx SENZ_FRAMEWORK_PATH "D:\Soft Projx\API Template\framework"
```

Then restart your terminal.
