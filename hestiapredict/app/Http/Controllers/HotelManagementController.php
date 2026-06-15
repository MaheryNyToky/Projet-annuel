<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\User;
use App\Services\AuthService;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\YieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class HotelManagementController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly BookingService $bookingService,
        private readonly YieldService $yieldService,
        private readonly AuthService $authService,
    ) {
    }

    public function getLiveAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);
        $date = $validated['date'] ?? now()->toDateString();

        return response()->json($this->availabilityService->liveSummary($date));
    }

    public function getAvailableRoomsForDates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
        ]);

        return response()->json(
            RoomResource::collection(
                $this->availabilityService->availableRooms($validated['check_in'], $validated['check_out'])
            )->resolve()
        );
    }

    public function saveBooking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:120',
            'customer_phone' => 'nullable|string|max:40',
            'customer_email' => 'nullable|email|max:190',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'integer|distinct|exists:rooms,id',
            'room_prices' => 'nullable|array',
            'room_prices.*.id' => 'required_with:room_prices|integer|exists:rooms,id',
            'room_prices.*.price' => 'required_with:room_prices|integer|min:0|max:10000000',
            'source' => 'nullable|string|in:Appel,Mail,Booking',
            'receptionist_name' => 'nullable|string|max:120',
        ]);

        $reservation = $this->bookingService->createBooking($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Réservation enregistrée avec succès',
            'reference' => $reservation->booking_reference,
        ], 201);
    }

    public function updateBookingStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'reference' => 'nullable|string|max:40',
            'status' => 'required|string|in:en_attente,arrive,annule',
        ]);

        if (empty($validated['id']) && empty($validated['reference'])) {
            return response()->json(['status' => 'error', 'message' => 'Veuillez fournir un ID ou une référence'], 400);
        }

        $reservation = $this->bookingService->updateStatus($validated);

        if (!$reservation) {
            return response()->json(['status' => 'error', 'message' => 'Réservation non trouvée'], 404);
        }

        return response()->json(['status' => 'success']);
    }

    public function getAllReservations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', Rule::when(
                $request->query('date') !== 'all',
                ['date'],
                ['in:all'],
            )],
        ]);

        return response()->json(
            $this->bookingService->reservationsForDate($validated['date'] ?? null)->values()
        );
    }

    public function getActiveReservations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);
        $date = $validated['date'] ?? now()->toDateString();

        return response()->json($this->bookingService->activeReservations($date)->values());
    }

    public function getUsers(): JsonResponse
    {
        return response()->json(User::query()->orderBy('name')->get());
    }

    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users',
            'password' => 'required|string|min:6|max:255',
            'role' => 'required|string|in:admin,receptionist',
        ]);

        // Le modèle User applique le cast "hashed" : le mot de passe n'est jamais stocké en clair.
        $user = User::query()->create($validated);

        return response()->json(['status' => 'success', 'id' => $user->id]);
    }

    public function updateUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:users,id',
            'name' => 'required|string|max:120',
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($request->integer('id')),
            ],
            'role' => 'required|string|in:admin,receptionist',
            'password' => 'nullable|string|min:6|max:255',
        ]);

        $user = User::query()->findOrFail($validated['id']);
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Utilisateur mis à jour avec succès']);
    }

    public function deleteUser(int $id): JsonResponse
    {
        User::query()->where('id', $id)->delete();

        return response()->json(['status' => 'success']);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:190',
            'password' => 'required|string|max:255',
        ]);

        $user = $this->authService->attempt($validated['email'], $validated['password']);

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Identifiants incorrects'], 401);
        }

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function getAiPredictionsAndPricing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'start_date' => 'nullable|date',
        ]);
        $days = (int) ($validated['days'] ?? 30);
        $startDate = $validated['start_date'] ?? now()->toDateString();

        return response()->json($this->yieldService->predictions($days, $startDate));
    }

    public function auditDate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);
        $date = $validated['date'] ?? now()->toDateString();

        return response()->json($this->yieldService->auditDate($date));
    }

    public function checkGlobalOccupationAndAlert(): void
    {
        $tomorrow = now()->addDay()->toDateString();
        $totalRooms = Room::query()->count();

        if ($totalRooms === 0) {
            return;
        }

        $bookedRoomsTomorrow = $this->availabilityService->occupiedRoomCount($tomorrow);
        $rate = ($bookedRoomsTomorrow / $totalRooms) * 100;

        if ($rate >= 95) {
            $hotelEmail = config('mail.from.address', 'manager@kamorohotel.com');
            Mail::raw("URGENT - Kamoro Hotel : Taux d'occupation critique de " . round($rate, 1) . "% prévu pour demain ({$tomorrow}). Fermez immédiatement les vannes Booking.com pour bloquer les réservations.", function ($message) use ($hotelEmail) {
                $message->to($hotelEmail)->subject('ALERTE CRITIQUE : Seuil d\'occupation hôtelière à 95% !');
            });
        }
    }
}
