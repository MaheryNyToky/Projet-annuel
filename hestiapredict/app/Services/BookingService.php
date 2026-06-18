<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationAudit;
use App\Models\Room;
use App\Models\User;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $customerPhone = PhoneNumber::normalize($data['customer_phone'] ?? null);
            $reservation = Reservation::query()->create([
                'client_name' => $data['client_name'],
                'client_phone' => $customerPhone ?? '000000000',
                'customer_phone' => $customerPhone,
                'customer_email' => $data['customer_email'] ?? null,
                'booking_reference' => 'RES-' . strtoupper(bin2hex(random_bytes(3))),
                'source' => $source,
                'is_booking_com' => $source === 'Booking',
                'check_in_date' => $data['check_in'],
                'check_out_date' => $data['check_out'],
                'status' => 'en_attente',
                'payment_status' => 'unbilled',
                'user_id' => $userId,
                'extra_beds' => (int) ($data['extra_beds'] ?? 0),
                'extra_mattresses' => (int) ($data['extra_mattresses'] ?? 0),
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

            ReservationAudit::create([
                'reservation_id' => $reservation->id,
                'action' => 'booked',
                'actor_name' => $data['receptionist_name'] ?? null,
                'actor_role' => 'receptionist',
                'details' => [
                    'room_ids' => collect($data['room_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all(),
                    'check_in' => $data['check_in'],
                    'check_out' => $data['check_out'],
                    'source' => $source,
                ],
            ]);

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

        $status = $data['status'];
        $paymentStatus = $data['payment_status'] ?? null;

        if (in_array($status, ['arrive_paid', 'arrive_unpaid'], true)) {
            $paymentStatus = $status === 'arrive_paid' ? 'paid' : 'unpaid';
            $status = 'arrive';
        }

        $updateData = ['status' => $status];
        if ($status === 'annule') {
            if (Schema::hasColumn('reservations', 'cancelled_by_name')) {
                $updateData['cancelled_by_name'] = $data['cancelled_by_name'] ?? null;
            }
            if (Schema::hasColumn('reservations', 'cancelled_at')) {
                $updateData['cancelled_at'] = now();
            }
        }
        if ($paymentStatus !== null) {
            $updateData['payment_status'] = $paymentStatus;
        }

        $reservation->update($updateData);

        if ($status === 'annule') {
            ReservationAudit::create([
                'reservation_id' => $reservation->id,
                'action' => 'cancelled',
                'actor_name' => $data['cancelled_by_name'] ?? null,
                'actor_role' => null,
                'details' => [
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                ],
            ]);
        }

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

        $isCheckedIn = $reservation->status === 'arrive';
        $checkIn = $isCheckedIn
            ? Carbon::parse($reservation->check_in_date)->toDateString()
            : $data['check_in'];
        $checkOut = $isCheckedIn
            ? Carbon::parse($reservation->check_out_date)->toDateString()
            : $data['check_out'];
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

        return DB::transaction(function () use ($reservation, $data, $roomIds, $isCheckedIn) {
            $customerPhone = PhoneNumber::normalize($data['customer_phone'] ?? null);
            $previousPrices = $reservation->rooms
                ->mapWithKeys(fn (Room $room) => [$room->id => (int) $room->pivot->price_snapshot_ariary]);
            $previousRoomIds = $reservation->rooms->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $newRoomIds = $roomIds->sort()->values()->all();

            $changeSet = [];
            $registerChange = function (string $field, mixed $before, mixed $after) use (&$changeSet): void {
                if ($before !== $after) {
                    $changeSet[$field] = [
                        'before' => $before,
                        'after' => $after,
                    ];
                }
            };

            $updateData = [
                'extra_beds' => (int) ($data['extra_beds'] ?? 0),
                'extra_mattresses' => (int) ($data['extra_mattresses'] ?? 0),
            ];

            if (!$isCheckedIn) {
                $updateData = array_merge($updateData, [
                    'client_name' => $data['client_name'],
                    'client_phone' => $customerPhone ?? '000000000',
                    'customer_phone' => $customerPhone,
                    'customer_email' => $data['customer_email'] ?? null,
                    'check_in_date' => $data['check_in'],
                    'check_out_date' => $data['check_out'],
                ]);
            }

            $registerChange('room_ids', $previousRoomIds, $newRoomIds);
            $registerChange('extra_beds', (int) $reservation->extra_beds, (int) ($data['extra_beds'] ?? 0));
            $registerChange(
                'extra_mattresses',
                (int) $reservation->extra_mattresses,
                (int) ($data['extra_mattresses'] ?? 0)
            );

            if (!$isCheckedIn) {
                $registerChange('client_name', $reservation->client_name, $data['client_name']);
                $registerChange(
                    'customer_phone',
                    $reservation->customer_phone,
                    $customerPhone
                );
                $registerChange(
                    'customer_email',
                    $reservation->customer_email,
                    $data['customer_email'] ?? null
                );
                $registerChange('check_in', $reservation->check_in_date->toDateString(), $data['check_in']);
                $registerChange('check_out', $reservation->check_out_date->toDateString(), $data['check_out']);
            }

            $reservation->update($updateData);

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

            if (!empty($changeSet)) {
                ReservationAudit::create([
                    'reservation_id' => $reservation->id,
                    'action' => 'modified',
                    'actor_name' => $data['modified_by_name'] ?? null,
                    'actor_role' => $data['modified_by_role'] ?? null,
                    'details' => $changeSet,
                ]);
            }

            return $reservation->refresh()->load(['rooms', 'user']);
        });
    }

    public function reservationsForDate(?string $date): Collection
    {
        return Reservation::query()
            ->with(['rooms', 'user', 'invoice.payments', 'latestAudit', 'latestCheckInAudit', 'latestModificationAudit'])
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
            ->with(['rooms', 'user', 'invoice.payments', 'latestAudit', 'latestCheckInAudit', 'latestModificationAudit'])
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
                    'payment_status' => $formatted['payment_status'],
                    'source' => $formatted['source'],
                    'total_price' => $formatted['total_price'],
                    'fixed_total_price' => $formatted['fixed_total_price'],
                    'paid_amount_ariary' => $formatted['paid_amount_ariary'],
                    'deposit_amount_ariary' => $formatted['deposit_amount_ariary'],
                    'cancelled_by_name' => $formatted['cancelled_by_name'],
                    'latest_payment_processed_by' => $formatted['latest_payment_processed_by'],
                    'latest_payment_processed_by_role' => $formatted['latest_payment_processed_by_role'],
                    'latest_payment_method' => $formatted['latest_payment_method'],
                    'latest_deposit_processed_by' => $formatted['latest_deposit_processed_by'],
                    'latest_deposit_processed_by_role' => $formatted['latest_deposit_processed_by_role'],
                    'latest_deposit_method' => $formatted['latest_deposit_method'],
                    'payment_methods_display' => $formatted['payment_methods_display'],
                    'is_booking' => $formatted['is_booking'],
                    'receptionist' => $formatted['receptionist'],
                    'check_in_by' => $formatted['check_in_by'],
                    'check_in_role' => $formatted['check_in_role'],
                    'check_in_at' => $formatted['check_in_at'],
                    'modified_by' => $formatted['modified_by'],
                    'modified_by_role' => $formatted['modified_by_role'],
                    'modified_at' => $formatted['modified_at'],
                    'modified_details' => $formatted['modified_details'],
                    'last_action' => $formatted['last_action'],
                    'last_action_by' => $formatted['last_action_by'],
                    'last_action_role' => $formatted['last_action_role'],
                    'last_action_at' => $formatted['last_action_at'],
                    'last_action_details' => $formatted['last_action_details'],
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

        $extraBeds = $reservation->extra_beds ?? 0;
        $extraMattresses = $reservation->extra_mattresses ?? 0;
        $extrasPrice = ($extraBeds * 50000) + ($extraMattresses * 30000);

        $totalPrice = ($rooms->sum(fn (Room $room) => (int) $room->pivot->price_snapshot_ariary) * $nights) + $extrasPrice;
        $fixedTotalPrice = ($rooms->sum(fn (Room $room) => (int) $room->base_price_ariary) * $nights) + $extrasPrice;
        $invoice = $reservation->invoice;
        $paymentStatus = $reservation->payment_status ?? 'unbilled';
        $payments = $invoice?->relationLoaded('payments')
            ? $invoice->payments
            : ($invoice ? $invoice->payments()->get() : collect());
        $latestPayment = $payments
            ->where('payment_context', 'payment')
            ->sortByDesc('created_at')
            ->first()
            ?? $payments->sortByDesc('created_at')->first();
        $latestDeposit = $payments
            ->where('payment_context', 'deposit')
            ->sortByDesc('created_at')
            ->first();
        $paymentMethods = $invoice?->relationLoaded('payments')
            ? $invoice->payments->pluck('payment_method')->filter()->unique()->values()
            : collect();
        $paymentMethodsDisplay = $paymentMethods->isNotEmpty()
            ? $paymentMethods->implode(' / ')
            : 'N/A';
        if ($invoice && $paymentStatus === 'unbilled') {
            $paymentStatus = ((int) $invoice->balance_amount_ariary <= 0)
                ? 'paid'
                : (((int) $invoice->paid_amount_ariary > 0) ? 'partial' : 'unpaid');
        }

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
            'contact' => ($reservation->customer_phone && $reservation->customer_phone !== '000000000')
                ? $reservation->customer_phone
                : ($reservation->client_phone ?? ($reservation->customer_email ?? 'N/A')),
            'status' => $reservation->status,
            'payment_status' => $paymentStatus,
            'source' => $reservation->source,
            'cancelled_by_name' => $reservation->cancelled_by_name,
            'cancelled_at' => optional($reservation->cancelled_at)->toDateTimeString(),
            'rooms' => $groupedRooms,
            'room_ids' => $rooms->pluck('id')->values(),
            'room_details' => $roomDetails,
            'room_numbers' => $roomNumbers,
            'extra_beds' => $extraBeds,
            'extra_mattresses' => $extraMattresses,
            'deposit_amount_ariary' => (int) ($invoice?->deposit_amount_ariary ?? 0),
            'total_price' => (int) $totalPrice,
            'fixed_total_price' => (int) $fixedTotalPrice,
            'paid_amount_ariary' => (int) ($invoice?->paid_amount_ariary ?? 0),
            'balance_amount_ariary' => (int) ($invoice?->balance_amount_ariary ?? 0),
            'is_booking' => $reservation->source === 'Booking',
            'receptionist' => $reservation->user?->name ?? 'N/A',
            'check_in_by' => $reservation->latestCheckInAudit?->actor_name ?? 'N/A',
            'check_in_role' => $reservation->latestCheckInAudit?->actor_role,
            'check_in_at' => optional($reservation->latestCheckInAudit?->created_at)->toDateTimeString(),
            'modified_by' => $reservation->latestModificationAudit?->actor_name ?? 'N/A',
            'modified_by_role' => $reservation->latestModificationAudit?->actor_role,
            'modified_at' => optional($reservation->latestModificationAudit?->created_at)->toDateTimeString(),
            'modified_details' => $reservation->latestModificationAudit?->details,
            'last_action' => $reservation->latestAudit?->action,
            'last_action_by' => $reservation->latestAudit?->actor_name,
            'last_action_role' => $reservation->latestAudit?->actor_role,
            'last_action_at' => optional($reservation->latestAudit?->created_at)->toDateTimeString(),
            'last_action_details' => $reservation->latestAudit?->details,
            'invoice_number' => $invoice?->invoice_number,
            'invoice_status' => $invoice?->status ?? 'none',
            'document_type' => $invoice?->document_type ?? 'facture',
            'pdf_url' => $invoice?->pdf_path ? url("/api/invoices/{$invoice->id}/pdf") : null,
            'latest_payment_processed_by' => $latestPayment?->processed_by_name,
            'latest_payment_processed_by_role' => $latestPayment?->processed_by_role,
            'latest_payment_method' => $latestPayment?->payment_method,
            'latest_deposit_processed_by' => $latestDeposit?->processed_by_name,
            'latest_deposit_processed_by_role' => $latestDeposit?->processed_by_role,
            'latest_deposit_method' => $latestDeposit?->payment_method,
            'payment_methods' => $paymentMethods->all(),
            'payment_methods_display' => $paymentMethodsDisplay,
            'created_at' => optional($reservation->created_at)->toDateTimeString(),
        ];
    }
}
