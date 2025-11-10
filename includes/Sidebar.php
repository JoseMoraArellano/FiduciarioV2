<?php
/**
 * Clase Sidebar - Genera menú lateral dinámico desde base de datos
 * Lee estructura desde t_menu y verifica permisos desde t_user_rights/t_usergroup_rights
 */
class Sidebar {
    
    private $db;
    private $userPermissions;
    private $userId;
    private $currentPage;
    private $menuItems = [];
    private $isUserAdmin = false;
    
    /**
     * Constructor
     * @param array $userPermissions - Array de permisos del usuario desde Session
     * @param int $userId - ID del usuario actual
     * @param string $currentPage - Página actual para resaltar en el menú
     * @param bool $isAdmin - Si el usuario es administrador
     */
public function __construct($userPermissions, $userId, $currentPage = '', $isAdmin = false) {
    $this->db = Database::getInstance()->getConnection();
    $this->userPermissions = $userPermissions;
    $this->userId = $userId;
    $this->currentPage = $currentPage;
    $this->isUserAdmin = $isAdmin;
    
    $this->loadMenuFromDatabase();
}
    
    /**
     * Carga la estructura del menú desde la base de datos
     */
    private function loadMenuFromDatabase() {
        try {
            // Cargar todos los items del menú activos, ordenados
            $sql = "
                SELECT 
                    id,
                    label,
                    icon,
                    url,
                    parent_id,
                    orden,
                    modulo,
                    permiso_requerido,
                    subpermiso_requerido
                FROM t_menu
                WHERE activo = TRUE
                ORDER BY 
                    COALESCE(parent_id, id),
                    orden,
                    label
            ";
            
            $stmt = $this->db->query($sql);
            $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Construir árbol jerárquico
            $this->menuItems = $this->buildMenuTree($allItems);
            
        } catch (PDOException $e) {
            error_log("Error loading menu from database: " . $e->getMessage());
            $this->menuItems = [];
        }
    }
    
    /**
     * Construye árbol jerárquico del menú
     */
    private function buildMenuTree($items, $parentId = null) {
        $branch = [];
        
        foreach ($items as $item) {
            // Si el parent_id coincide con el que buscamos
            if ($item['parent_id'] == $parentId) {
                // Buscar hijos de este item
                $children = $this->buildMenuTree($items, $item['id']);
                
                if ($children) {
                    $item['submenu'] = $children;
                } else {
                    $item['submenu'] = null;
                }
                
                $branch[] = $item;
            }
        }
        
        return $branch;
    }
    
    /**
     * Verifica si el usuario es administrador
     */
    
    private function isAdmin() { 
        return $this->isUserAdmin;
    }

