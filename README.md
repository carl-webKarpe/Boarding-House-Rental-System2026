# Boarding House Rental System

A PHP and MySQL web application that helps students, especially newcomers, find safe and affordable boarding houses near their school. It is built around Siargao Island Institute of Technology (SIIT) in Dapa.

- **Tenants** search rooms, save favorites, request bookings, and leave reviews.
- **Landlords** register with their ID documents, list their boarding houses and rooms with photos, and approve or decline booking requests.
- **Administrators** verify landlords, manage users, and moderate listings.

## Features

### Tenants
- Browse and search rooms by school, barangay, city, price, room type, amenity, and gender policy.
- The landing page map shows boarding houses near SIIT with walking distance.
- Room page with photos, prices (rent, deposit, advance), open slots, amenities, house rules, and reviews.
- Request a booking with a move-in date and a message to the landlord.
- **My Bookings** page to track or cancel requests, plus a saved-rooms list.
- Notifications when a landlord approves or declines a request.
- Review a room after the booking is approved.

### Landlords
- Registration with government ID and selfie (business permit and proof of ownership are optional).
- New landlord accounts wait for admin approval, and their listings stay hidden until then.
- Add and edit boarding houses, including address, nearest school, map coordinates, gender policy, and house rules.
- Add and edit rooms with prices, capacity, open slots, amenities, and up to 8 photos.
- Approve or decline booking requests. Open slots update automatically, and a room is marked **Full** when no slots are left.

### Administrators
- Dashboard with counts, landlords waiting for approval, and recent activity.
- User management: search, filter, approve or reject landlords, and activate or deactivate accounts.
- View each user's uploaded documents (stored privately, visible to admins only).
- Hide or show any boarding house listing.

### Security
- Passwords are hashed with PHP `password_hash`, and every query uses a prepared statement.
- Forms are protected with CSRF tokens and all output is escaped to prevent XSS.
- Pages are restricted by role (tenant, landlord, admin), and landlords can only edit their own listings.
- Logins are rate-limited and accounts lock after repeated failures.
- Uploads are checked by their real file type and saved with random names. ID documents are kept outside the public folders.

## Requirements

| Software | Purpose | Download |
|---|---|---|
| **PHP 8.1+** | Runs the website | https://windows.php.net/download (VS16 x64 Thread Safe zip) |
| **MySQL Server 8** | The database | https://dev.mysql.com/downloads/installer/ |
| **MySQL Workbench** | View and manage the database | Included in the MySQL Installer |

XAMPP is **not** needed.

## Setup (Windows)

### 1. Install PHP
1. Extract the PHP zip to `C:\php`.
2. Add `C:\php` to your Windows **PATH** (Start → "Edit the system environment variables" → Environment Variables → Path → New).
3. In `C:\php`, copy `php.ini-development` to `php.ini`, then open `php.ini` and remove the `;` at the start of these lines:
   ```
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   ```
4. Open a new terminal and run `php -v` to confirm it works.

### 2. Install MySQL
1. Run the MySQL Installer and choose **Server** and **Workbench**.
2. Set a **root password** you will remember.

### 3. Create the database (MySQL Workbench)
1. Open MySQL Workbench and connect to your local server.
2. **File → Open SQL Script** → choose `database/schema.sql`, then click the ⚡ **Execute** button.
3. Do the same for `database/seed.sql` to add sample data and test accounts.

> `schema.sql` deletes and recreates the `bhsystem` database. Only run it for a fresh start.

### 4. Configure the connection
Copy `.env.example` to a new file named `.env` and enter your MySQL password:
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bhsystem
DB_USER=root
DB_PASS=your_mysql_password
```
`.env` is ignored by git, so each teammate keeps their own password.

### 5. Run it
Double-click **`start.bat`**, or run this in the VS Code terminal:
```
php -d upload_max_filesize=10M -d post_max_size=60M -S localhost:8000 router.php
```
Then open **http://localhost:8000**. Press `Ctrl + C` in the terminal to stop the server.

## Test accounts (from `database/seed.sql`)

| Role | Email | Password |
|---|---|---|
| Admin | admin@example.com | Admin@12345 |
| Landlord | landlord@example.com | Landlord@123 |
| Landlord | renato@example.com | Landlord@123 |
| Landlord (waiting for approval) | pending@example.com | Landlord@123 |
| Tenant | tenant@example.com | Tenant@1234 |

Change these passwords before any real use.

## Folder structure

```text
admin/          Admin panel, users, user details, documents, listings
api/            JSON endpoints: login, registration, CSRF token, room search
database/       schema.sql (tables) and seed.sql (sample data)
html/           Landing page, login, and registration forms
Image/          Images used by the landing page and sample listings
includes/       Shared page layout, helpers, and listing queries
landlord/       Landlord dashboard, house form, room form, booking requests
php/            Browse rooms, room details, tenant dashboard, profile, notifications, password reset
registerJS/     JavaScript for the landing page, login, and registration
security/       Config, database connection, sessions, CSRF, auth, validation, uploads
storage/        Logs, login counters, and private ID documents (never served)
uploads/        Room photos uploaded by landlords (created automatically)
router.php      Router for PHP's built-in server; blocks private folders
start.bat       One-click start on Windows
```

## Database tables

| Table | Stores |
|---|---|
| `users` | All accounts: role, personal details, approval status |
| `landlord_profiles` | Business details from landlord registration |
| `user_documents` | Uploaded IDs, selfies, and permits |
| `boarding_houses` | A landlord's properties (address, school, coordinates, rules) |
| `rooms` | Rooms in each house (type, rent, slots, amenities, status) |
| `room_photos` | Photos for each room |
| `bookings` | Booking requests (pending, approved, rejected, cancelled) |
| `favorites` | Rooms saved by tenants |
| `reviews` | Tenant ratings and comments |
| `audit_logs` | Important actions, for the admin activity feed |
| `password_resets` | Password reset tokens |

## Notes
- **Password reset:** email sending is not set up yet. When someone requests a reset, the link is written to `storage/app.log` so you can test the flow locally.
- **Map location:** boarding houses show on the landing-page SIIT map only when the landlord enters latitude and longitude. In Google Maps, right-click the place and click the coordinates to copy them.
- **Room photos** are saved in `uploads/rooms/`. This folder is ignored by git, so each computer has its own uploads.
- `VERCEL_DEPLOYMENT_GUIDE.md` is out of date: Vercel cannot run PHP. Deployment will be planned later.
