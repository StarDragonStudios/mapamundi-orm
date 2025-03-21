# Mapamundi ORM
Mapamundi ORM es una librería ligera para PHP que implementa un mini-ORM con enfoque “Code First” utilizando Attributes (requiere PHP 8+). Permite definir entidades usando anotaciones en clases, sincronizar tablas automáticamente en la base de datos, y manejar operaciones CRUD sencillas, así como relaciones (One2One, One2Many, Many2One y Many2Many).

## Características principales
- Code First: Define tus modelos con Attributes y genera (o sincroniza) las tablas mediante un SchemaManager.
- Mini-ORM: Clase base Model con métodos save() y find().
- Soporte de relaciones:
  - Many2One, One2Many, One2One, Many2Many.
  - Carga de relaciones a través de métodos como loadRelations().
- Enfoque modular: Separado en namespaces para Database (conexión y schema) y ORM (modelo, columnas, relaciones).
## Requisitos
- PHP 8 o superior (por el uso de Attributes).
- PDO habilitado en PHP (usa PDO para conectar a la base de datos).
## Instalación
Puedes instalar la librería vía Composer.
En la raíz de tu proyecto, ejecuta:

```bash
composer require sdstudios/mapamundi-orm
```
## Uso básico
1. Configurar la conexión
   Define tu configuración de base de datos e inyecta en el DBCore y luego en Model:

```php
use Mapamindi\ORM\Database\DBConfig;
use Mapamindi\ORM\Database\DBCore;
use Mapamindi\ORM\Model;

// 1. Crear un objeto de configuración
$dbConfig = new DBConfig(host: 'localhost', user: 'root', password: '', dbName: 'test_db');

// 2. Crear el DBCore para obtener la instancia PDO
$dbCore = new DBCore($dbConfig);

// 3. Asignar la conexión PDO a Model
Model::setConnection($dbCore->getPDO());
```
2. Definir tus entidades
   Crea tus clases modelo en tu propio espacio de nombres (p. ej. App\Models). Cada propiedad con #[Column(...)] generará una columna en la tabla. El método tableName() por defecto convierte la clase a minúsculas y le agrega "s" (pero puedes sobrescribirlo).

```php
namespace App\Models;

use Mapamindi\ORM\Model;
use Mapamindi\ORM\Column;

class User extends Model
{
    #[Column(type: "int", primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(type: "varchar", length: 100)]
    public string $name;
}
```

3. Sincronizar la tabla (Code First)
   Usa SchemaManager para crear o sincronizar la tabla correspondiente a tu modelo:

```php
use Mapamindi\ORM\Database\SchemaManager;

$schemaManager = new SchemaManager($dbCore->getPDO());
$schemaManager->syncTable(User::class);
// Crea la tabla "users" si no existe (con campos id, name, etc.)
```
4. Operaciones CRUD
   Con la clase base Model, puedes hacer:

```php
// Crear (INSERT)
$user = new User();
$user->name = "Alice";
$user->save(); // auto-detecta que la PK está vacía => INSERT

// Leer (SELECT / find)
$loadedUser = User::find($user->id);
echo $loadedUser->name;

// Actualizar
$loadedUser->name = "Alice Wonderland";
$loadedUser->save(); // la PK está definida => UPDATE
```
5. Relaciones (opcional)
   Mapamindi ORM soporta Attributes adicionales para describir relaciones. Ejemplo de **Many2One**:

```php
use Mapamindi\ORM\Many2One;

class Post extends Model
{
#[Column(type: "int", primaryKey: true, autoIncrement: true)]
public int $id;

    #[Column(type: "varchar", length: 255)]
    public string $title;

    #[Column(type: "text")]
    public string $content;

    #[Column(type: "int")]
    public int $user_id;

    #[Many2One(target: 'App\Models\User', foreignKey: 'user_id', ownerKey: 'id')]
    public ?User $user = null;
}
```
Al sincronizar la tabla, se generará la FK (si usas la versión avanzada de SchemaManager que procesa relaciones). Para cargar la relación en tiempo de ejecución:
```php
$post = Post::find(10);
$post->loadRelations();
echo $post->user->name; // si user_id = 1, cargará el usuario con id=1
```
Existen también atributos #[One2One], #[One2Many], #[Many2Many] con su correspondiente lógica en SchemaManager.

## Ejemplo rápido
Un ejemplo simplificado de flujo:
```php
// 1. Conexión
use Mapamindi\ORM\Database\DBConfig;
use Mapamindi\ORM\Database\DBCore;
use Mapamindi\ORM\Model;
use Mapamindi\ORM\Database\SchemaManager;
use App\Models\User;
use App\Models\Post;

$dbConfig = new DBConfig('localhost', 'root', '', 'test_db', 3306);
$dbCore   = new DBCore($dbConfig);
Model::setConnection($dbCore->getPDO());

// 2. Code First (crear tablas)
$schema = new SchemaManager($dbCore->getPDO());
$schema->syncTable(User::class);
$schema->syncTable(Post::class);

// 3. CRUD
$user = new User();
$user->name = "Alice";
$user->save();

$post = new Post();
$post->title   = "Mi primer post";
$post->content = "Contenido...";
$post->user_id = $user->id;
$post->save();

// 4. Relaciones
$post2 = Post::find($post->id);
$post2->loadRelations();
echo $post2->user->name; // "Alice"
```
## Roadmap / Limitaciones
- Migraciones: No se incluye un sistema de versiones (migraciones secuenciales). Se recomienda usar un flujo de migraciones para entornos productivos.
- Orden de creación: Para relaciones con FK, debe existir la tabla referenciada primero. En proyectos más grandes, se maneja orden o se usa CREATE TABLE IF NOT EXISTS + ALTER TABLE.
- Lazy/Eager Loading: Actualmente la carga de relaciones es manual con loadRelations(). Se podrían implementar triggers de “lazy loading” al acceder a la propiedad.
## Contribuciones
¡Se aceptan Pull Requests! Si deseas mejorar la librería (migraciones, tipos adicionales de columna, transacciones, etc.), siéntete libre de abrir un Issue o un PR.

## Licencia
MIT License – Puedes usar libremente Mapamindi ORM en proyectos personales y comerciales. Consulta el archivo LICENSE para más detalles.

---
¡Gracias por usar Mapamindi ORM! Esperamos que te ayude a construir aplicaciones rápidas y ligeras en PHP con un modelo de datos sencillo y un sistema de tablas automatizado.