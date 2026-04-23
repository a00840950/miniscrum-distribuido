# Laboratorio End to End: MiniScrum Distribuido

## 1. Objetivo del laboratorio

En este laboratorio vas a construir una aplicacion web distribuida desde cero, usando varios servicios conectados entre si mediante HTTP y Docker.

La aplicacion se llama **MiniScrum Distribuido**. Su objetivo es gestionar tareas de un proyecto Scrum y sugerir automaticamente la cantidad de puntos Scrum de una tarea usando una regresion lineal sencilla.

Al finalizar tendras:

- Una aplicacion web con frontend.
- Un API Gateway en Node.js + Express.
- Un servicio CRUD en PHP conectado a MySQL.
- Un servicio de Machine Learning en Python + FastAPI.
- Una base de datos MySQL.
- Un proyecto versionado con Git.
- Una aplicacion desplegada en un VPS usando Dockploy.

## 2. Arquitectura general

El sistema tendra 4 contenedores principales:

```text
Navegador del usuario
        |
        v
Node.js + Express
Frontend + API Gateway
        |
        |--------------------------|
        v                          v
PHP API + CRUD              Python API + Regresion lineal
        |
        v
MySQL
```

Responsabilidades:

```text
gateway:
  Sirve el frontend y recibe las peticiones del navegador.
  Redirige las peticiones hacia PHP o Python.

php-api:
  Gestiona las tareas.
  Crea, consulta y actualiza registros en MySQL.

python-ml:
  Recibe horas estimadas.
  Devuelve puntos Scrum sugeridos mediante una regresion lineal.

mysql:
  Guarda las tareas de forma persistente.
```

## 3. Requisitos previos

Antes de comenzar, verifica que tienes:

- Git instalado.
- Docker Desktop instalado, si vas a probar localmente.
- Cuenta en GitHub, GitLab o un proveedor Git compatible con Dockploy.
- Acceso al VPS de la clase.
- Acceso a Dockploy.
- Un editor de codigo, por ejemplo Visual Studio Code.

Para verificar Git:

```bash
git --version
```

Para verificar Docker:

```bash
docker --version
docker compose version
```

## 4. Resultado esperado

La aplicacion final debe permitir:

1. Ver una pagina web principal.
2. Crear una tarea con:
   - titulo
   - descripcion
   - horas estimadas
3. Pedir una prediccion de puntos Scrum al servicio Python.
4. Guardar la tarea en MySQL mediante el servicio PHP.
5. Listar las tareas guardadas.
6. Cambiar el estado de una tarea:
   - Pendiente
   - En proceso
   - Terminada
7. Desplegar todo el sistema usando Docker Compose en Dockploy.

## 5. Crear el repositorio del proyecto

Crea una carpeta para el proyecto:

```bash
mkdir miniscrum-distribuido
cd miniscrum-distribuido
```

Inicializa Git:

```bash
git init
```

Crea un archivo llamado `.gitignore`:

```text
node_modules/
__pycache__/
.env
.DS_Store
vendor/
*.log
```

Haz tu primer commit:

```bash
git add .
git commit -m "Inicializar repositorio del laboratorio"
```

## 6. Crear la estructura de carpetas

Dentro del proyecto crea esta estructura:

```text
miniscrum-distribuido/
|
|-- docker-compose.yml
|
|-- gateway/
|   |-- Dockerfile
|   |-- package.json
|   |-- server.js
|   |-- public/
|       |-- index.html
|       |-- style.css
|       |-- app.js
|
|-- php-api/
|   |-- Dockerfile
|   |-- index.php
|   |-- db.php
|
|-- python-ml/
|   |-- Dockerfile
|   |-- requirements.txt
|   |-- main.py
|
|-- mysql/
    |-- init.sql
```

Puedes crear las carpetas desde la terminal:

```bash
mkdir gateway
mkdir gateway/public
mkdir php-api
mkdir python-ml
mkdir mysql
```

Despues de crear la estructura, guarda el avance:

```bash
git status
git add .
git commit -m "Crear estructura inicial de servicios"
```

## 7. Crear la base de datos MySQL

Crea el archivo:

```text
mysql/init.sql
```

