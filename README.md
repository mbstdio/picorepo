# Pico Repo

Pico Repo is a self-hosted Composer repository manager for publishing and distributing ZIP-based PHP packages. It provides a web interface for managing repositories, packages, versions, collaborators, and access tokens.

## Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Using a Repository](#using-a-repository)
- [Development](#development)
- [Deployment](#deployment)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

## Features

- **Public and private repositories**

  Create Composer repositories that are publicly available or restricted to authorized users.

- **Package and version management**

  Publish ZIP archives for versioned packages and provide Composer-compatible metadata for each release.

- **Composer v2 support**

  Pico Repo exposes `packages.json` and per-package metadata endpoints expected by Composer v2.

- **Repository access control**

  Assign owner or collaborator roles to users and create API tokens for private repository access.

- **Flexible archive storage**

  Store archives locally by default, or use S3 after installing and configuring its Flysystem adapter.

## Installation

Before installing Pico Repo, make sure PHP 8.3 or later, Composer 2, Node.js 20.19+ or 22.12+, npm, and Git are installed on your machine. PHP must include the PDO SQLite extension, along with Laravel's required extensions: `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `tokenizer`, and `xml`.

Clone the repository and install its dependencies:

```bash
git clone <REPOSITORY_URL> pico-repo
cd pico-repo
composer install
npm ci
```

Create the environment file and the SQLite database:

```bash
cp .env.example .env
touch database/database.sqlite
```

On PowerShell, use:

```powershell
Copy-Item .env.example .env
New-Item -ItemType File database/database.sqlite
```

Generate the application key and run the database migrations:

```bash
php artisan key:generate
php artisan migrate
```

Start the local development environment:

```bash
composer run dev
```

This starts the Laravel server, Vite, the queue worker, and Laravel Pail. Visit `http://127.0.0.1:8000/register` to create the first account.

## Configuration

Pico Repo is configured through the `.env` file. Copy `.env.example` instead of committing environment-specific settings or credentials to source control.

### Application URL

Set `APP_URL` to the public URL of your Pico Repo instance. It is used to generate archive download URLs and must be reachable by Composer clients.

```dotenv
APP_URL=https://packages.example.com
```

Use HTTPS in production.

### Database

SQLite is the default database driver:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

Pico Repo can use another Laravel-supported database driver. Update the relevant `DB_*` values in `.env` and run `php artisan migrate` after changing the configuration.

### Archive Storage

Package archives are stored on the `local` disk by default. They are kept under `storage/app/private` and are delivered through the application so private repository access rules are enforced.

To store archives on S3, install the adapter:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

Then configure the S3 credentials in `.env`:

```dotenv
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
```

### Mail

Configure `MAIL_*` values to deliver password reset and other application emails. The default local configuration writes email messages to the application logs.

## Using a Repository

Create a repository, package, and package version with its ZIP archive from the Pico Repo interface. The repository page displays its Composer endpoint.

For a repository with the `<slug>` slug, add the endpoint to a consuming project:

```bash
composer config repositories.pico-repo composer https://packages.example.com/composer/<slug>
composer require <vendor>/<package>
```

For private repositories, create a token from the profile of an authorized user, then configure Composer to send it as a Bearer token:

```bash
composer config --global --auth bearer.packages.example.com <token>
```

Use the hostname only in the Bearer configuration: do not include a protocol or path.

## Development

`composer run dev` starts all local services together. To run them separately:

```bash
php artisan serve
npm run dev
php artisan queue:listen
php artisan pail
```

Vite provides frontend hot module replacement while `npm run dev` is running. Build production assets with:

```bash
npm run build
```

Run the test suite with:

```bash
composer test
```

Tests use an in-memory SQLite database and do not modify the local development database.

Check or apply PHP formatting with:

```bash
vendor/bin/pint --test
vendor/bin/pint
```

## Deployment

Configure a production environment with `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, a persistent database, and durable archive storage. Configure the web server to use the project's `public` directory as its document root.

Deploy the application with at least the following commands:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

When `QUEUE_CONNECTION` is not `sync`, run a supervised queue worker. The web process must be able to read locally stored archives.

## Contributing

Contributions are welcome. Open an issue to report a bug or discuss a proposed enhancement before submitting a pull request.

Before opening a pull request, add or update the relevant tests and run:

```bash
composer test
vendor/bin/pint --test
```

Keep pull requests focused and describe the problem, the implementation, and the validation performed.

## Security

Do not disclose security vulnerabilities in a public issue. Use the repository's private vulnerability reporting mechanism, if enabled, or contact the project maintainers directly.

## License

Pico Repo is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).
