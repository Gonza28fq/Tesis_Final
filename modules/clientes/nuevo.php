<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('clientes_crear')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$error = '';
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // Validaciones
        $nombre = sanitize($_POST['nombre']);
        $apellido = sanitize($_POST['apellido']);
        $email = sanitize($_POST['email']);
        $telefono = sanitize($_POST['telefono'] ?? null);
        $direccion = sanitize($_POST['direccion'] ?? null);
        $dni_cuit = sanitize($_POST['dni_cuit'] ?? null);
        $tipo_cliente = sanitize($_POST['tipo_cliente']);
        
        $errores = [];
        
        if (empty($nombre)) $errores[] = 'El nombre es obligatorio';
        if (empty($apellido)) $errores[] = 'El apellido es obligatorio';
        if (empty($email)) $errores[] = 'El email es obligatorio';
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no es válido';
        }
        
        if (!empty($errores)) {
            throw new Exception(implode(', ', $errores));
        }
        
        // Verificar duplicados
        $sqlCheck = "SELECT id_cliente FROM Clientes 
                     WHERE email = :email 
                     OR (dni_cuit = :dni_cuit AND dni_cuit IS NOT NULL AND dni_cuit != '')";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute([
            ':email' => $email,
            ':dni_cuit' => $dni_cuit
        ]);
        
        if ($stmtCheck->fetch()) {
            throw new Exception('Ya existe un cliente con ese email o DNI/CUIT');
        }
        
        // Insertar cliente
        $sql = "INSERT INTO Clientes 
                (nombre, apellido, email, telefono, direccion, dni_cuit, tipo_cliente) 
                VALUES 
                (:nombre, :apellido, :email, :telefono, :direccion, :dni_cuit, :tipo_cliente)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':email' => $email,
            ':telefono' => $telefono,
            ':direccion' => $direccion,
            ':dni_cuit' => $dni_cuit,
            ':tipo_cliente' => $tipo_cliente
        ]);
        
        header('Location: index.php?success=created');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente - Sistema de Gestión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #4299e1 0%, #667eea 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4299e1 0%, #667eea 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }

        .btn-success {
            background: #48bb78;
            color: white;
            width: 100%;
            padding: 15px;
            font-size: 16px;
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fed7d7;
            border: 2px solid #fc8181;
            color: #742a2a;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .required {
            color: #f56565;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4299e1;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .help-text {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #4299e1;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Nuevo Cliente</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="formCliente">
                <div class="section-title">📋 Información Personal</div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" name="nombre" required 
                               value="<?php echo $_POST['nombre'] ?? ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Apellido <span class="required">*</span></label>
                        <input type="text" name="apellido" required 
                               value="<?php echo $_POST['apellido'] ?? ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" required 
                               value="<?php echo $_POST['email'] ?? ''; ?>">
                        <div class="help-text">Se verificará que no esté duplicado</div>
                    </div>

                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" 
                               value="<?php echo $_POST['telefono'] ?? ''; ?>"
                               placeholder="Ej: 381-1234567">
                    </div>

                    <div class="form-group">
                        <label>DNI/CUIT</label>
                        <input type="text" name="dni_cuit" 
                               value="<?php echo $_POST['dni_cuit'] ?? ''; ?>"
                               placeholder="Ej: 20-12345678-9">
                        <div class="help-text">Se verificará que no esté duplicado</div>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Cliente <span class="required">*</span></label>
                        <select name="tipo_cliente" required>
                            <option value="consumidor_final" <?php echo ($_POST['tipo_cliente'] ?? '') === 'consumidor_final' ? 'selected' : ''; ?>>
                                Consumidor Final
                            </option>
                            <option value="responsable_inscripto" <?php echo ($_POST['tipo_cliente'] ?? '') === 'responsable_inscripto' ? 'selected' : ''; ?>>
                                Responsable Inscripto
                            </option>
                            <option value="monotributista" <?php echo ($_POST['tipo_cliente'] ?? '') === 'monotributista' ? 'selected' : ''; ?>>
                                Monotributista
                            </option>
                            <option value="exento" <?php echo ($_POST['tipo_cliente'] ?? '') === 'exento' ? 'selected' : ''; ?>>
                                Exento
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Dirección</label>
                    <textarea name="direccion" placeholder="Calle, número, ciudad..."><?php echo $_POST['direccion'] ?? ''; ?></textarea>
                </div>

                <button type="submit" class="btn btn-success">✓ Guardar Cliente</button>
            </form>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.getElementById('formCliente').addEventListener('submit', function(e) {
            const email = document.querySelector('[name="email"]').value;
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Por favor ingrese un email válido');
                return false;
            }
            
            return confirm('¿Confirmar la creación del cliente?');
        });

        // Formatear DNI/CUIT mientras escribe
        document.querySelector('[name="dni_cuit"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            
            if (value.length >= 2) {
                value = value.substring(0, 2) + '-' + value.substring(2);
            }
            if (value.length >= 11) {
                value = value.substring(0, 11) + '-' + value.substring(11);
            }
            
            e.target.value = value.substring(0, 13);
        });
    </script>
</body>
</html>