Agrega el siguiente script SQL:

```sql
CREATE DATABASE IF NOT EXISTS miniscrum;

USE miniscrum;

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  estimated_hours DECIMAL(5,2) NOT NULL,
  scrum_points INT NOT NULL,
  status ENUM('Pendiente', 'En proceso', 'Terminada') DEFAULT 'Pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Explicacion:

- `id`: identificador unico de cada tarea.
- `title`: nombre corto de la tarea.
- `description`: detalle de la tarea.
- `estimated_hours`: horas estimadas.
- `scrum_points`: puntos Scrum sugeridos.
- `status`: estado de avance.
- `created_at`: fecha de creacion.

Guarda el cambio en Git:

```bash
git add .
git commit -m "Agregar script inicial de base de datos"
```

## 8. Crear el servicio PHP API

El servicio PHP sera responsable de conectarse a MySQL y gestionar las tareas.

### 8.1 Crear el Dockerfile de PHP

Crea el archivo:

```text
php-api/Dockerfile
```

Agrega:

```dockerfile
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

EXPOSE 80
```

Este contenedor usa Apache y PHP. Tambien instala las extensiones necesarias para conectarse a MySQL.

### 8.2 Crear la conexion a la base de datos

Crea el archivo:

```text
php-api/db.php
```

Agrega:

```php
<?php
$host = getenv("DB_HOST") ?: "mysql";
$db = getenv("DB_NAME") ?: "miniscrum";
$user = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD") ?: "root";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Error de conexion a la base de datos",
        "details" => $e->getMessage()
    ]);
    exit;
}
```

Nota importante:

```text
No escribas contrasenas reales directamente en el codigo.
Usa variables de entorno en Docker Compose y Dockploy.
```

### 8.3 Crear los endpoints del CRUD

Crea el archivo:

```text
php-api/index.php
```

Agrega:

```php
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if ($path === "/tasks" && $method === "GET") {
    $stmt = $pdo->query("SELECT * FROM tasks ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($path === "/tasks" && $method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $title = $data["title"] ?? "";
    $description = $data["description"] ?? "";
    $estimatedHours = $data["estimated_hours"] ?? 0;
    $scrumPoints = $data["scrum_points"] ?? 0;

    if ($title === "" || $estimatedHours <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Titulo y horas estimadas son obligatorios"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO tasks (title, description, estimated_hours, scrum_points)
        VALUES (:title, :description, :estimated_hours, :scrum_points)
    ");

    $stmt->execute([
        ":title" => $title,
        ":description" => $description,
        ":estimated_hours" => $estimatedHours,
        ":scrum_points" => $scrumPoints
    ]);

    echo json_encode([
        "message" => "Tarea creada correctamente",
        "id" => $pdo->lastInsertId()
    ]);
    exit;
}

if (preg_match("#^/tasks/([0-9]+)/status$#", $path, $matches) && $method === "PUT") {
    $id = $matches[1];
    $data = json_decode(file_get_contents("php://input"), true);
    $status = $data["status"] ?? "";

    $allowedStatuses = ["Pendiente", "En proceso", "Terminada"];

    if (!in_array($status, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(["error" => "Estado invalido"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
    $stmt->execute([
        ":status" => $status,
        ":id" => $id
    ]);

    echo json_encode(["message" => "Estado actualizado"]);
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Ruta no encontrada"]);
```

Guarda el avance:

```bash
git add .
git commit -m "Crear API PHP para tareas"
```

## 9. Crear el servicio Python de regresion lineal

El servicio Python recibira horas estimadas y devolvera una prediccion de puntos Scrum.

### 9.1 Crear requirements.txt

Crea el archivo:

```text
python-ml/requirements.txt
```

Agrega:

```text
fastapi==0.115.0
uvicorn==0.30.6
scikit-learn==1.5.1
numpy==2.1.1
```

### 9.2 Crear el Dockerfile de Python

Crea el archivo:

```text
python-ml/Dockerfile
```

Agrega:

```dockerfile
FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
```

### 9.3 Crear el API de prediccion

Crea el archivo:

```text
python-ml/main.py
```

Agrega:

```python
from fastapi import FastAPI
from pydantic import BaseModel
from sklearn.linear_model import LinearRegression
import numpy as np

app = FastAPI(title="MiniScrum ML API")

training_hours = np.array([[1], [2], [4], [6], [8], [12], [16], [20]])
training_points = np.array([1, 1, 2, 3, 5, 8, 13, 21])

model = LinearRegression()
model.fit(training_hours, training_points)


class PredictionRequest(BaseModel):
    estimated_hours: float


@app.get("/")
def health_check():
    return {"message": "Servicio Python ML funcionando"}


@app.post("/predict")
def predict_points(request: PredictionRequest):
    prediction = model.predict([[request.estimated_hours]])[0]
    rounded_prediction = max(1, round(prediction))

    return {
        "estimated_hours": request.estimated_hours,
        "predicted_points": rounded_prediction
    }
```

Explicacion:

- `training_hours` contiene ejemplos de horas.
- `training_points` contiene los puntos Scrum asociados.
- `LinearRegression` aprende una relacion aproximada entre horas y puntos.
- `/predict` recibe horas y devuelve puntos sugeridos.

Guarda el avance:

```bash
git add .
git commit -m "Crear servicio Python de prediccion"
```

## 10. Crear el API Gateway con Node.js + Express

El Gateway tiene dos trabajos:

1. Servir el frontend.
2. Recibir peticiones del navegador y reenviarlas a PHP o Python.

### 10.1 Crear package.json

Crea el archivo:

```text
gateway/package.json
```

Agrega:

```json
{
  "name": "miniscrum-gateway",
  "version": "1.0.0",
  "main": "server.js",
  "scripts": {
    "start": "node server.js"
  },
  "dependencies": {
    "express": "^4.18.3"
  }
}
```

Nota:

Node.js 18 o superior ya incluye `fetch`, asi que no necesitas instalar Axios.

### 10.2 Crear el Dockerfile del Gateway

Crea el archivo:

```text
gateway/Dockerfile
```

Agrega:

```dockerfile
FROM node:20-alpine

WORKDIR /app

COPY package.json .
RUN npm install

COPY . .

EXPOSE 3000

CMD ["npm", "start"]
```

### 10.3 Crear server.js

Crea el archivo:

```text
gateway/server.js
```

Agrega:

```javascript
const express = require("express");
const path = require("path");

const app = express();
const PORT = process.env.PORT || 3000;

const PHP_API_URL = process.env.PHP_API_URL || "http://php-api";
const PYTHON_ML_URL = process.env.PYTHON_ML_URL || "http://python-ml:8000";

app.use(express.json());
app.use(express.static(path.join(__dirname, "public")));

app.get("/api/tasks", async (req, res) => {
  try {
    const response = await fetch(`${PHP_API_URL}/tasks`);
    const data = await response.json();
    res.status(response.status).json(data);
  } catch (error) {
    res.status(500).json({ error: "No se pudo consultar el servicio PHP" });
  }
});

app.post("/api/tasks", async (req, res) => {
  try {
    const response = await fetch(`${PHP_API_URL}/tasks`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(req.body)
    });

    const data = await response.json();
    res.status(response.status).json(data);
  } catch (error) {
    res.status(500).json({ error: "No se pudo crear la tarea" });
  }
});

app.put("/api/tasks/:id/status", async (req, res) => {
  try {
    const response = await fetch(`${PHP_API_URL}/tasks/${req.params.id}/status`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(req.body)
    });

    const data = await response.json();
    res.status(response.status).json(data);
  } catch (error) {
    res.status(500).json({ error: "No se pudo actualizar la tarea" });
  }
});

app.post("/api/predict", async (req, res) => {
  try {
    const response = await fetch(`${PYTHON_ML_URL}/predict`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(req.body)
    });

    const data = await response.json();
    res.status(response.status).json(data);
  } catch (error) {
    res.status(500).json({ error: "No se pudo consultar el servicio Python" });
  }
});

app.listen(PORT, () => {
  console.log(`Gateway ejecutandose en puerto ${PORT}`);
});
```

Guarda el avance:

```bash
git add .
git commit -m "Crear API Gateway con Express"
```

## 11. Crear el frontend

El frontend estara dentro de:

```text
gateway/public/
```

### 11.1 Crear index.html

Crea el archivo:

```text
gateway/public/index.html
```

Agrega:

```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MiniScrum Distribuido</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="container">
    <section class="header">
      <h1>MiniScrum Distribuido</h1>
      <p>CRUD de tareas con PHP, prediccion con Python y Gateway con Node.js.</p>
    </section>

    <section class="panel">
      <h2>Nueva tarea</h2>

      <form id="taskForm">
        <label for="title">Titulo</label>
        <input id="title" name="title" type="text" required>

        <label for="description">Descripcion</label>
        <textarea id="description" name="description" rows="3"></textarea>

        <label for="estimatedHours">Horas estimadas</label>
        <input id="estimatedHours" name="estimatedHours" type="number" min="1" step="0.5" required>

        <button type="button" id="predictButton">Predecir puntos</button>

        <p id="predictionResult">Puntos sugeridos: pendiente</p>

        <button type="submit">Guardar tarea</button>
      </form>
    </section>

    <section class="panel">
      <h2>Tareas registradas</h2>
      <div id="tasksList"></div>
    </section>
  </main>

  <script src="app.js"></script>
</body>
</html>
```

### 11.2 Crear style.css

Crea el archivo:

```text
gateway/public/style.css
```

Agrega:

```css
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: Arial, sans-serif;
  background: #f4f6f8;
  color: #1f2933;
}

.container {
  width: min(1000px, 92%);
  margin: 0 auto;
  padding: 32px 0;
}

.header {
  margin-bottom: 24px;
}

.header h1 {
  margin-bottom: 8px;
}

.panel {
  background: #ffffff;
  border: 1px solid #d9e2ec;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
}

form {
  display: grid;
  gap: 12px;
}

label {
  font-weight: bold;
}

input,
textarea,
select,
button {
  width: 100%;
  padding: 10px;
  font-size: 16px;
}

button {
  border: none;
  border-radius: 6px;
  background: #2563eb;
  color: white;
  cursor: pointer;
}

button:hover {
  background: #1d4ed8;
}

.task {
  border: 1px solid #d9e2ec;
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 12px;
  background: #ffffff;
}

.task h3 {
  margin: 0 0 8px;
}

.task p {
  margin: 6px 0;
}
```

### 11.3 Crear app.js

Crea el archivo:

```text
gateway/public/app.js
```

Agrega:

```javascript
const taskForm = document.getElementById("taskForm");
const predictButton = document.getElementById("predictButton");
const predictionResult = document.getElementById("predictionResult");
const tasksList = document.getElementById("tasksList");

let predictedPoints = null;

async function loadTasks() {
  const response = await fetch("/api/tasks");
  const tasks = await response.json();

  tasksList.innerHTML = "";

  if (tasks.length === 0) {
    tasksList.innerHTML = "<p>No hay tareas registradas.</p>";
    return;
  }

  tasks.forEach((task) => {
    const element = document.createElement("article");
    element.className = "task";

    element.innerHTML = `
      <h3>${task.title}</h3>
      <p>${task.description || "Sin descripcion"}</p>
      <p><strong>Horas:</strong> ${task.estimated_hours}</p>
      <p><strong>Puntos Scrum:</strong> ${task.scrum_points}</p>
      <p><strong>Estado:</strong> ${task.status}</p>
      <select data-id="${task.id}">
        <option value="Pendiente" ${task.status === "Pendiente" ? "selected" : ""}>Pendiente</option>
        <option value="En proceso" ${task.status === "En proceso" ? "selected" : ""}>En proceso</option>
        <option value="Terminada" ${task.status === "Terminada" ? "selected" : ""}>Terminada</option>
      </select>
    `;

    tasksList.appendChild(element);
  });
}

predictButton.addEventListener("click", async () => {
  const estimatedHours = Number(document.getElementById("estimatedHours").value);

  if (!estimatedHours || estimatedHours <= 0) {
    predictionResult.textContent = "Ingresa horas estimadas validas.";
    return;
  }

  const response = await fetch("/api/predict", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ estimated_hours: estimatedHours })
  });

  const data = await response.json();
  predictedPoints = data.predicted_points;

  predictionResult.textContent = `Puntos sugeridos: ${predictedPoints}`;
});

taskForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  const title = document.getElementById("title").value;
  const description = document.getElementById("description").value;
  const estimatedHours = Number(document.getElementById("estimatedHours").value);

  if (!predictedPoints) {
    alert("Primero debes predecir los puntos Scrum.");
    return;
  }

  await fetch("/api/tasks", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      title,
      description,
      estimated_hours: estimatedHours,
      scrum_points: predictedPoints
    })
  });

  taskForm.reset();
  predictedPoints = null;
  predictionResult.textContent = "Puntos sugeridos: pendiente";

  loadTasks();
});

tasksList.addEventListener("change", async (event) => {
  if (event.target.tagName !== "SELECT") {
    return;
  }

  const taskId = event.target.dataset.id;
  const status = event.target.value;

  await fetch(`/api/tasks/${taskId}/status`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ status })
  });

  loadTasks();
});

loadTasks();
```

Guarda el avance:

```bash
git add .
git commit -m "Crear frontend de MiniScrum"
```

## 12. Crear Docker Compose

Crea el archivo:

```text
docker-compose.yml
```

Agrega:

```yaml
services:
  gateway:
    build: ./gateway
    container_name: miniscrum_gateway
    ports:
      - "3000:3000"
    environment:
      PORT: 3000
      PHP_API_URL: http://php-api
      PYTHON_ML_URL: http://python-ml:8000
    depends_on:
      - php-api
      - python-ml

  php-api:
    build: ./php-api
    container_name: miniscrum_php_api
    environment:
      DB_HOST: mysql
      DB_NAME: miniscrum
      DB_USER: root
      DB_PASSWORD: root
    depends_on:
      - mysql

  python-ml:
    build: ./python-ml
    container_name: miniscrum_python_ml

  mysql:
    image: mysql:8.0
    container_name: miniscrum_mysql
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: miniscrum
    volumes:
      - mysql_data:/var/lib/mysql
      - ./mysql/init.sql:/docker-entrypoint-initdb.d/init.sql

