
<?php

require_once __DIR__ . "/../core/Model.php";

class Usuario extends Model
{

    public function buscarPorEmail($email)
    {
        $sql = "SELECT * FROM usuarios WHERE email = :email";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            "email" => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorId($id)
    {
        $sql = "SELECT id, nombre, email, rol, creado
                FROM usuarios
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            "id" => $id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Guarda un usuario nuevo en la tabla usuarios. 
// Recibe el nombre, el email y la contraseña YA hasheada (nunca en texto plano). 
    public function crear($nombre, $email, $contraseñaHash)
    {
        // Armamos la consulta con "marcadores" (:nombre, :email, :clave) en vez de pegar 
        // los valores directamente. Así PDO los envía por separado y evita SQL Injection. 
        // OJO: los marcadores NO pueden llevar ñ, por eso se llama :clave y no :contraseña. 
        // La columna de la tabla sí se sigue llamando contraseña. 
        $sql = "INSERT INTO usuarios (nombre, email, contraseña, rol, creado) 
            VALUES (:nombre, :email, :clave, 'user', NOW())";   // todo usuario nuevo es 'user' 

        // prepare() le avisa a MySQL la forma de la consulta antes de mandar los datos 
        $stmt = $this->pdo->prepare($sql);

        // execute() reemplaza cada marcador por su valor. Las claves del array 
        // deben coincidir con los marcadores (sin los dos puntos). 
        return $stmt->execute([
            "nombre" => $nombre,
            "email" => $email,
            "clave" => $contraseñaHash
        ]);
    }
   public function todos() { 
    // Elegimos las columnas a mano: NUNCA devolvemos la contraseña ni el token 
    $sql = "SELECT id, nombre, email, rol, creado FROM usuarios ORDER BY id"; 
    return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); 
}
}

?>