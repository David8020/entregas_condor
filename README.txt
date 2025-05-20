Entregas Condor Simple (Versión Novato)
=======================================

1) Requisitos
-------------
- XAMPP instalado (Apache + MySQL).
- Base de datos MySQL ‘entregas_condor_simple’ creada y cargada con `database/schema.sql`.
- Apache con mod_rewrite habilitado.

2) Instalación
--------------
1. Copia la carpeta `entregas_condor_simple` en `C:/xampp/htdocs/`.
2. Importa el script `database/schema.sql` en phpMyAdmin o MySQL Workbench.
3. Asegúrate de que `backend/config.php`  tenga las credenciales correctas (por defecto root sin contraseña).
4. Reinicia Apache.

3) Uso de la API
----------------
La API principal está en `backend/api.php`. Tienes dos formas de invocar rutas:

a) Con parámetro `r`:
   - Registro:    `POST http://localhost/entregas_condor_simple/backend/api.php?r=registro`
   - Login:       `POST http://localhost/entregas_condor_simple/backend/api.php?r=login`
   - Logout:      `GET  http://localhost/entregas_condor_simple/backend/api.php?r=logout`
   - Productos:   `GET  http://localhost/entregas_condor_simple/backend/api.php?r=productos`
                  `POST http://localhost/entregas_condor_simple/backend/api.php?r=productos`
   - Proveedores: `GET  http://localhost/entregas_condor_simple/backend/api.php?r=proveedores`
   - Pedidos:     `GET  http://localhost/entregas_condor_simple/backend/api.php?r=pedidos`
                  `POST http://localhost/entregas_condor_simple/backend/api.php?r=pedidos`
   - Inventario:  `GET  http://localhost/entregas_condor_simple/backend/api.php?r=inventario`
   - Ver sesión:  `GET  http://localhost/entregas_condor_simple/backend/api.php?r=ver_sesion`

b) Con URLs limpias (requiere `.htaccess`):
   - Registro:    `POST http://localhost/entregas_condor_simple/backend/registro`
   - Productos:   `GET  http://localhost/entregas_condor_simple/backend/productos`
   - etc.

4) Ejemplos con Postman
------------------------
- **Registro**:
    - URL: `POST http://.../backend/api.php?r=registro`
    - Headers: `Content-Type: application/x-www-form-urlencoded`
    - Body (form-data o x-www-form-urlencoded):
      ```
      nombre=Juan
      apellidos=Pérez
      correo=juan.perez@email.com
      password=clave123
      ```
- **Login**:
    - URL: `POST http://.../backend/api.php?r=login`
    - Body (igual estructura, con correo y password)

- **Obtener productos**:
    - URL: `GET http://.../backend/api.php?r=productos`

- **Crear pedido**:
    - URL: `POST http://.../backend/api.php?r=pedidos`
    - Headers: `Content-Type: application/json`
    - Body (raw JSON):
      ```json
      {
        "productos": [
          { "id_producto": 1, "cantidad": 2 },
          { "id_producto": 3, "cantidad": 1 }
        ]
      }
      ```

5) Notas finales
----------------
- Asegúrate de revisar la consola Network (F12) o **Postman** para ver **status codes** y **bodies** de respuesta.
- Si ves errores 500, revisa `xampp/apache/logs/error.log`.
- ¡Listo! Con esto deberías tener la API funcionando. Si algo falla, dime exactamente qué URL pruebas, con qué método, headers, body y el mensaje de respuesta. 

---

Cuando tengas `.htaccess` y `README.txt` creados, avísame y probamos juntos con Postman paso a paso.
