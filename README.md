# 🖥 Sistema de Gestión de Equipos TI

Sistema web completo para administrar el inventario de equipos informáticos, usuarios, soporte técnico y mantenimientos preventivos/correctivos.

---

## 🗂 Estructura del proyecto

```
gestion_ti/
├── index.php                        ← Dashboard principal
├── equipos.php                      ← CRUD de equipos (con IP, MAC, nodo)
├── usuarios.php                     ← CRUD de usuarios y asignación de equipos
├── inventario.php                   ← Periféricos y accesorios
├── tickets.php                      ← Tickets de soporte
├── mantenimiento.php                ← Órdenes de mantenimiento + checklist
├── areas.php                        ← Áreas / departamentos
├── api.php                          ← API JSON (búsqueda IP, MAC, folios)
├── schema.sql                       ← Base de datos completa con datos de ejemplo
├── includes/
│   ├── db.php                       ← Configuración PDO
│   ├── functions.php                ← Toda la lógica de negocio
│   ├── header.php                   ← Navbar + flash messages
│   └── footer.php                   ← Scripts Bootstrap
├── assets/
│   ├── css/app.css                  ← Estilos personalizados
│   └── js/app.js                    ← JavaScript general
└── exports/
    ├── export_equipos_pdf.php       ← Reporte equipos (HTML imprimible → PDF)
    ├── export_equipos_xls.php       ← Equipos en CSV/Excel
    ├── export_inventario_xls.php    ← Inventario en CSV/Excel
    ├── export_tickets_xls.php       ← Tickets en CSV/Excel
    └── export_mantenimiento_pdf.php ← Orden de mantenimiento imprimible
```

---

## ⚙️ Requisitos

| Componente | Versión mínima |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Servidor web | Apache / Nginx / XAMPP / Laragon |

---

## 🚀 Instalación paso a paso

### 1. Copiar archivos
Coloca la carpeta `gestion_ti/` dentro de tu directorio web:
- XAMPP: `C:\xampp\htdocs\gestion_ti\`
- Laragon: `C:\laragon\www\gestion_ti\`
- Linux: `/var/www/html/gestion_ti/`

### 2. Crear la base de datos
Ejecuta el archivo `schema.sql` en tu gestor MySQL:

```bash
# Opción A — línea de comandos
mysql -u root -p < schema.sql

# Opción B — phpMyAdmin
# Abrir phpMyAdmin → pestaña "SQL" → pegar contenido → Ejecutar
```

### 3. Configurar conexión
Edita `includes/db.php`:

```php
define('DB_HOST', 'localhost');     // Host de MySQL
define('DB_NAME', 'gestion_ti');   // Nombre de la BD
define('DB_USER', 'root');         // Usuario MySQL
define('DB_PASS', '');             // Contraseña MySQL
```

### 4. Acceder al sistema
```
http://localhost/gestion_ti/
```

---

## 📋 Módulos del sistema

### 🏠 Dashboard
- KPIs: total equipos, activos, en mantenimiento, usuarios, tickets activos, IPs libres
- Tabla de equipos por tipo con conteos
- Últimos tickets recientes
- Acceso rápido a todas las funciones

### 🖥 Equipos
- Registro completo: tipo, marca, modelo, serie
- **Dirección IP** y **MAC Address** (buscables)
- Nodo de red, medio de conexión
- Vinculación con área y usuario
- Vista detalle: periféricos, historial de mantenimientos, tickets
- Filtros: tipo, área, estado, búsqueda libre

### 👤 Usuarios
- Datos personales y puesto
- Usuario de Windows y referencia de contraseña
- Vinculación a área
- Vista de equipos y periféricos asignados

### 📦 Inventario
- Periféricos: monitor, teclado, mouse, headset, webcam, UPS, etc.
- Vinculado a equipo, usuario y área
- Estados: Bueno / Regular / Malo / Baja

### 🎫 Tickets / Soporte
- Folio automático (TKT-YYYY-NNNN)
- Tipos: Mantenimiento Preventivo, Correctivo, Soporte, Instalación
- Prioridades: Urgente / Alta / Media / Baja
- Estados: Abierto → En Proceso → Resuelto / Cancelado
- Generación directa de mantenimiento desde un ticket

### 🔧 Mantenimiento
**Checklist completo con 4 categorías:**

| Categoría | Puntos |
|---|---|
| **Limpieza Física** | Carcasa, teclado, monitor, CPU interno, ventiladores, cables, RAM, disco |
| **Limpieza Lógica** | Windows Update, drivers, CCleaner, antivirus, Office, AAMY/Anydesk, Bitlocker |
| **Hardware / Periféricos** | Monitor, teclado, CPU, USB, red, sonido, cámara |
| **Seguridad / Red** | IP correcta, MAC registrada, nodo correcto, firewall, acceso remoto |

- Resultado por punto: ✓ OK / ⚠ Requiere Atención / ✗ Mal / — N/A
- Notas por punto
- Marcar todo OK con un clic
- Exportar a PDF con firmas de técnico y usuario
- Historial por equipo

### 📊 Exportación
| Módulo | Formato |
|---|---|
| Equipos | PDF (imprimible) + CSV/Excel |
| Inventario | CSV/Excel |
| Tickets | CSV/Excel |
| Mantenimiento | PDF con checklist + firmas |

> **Para exportación avanzada (.xlsx real):** instala `composer require phpoffice/phpspreadsheet`

---

## 🔑 Atajos y funciones útiles

### API JSON (`api.php`)
```
/api.php?action=search_ip&ip=192.168.1.10     → Busca equipo por IP
/api.php?action=search_mac&mac=AA:BB           → Busca equipo por MAC
/api.php?action=next_folio_equipo              → Siguiente folio disponible
/api.php?action=equipo_info&equipo_id=5        → Info completa de un equipo
```

### Folios automáticos
- Equipos: `EQ-0001`, `EQ-0002`, ...
- Tickets: `TKT-2024-0001`, `TKT-2024-0002`, ...

---

## 🎨 Personalización

- **Colores:** editar variables CSS en `assets/css/app.css` (`:root`)
- **Logo/nombre:** editar `APP_NAME` en `includes/db.php`
- **Checklist items:** editar `getChecklistTemplate()` en `includes/functions.php`
- **Tipos de equipo:** agregar desde la BD o crear una interfaz admin

---

## 🔒 Seguridad en producción

1. Proteger acceso con autenticación (sesión PHP o OAuth)
2. Usar HTTPS
3. Crear usuario MySQL con permisos mínimos (SELECT, INSERT, UPDATE, DELETE)
4. No almacenar contraseñas en texto claro
5. Configurar `.htaccess` para proteger carpeta `includes/`
6. Validar y sanitizar todas las entradas (el sistema ya usa PDO con parámetros)

---

## 📱 Capturas de pantalla

```
Dashboard → KPIs + tabla resumen + tickets recientes
Equipos   → Grid con IP, MAC, nodo, estado
Detalle   → Periféricos + historial mantenimientos + tickets
Checklist → 40+ puntos organizados en 4 categorías con colores
PDF       → Orden de mantenimiento con firmas lista para imprimir
```