volumes:
  mysql_data:
```

Importante:

```text
En clase se usa root/root para simplificar.
En un proyecto real no debes usar contrasenas simples.
```

Guarda el avance:

```bash
git add .
git commit -m "Agregar Docker Compose del sistema distribuido"
```

## 13. Probar el sistema localmente

Desde la raiz del proyecto ejecuta:

```bash
docker compose up --build
```

Cuando termine de construir los contenedores, abre:

```text
http://localhost:3000
```

Prueba:

1. Crear una tarea.
2. Escribir horas estimadas.
3. Presionar "Predecir puntos".
4. Guardar tarea.
5. Ver la tarea en la lista.
6. Cambiar el estado.

Si necesitas detener los contenedores:

```bash
docker compose down
```

Si quieres detenerlos y borrar el volumen de la base de datos:

```bash
docker compose down -v
```

Usa `-v` con cuidado, porque borra los datos guardados en MySQL.

## 14. Probar APIs manualmente

Puedes probar el Gateway desde la terminal.

Listar tareas:

```bash
curl http://localhost:3000/api/tasks
```

Predecir puntos:

```bash
curl -X POST http://localhost:3000/api/predict \
  -H "Content-Type: application/json" \
  -d "{\"estimated_hours\": 6}"
```

Crear tarea:

```bash
curl -X POST http://localhost:3000/api/tasks \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"Crear login\",\"description\":\"Pantalla inicial de acceso\",\"estimated_hours\":6,\"scrum_points\":4}"
```

Actualizar estado:

```bash
curl -X PUT http://localhost:3000/api/tasks/1/status \
  -H "Content-Type: application/json" \
  -d "{\"status\":\"En proceso\"}"
