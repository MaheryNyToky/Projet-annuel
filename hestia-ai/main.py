from fastapi import FastAPI, HTTPException

from app.config import MIN_HISTORY_ROWS
from app.models import PredictionRequest
from app.services.forecasting import predict_prices

"""
HestiaPredict AI Engine
Service de prédiction basé sur l'algorithme Prophet de Meta.
"""

app = FastAPI(
    title="HestiaPredict AI Engine - Kamoro Hotel",
    version="1.0.0",
    description=(
        "Moteur FastAPI interne charge de transformer l'historique "
        "d'occupation hotelier en predictions de demande et en prix suggeres. "
        "Le backend Laravel reste la source de verite metier; ce service est "
        "stateless et peut etre remplace ou versionne sans migrer la base."
    ),
    openapi_tags=[
        {
            "name": "Health",
            "description": "Verification de disponibilite du moteur IA.",
        },
        {
            "name": "Forecasting",
            "description": "Prediction Prophet et application des regles de yield.",
        },
    ],
)


@app.get(
    "/health",
    tags=["Health"],
    summary="Verifier que le moteur IA repond",
)
def health_check():
    return {"status": "ok"}


@app.post(
    "/predict",
    tags=["Forecasting"],
    summary="Predire l'occupation et les prix par categorie",
    description=(
        "Entraine Prophet avec l'historique fourni, applique les effets "
        "saisonniers metier puis calcule un prix suggere par date et categorie."
    ),
)
def predict_and_price(data: PredictionRequest):
    if len(data.history) < MIN_HISTORY_ROWS:
        raise HTTPException(
            status_code=400,
            detail="Historique de données insuffisant pour l'entraînement du modèle.",
        )

    results = predict_prices(
        history=data.history,
        base_prices=data.base_prices,
        days_to_predict=data.days_to_predict or 30,
        start_date=data.start_date,
        room_capacities=data.room_capacities,
        yield_strategy=data.yield_strategy,
    )

    return {"status": "success", "results": results}
