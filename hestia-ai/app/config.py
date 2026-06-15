DEFAULT_BASE_PRICE = 100000
DEFAULT_CAPACITY = 10
MIN_HISTORY_ROWS = 10
MIN_ROOM_TYPE_ROWS = 5

DEFAULT_YIELD_STRATEGY = [
    {"min_occupancy_rate": 90, "multiplier": 1.15},
    {"min_occupancy_rate": 80, "multiplier": 1.135},
    {"min_occupancy_rate": 70, "multiplier": 1.12},
    {"min_occupancy_rate": 60, "multiplier": 1.105},
    {"min_occupancy_rate": 50, "multiplier": 1.09},
    {"min_occupancy_rate": 40, "multiplier": 1.075},
    {"min_occupancy_rate": 30, "multiplier": 1.06},
    {"min_occupancy_rate": 20, "multiplier": 1.045},
    {"min_occupancy_rate": 10, "multiplier": 1.03},
    {"min_occupancy_rate": 5, "multiplier": 1.015},
    {"min_occupancy_rate": 0, "multiplier": 1.00},
]

CYCLONE_MONTHS = {1, 2, 3}
WEEKEND_DAYS = {4, 5}
