import pandas as pd
from prophet import Prophet

data = [
    {"ds": "2023-01-01", "y": 1}, {"ds": "2023-01-02", "y": 2},
    {"ds": "2023-01-03", "y": 1}, {"ds": "2023-01-04", "y": 3},
    {"ds": "2023-01-05", "y": 1}, {"ds": "2023-01-06", "y": 2},
    {"ds": "2023-01-07", "y": 4}, {"ds": "2023-01-08", "y": 1},
    {"ds": "2023-01-09", "y": 2}, {"ds": "2023-01-10", "y": 1}
]
df = pd.DataFrame(data)
df['ds'] = pd.to_datetime(df['ds'])

try:
    model = Prophet(yearly_seasonality=True, weekly_seasonality=True, daily_seasonality=False)
    model.fit(df)
    future = model.make_future_dataframe(periods=30, freq='D')
    forecast = model.predict(future)
    print("Success")
except Exception as e:
    print(f"Error: {e}")
