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