```

## 15. Buenas practicas de Git durante el laboratorio

No esperes hasta el final para guardar todo. Trabaja por avances pequenos.

Comandos recomendados:

```bash
git status
```

Muestra que archivos cambiaron.

```bash
git add .
```

Prepara los cambios para el commit.

```bash
git commit -m "Mensaje claro del cambio"
```

Guarda una version del proyecto.

Ejemplos de buenos mensajes:

```bash
git commit -m "Crear servicio PHP de tareas"
git commit -m "Agregar endpoint de prediccion en Python"
git commit -m "Conectar frontend con API Gateway"
git commit -m "Configurar despliegue con Docker Compose"
```

Ejemplos de mensajes poco claros:

```bash
git commit -m "cosas"
git commit -m "cambios"
git commit -m "final"
```

Para ver el historial:

```bash
git log --oneline
```

## 16. Subir el repositorio a GitHub

Crea un repositorio vacio en GitHub. No agregues README ni `.gitignore` desde GitHub si ya los creaste localmente.

Conecta tu repositorio local con GitHub:

```bash
git remote add origin https://github.com/TU_USUARIO/miniscrum-distribuido.git
```

Cambia el nombre de la rama principal a `main`:

```bash
git branch -M main
```

Sube el proyecto:

```bash
git push -u origin main
```

Cada vez que hagas cambios despues:

```bash
git add .
git commit -m "Describir cambio realizado"
git push
```

Dockploy usara tu repositorio para construir y desplegar la aplicacion.

## 17. Preparar el proyecto para Dockploy

Antes de desplegar revisa:

```text
El archivo docker-compose.yml esta en la raiz del repositorio.
Todos los Dockerfile estan dentro de sus carpetas.
El repositorio esta subido a GitHub.
La rama main contiene la version final.
El puerto publico del Gateway es 3000.
```

En este laboratorio, el unico servicio que necesita exponerse publicamente es:

```text
gateway:3000
```

Los servicios internos no deben exponerse al navegador directamente:

```text
php-api
python-ml
mysql
```

El navegador debe entrar por Node.js, no directamente por PHP o Python.

## 18. Desplegar en Dockploy

Los nombres exactos pueden variar segun la configuracion del VPS, pero el flujo general es:

1. Entra a Dockploy.
2. Crea una nueva aplicacion o proyecto.
3. Conecta tu repositorio Git.
4. Selecciona la rama:

```text
main
```

5. Indica que el despliegue usa Docker Compose.
6. Verifica que Dockploy detecte:

```text
docker-compose.yml
```

7. Configura el puerto publico de la aplicacion:

```text
3000
```

8. Inicia el despliegue.
9. Espera a que Docker construya las imagenes.
10. Abre la URL publica asignada por Dockploy.

Si el VPS es compartido, usa nombres unicos para evitar conflictos. Por ejemplo:

```text
miniscrum-juan-perez
miniscrum-ana-gomez
miniscrum-carlos-ruiz
```

Si Dockploy permite configurar variables de entorno desde su panel, puedes mover ahi:

```text
DB_HOST=mysql
DB_NAME=miniscrum
DB_USER=root
DB_PASSWORD=root
PHP_API_URL=http://php-api
PYTHON_ML_URL=http://python-ml:8000
```

## 19. Problemas comunes y soluciones

### El navegador no abre la app

Revisa que el contenedor `gateway` este activo y que el puerto sea `3000`.

```bash
docker compose ps
```

### El frontend carga, pero no aparecen tareas

Puede fallar la comunicacion entre Node y PHP.

Revisa en `docker-compose.yml`:

```yaml
PHP_API_URL: http://php-api
```

El nombre `php-api` debe coincidir con el nombre del servicio en Docker Compose.

### No se guardan las tareas

Revisa las variables de entorno de PHP:

```yaml
DB_HOST: mysql
DB_NAME: miniscrum
DB_USER: root
DB_PASSWORD: root
```

Tambien revisa que MySQL haya iniciado correctamente.

### La prediccion falla

Revisa que el Gateway apunte al servicio Python:

```yaml
PYTHON_ML_URL: http://python-ml:8000
```

Y que el servicio se llame `python-ml` en `docker-compose.yml`.

### MySQL tarda en arrancar

Es normal que MySQL tarde algunos segundos. Si el API PHP falla al inicio, espera un poco y vuelve a intentar.

### Cambie el SQL, pero no se refleja

El script `init.sql` solo se ejecuta cuando el volumen de MySQL se crea por primera vez.

Para reiniciar la base de datos en desarrollo:

```bash
docker compose down -v
docker compose up --build
```

Recuerda que esto borra los datos.

## 20. README que debes entregar

En la raiz del proyecto crea un archivo:

```text
README.md
```

Debe contener:

```markdown
# MiniScrum Distribuido

