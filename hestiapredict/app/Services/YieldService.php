<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class YieldService
{
    public function __construct(private readonly AvailabilityService $availabilityService)
    {
    }

    public function predictions(int $days, string $startDate): array
    {
        $historyData = $this->historyData();
        $roomsInfo = $this->roomsInfo();
        $basePrices = $roomsInfo->mapWithKeys(fn ($info) => [$info['identifier'] => $info['base_price']])->all();
        $roomCapacities = $roomsInfo->mapWithKeys(fn ($info) => [$info['identifier'] => $info['capacity']])->all();
        $fixedPriceFlags = $roomsInfo->mapWithKeys(fn ($info) => [$info['identifier'] => $info['is_fixed_price']])->all();
        $roomMetadata = $roomsInfo->mapWithKeys(fn ($info) => [$info['identifier'] => $info])->all();

        try {
            $response = (new Client([
                'connect_timeout' => 1.0,
                'timeout' => 3.0,
            ]))->post(config('services.ai_engine.url') . '/predict', [
                'json' => [
                    'base_prices' => $basePrices,
                    'days_to_predict' => $days,
                    'start_date' => $startDate,
                    'history' => $historyData,
                    'room_capacities' => $roomCapacities,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                ...$this->alignAiPrices($data, $basePrices, $fixedPriceFlags, $roomMetadata, $days, $startDate),
                'mode' => 'ai',
                'ai_available' => true,
                'is_fallback' => false,
            ];
        } catch (\Throwable) {
            return $this->fallbackPredictions($days, $startDate);
        }
    }

    public function auditDate(string $date): array
    {
        $roomsConfirmed = $this->availabilityService->occupiedRoomCount($date, ['arrive']);
        $roomsEstimated = $this->availabilityService->occupiedRoomCount($date);

        $dailyCaOfficial = $this->revenueForDate($date, ['arrive']);
        $dailyCaPending = $this->revenueForDate($date, ['en_attente']);
        $totalCA = Reservation::query()
            ->where('check_in_date', '<=', $date)
            ->where('status', 'arrive')
            ->with('rooms')
            ->get()
            ->sum(fn (Reservation $reservation) => $reservation->rooms->sum(fn (Room $room) => (int) $room->pivot->price_snapshot_ariary));

        return [
            'status' => 'success',
            'rooms_confirmed' => (int) $roomsConfirmed,
            'rooms_estimated' => (int) $roomsEstimated,
            'daily_ca_official' => (int) $dailyCaOfficial,
            'daily_ca_pending' => (int) $dailyCaPending,
            'total_ca' => (int) $totalCA,
            'period' => 'Depuis le début de l\'année jusqu\'au ' . date('d/m/Y', strtotime($date)),
        ];
    }

    private function historyData(): array
    {
        return Reservation::query()
            ->with('rooms')
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->get()
            ->flatMap(function (Reservation $reservation) {
                return $reservation->rooms->map(fn (Room $room) => [
                    'date' => $reservation->check_in_date->toDateString(),
                    'room_type' => $room->identifier,
                    'room_id' => $room->id,
                ]);
            })
            ->groupBy(fn (array $item) => $item['date'] . '|' . $item['room_type'])
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'date' => $first['date'],
                    'room_type' => $first['room_type'],
                    'rooms_booked' => $items->pluck('room_id')->unique()->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function roomsInfo(): Collection
    {
        return Room::query()
            ->get()
            ->groupBy(fn (Room $room) => $room->identifier)
            ->map(fn (Collection $rooms, string $identifier) => [
                'identifier' => $identifier,
                'base_price' => (int) $rooms->first()->base_price_ariary,
                'capacity' => $rooms->count(),
                'type' => $rooms->first()->type,
                'model' => $rooms->first()->model,
                'is_fixed_price' => $rooms->every(fn (Room $room) => $room->is_fixed_price),
            ])
            ->values();
    }

    private function alignAiPrices(
        array $data,
        array $basePrices,
        array $fixedPriceFlags,
        array $roomMetadata,
        int $days,
        string $startDate,
    ): array
    {
        if (!isset($data['results'])) {
            return $data;
        }

        $maxMultiplier = 1.0;
        foreach ($data['results'] as $category => $predictions) {
            if ($fixedPriceFlags[$category] ?? false) {
                continue;
            }

            foreach ($predictions as $prediction) {
                if ($prediction['date'] === $startDate && !empty($prediction['base_price'])) {
                    $maxMultiplier = max($maxMultiplier, $prediction['suggested_price_ariary'] / $prediction['base_price']);
                }
            }
        }

        foreach ($data['results'] as $category => &$predictions) {
            $basePrice = $basePrices[$category] ?? 0;
            $isFixedPrice = $fixedPriceFlags[$category] ?? false;
            foreach ($predictions as &$prediction) {
                $adjustedPrice = $basePrice;

                if (!$isFixedPrice) {
                    $realGlobalOccupancy = $this->availabilityService->occupiedRoomCount($prediction['date']);
                    $realMultiplier = $this->realTimeMultiplier($realGlobalOccupancy);
                    $finalMultiplier = min(1.15, max($realMultiplier, $maxMultiplier));
                    $adjustedPrice = (int) round($basePrice * $finalMultiplier, -3);

                    if ($adjustedPrice < $basePrice) {
                        $adjustedPrice = $basePrice;
                    }
                }

                $prediction['fixed_price_ariary'] = $basePrice;
                $prediction['adjusted_price_ariary'] = $adjustedPrice;
                $prediction['suggested_price_ariary'] = $adjustedPrice;
                $prediction['base_price'] = $basePrice;
                $prediction['is_fixed_price'] = $isFixedPrice;
            }
        }

        foreach ($roomMetadata as $category => $room) {
            if (isset($data['results'][$category])) {
                continue;
            }

            $data['results'][$category] = [];
            for ($i = 0; $i < $days; $i++) {
                $date = date('Y-m-d', strtotime("$startDate +$i days"));
                $categoryBookedCount = $this->availabilityService->categoryOccupiedCount($date, $room['type'], $room['model']);

                $data['results'][$category][] = [
                    'date' => $date,
                    'predicted_occupancy' => $categoryBookedCount,
                    'fixed_price_ariary' => $room['base_price'],
                    'adjusted_price_ariary' => $room['base_price'],
                    'suggested_price_ariary' => $room['base_price'],
                    'base_price' => $room['base_price'],
                    'is_fixed_price' => $room['is_fixed_price'],
                ];
            }
        }

        return $data;
    }

    private function fallbackPredictions(int $days, string $startDate): array
    {
        $results = [];

        foreach ($this->roomsInfo() as $room) {
            $key = $room['identifier'];
            $results[$key] = [];

            for ($i = 0; $i < $days; $i++) {
                $date = date('Y-m-d', strtotime("$startDate +$i days"));
                $categoryBookedCount = $this->availabilityService->categoryOccupiedCount($date, $room['type'], $room['model']);

                $results[$key][] = [
                    'date' => $date,
                    'predicted_occupancy' => $categoryBookedCount,
                    'fixed_price_ariary' => $room['base_price'],
                    'adjusted_price_ariary' => $room['base_price'],
                    'suggested_price_ariary' => $room['base_price'],
                    'base_price' => $room['base_price'],
                    'is_fixed_price' => $room['is_fixed_price'],
                ];
            }
        }

        return [
            'status' => 'success',
            'mode' => 'fallback',
            'ai_available' => false,
            'is_fallback' => true,
            'message' => 'Mode sécurité : IA indisponible, prix de base appliqués',
            'results' => $results,
        ];
    }

    private function realTimeMultiplier(int $globalOccupancy): float
    {
        return match (true) {
            $globalOccupancy >= 35 => 1.15,
            $globalOccupancy >= 31 => 1.135,
            $globalOccupancy >= 27 => 1.12,
            $globalOccupancy >= 23 => 1.105,
            $globalOccupancy >= 19 => 1.09,
            $globalOccupancy >= 15 => 1.075,
            $globalOccupancy >= 11 => 1.06,
            $globalOccupancy >= 7  => 1.045,
            $globalOccupancy >= 4  => 1.03,
            $globalOccupancy >= 2  => 1.015,
            default => 1.0,
        };
    }

    private function revenueForDate(string $date, array $statuses): int
    {
        return Reservation::query()
            ->where('check_in_date', '<=', $date)
            ->where('check_out_date', '>', $date)
            ->whereIn('status', $statuses)
            ->with('rooms')
            ->get()
            ->sum(fn (Reservation $reservation) => $reservation->rooms->sum(fn (Room $room) => (int) $room->pivot->price_snapshot_ariary));
    }
}
