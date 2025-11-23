# CLAUDE.md - Fiduciario System Development Guide

## Project Overview

**Application Name:** Fiduciario (Afianzadora Fiducia Management System)
**Type:** Web-based business management application for fiduciary/trustee operations
**Version:** 1.0.0
**Language:** Spanish (UI), Mixed Spanish/English (code)
**Database:** PostgreSQL 16 (tes_db)

This is a comprehensive business management system featuring user management, group-based permissions, catalog management, and external API integration for compliance/risk detection.

---

## Technology Stack

### Backend
- **PHP** (Object-oriented, no framework)
- **PostgreSQL 16** via Docker
- **PDO** with prepared statements
- **Session-based authentication**

### Frontend
- **Tailwind CSS** (CDN, latest)
- **Alpine.js 3.x** (reactive UI components)
- **Font Awesome 6.4.0** (icons)
- **Vanilla JavaScript** (dashboard, sidebar interactions)

### Server
- **Apache** (XAMPP/Bitnami stack)
- **Docker** (PostgreSQL container)

---

## Architecture Patterns

### 1. **Singleton Pattern**
- `includes/Database.php` - Single PDO connection instance
- Prevents connection overhead

### 2. **Manager Pattern**
- Business logic encapsulated in Manager classes:
  - `UsuariosManager` - User CRUD operations
  - `GruposManager` - Group CRUD operations
  - Pattern: `{Entity}Manager.php`

### 3. **Module Router Pattern**
- `catalogos.php` - Central router for all modules
- Routes based on `?mod={module}&action={action}`
- Example: `catalogos.php?mod=usuarios&action=list`

### 4. **MVC-Inspired Structure**
```
Model: Manager classes + Database access
View: Module PHP files (list.php, form.php)
Controller: Action handlers (actions.php)
```

---

## Directory Structure

```
/home/user/htdocs/
├── config.php                    # Database config & helper functions
├── index.php                     # Redirects to XAMPP dashboard
├── login.php                     # Authentication page
├── logout.php                    # Session termination
├── dashboard.php                 # Main application dashboard
├── catalogos.php                 # Module router/loader
│
├── includes/                     # Core business logic
│   ├── Database.php              # Singleton PDO connection
│   ├── Auth.php                  # Authentication with migration
│   ├── Session.php               # Secure session management
│   ├── Permissions.php           # Authorization system
│   ├── UsuariosManager.php       # User CRUD operations
│   ├── GruposManager.php         # Group management
│   └── Sidebar.php               # Dynamic menu generation
│
├── modules/                      # Feature modules
│   ├── usuarios/                 # User management
│   │   ├── list.php             # User listing
│   │   ├── form.php             # Create/edit form
│   │   ├── actions.php          # AJAX handler
│   │   └── permissions.php      # Permission assignment
│   ├── grupos/                   # Group management
│   │   ├── list.php
│   │   ├── form.php
│   │   ├── actions.php
│   │   ├── permissions.php
│   │   └── usuarios.php         # Group members
│   └── honorarios/               # Fees module
│       └── list.php
│
├── API/                          # API endpoints
│   ├── busqueda_qdetect.php     # Q-Detect integration
│   ├── actualizar_token.php     # Token management
│   ├── buscar.php               # Search functionality
│   ├── save-sidebar-state.php   # User preferences
│   ├── TokenManager.php         # Token handling
│   └── .htaccess                # API security
│
├── public/                       # Static assets
│   ├── css/
│   │   ├── dashboard.css
│   │   ├── login.css
│   │   ├── sidebar.css
│   │   └── style.css
│   └── js/
│       ├── dashboard.js
│       ├── login.js
│       ├── sidebar.js
│       └── main.js
│
├── layout/                       # Layout components (placeholders)
├── img/                          # Images and logos
└── xampp/                        # XAMPP default files
```

---

## Database Schema Overview

