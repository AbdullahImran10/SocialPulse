# SocialPulse – Social Media Analytics Dashboard

SocialPulse is a web-based social media management and analytics platform built with PHP and MySQL. It allows businesses and social media managers to manage multiple brands, organize social media posts, record performance metrics, and generate analytical reports through a centralized dashboard.

The platform supports role-based access control, ensuring that users can only access the data and features authorized for their roles.

## Features

### 1. Authentication and Authorization
- Secure login and logout.
- Password hashing and verification.
- Session-based authentication.
- Role-based access control.
- Three user roles: Admin, Social Media Manager, and Client.

### 2. Admin Management
- Manage users and their roles.
- Create and manage clients.
- Create and manage brands.
- Manage social media accounts.
- Assign users to brands.
- View system audit logs.

### 3. Post Management
- Add, edit, and delete social media posts.
- Associate posts with brands and social media accounts.
- Support multiple post types:
  - Image
  - Video
  - Reel
  - Story
  - Carousel
- Search and filter posts.
- Import posts through CSV files.

### 4. Analytics and Metrics
- Record post performance metrics.
- Track views, likes, comments, shares, and reach.
- View performance history.
- Calculate engagement rates.
- Compare the performance of selected posts.
- Visualize analytics through interactive charts.

### 5. Reports
- Generate analytical reports.
- View and download reports.
- Delete reports.
- Restrict report access according to user permissions.

### 6. Client Workspace
- View assigned brands.
- View associated social media accounts.
- Access recent posts.
- View available reports.
- Access only authorized client data.

### 7. Audit Logs
- Maintain records of important system activities.
- Track user actions for accountability and monitoring.

## Technologies Used

| Technology | Purpose |
|---|---|
| PHP | Backend development |
| MySQL | Database management |
| Apache2 | Web server |
| HTML5 | Page structure |
| CSS3 | Styling and responsive design |
| JavaScript | Client-side functionality |
| Chart.js | Data visualization |
| PDO | Secure database interaction |

## Project Structure

```text
socialpulse/
│
├── assets/
│   └── css/
│
├── config/
│   └── database.php
│
├── includes/
│   ├── auth.php
│   └── audit.php
│
├── public/
│   ├── admin/
│   │   ├── users.php
│   │   ├── clients.php
│   │   ├── brands.php
│   │   ├── social_accounts.php
│   │   ├── brand_members.php
│   │   └── audit_logs.php
│   │
│   ├── imports/
│   ├── metrics/
│   ├── posts/
│   ├── reports/
│   │
│   ├── client/
│   ├── dashboard.php
│   ├── login.php
│   ├── logout.php
│   ├── posts.php
│   ├── metrics.php
│   ├── reports.php
│   └── post_comparison.php
│
├── create_admin.php
└── README.md
```

## User Roles

| Role | Permissions |
|---|---|
| Admin | Full system management and access |
| Social Media Manager | Manage assigned brands, posts, and metrics |
| Client | View authorized brands, posts, and reports |

Access permissions are enforced on the server side.

## Installation and Setup

### Prerequisites

Make sure the following are installed:

- Ubuntu/Linux
- Apache2
- PHP
- MySQL Server
- PHP MySQL extensions
- Git

### 1. Clone the Repository

```bash
git clone YOUR_REPOSITORY_URL
```

Move the project into your Apache web directory:

```bash
sudo cp -r socialpulse /var/www/dev/
```

Alternatively, clone it directly into your configured web directory.

### 2. Configure the Database

Create the MySQL database:

```sql
CREATE DATABASE socialpulse_db;
```

Create a MySQL user and grant the required permissions:

```sql
CREATE USER 'socialpulse'@'localhost'
IDENTIFIED BY 'YOUR_DATABASE_PASSWORD';

GRANT ALL PRIVILEGES ON socialpulse_db.*
TO 'socialpulse'@'localhost';

FLUSH PRIVILEGES;
```

Import the project's database SQL file if one is included:

```bash
mysql -u socialpulse -p socialpulse_db < database/socialpulse_db.sql
```

### 3. Configure Database Connection

Open:

```text
config/database.php
```

Update the database connection settings:

- Database host
- Database name
- Database username
- Database password

Do not publish real database credentials to GitHub.

### 4. Configure Apache

Ensure Apache has access to the project directory and that PHP is configured correctly.

For a local development environment, configure an Apache virtual host if required.

### 5. Run the Application

Open the application in your browser using your configured local URL.

Example:

```text
http://dev.local/socialpulse/public/
```

Log in using an administrator account created through the project's admin setup process.

## Security

SocialPulse includes several security measures:

- Password hashing using PHP password functions.
- PDO prepared statements for database operations.
- Server-side session authentication.
- Role-based authorization.
- CSRF protection for important POST operations.
- Server-side validation of submitted data.
- Client data isolation.
- Access restrictions for administrative pages.

**Important:** Configure production credentials securely and disable public access to setup and diagnostic scripts.

## Data Collection

SocialPulse currently supports:

- CSV-based post imports.
- Manual entry of post performance metrics.

Automatic live data synchronization through Instagram and Facebook APIs is not currently implemented.

## Future Improvements

Potential future enhancements include:

- Meta Graph API integration.
- Automatic synchronization of social media metrics.
- Scheduled report generation.
- Advanced analytics and performance insights.
- Additional social media platform integrations.

## Author

Abdullah Imran

**SocialPulse**

A PHP and MySQL-based social media management and analytics project.