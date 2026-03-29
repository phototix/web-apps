# Carce PHP App

Phase 1 converts the static Carce template into a working PHP authentication app.

## Included

- Front controller routing from `public/index.php`
- Landing page at `/`
- User registration at `/register`
- User login at `/login`
- Authenticated welcome page at `/welcome`
- Role support for `superadmin`, `admin`, and `users`
- Database bootstrap SQL in `database/schema.sql`
- Superadmin seed script in `scripts/create_superadmin.php`

## MySQL Setup

✅ **MySQL Community Edition 9.6.0** - Ready for production deployment

**Root User:**
- Username: `root`
- Password: `password`

**Development User:**
- Username: `devuser`
- Password: `devpass`
- Database: `carce_app`

**Test Database:**
- Database: `test_db`

## Setup

1. **Create `.env` file** from `.env.example` with your database credentials:
   ```ini
   DB_DRIVER=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=carce_app
   DB_USER=devuser
   DB_PASS=devpass
   DB_CHARSET=utf8mb4
   APP_THEME=Sasoft-v2.0
   ```

## Theme Switching

Set `APP_THEME` in `.env` to switch templates without code changes.

Supported values:
- `Sasoft-v2.0` (default)
- `softing-v2.0`
- `Anada-v2.0`

## Template Licensing

This project is a PHP application foundation that integrates commercial web templates.

If you use this foundation for your own app, you are responsible for purchasing the appropriate template license from the original designer/publisher.

Official template marketplace link:
- https://app.envato.com/search?itemType=web-templates&filter.portfolio=validthemes

2. **Create the database** (if not already created):
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS carce_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

3. **Import the database schema**:
   ```bash
   mysql -u devuser -pdevpass carce_app < database/schema.sql
   ```

4. **Seed the superadmin account**:
   ```bash
   php scripts/create_superadmin.php
   ```

5. **Start the development server**:
   ```bash
   php -S 127.0.0.1:8000 router.php
   ```

6. **Access the app** at `http://127.0.0.1:8000`

## Production Deployment

For deployment to a VPS (Ubuntu + Apache + PHP + MySQL):

1. Set Apache document root to `/var/www/project-name/public`
2. Update `.env` with production database credentials
3. Ensure PHP MySQL extensions are installed (`php-mysql` or `php-pdo`)
4. Set proper file permissions and security group rules for MySQL

## Testing

**Login with superadmin:**
- Email: `brandon@kkbuddy.com`
- Password: `#Quidents64#`

## Superadmin Account

- Email: `brandon@kkbuddy.com`
- Password: `#Quidents64#` (run `scripts/create_superadmin.php` to recreate)