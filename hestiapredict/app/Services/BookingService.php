<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function createBooking(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $userId = null;
            if (!empty($data['receptionist_name'])) {
                $userId = User::query()
                    ->where('name', $data['receptionist_name'])
                    ->value('id');
            }

            $source = $data['source'] ?? 'Appel';
            $reservation = Reservation::query()->create([
                'client_name' => $data['client_name'],
                'client_phone' => $data['customer_phone'] ?? '000000000',
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'booking_reference' => 'RES-' . strtoupper(bin2hex(random_bytes(3))),
                'source' => $source,
                'is_booking_com' => $source === 'Booking',
                'check_in_date' => $data['check_in'],
                'check_out_date' => $data['check_out'],
                'status' => 'en_attente',
                'user_id' => $userId,
            ]);

            $providedPrices = collect($data['room_prices'] ?? [])
                ->mapWithKeys(fn (array $roomPrice) => [$roomPrice['id'] => $roomPrice['price']]);

            Room::query()
                ->whereIn('id', $data['room_ids'])
                ->get()
                ->each(function (Room $room) use ($reservation, $providedPrices, $source) {
                    $price = $room->is_fixed_price
                        ? $room->base_price_ariary
                        : $providedPrices->get(
                            $room->id,
                            $source === 'Booking' ? 162500 : $room->base_price_ariary
                        );

                    $reservation->rooms()->attach($room->id, [
                        'price_snapshot_ariary' => $price,
                    ]);
                });

            return $reservation->load('rooms');
        });
    }

    public function updateStatus(array $data): ?Reservation
    {
        $reservation = Reservation::query()
            ->when(!empty($data['id']), fn ($query) => $query->where('id', $data['id']))
            ->when(empty($data['id']) && !empty($data['reference']), fn ($query) => $query->where('booking_reference', $data['reference']))
            ->first();

        if (!$reservation) {
            return null;
        }

        $reservation->update(['status' => $data['status']]);

        return $reservation;
    }

    public function updateReservation(int $id, array $data): ?Reservation
    {
        $reservation = Reservation::query()
            ->with('rooms')
            ->find($id);

        if (!$reservation) {
            return null;
        }

        if (Carbon::parse($reservation->check_out_date)->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'reservation' => 'Seules les réservations non terminées peuvent être modifiées.',
            ]);
        }

        $checkIn = $data['check_in'];
        $checkOut = $data['check_out'];
        $roomIds = collect($data['room_ids'])->map(fn ($roomId) => (int) $roomId)->values();
        $conflictingRoomNumbers = Room::query()
            ->whereIn('id', $roomIds)
            ->whereHas('reservations', function ($query) use ($reservation, $checkIn, $checkOut) {
                $query->where('reservations.id', '!=', $reservation->id)
                    ->whereIn('reservations.status', Reservation::ACTIVE_STATUSES)
                    ->where('reservations.check_in_date', '<', $checkOut)
                    ->where('reservations.check_out_date', '>', $checkIn);
            })
            ->orderBy('room_number')
            ->pluck('room_number');

        if ($conflictingRoomNumbers->isNotEmpty()) {
            throw ValidationException::withMessages([
                'room_ids' => 'Chambre(s) déjà occupée(s) : ' . $conflictingRoomNumbers->implode(', '),
            ]);
        }

        return DB::transaction(function () use ($reservation, $data, $roomIds) {
            $previousPrices = $reservation->rooms
                ->mapWithKeys(fn (Room $room) => [$room->id => (int) $room->pivot->price_snapshot_ariary]);

            $reservation->update([
                'client_name' => $data['client_name'],
                'client_phone' => $data['customer_phone'] ?? '000000000',
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'check_in_date' => $data['check_in'],
                'check_out_date' => $data['check_out'],
            ]);

            $syncData = Room::query()
                ->whereIn('id', $roomIds)
                ->get()
                ->mapWithKeys(function (Room $room) use ($previousPrices, $reservation) {
                    $price = $previousPrices->get(
                        $room->id,
                        $room->is_fixed_price
                            ? $room->base_price_ariary
                            : ($reservation->source === 'Booking' ? 162500 : $room->base_price_ariary)
                    );

                    return [$room->id => ['price_snapshot_ariary' => $price]];
                })
                ->all();

            $reservation->rooms()->sync($syncData);

            return $reservation->refresh()->load(['rooms', 'user']);
        });
    }

    public function reservationsForDate(?string $date): Collection
    {
        return Reservation::query()
            ->with(['rooms', 'user'])
            ->where('status', '!=', 'annule')
            ->when($date && $date !== 'all', function ($query) use ($date) {
                $query->where('check_in_date', '<=', $date)
                    ->where('check_out_date', '>', $date);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Reservation $reservation) => $this->formatReservation($reservation));
    }

    public function activeReservations(string $date): Collection
    {
        return Reservation::query()
            ->with('rooms')
            ->where('check_in_date', '<=', $date)
            ->where('check_out_date', '>', $date)
            ->get()
            ->map(function (Reservation $reservation) {
                $formatted = $this->formatReservation($reservation);

                return [
                    'reference' => $formatted['reference'],
                    'client_name' => $formatted['client_name'],
                    'contact' => $formatted['phone'] !== 'N/A' ? $formatted['phone'] : $formatted['email'],
                    'check_in' => $formatted['check_in'],
                    'check_out' => $formatted['check_out'],
                    'status' => $formatted['status'],
                    'source' => $formatted['source'],
                    'total_price' => $formatted['total_price'],
                    'fixed_total_price' => $formatted['fixed_total_price'],
                    'is_booking' => $formatted['is_booking'],
                    'rooms' => $formatted['rooms'],
                    'room_numbers' => $formatted['room_numbers'],
                ];
            });
    }

    public function formatReservation(Reservation $reservation): array
    {
        $rooms = $reservation->rooms;
        $groupedRooms = $rooms
            ->groupBy(fn (Room $room) => $room->type . ' ' . $room->model)
            ->map(fn (Collection $group) => $group->count() . 'x ' . $group->first()->type . ' (' . $group->first()->model . ')')
            ->values()
            ->implode(', ');
        $roomNumbers = $rooms
            ->sortBy('room_number', SORT_NATURAL)
            ->pluck('room_number')
            ->values()
            ->implode(', ');
        $roomDetails = $rooms
            ->sortBy('room_number', SORT_NATURAL)
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'type' => $room->type,
                'model' => $room->model,
                'base_price_ariary' => $room->base_price_ariary,
                'fixed_price_ariary' => $room->base_price_ariary,
                'is_fixed_price' => $room->is_fixed_price,
                'price_snapshot_ariary' => (int) $room->pivot->price_snapshot_ariary,
            ])
            ->values();

        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $totalPrice = $rooms->sum(fn (Room $room) => (int) $room->pivot->price_snapshot_ariary) * $nights;
        $fixedTotalPrice = $rooms->sum(fn (Room $room) => (int) $room->base_price_ariary) * $nights;

        return [
            'id' => $reservation->id,
            'reference' => $reservation->booking_reference,
            'client_name' => $reservation->client_name,
            'phone' => ($reservation->customer_phone && $reservation->customer_phone !== '000000000')
                ? $reservation->customer_phone
                : ($reservation->client_phone ?? 'N/A'),
            'email' => $reservation->customer_email ?? 'N/A',
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'status' => $reservation->status,
            'source' => $reservation->source,
            'rooms' => $groupedRooms,
            'room_ids' => $rooms->pluck('id')->values(),
            'room_details' => $roomDetails,
            'room_numbers' => $roomNumbers,
            'total_price' => (int) $totalPrice,
            'fixed_total_price' => (int) $fixedTotalPrice,
            'is_booking' => $reservation->source === 'Booking',
            'receptionist' => $reservation->user?->name ?? 'N/A',
            'created_at' => optional($reservation->created_at)->toDateTimeString(),
        ];
    }
}
