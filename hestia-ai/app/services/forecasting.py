import time
import hashlib
from math import ceil
from typing import Dict, List

import pandas as pd
from prophet import Prophet

from app.config import (
    CYCLONE_MONTHS,
    DEFAULT_BASE_PRICE,
    DEFAULT_CAPACITY,
    DEFAULT_YIELD_STRATEGY,
    MIN_ROOM_TYPE_ROWS,
    WEEKEND_DAYS,
)
from app.models import HistoricData, YieldRule


MODEL_CACHE_TTL_SECONDS = 6 * 60 * 60
MODELS_CACHE = {}


def predict_prices(
    history: List[HistoricData],
    base_prices: Dict[str, int],
    days_to_predict: int = 30,
    start_date: str | None = None,
    room_capacities: Dict[str, int] | None = None,
    yield_strategy: List[YieldRule] | None = None,
) -> Dict[str, list]:
    raw_records = [
        {"ds": item.date, "room_type": item.room_type, "y": item.rooms_booked}
        for item in history
    ]
    if not raw_records:
        return {}

    df_total = pd.DataFrame(raw_records)
    df_total["ds"] = pd.to_datetime(df_total["ds"])

    all_predictions = {}
    # Garde-fou contre les requêtes trop volumineuses, même hors validation FastAPI.
    periods = max(1, min(days_to_predict or 30, 365))
    start_dt_str = start_date or pd.Timestamp.now().strftime("%Y-%m-%d")
    rules = _normalize_yield_strategy(yield_strategy)

    for room_type in df_total["room_type"].unique():
        df_type = df_total[df_total["room_type"] == room_type].copy()
        if len(df_type) < MIN_ROOM_TYPE_ROWS:
            continue

        try:
            base_price = max(0, base_prices.get(room_type, DEFAULT_BASE_PRICE))
            capacity = max(0, (room_capacities or {}).get(room_type, DEFAULT_CAPACITY))
            history_signature = _history_signature(df_type)
            predictions_df = _forecast_room_type(
                room_type,
                df_type,
                start_dt_str,
                periods,
                capacity,
                history_signature,
            )
            all_predictions[room_type] = _price_predictions(
                predictions_df,
                base_price,
                capacity,
                rules,
            )
        except Exception:
            continue

    return all_predictions


def _forecast_room_type(
    room_type: str,
    df_type: pd.DataFrame,
    start_date: str,
    periods: int,
    capacity: int,
    history_signature: str,
) -> pd.DataFrame:
    cached = MODELS_CACHE.get(room_type)
    now = time.time()

    if cached and now - cached[0] < MODEL_CACHE_TTL_SECONDS and cached[1] == history_signature:
        model = cached[2]
    else:
        model = Prophet(
            yearly_seasonality=True,
            weekly_seasonality=True,
            daily_seasonality=False,
            changepoint_prior_scale=0.1,
            seasonality_mode="multiplicative",
        )
        model.add_country_holidays(country_name="MG")
        model.fit(df_type[["ds", "y"]])
        MODELS_CACHE[room_type] = (now, history_signature, model)

    future_dates = pd.date_range(start=start_date, periods=periods, freq="D")
    forecast = model.predict(pd.DataFrame({"ds": future_dates}))
    predictions_df = forecast[["ds", "yhat"]].copy()

    for index, row in predictions_df.iterrows():
        date = row["ds"]
        value = row["yhat"]

        if date.month in CYCLONE_MONTHS:
            value *= 0.4

        if date.weekday() in WEEKEND_DAYS:
            value *= 1.5

        seasonal_floor = _seasonal_occupancy_floor(date, capacity)
        predictions_df.at[index, "yhat"] = max(seasonal_floor, max(1, round(value)))

    return predictions_df


def _history_signature(df_type: pd.DataFrame) -> str:
    payload = df_type[["ds", "y"]].sort_values("ds").to_json(
        orient="records",
        date_format="iso",
    )
    return hashlib.sha1(payload.encode("utf-8")).hexdigest()


def _seasonal_occupancy_floor(date: pd.Timestamp, capacity: int) -> int:
    if capacity <= 0:
        return 0

    if date.month in {7, 8}:
        target_rate = 0.9
    elif date.month in {6, 9}:
        target_rate = 0.75
    elif date.month in {5, 10}:
        target_rate = 0.6
    elif date.month in CYCLONE_MONTHS:
        target_rate = 0.35
    else:
        target_rate = 0.45

    if date.weekday() in WEEKEND_DAYS:
        target_rate += 0.05

    return max(0, min(capacity, int(ceil(capacity * target_rate))))


def _price_predictions(
    predictions_df: pd.DataFrame,
    base_price: int,
    capacity: int,
    yield_strategy: list[dict],
) -> list:
    type_results = []

    for _, row in predictions_df.iterrows():
        date_str = row["ds"].strftime("%Y-%m-%d")
        predicted_load = max(0, min(int(row["yhat"]), capacity))
        occupancy_rate = (predicted_load / capacity) * 100 if capacity > 0 else 0
        multiplier = _multiplier_for_occupancy(occupancy_rate, yield_strategy)
        suggested_price = int(round(base_price * multiplier, -3))

        type_results.append(
            {
                "date": date_str,
                "predicted_occupancy": predicted_load,
                "suggested_price_ariary": suggested_price,
                "base_price": base_price,
            }
        )

    return type_results


def _normalize_yield_strategy(yield_strategy: List[YieldRule] | None) -> list[dict]:
    rules = [
        rule.model_dump() if hasattr(rule, "model_dump") else rule.dict()
        for rule in (yield_strategy or [])
    ]

    if not rules:
        rules = DEFAULT_YIELD_STRATEGY

    return sorted(rules, key=lambda rule: rule["min_occupancy_rate"], reverse=True)


def _multiplier_for_occupancy(occupancy_rate: float, rules: list[dict]) -> float:
    for rule in rules:
        if occupancy_rate >= rule["min_occupancy_rate"]:
            return rule["multiplier"]

    return 1.0