    /**
     * Verifica si el usuario tiene un permiso específico
     */
    private function hasPermission($modulo, $permiso, $subpermiso = null) {
        // Si es administrador, tiene todos los permisos
        if ($this->isAdmin()) {
            return true;
        }
                
        // Para usuarios normales, verificar permisos
        foreach ($this->userPermissions as $perm) {
            if ($perm['modulo'] === $modulo && $perm['permiso'] === $permiso) {
                if ($subpermiso === null) {
                    return true;
                }
                
                if ($perm['subpermiso'] === $subpermiso) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Filtra el menú según permisos del usuario
     */
    private function getFilteredMenu() {
        return $this->filterMenuRecursive($this->menuItems);
    }
    
    /**
     * Filtra menú recursivamente verificando permisos
     */
    private function filterMenuRecursive($items) {
        $filtered = [];
        
        foreach ($items as $item) {
            // Verificar si tiene el permiso requerido
            $hasRequiredPermission = $this->hasPermission(
                $item['modulo'],
                $item['permiso_requerido'],
                $item['subpermiso_requerido']
            );
            
            // Si tiene submenu, filtrar recursivamente
            if (!empty($item['submenu'])) {
                $filteredSubmenu = $this->filterMenuRecursive($item['submenu']);
                
                // Solo incluir el item padre si tiene al menos 1 hijo visible
                if (!empty($filteredSubmenu)) {
                    $item['submenu'] = $filteredSubmenu;
                    $filtered[] = $item;
                }
            } else {
                // Item sin submenu - incluir solo si tiene permiso
                if ($hasRequiredPermission) {
                    $filtered[] = $item;
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Verifica si una URL es la página actual
     */
    private function isActive($url) {
        if (empty($this->currentPage) || empty($url) || $url === '#') {
            return false;
        }
        
        // Extraer la página base de la URL
        $urlParts = parse_url($url);
        $itemPage = isset($urlParts['path']) ? basename($urlParts['path']) : '';
        
        // Comparar con la página actual
        $currentPageBase = basename($this->currentPage);
        
        if ($itemPage === $currentPageBase) {
            // Si tienen query params, verificar también
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $urlParams);
                
                // Si la URL tiene parámetro 'mod', verificar que coincida
                if (isset($urlParams['mod']) && isset($_GET['mod'])) {
                    return $urlParams['mod'] === $_GET['mod'];
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Verifica si un submenu contiene la página activa
     */
    private function hasActiveSubmenu($submenu) {
        if (empty($submenu)) {
            return false;
        }
        
        foreach ($submenu as $subitem) {
            if ($this->isActive($subitem['url'])) {
                return true;
            }
            
            // Verificar recursivamente si tiene más niveles
            if (!empty($subitem['submenu'])) {
                if ($this->hasActiveSubmenu($subitem['submenu'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Renderiza un item del menú recursivamente
     */
    private function renderMenuItem($item, $level = 0) {
        $hasSubmenu = !empty($item['submenu']);
        $isActivePage = $this->isActive($item['url']);
        $hasActiveChild = $hasSubmenu && $this->hasActiveSubmenu($item['submenu']);
        
        // Clases CSS según nivel y estado
        $isSubmenu = ($level > 0);
        $itemClasses = 'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors group';
        $itemClasses .= $isSubmenu ? ' text-sm text-gray-300 hover:text-white' : '';
        $itemClasses .= $isActivePage ? ' bg-blue-600 text-white' : ' hover:bg-gray-800';
        
        // Padding según nivel de anidación
        $paddingClass = $isSubmenu ? 'ml-' . ($level * 4) : '';
        
        ob_start();
        ?>
        <li x-data="{ open: <?php echo ($hasActiveChild ? 'true' : 'false'); ?> }" class="<?php echo $paddingClass; ?>">
            <?php if ($hasSubmenu): ?>
                <!-- Item con submenu -->
                <button 
                    @click="open = !open"
                    class="w-full <?php echo $itemClasses; ?>"
                >
                    <div class="flex items-center gap-3 flex-1">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?> text-lg w-5"></i>
                        <span x-show="sidebarOpen" class="whitespace-nowrap"><?php echo htmlspecialchars($item['label']); ?></span>
                    </div>
                    <i 
                        x-show="sidebarOpen"
                        :class="open ? 'fa-chevron-down' : 'fa-chevron-right'" 
                        class="fas text-xs transition-transform"
                    ></i>
                </button>
                
                <!-- Submenu recursivo -->
                <ul 
                    x-show="open && sidebarOpen" 
                    x-collapse
                    class="mt-1 space-y-1"
                >
                    <?php foreach ($item['submenu'] as $subitem): ?>
                        <?php echo $this->renderMenuItem($subitem, $level + 1); ?>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <!-- Item sin submenu -->
                <a 
                    href="<?php echo htmlspecialchars($item['url']); ?>" 
                    class="<?php echo $itemClasses; ?>"
                >
                    <i class="fas <?php echo htmlspecialchars($item['icon']); ?> <?php echo $isSubmenu ? 'text-base w-4' : 'text-lg w-5'; ?>"></i>
                    <span x-show="sidebarOpen" class="whitespace-nowrap"><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
            <?php endif; ?>
        </li>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Carga el estado del sidebar desde las preferencias del usuario
     */
    private function getSidebarState() {
        try {
            $sql = "SELECT sidebar_open FROM t_user_preferences WHERE fk_user = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $this->userId]);
            
            $result = $stmt->fetch();
            
            if ($result) {
                return (bool)$result['sidebar_open'];
            }
            
            return true; // Por defecto abierto
            
        } catch (PDOException $e) {
            error_log("Error loading sidebar state: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Guarda el estado del sidebar
     */
    public function saveSidebarState($isOpen) {
        try {
            $sql = "
                UPDATE t_user_preferences 
                SET sidebar_open = :is_open, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE fk_user = :user_id
            ";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'is_open' => $isOpen ? 1 : 0,
                'user_id' => $this->userId
            ]);
            
        } catch (PDOException $e) {
            error_log("Error saving sidebar state: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Renderiza el sidebar completo
     */
    public function render($userData = []) {
        $filteredMenu = $this->getFilteredMenu();
        $sidebarOpen = $this->getSidebarState();
        
        // Datos del usuario
        $userName = $userData['name'] ?? 'Usuario';
        $userEmail = $userData['email'] ?? '';
        $fullName = '';
        
        // Obtener nombre completo del perfil
        if (!empty($userData['perfil'])) {
            $perfil = $userData['perfil'];
            if (!empty($perfil['firstname']) || !empty($perfil['lastname'])) {
                $fullName = trim(($perfil['firstname'] ?? '') . ' ' . ($perfil['lastname'] ?? ''));
            }
        }
        
        if (empty($fullName)) {
            $fullName = $userName;
        }
        
        $userAvatar = !empty($userData['perfil']['avatar']) 
            ? $userData['perfil']['avatar'] 
            : "https://ui-avatars.com/api/?name=" . urlencode($fullName) . "&background=3b82f6&color=fff";
        
        ob_start();
        ?>
        <aside 
            x-data="sidebar(<?php echo $sidebarOpen ? 'true' : 'false'; ?>, <?php echo $this->userId; ?>)"
            x-init="init()"
            :class="sidebarOpen ? 'w-64' : 'w-20'" 
            class="bg-gray-900 text-white transition-all duration-300 flex flex-col overflow-hidden"
        >
            <!-- Header -->
            <div class="p-4 border-b border-gray-700 flex items-center justify-between">
                <div x-show="sidebarOpen" class="flex items-center gap-3">
                    <a href="../../dashboard.php" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
                       <!-- <i class="fas fa-briefcase text-xl"></i>-->
                        <img href="../../dashboard.php" src="../../img/Fiduciapalomas.jpg"  alt="Logo" class="w-12 h-12">
                        </a>
                    </div>
                    <div>
                        <h1 class="font-bold text-sm whitespace-nowrap">Afianzadora Fiducia</h1>
                        <p class="text-xs text-gray-400">Fiduciario</p>
                    </div>
                </div>
                
                <button 
                    @click="toggleSidebar()"
                    class="p-2 hover:bg-gray-800 rounded-lg transition-colors"
                    title="Colapsar menú"
                >
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <!-- Menu Items -->
            <nav class="flex-1 overflow-y-auto py-4 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800">
                <?php if (empty($filteredMenu)): ?>
                    <div class="px-4 py-8 text-center">
                        <i class="fas fa-lock text-4xl text-gray-600 mb-3"></i>
                        <p class="text-sm text-gray-400">No tienes permisos asignados</p>
                    </div>
                <?php else: ?>
                    <ul class="space-y-1 px-2">
                        <?php foreach ($filteredMenu as $item): ?>
                            <?php echo $this->renderMenuItem($item, 0); ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </nav>

            <!-- User Footer -->
            <div class="p-4 border-t border-gray-700">
                <div class="flex items-center gap-3">
                    <img 
                        src="<?php echo htmlspecialchars($userAvatar); ?>" 
                        alt="<?php echo htmlspecialchars($fullName); ?>" 
                        class="w-10 h-10 rounded-full"
                   >
                    <div x-show="sidebarOpen" class="flex-1 overflow-hidden">
                        <p class="text-sm font-medium truncate">
                            <?php echo htmlspecialchars($fullName); ?>
                            <?php if ($this->isAdmin()): ?>
                            <span class="ml-2 text-xs bg-red-500 text-white px-2 py-0.5 rounded-full">Admin</span>
                            <?php endif; ?>
                        </p>
                        <?php if ($userEmail): ?>
                            <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($userEmail); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Menú desplegable del usuario -->
                    <div x-data="{ userMenuOpen: false }" class="relative" x-show="sidebarOpen">
                        <button 
                            @click="userMenuOpen = !userMenuOpen"
                            class="p-1 hover:bg-gray-800 rounded transition-colors"
                        >
                            <i class="fas fa-ellipsis-v text-gray-400"></i>
                        </button>
                        
                        <!-- Dropdown -->
                        <div 
                            x-show="userMenuOpen"
                            @click.away="userMenuOpen = false"
                            x-transition
                            class="absolute bottom-full right-0 mb-2 w-48 bg-gray-800 rounded-lg shadow-lg py-2 z-50"
                        >
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition-colors">
                                <i class="fas fa-user w-4"></i>
                                <span>Mi Perfil</span>
                            </a>
                            <a href="settings.php" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition-colors">
                                <i class="fas fa-cog w-4"></i>
                                <span>Configuración</span>
                            </a>
                            <hr class="my-2 border-gray-700">
                            <a href="logout.php" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition-colors text-red-400">
                                <i class="fas fa-sign-out-alt w-4"></i>
                                <span>Cerrar Sesión</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Obtiene el árbol del menú filtrado (útil para APIs)
     */
    public function getMenuTree() {
        return $this->getFilteredMenu();
    }
    
    /**
     * Cuenta los items del menú disponibles para el usuario
     */
    public function getMenuStats() {
        $filtered = $this->getFilteredMenu();
        
        $stats = [
            'total_items' => 0,
            'main_items' => count($filtered),
            'sub_items' => 0
        ];
        
        foreach ($filtered as $item) {
            $stats['total_items']++;
            if (!empty($item['submenu'])) {
                $stats['sub_items'] += count($item['submenu']);
                $stats['total_items'] += count($item['submenu']);
            }
        }
        
        return $stats;
    }
}
?>