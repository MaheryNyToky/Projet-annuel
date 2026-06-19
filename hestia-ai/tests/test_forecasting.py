import os
import unittest

os.environ.setdefault("MPLCONFIGDIR", "/tmp/hestia-ai-matplotlib-cache")
os.makedirs(os.environ["MPLCONFIGDIR"], exist_ok=True)

import pandas as pd

from app.models import YieldRule
from app.services.forecasting import (
    _multiplier_for_occupancy,
    _normalize_yield_strategy,
    _price_predictions,
    _seasonal_occupancy_floor,
)


class ForecastingPriceTest(unittest.TestCase):
    def test_price_predictions_apply_yield_rules_rounding_and_capacity_cap(self):
        predictions_df = pd.DataFrame(
            {
                "ds": pd.to_datetime(["2026-07-01", "2026-07-02", "2026-07-03"]),
                "yhat": [2, 8, 15],
            }
        )

        results = _price_predictions(
            predictions_df=predictions_df,
            base_price=120000,
            capacity=10,
            yield_strategy=[
                {"min_occupancy_rate": 80, "multiplier": 1.135},
                {"min_occupancy_rate": 20, "multiplier": 1.045},
                {"min_occupancy_rate": 0, "multiplier": 1.0},
            ],
        )

        self.assertEqual([2, 8, 10], [item["predicted_occupancy"] for item in results])
        self.assertEqual([125000, 136000, 136000], [item["suggested_price_ariary"] for item in results])
        self.assertTrue(all(item["base_price"] == 120000 for item in results))

    def test_price_predictions_handle_zero_capacity_without_division_error(self):
        predictions_df = pd.DataFrame(
            {
                "ds": pd.to_datetime(["2026-07-01"]),
                "yhat": [8],
            }
        )

        results = _price_predictions(
            predictions_df=predictions_df,
            base_price=95000,
            capacity=0,
            yield_strategy=[{"min_occupancy_rate": 0, "multiplier": 1.0}],
        )

        self.assertEqual(0, results[0]["predicted_occupancy"])
        self.assertEqual(95000, results[0]["suggested_price_ariary"])

    def test_yield_strategy_is_normalized_from_models_and_sorted_by_threshold(self):
        rules = _normalize_yield_strategy(
            [
                YieldRule(min_occupancy_rate=20, multiplier=1.045),
                YieldRule(min_occupancy_rate=80, multiplier=1.135),
                YieldRule(min_occupancy_rate=0, multiplier=1.0),
            ]
        )

        self.assertEqual([80, 20, 0], [rule["min_occupancy_rate"] for rule in rules])
        self.assertEqual(1.135, _multiplier_for_occupancy(90, rules))
        self.assertEqual(1.045, _multiplier_for_occupancy(30, rules))
        self.assertEqual(1.0, _multiplier_for_occupancy(1, rules))

    def test_seasonal_occupancy_floor_is_higher_in_peak_summer_months(self):
        july_floor = _seasonal_occupancy_floor(pd.Timestamp("2026-07-15"), 12)
        august_floor = _seasonal_occupancy_floor(pd.Timestamp("2026-08-15"), 12)
        november_floor = _seasonal_occupancy_floor(pd.Timestamp("2026-11-15"), 12)

        self.assertGreaterEqual(july_floor, 10)
        self.assertGreaterEqual(august_floor, 10)
        self.assertLess(november_floor, july_floor)
        self.assertGreater(november_floor, 1)


if __name__ == "__main__":
    unittest.main()
