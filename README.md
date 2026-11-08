# SocialPulse Neon – Social Media Analytics Dashboard

SocialPulse is a web-based social media management and analytics platform built with PHP and MySQL. Businesses and social media managers can manage multiple brands, organize posts, record performance metrics, and generate reports through a centralized dashboard.

It features a **neon red/orange dark UI** and a **live-updating dashboard** whose numbers, charts and activity feed refresh every 5 seconds without reloading the page.

Access is role-based: users only see the data and features their role allows.

## Features

### 1. Authentication and Authorization
- Secure login and logout
- Password hashing and verification
- Session-based authentication
- Three roles: Admin, Social Media Manager, Client

### 2. Admin Management
- Manage users and roles
- Create and manage clients and brands
- Manage social media accounts
- Assign users to brands
- View system audit logs

### 3. Post Management
- Add, edit and delete posts
- Link posts to brands and social accounts
- Post types: Image, Video, Reel, Story, Carousel
- Search and filter posts
- Import posts through CSV files

### 4. Analytics and Metrics
- Record views, likes, comments, shares and reach
- View performance history and engagement rates
- Compare selected posts
- Interactive charts (Chart.js)
- **Live dashboard updates** (stats, charts, activity feed) through `public/api/live.php` and `assets/js/live.js`

### 5. Reports
- Generate, view, download and delete reports
- Report access restricted by role

### 6. Client Workspace
- View assigned brands, accounts, recent posts and reports
- Access only authorized client data

### 7. Audit Logs
- Records of important system activities for accountability

### 8. Neon UI
- Dark red/orange neon theme (`assets/css/theme.css`) applied across all pages
- Responsive layout for smaller screens

## Technologies Used

| Technology | Purpose |
|---|---|
| PHP | Backend |
| MySQL | Database |
| PDO | Secure database access |
| HTML5 / CSS3 | Pages and neon theme |
| JavaScript (fetch API) | Live polling |
| Chart.js | Charts |
| Apache2 or PHP built-in server | Web server |

## Project Structure

```text
socialpulse/
├── assets/
│   ├── css/            (page styles + theme.css)
│   └── js/
│       └── live.js     (live dashboard polling)
├── config/
│   └── database.php
├── database/
│   └── socialpulse_db.sql
├── includes/
│   ├── auth.php
│   └── audit.php
├── public/
│   ├── admin/
│   ├── api/
│   │   └── live.php    (live JSON endpoint)
│   ├── client/
│   ├── imports/
│   ├── metrics/
│   ├── posts/
│   ├── reports/
│   ├── dashboard.php
│   ├── login.php
│   ├── logout.php
│   ├── posts.php
│   ├── metrics.php
│   ├── reports.php
│   └── post_comparison.php
├── tools/
│   └── simulate_metrics.php   (demo only)
├── create_admin.php
└── README.md
```

## User Roles

| Role | Permissions |
|---|---|
| Admin | Full system management |
| Social Media Manager | Manage assigned brands, posts and metrics |
| Client | View authorized brands, posts and reports |

Permissions are enforced on the server side.

## Installation and Setup

### Prerequisites

- Linux (Ubuntu/Debian), macOS or Windows
- PHP 8+ with the `pdo_mysql` extension
- MySQL Server
- Git

On Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y php-cli php-mysql mysql-server
sudo systemctl enable --now mysql
```

### 1. Clone the Repository

```bash
git clone YOUR_REPOSITORY_URL
cd SocialPulse
```

### 2. Configure the Database

```bash
sudo mysql -e "CREATE DATABASE socialpulse_db; CREATE USER 'socialpulse'@'localhost' IDENTIFIED BY 'YOUR_DATABASE_PASSWORD'; GRANT ALL ON socialpulse_db.* TO 'socialpulse'@'localhost'; FLUSH PRIVILEGES;"
mysql -u socialpulse -p socialpulse_db < database/socialpulse_db.sql
```

Warning: the SQL file drops and recreates its tables, so only import it into a fresh or disposable database.

### 3. Configure the Database Connection

Edit `config/database.php` and set host, database name, username and password.
Do not publish real credentials to GitHub.

### 4. Create an Admin User

```bash
php create_admin.php
```

### 5. Run the Application

**Option A: PHP built-in server (quickest for development)**

From the project root:

```bash
php -S localhost:8000
```

Open `http://localhost:8000/public/login.php`.

Run the server from the project root, not with `-t public`: the stylesheets live in `assets/`, outside `public/`, and would return 404.

The login redirect in `includes/auth.php` points to `/socialpulse/public/login.php`. With the built-in server, change it to `/public/login.php`.

**Option B: Apache**

```bash
sudo apt install -y apache2 libapache2-mod-php
sudo cp -r SocialPulse /var/www/html/socialpulse
sudo chown -R www-data:www-data /var/www/html/socialpulse
```

Open `http://localhost/socialpulse/public/login.php`.

### 6. Watch the Live Dashboard (demo)

With the dashboard open, run in a second terminal from the project root:

```bash
php tools/simulate_metrics.php
```

Every 4 seconds a random new metrics row is inserted; the dashboard cards, charts and Live Activity feed update within about 5 seconds. Press `Ctrl+C` to stop.

To remove simulator rows afterwards (the original sample data uses metric IDs 1 to 6):

```bash
mysql -u socialpulse -p socialpulse_db -e "DELETE FROM post_metrics WHERE metric_id > 6;"
```

## How the Live Updates Work

1. `dashboard.php` renders the first view on the server.
2. `assets/js/live.js` calls `public/api/live.php` every 5 seconds with the selected date range.
3. `live.php` checks the session, applies the same role-based access rules, reads `post_metrics` and returns totals, platform split and the latest activity as JSON.
4. The script updates the cards, both charts and the activity feed in place.

New data appears as soon as new rows reach the `post_metrics` table, whether they come from manual entry, CSV import, the demo simulator, or a future API sync.

## Security

- Password hashing using PHP password functions
- PDO prepared statements
- Server-side session authentication
- Role-based authorization (also enforced in the live API)
- CSRF protection for important POST operations
- Server-side validation of submitted data
- Client data isolation
- Access restrictions for administrative pages

**Important:** configure production credentials securely and disable public access to setup and diagnostic scripts (`create_admin.php`, `test_db.php`).

## Data Collection

SocialPulse currently supports:

- CSV-based post imports
- Manual entry of post performance metrics
- Demo data through `tools/simulate_metrics.php` (random numbers, not real social media data)

Automatic synchronization with Instagram and Facebook APIs is not yet implemented. The Meta Graph API requires an Instagram Business or Creator account and an access token; a sync script only needs to insert rows into `post_metrics`, and the live dashboard will pick them up.

## Future Improvements

- Meta Graph API integration (`tools/sync_meta.php`)
- Automatic scheduled synchronization of metrics
- Scheduled report generation
- Advanced analytics and performance insights
- Additional social media platform integrations

## Video Demo Link

https://drive.google.com/file/d/13yWOAUgjnbIH1lCKSAweokw3H9wUCgbm/view?usp=drive_link

## Authors

- **Ali Hassan**: neon theme, live dashboard and API endpoint
- **Abdullah Imran**: original project (backend, database and core pages)
-
- <img width="1737" height="1157" alt="Screenshot From 2026-11-07 02-22-05" src="https://github.com/user-attachments/assets/d946b16c-9338-4946-8149-5eb25cc9de72" />
