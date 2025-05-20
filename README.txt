==================================================
 Entregas Cóndor Ltda. - Tienda en Línea (Versión Novato)
==================================================

¡Bienvenido! Esta es una guía para poner en marcha tu proyecto de tienda en línea
desarrollado con PHP puro y MySQL, ideal para aprender y para ejecución local en XAMPP.

--------------------
Estructura de Carpetas:
--------------------
Asegúrate de tener esta estructura dentro de tu directorio `htdocs` de XAMPP:

C:\xampp\htdocs\
└── entregas_condor\      <-- Carpeta raíz de tu proyecto
    ├── admin\
    │   ├── index.php
    │   ├── inventario.php
    │   ├── logout_admin.php
    │   ├── pedidos_actualizar_estado.php
    │   ├── pedidos.php
    │   ├── productos.php
    │   └── proveedores.php
    ├── backend\
    │   └── config.php
    ├── css\
    │   └── style.css
    ├── database\
    │   └── schema.sql
    ├── includes\
    │   ├── footer.php
    │   ├── header.php
    │   ├── nav_admin.php
    │   ├── nav_cliente.php
    │   └── nav_visitante.php
    ├── js\
    │   └── (vacía por ahora o con main.js si se añadió)
    ├── carrito.php
    ├── catalogo.php
    ├── checkout.php
    ├── index.php       
    ├── login.php
    ├── logout.php
    ├── mis_pedidos.php
    ├── registro.php
    └── README.txt          <-- Este archivo

--------------------
Pasos para la Instalación y Ejecución Local (XAMPP):
--------------------

**Paso 1: Configurar XAMPP**

1.  **Descarga e Instala XAMPP**: Desde [https://www.apachefriends.org/index.html](https://www.apachefriends.org/index.html).
2.  **Inicia XAMPP**: Abre el Panel de Control de XAMPP.
3.  **Inicia Apache y MySQL**.

**Paso 2: Crear la Base de Datos e Importar el Esquema**

1.  **Abre phpMyAdmin**: En XAMPP Control Panel, clic en "Admin" junto a MySQL.
2.  **Crea la Base de Datos**:
    * Nombre: `entregas_condor_simple`
    * Codificación: `utf8mb4_unicode_ci` (recomendado).
3.  **Importa `database/schema.sql`**:
    * Selecciona la base de datos `entregas_condor_simple`.
    * Ve a la pestaña "Importar".
    * Selecciona el archivo `entregas_condor\database\schema.sql`.
    * Haz clic en "Importar".
    * Deberías ver un mensaje de éxito. Las tablas y datos de ejemplo se habrán cargado.

**Paso 3: Actualizar Contraseñas de Ejemplo en la Base de Datos (¡IMPORTANTE!)**

El archivo `schema.sql` inserta usuarios de ejemplo (`ana.perez@email.com` y `luis.gomez@email.com`) con contraseñas ya hasheadas. Las contraseñas en texto plano son:
* `ana.perez@email.com` -> `clave123`
* `luis.gomez@email.com` -> `segura456`

Si quieres cambiarlas o añadir nuevos usuarios directamente en la BD y necesitas generar el hash:
1.  Crea un script PHP temporal (ej. `generar_hash.php`) en `entregas_condor/backend/` con:
    ```php
    <?php
    // backend/generar_hash.php
    $passwordTextoPlano = 'NUEVA_CLAVE_AQUI'; // Cambia esto
    echo "Hash para '" . $passwordTextoPlano . "': " . password_hash($passwordTextoPlano, PASSWORD_DEFAULT);
    ?>
    ```
2.  Ejecútalo en el navegador: `http://localhost/entregas_condor/backend/generar_hash.php`.
3.  Copia el hash y actualiza la columna `password_hash` en la tabla `Cliente` usando phpMyAdmin.
4.  Elimina `generar_hash.php`.

**Paso 4: Colocar los Archivos del Proyecto**

1.  Asegúrate de que todos los archivos y carpetas del proyecto estén dentro de `C:\xampp\htdocs\entregas_condor\`.

**Paso 5: Acceder a la Aplicación**

1.  **Página Principal Cliente**: Abre en tu navegador `http://localhost/entregas_condor/index.php`
2.  **Sección de Administración**:
    * Accede a `http://localhost/entregas_condor/admin/index.php`
    * Usuario: `admin`
    * Contraseña: `condor123` (puedes cambiarla en `admin/index.php` si lo deseas)

**Funcionalidades Implementadas:**

* **Cliente:**
    * Registro e Inicio de Sesión.
    * Ver Catálogo de Productos.
    * Añadir productos al Carrito de Compras (basado en sesión).
    * Ver y modificar Carrito.
    * Realizar Pedido (Checkout), lo que actualiza el stock.
    * Ver Historial de "Mis Pedidos".
    * Cerrar Sesión.
* **Administrador:**
    * Login y Logout de Administrador (credenciales fijas en el código).
    * Panel de Administración básico.
    * Ver Todos los Pedidos de Clientes.
    * Filtrar Pedidos por Estado.
    * Actualizar Estado de los Pedidos.
    * Listar Productos existentes.
    * Añadir Nuevos Productos (con inventario inicial).
    * Consultar Inventario actual.
    * Listar Proveedores existentes.
    * Añadir Nuevos Proveedores.
* **General:**
    * Registro de acciones importantes en la tabla `Bitacora`.

--------------------
Notas sobre Despliegue en Línea (Hosting):
--------------------
Este proyecto está diseñado para XAMPP (PHP y MySQL). Para ponerlo en línea en una URL pública (como la que intentaste con Netlify), necesitarás:

1.  **Un proveedor de hosting que soporte PHP y MySQL.** Netlify no es adecuado para este tipo de proyectos directamente.
2.  Subir todos los archivos del proyecto a tu cuenta de hosting.
3.  Crear una base de datos en tu hosting e importar `schema.sql`.
4.  Actualizar las credenciales de la base de datos en `backend/config.php` para que coincidan con las de tu hosting.

--------------------

  @media print {
    .ms-editor-squiggler {
        display:none !important;
    }
  }
  .ms-editor-squiggler {
    all: initial;
    display: block !important;
    height: 0px !important;
    width: 0px !important;
  }