## Objetivo

Explicar brevemente que hace la aplicacion.

## Arquitectura

Navegador -> Node Gateway -> PHP API -> MySQL
Navegador -> Node Gateway -> Python ML API

## Servicios

- gateway: frontend y API Gateway.
- php-api: CRUD de tareas.
- python-ml: prediccion de puntos Scrum.
- mysql: base de datos relacional.

## Endpoints principales

- GET /api/tasks
- POST /api/tasks
- PUT /api/tasks/:id/status
- POST /api/predict

## Base de datos

Tabla principal: tasks.

## Como ejecutar localmente

docker compose up --build

## URL desplegada

Pegar aqui la URL de Dockploy.

## Evidencias

Agregar capturas de:
- aplicacion funcionando
- tareas guardadas
- contenedores activos
- repositorio con commits

## Retrospectiva

Que salio bien:

Que fue dificil:

Que mejoraria:
```

Guarda el README:

```bash
git add README.md
git commit -m "Agregar documentacion del proyecto"
git push
```

## 21. Evidencias de entrega

Debes entregar:

```text
1. URL publica de Dockploy.
2. URL del repositorio Git.
3. Captura de la aplicacion funcionando.
4. Captura de una tarea guardada.
5. Captura del historial de commits.
6. README completo.
```

## 22. Rubrica sugerida

```text
20% Arquitectura distribuida
    - Entiende la separacion de servicios.
    - Explica el papel del Gateway.
    - Identifica que servicios son internos y cual es publico.

