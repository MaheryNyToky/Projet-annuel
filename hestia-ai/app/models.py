from typing import Dict, List, Optional

from pydantic import BaseModel, Field


class HistoricData(BaseModel):
    date: str = Field(
        examples=["2026-07-01"],
        description="Date d'observation au format YYYY-MM-DD.",
    )
    room_type: str = Field(
        examples=["Chambre Double - Superieure"],
        description="Identifiant fonctionnel de categorie, construit cote Laravel avec type + modele.",
    )
    rooms_booked: int = Field(
        ge=0,
        examples=[4],
        description="Nombre de chambres occupees pour cette categorie et cette date.",
    )


class YieldRule(BaseModel):
    min_occupancy_rate: float = Field(
        ge=0,
        le=100,
        examples=[80],
        description="Seuil minimal d'occupation en pourcentage.",
    )
    multiplier: float = Field(
        gt=0,
        examples=[1.135],
        description="Multiplicateur applique au prix plancher lorsque le seuil est atteint.",
    )


class PredictionRequest(BaseModel):
    base_prices: Dict[str, int] = Field(
        examples=[{"Chambre Double - Superieure": 125000}],
        description="Prix plancher par categorie de chambre, en ariary.",
    )
    days_to_predict: Optional[int] = Field(
        default=30,
        ge=1,
        le=365,
        examples=[30],
        description="Nombre de jours a predire a partir de start_date.",
    )
    start_date: Optional[str] = Field(
        default=None,
        examples=["2026-07-01"],
        description="Date de debut des predictions. Si absent, le moteur utilise la date courante.",
    )
    history: List[HistoricData] = Field(
        description="Historique d'occupation agrégé par date et categorie.",
    )
    room_capacities: Optional[Dict[str, int]] = Field(
        default=None,
        examples=[{"Chambre Double - Superieure": 8}],
        description="Capacite maximale par categorie. Si absent, DEFAULT_CAPACITY est utilise.",
    )
    yield_strategy: Optional[List[YieldRule]] = Field(
        default=None,
        description="Regles de yield optionnelles. Si absent, DEFAULT_YIELD_STRATEGY est utilise.",
    )
