# 📊 Commercial Management System 2.0

> Sistema integral de gestión comercial con arquitectura modular, control de roles y auditoría completa

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-7952B3?style=flat&logo=bootstrap&logoColor=white)

## 🎯 Descripción

Sistema de gestión empresarial desarrollado como proyecto de tesis que automatiza procesos comerciales internos, eliminando métodos manuales y sistemas aislados. Logró una reducción del 85% en errores administrativos mediante centralización de datos y control de accesos por roles.

## ✨ Características Principales

- ✅ **Gestión de Clientes**: Cartera completa con historial de transacciones
- 🛒 **Módulo de Ventas**: Registro, facturación y cálculos automáticos de impuestos
- 📦 **Control de Inventario**: Stock en tiempo real con alertas automáticas
- 💰 **Gestión de Compras**: Proveedores y órdenes con seguimiento
- 👥 **Sistema de Usuarios**: Roles y permisos con interfaces dinámicas
- 📊 **Reportes Estadísticos**: Dashboards en tiempo real para decisiones estratégicas
- 🔒 **Auditoría Completa**: Trazabilidad total de operaciones por usuario

## 🛠️ Stack Tecnológico

**Frontend:**
- HTML5, CSS3, JavaScript (ES6+)
- Bootstrap 5 para diseño responsive
- AJAX para interacciones asíncronas

**Backend:**
- PHP 7.4+
- Arquitectura MVC modular
- API REST con JSON
- Stored Procedures para seguridad

**Base de Datos:**
- MySQL/MariaDB
- Diseño normalizado con relaciones complejas
- Triggers y procedures para integridad

**Seguridad:**
- Hashing de contraseñas (bcrypt)
- Validaciones en tiempo real
- Protección contra SQL Injection
- Control de acceso por roles (RBAC)

**Otras Tecnologías:**
- Composer (gestión de dependencias)
- Cron Jobs (tareas programadas)
- Apache Server

## 📋 Requisitos

- PHP >= 7.4
- MySQL >= 5.7 o MariaDB >= 10.3
- Apache Server
- Composer

## 🚀 Instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/Gonza28fq/Tesis_Final.git
cd Tesis_Final
```

2. **Instalar dependencias**
```bash
composer install
```

3. **Configurar base de datos**
   - Crear una base de datos MySQL
   - Importar el archivo `database.sql` (si está disponible)
   - Editar `config/database.php` con tus credenciales

4. **Configurar el servidor**
   - Apuntar el DocumentRoot a la carpeta del proyecto
   - O usar XAMPP/WAMP apuntando a la carpeta

5. **Acceder al sistema**
```
http://localhost/Tesis_Final
```

## 👤 Credenciales de Prueba
```
Usuario: admin
Contraseña: [contactar al desarrollador]
```

## 📸 Capturas de Pantalla

[Aquí agregar screenshots cuando los tengas]

## 🏗️ Arquitectura del Sistema

### Módulos Principales:
```
📁 modules/
  ├── clientes/      # Gestión de clientes
  ├── ventas/        # Módulo de ventas
  ├── compras/       # Gestión de compras
  ├── productos/     # Control de inventario
  ├── usuarios/      # Administración de usuarios
  ├── auditorias/    # Sistema de trazabilidad
  └── reportes/      # Dashboards y estadísticas
```

### Base de Datos:
- 15+ tablas relacionales
- Triggers para auditoría automática
- Stored procedures para operaciones críticas
- Vistas para reportes optimizados

## 🔄 Migración a Stack Moderno (En Desarrollo)

Actualmente desarrollando una versión 3.0 con:
- **Frontend**: React + TypeScript
- **Backend**: Node.js + Express
- **Base de Datos**: PostgreSQL
- **Seguridad mejorada**: JWT + cifrado robusto

## 📄 Documentación

Documentación técnica completa (240 páginas) disponible que incluye:
- Análisis de requerimientos
- Diseño de arquitectura
- Diagramas UML (casos de uso, clases, secuencia)
- Manual de usuario
- Manual técnico

## 🤝 Contribuciones

Este es un proyecto académico completado. Si encontrás bugs o tenés sugerencias, no dudes en abrir un issue.

## 👨‍💻 Autor

**Gonzalo Iván Pedraza**
- 📧 Email: gonza280797@hotmail.com
- 💼 LinkedIn: [linkedin.com/in/gonzalo-pedraza]](https://www.linkedin.com/in/gonzalo-pedraza-b8b568298/)

## 📝 Licencia

Este proyecto fue desarrollado como trabajo de tesis para el título de Técnico Universitario en Desarrollo de Software - Instituto 9 de Julio (2024)

---

⭐ Si te resultó útil este proyecto, no dudes en darle una estrella!