### User Management
- `users` - Core user data (id, name, email, password, admin, empleado, statut)
- `t_perfil` - Extended profiles (firstname, lastname, civility, puesto)
- `t_remember_tokens` - "Remember Me" tokens
- `t_login_attempts` - Failed login tracking
- `t_user_preferences` - UI preferences (sidebar state)

### Permission System (Dolibarr-inspired)
- `t_rights_def` - Permission definitions (modulo, permiso, subpermiso)
- `t_user_rights` - Individual user permissions
- `t_usergroup` - User groups
- `t_usergroup_user` - Group membership
- `t_usergroup_rights` - Group permissions

### UI Configuration
- `t_menu` - Dynamic sidebar menu structure
- `t_const` - System constants/configuration

---

## Permission System

### Hierarchical Structure
```
modulo → permiso → subpermiso
Example: catalogos → lire → usuarios
```

### Permission Types (French-inspired from Dolibarr)
- `lire` - Read/view
- `creer` - Create
- `modifier` - Modify/update
- `supprimer` - Delete

### Permission Sources
1. **Individual permissions** - Directly assigned via `t_user_rights`
2. **Group permissions** - Inherited from groups via `t_usergroup_rights`
3. **Union model** - User has permission if granted individually OR through any group
4. **Admin override** - Users with `admin = 1` bypass all checks

### Permission Check Pattern
```php
// At start of protected pages/modules
if (!$session->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check specific permission (non-admin users)
if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', 'usuarios')) {
    die('Acceso Denegado');
}
```

---

## Coding Conventions

### Naming Conventions
- **Classes:** PascalCase (`UsuariosManager`, `Database`)
- **Methods:** camelCase (`getUsuarios()`, `hasPermission()`)
- **Database tables:** snake_case with `t_` prefix (`t_user_rights`, `t_perfil`)
- **Constants:** UPPER_SNAKE_CASE (`DB_HOST`, `APP_NAME`)
- **Variables:** camelCase (`$isAdmin`, `$userId`)

### File Organization
- **Entry points:** Root directory (login.php, dashboard.php, catalogos.php)
- **Business logic:** `/includes/` directory
- **Feature modules:** `/modules/{module_name}/`
- **APIs:** `/API/` directory
- **Assets:** `/public/css/` and `/public/js/`

### Error Handling
```php
try {
    // Database operations
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la operación']);
}
```

### AJAX Response Pattern
```php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Operación exitosa',
    'data' => $result
]);
```

---

## Standard Module Structure

Every module follows this pattern:

### 1. `list.php` - List View
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/Session.php';
require_once __DIR__ . '/../../includes/Permissions.php';

$session = Session::getInstance();
if (!$session->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$permissions = new Permissions($session->getUserId());
$isAdmin = $session->get('admin') == 1;

// Check read permission
if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', 'module_name')) {
    die('Acceso Denegado');
}

// Load data
$manager = new ModuleManager();
$items = $manager->getItems();
?>

<!-- HTML with Alpine.js controller -->
<div x-data="listController()">
    <!-- Statistics cards -->
    <!-- Search filters -->
    <!-- Data table -->
    <!-- Pagination -->
</div>
```

### 2. `form.php` - Create/Edit Form
```php
<?php
// Similar session/permission checks
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$item = $itemId ? $manager->getItemById($itemId) : null;
?>

<!-- Form with Alpine.js controller -->
<form x-data="formController()" @submit.prevent="saveItem">
    <!-- Form fields -->
    <!-- Tabs (basic info, permissions, etc.) -->
</form>
```

### 3. `actions.php` - AJAX Handler
```php
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/Session.php';