20% PHP + MySQL
    - Crea tareas.
    - Lista tareas.
    - Actualiza estados.
    - Usa base de datos relacional.

20% Python ML API
    - Expone endpoint /predict.
    - Recibe horas estimadas.
    - Devuelve puntos Scrum sugeridos.

20% Node.js + Frontend
    - Sirve la interfaz web.
    - Consume endpoints del Gateway.
    - Redirige correctamente hacia PHP y Python.

20% Docker, Git y despliegue
    - Usa Docker Compose.
    - Tiene commits claros.
    - Repositorio disponible.
    - Aplicacion desplegada en Dockploy.
```

## 23. Preguntas de reflexion

Responde al final del README:

```text
1. Por que el navegador no se conecta directamente a MySQL?
2. Que ventaja tiene separar el servicio PHP del servicio Python?
3. Que funcion cumple el API Gateway?
4. Que pasaria si el servicio Python deja de funcionar?
5. Que diferencia hay entre ejecutar localmente con Docker Compose y desplegar en Dockploy?
6. Que parte del sistema representa la capa de datos?
7. Que parte representa la capa de presentacion?
8. Que parte representa la logica de negocio?
```

## 24. Retos opcionales

Si terminas antes, puedes agregar:

```text
1. Eliminar tareas.
2. Filtrar tareas por estado.
3. Agregar prioridad: Baja, Media, Alta.
4. Agregar fecha limite.
5. Agregar contador de tareas terminadas.
6. Mejorar los estilos CSS.
7. Mostrar errores en pantalla en lugar de usar alert.
8. Crear un endpoint /health en cada servicio.
```

## 25. Resumen final

Este laboratorio integra los temas principales del curso:

```text
Gestion de proyectos:
  Backlog, tareas, estados, documentacion y retrospectiva.

Desarrollo web:
  HTML, CSS, JavaScript, PHP, Node.js y APIs.

Bases de datos:
  MySQL, tablas, INSERT, SELECT y UPDATE.

Arquitectura:
  Sistema distribuido, API Gateway, servicios internos y persistencia.

DevOps:
  Git, Docker, Docker Compose, repositorio remoto y despliegue en Dockploy.
```

El resultado no es solo una pagina web. Es una aplicacion distribuida completa, pequena pero parecida a como se organizan muchos sistemas reales.
