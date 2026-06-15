import traceback

from app.models import PredictionRequest

data = PredictionRequest(base_prices={"Standard": 100000}, history=[])
try:
    if data.room_capacities:
        pass
except Exception as e:
    print(f"Exception: {type(e).__name__} - {e}")
    traceback.print_exc()