$session = Session::getInstance();
if (!$session->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            // Handle save
            break;
        case 'delete':
            // Handle delete
            break;
        case 'duplicate':
            // Handle duplication
            break;
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    error_log("Error in actions: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
```

### 4. `permissions.php` - Permission Management
```php
<?php
// Permission assignment UI with checkbox grid
// Shows both individual and group-inherited permissions
?>
```

---

## Security Best Practices

### Authentication
- **Login:** `includes/Auth.php:167` - `login()` method
- **Session validation:** Every protected page checks `$session->isLoggedIn()`
- **Failed attempts:** 5 max attempts, 15-minute lockout
- **Password migration:** Automatic upgrade from SHA256 to bcrypt
- **Remember Me:** Secure tokens with 30-day expiration

### SQL Injection Prevention
```php
// Always use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
```

### Session Security
- Custom session name: `BUSINESS_SESSION`
- Session regeneration every 30 minutes
- Hijacking protection (user agent + IP validation)
- HttpOnly cookies with SameSite protection

### XSS Prevention
```php
// Escape output
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

### Permission Checks
```php
// Always check permissions for non-admin actions
if (!$isAdmin) {
    $canCreate = $session->hasPermission('catalogos', 'creer', 'usuarios');
    $canModify = $session->hasPermission('catalogos', 'modifier', 'usuarios');
    $canDelete = $session->hasPermission('catalogos', 'supprimer', 'usuarios');
}
```

---

## Common Development Tasks

### Adding a New Module

1. **Create module directory:**
```bash
mkdir -p modules/new_module
```

2. **Create standard files:**
```bash
touch modules/new_module/list.php
touch modules/new_module/form.php
touch modules/new_module/actions.php
```

3. **Create Manager class:**
```php
// includes/NewModuleManager.php
class NewModuleManager {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getItems() {
        $stmt = $this->pdo->query("SELECT * FROM t_new_module ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

4. **Add permissions to database:**
```sql
INSERT INTO t_rights_def (modulo, permiso, subpermiso, descripcion) VALUES
('catalogos', 'lire', 'new_module', 'Read new module'),
('catalogos', 'creer', 'new_module', 'Create new module'),
('catalogos', 'modifier', 'new_module', 'Modify new module'),
('catalogos', 'supprimer', 'new_module', 'Delete new module');
```

5. **Add menu entry:**
```sql
INSERT INTO t_menu (label, icon, url, parent_id, orden, modulo, permiso_requerido, subpermiso_requerido, activo)
VALUES ('New Module', 'fa-solid fa-icon', 'catalogos.php?mod=new_module&action=list', 1, 10, 'catalogos', 'lire', 'new_module', 1);
```

### Adding a New Permission

```sql
-- 1. Add permission definition
INSERT INTO t_rights_def (modulo, permiso, subpermiso, type, descripcion, modulo_posicion, bydefault)
VALUES ('catalogos', 'export', 'usuarios', 'w', 'Export users to CSV', 100, 1);

-- 2. Grant to admin user (id=1)
INSERT INTO t_user_rights (fk_user, fk_id)
SELECT 1, id FROM t_rights_def WHERE modulo='catalogos' AND permiso='export' AND subpermiso='usuarios';

-- 3. Grant to a group
INSERT INTO t_usergroup_rights (fk_usergroup, fk_id)
SELECT 1, id FROM t_rights_def WHERE modulo='catalogos' AND permiso='export' AND subpermiso='usuarios';
```

### Modifying Existing Users

```php
// Use UsuariosManager
require_once 'includes/UsuariosManager.php';

$manager = new UsuariosManager();

// Update user
$success = $manager->updateUsuario($userId, [
    'nombre' => 'New Name',
    'email' => 'newemail@example.com',
    'activo' => 1
]);

// Update permissions
$manager->updateUserRights($userId, [1, 2, 3, 4]); // Array of right IDs
```

### Database Queries

```php
// Get connection
$pdo = Database::getInstance()->getConnection();

// Simple query
$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Insert
$stmt = $pdo->prepare("INSERT INTO users (name, email) VALUES (:name, :email)");
$stmt->execute(['name' => $name, 'email' => $email]);
$newId = $pdo->lastInsertId();

// Update
$stmt = $pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
$stmt->execute(['name' => $name, 'id' => $id]);

// Delete
$stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
$stmt->execute(['id' => $id]);
```

---

## Key Files Reference

### Core Classes

| File | Purpose | Key Methods |
|------|---------|-------------|
| `includes/Database.php:1` | Singleton DB connection | `getInstance()`, `getConnection()` |
| `includes/Auth.php:1` | Authentication | `login()`, `logout()`, `checkRememberMe()` |
| `includes/Session.php:1` | Session management | `isLoggedIn()`, `get()`, `set()`, `hasPermission()` |
| `includes/Permissions.php:1` | Authorization | `getUserRights()`, `getGroupRights()`, `getAllRights()` |
| `includes/UsuariosManager.php:1` | User CRUD | `getUsuarios()`, `createUsuario()`, `updateUsuario()` |
| `includes/GruposManager.php:1` | Group CRUD | `getGrupos()`, `createGrupo()`, `updateGrupo()` |
| `includes/Sidebar.php:1` | Menu generation | `generateSidebar()` |

### Entry Points

| File | Purpose | Access Level |
|------|---------|--------------|
| `login.php:1` | User authentication | Public |
| `logout.php:1` | Session termination | Authenticated |
| `dashboard.php:1` | Main dashboard | Authenticated |
| `catalogos.php:1` | Module router | Authenticated + Permission check |

### Configuration

| File | Purpose | Important Constants |
|------|---------|---------------------|
| `config.php:1` | DB config & helpers | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` |
| `.ven:1` | Docker PostgreSQL setup | Container configuration |

---

## Testing Approach

### Manual Testing Checklist

**Authentication:**
- [ ] Login with valid credentials
- [ ] Login with invalid credentials (check lockout after 5 attempts)
- [ ] "Remember Me" functionality
- [ ] Session persistence across page loads
- [ ] Logout functionality

**Permissions:**
- [ ] Admin user can access all modules
- [ ] Non-admin user respects permission restrictions
- [ ] Group permissions are inherited correctly
- [ ] Individual permissions override group permissions

**Module Functionality:**
- [ ] List view loads data
- [ ] Create new record
- [ ] Edit existing record
- [ ] Delete record (with confirmation)
- [ ] Duplicate record
- [ ] Export to CSV
- [ ] Search and filters work

**Security:**
- [ ] SQL injection attempts are blocked
- [ ] XSS attempts are escaped
- [ ] Direct URL access without login redirects to login page
- [ ] Permission bypass attempts return "Access Denied"

---

## Database Connection

```php
// Database credentials (config.php:3-6)
define('DB_HOST', 'localhost');
define('DB_NAME', 'tes_db');
define('DB_USER', 'postgres');
define('DB_PASSWORD', 'password');

// Get connection
$pdo = Database::getInstance()->getConnection();
```

**Docker PostgreSQL Setup:**
```bash
# From .ven file
docker run -d \
  --name tes_db \
  -e POSTGRES_PASSWORD=password \
  -e POSTGRES_DB=tes_db \
  -p 5432:5432 \
  postgres:16
```

---

## Frontend Patterns

### Alpine.js Controllers

```javascript
// List controller pattern
function listController() {
    return {
        items: [],
        searchTerm: '',
        currentPage: 1,
        itemsPerPage: 10,

        init() {
            this.loadItems();
        },

        async loadItems() {
            const response = await fetch('actions.php', {
                method: 'POST',
                body: new FormData()
            });
            const data = await response.json();
            this.items = data.items;
        },

        get filteredItems() {
            return this.items.filter(item =>
                item.name.toLowerCase().includes(this.searchTerm.toLowerCase())
            );
        }
    }
}
```

### Form Controller Pattern

```javascript
// Form controller pattern
function formController() {
    return {
        formData: {},
        loading: false,

        async saveItem() {
            this.loading = true;
            const formData = new FormData();
            formData.append('action', 'save');
            Object.keys(this.formData).forEach(key => {
                formData.append(key, this.formData[key]);
            });

            const response = await fetch('actions.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                window.location.href = 'catalogos.php?mod=module&action=list';
            } else {
                alert(result.message);
            }
            this.loading = false;
        }
    }
}
```

---

## Important Notes for AI Assistants

### Language Preferences
- **User-facing content:** Always use Spanish
- **Code comments:** Can be Spanish or English
- **Variable names:** Use English for clarity
- **Database content:** Spanish

### Permission System
- **Always check permissions** for non-admin users before allowing actions
- **Admin users bypass all checks** - `admin = 1` in users table
- **Permission hierarchy:** modulo → permiso → subpermiso
- **Union model:** User has permission if granted individually OR through any group

### Security Requirements
- **Always use prepared statements** - Never concatenate SQL
- **Always escape output** - Use `htmlspecialchars()` for user input
- **Always validate session** - Check `$session->isLoggedIn()` on protected pages
- **Always check permissions** - Verify user has required permission for action

### Code Style
- **Follow existing patterns** - Look at similar modules before creating new ones
- **Use Manager classes** - Don't put business logic in view files
- **Return JSON for AJAX** - Use consistent `['success' => bool, 'message' => string]` format
- **Log errors** - Use `error_log()` for debugging, don't expose to users

### Database Conventions
- **Table prefix:** Use `t_` for all custom tables
- **Foreign keys:** Use `fk_` prefix (e.g., `fk_user`, `fk_usergroup`)
- **Timestamps:** Use `created_at`, `updated_at` (or `datec`, `dateedit` in some tables)
- **Status fields:** Use `activo` (1=active, 0=inactive) or `statut`

### Common Pitfalls to Avoid
1. **Don't hardcode IDs** - Use dynamic lookups
2. **Don't skip permission checks** - Even for "internal" pages
3. **Don't trust user input** - Always validate and sanitize
4. **Don't expose error details** - Log them, show generic messages to users
5. **Don't modify core classes** - Extend them instead
6. **Don't break existing modules** - Test after changes

---

## Debugging Tips

### Enable Error Reporting (Development Only)
```php
// Add to top of file temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Check Session Data
```php
// View current session
echo '<pre>';
var_dump($_SESSION);
echo '</pre>';
```

### Check User Permissions
```php
// View user's permissions
$permissions = new Permissions($userId);
echo '<pre>';
var_dump($permissions->getAllRights());
echo '</pre>';
```

### Database Query Debugging
```php
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    echo "Rows found: " . $stmt->rowCount();
    var_dump($stmt->fetchAll());
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## External Dependencies

### CDN Resources
- **Tailwind CSS:** `https://cdn.tailwindcss.com`
- **Alpine.js:** `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js`
- **Font Awesome:** `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css`

### External APIs
- **Q-Detect API:** Risk detection/compliance service
  - Endpoint configured in `t_const` table
  - Token managed via `API/TokenManager.php`
  - Integration in `API/busqueda_qdetect.php`

---

## Git Workflow

### Branch Naming
- Feature branches: `claude/{description}-{session-id}`
- Always push to the designated branch
- Never push to main/master without permission

### Commit Messages
- Use clear, descriptive messages in Spanish
- Format: `{action} {description}`
- Examples:
  - `Agregado módulo de reportes`
  - `Corregido bug en permisos de grupo`
  - `Actualizado sistema de autenticación`

### Common Git Operations
```bash
# Check status
git status

# Create and switch to feature branch
git checkout -b claude/new-feature-{session-id}

# Stage changes
git add .

# Commit
git commit -m "Descripción clara del cambio"

# Push with upstream tracking
git push -u origin claude/new-feature-{session-id}
```

---

## Contact & Support

For issues or questions about this codebase:
1. Check existing modules for similar patterns
2. Review this CLAUDE.md file
3. Examine the database schema
4. Test changes in a development environment first

---

**Last Updated:** 2025-11-23
**Version:** 1.0.0
**Maintained by:** AI Assistant for Afianzadora Fiducia
