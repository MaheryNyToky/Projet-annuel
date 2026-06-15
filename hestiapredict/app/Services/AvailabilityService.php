<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function occupiedRoomIdsForDate(string $date, array $statuses = Reservation::ACTIVE_STATUSES): Collection
    {
        return Room::query()
            ->whereHas('reservations', function ($query) use ($date, $statuses) {
                $query->whereIn('reservations.status', $statuses)
                    ->where('reservations.check_in_date', '<=', $date)
                    ->where('reservations.check_out_date', '>', $date);
            })
            ->pluck('id');
    }

    public function busyRoomIdsForPeriod(
        string $checkIn,
        string $checkOut,
        array $statuses = Reservation::ACTIVE_STATUSES,
        ?int $excludeReservationId = null,
    ): Collection
    {
        return Room::query()
            ->whereHas('reservations', function ($query) use ($checkIn, $checkOut, $statuses, $excludeReservationId) {
                $query->whereIn('reservations.status', $statuses)
                    ->where('reservations.check_in_date', '<', $checkOut)
                    ->where('reservations.check_out_date', '>', $checkIn)
                    ->when($excludeReservationId, fn ($query) => $query->where('reservations.id', '!=', $excludeReservationId));
            })
            ->pluck('id');
    }

    public function liveSummary(string $date): array
    {
        $occupiedRoomIds = $this->occupiedRoomIdsForDate($date)->all();

        return Room::query()
            ->orderBy('type')
            ->orderBy('model')
            ->get()
            ->groupBy(fn (Room $room) => $room->type . ' (' . $room->model . ')')
            ->map(function (Collection $rooms) use ($occupiedRoomIds) {
                $first = $rooms->first();

                return [
                    'type' => $first->type,
                    'model' => $first->model,
                    'base_price' => $first->base_price_ariary,
                    'fixed_price' => $first->base_price_ariary,
                    'is_fixed_price' => $rooms->every(fn (Room $room) => $room->is_fixed_price),
                    'total' => $rooms->count(),
                    'available' => $rooms->whereNotIn('id', $occupiedRoomIds)->count(),
                ];
            })
            ->values()
            ->all();
    }

    public function availableRooms(string $checkIn, string $checkOut, ?int $excludeReservationId = null): Collection
    {
        $busyRoomIds = $this->busyRoomIdsForPeriod($checkIn, $checkOut, Reservation::ACTIVE_STATUSES, $excludeReservationId);

        return Room::query()
            ->whereNotIn('id', $busyRoomIds)
            ->orderBy('room_number')
            ->get();
    }

    public function occupiedRoomCount(string $date, array $statuses = Reservation::ACTIVE_STATUSES): int
    {
        return $this->occupiedRoomIdsForDate($date, $statuses)->count();
    }

    public function categoryOccupiedCount(string $date, string $type, string $model): int
    {
        return Room::query()
            ->where('type', $type)
            ->where('model', $model)
            ->whereHas('reservations', function ($query) use ($date) {
                $query->whereIn('reservations.status', Reservation::ACTIVE_STATUSES)
                    ->where('reservations.check_in_date', '<=', $date)
                    ->where('reservations.check_out_date', '>', $date);
            })
            ->count();
    }
}
