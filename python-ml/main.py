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
