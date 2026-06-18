<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReservationAudit;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\PhoneNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PMSController extends Controller
{
    private const TOURIST_TAX_PER_ROOM_NIGHT = 2000;
    private const EXTRA_BED_PRICE_ARIARY = 50000;
    private const EXTRA_MATTRESS_PRICE_ARIARY = 30000;

    public function checkIn(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:50',
            'date_of_birth' => 'required|date',
            'sex' => 'required|in:Homme,Femme,Autre',
            'id_type' => 'required|in:CIN,Passeport,Permis',
            'id_number' => 'required|string|max:100',
            'id_document_number' => 'nullable|string|max:100',
            'passport_valid_from' => 'nullable|date|required_if:id_type,Passeport|before_or_equal:passport_valid_until',
            'passport_valid_until' => 'nullable|date|required_if:id_type,Passeport|after_or_equal:passport_valid_from',
            'loyalty_count' => 'nullable|integer|min:0',
            'first_name' => 'nullable|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'checked_in_by_name' => 'nullable|string|max:120',
            'checked_in_by_role' => 'nullable|string|in:admin,receptionist',
        ]);

        $reservation = Reservation::with(['rooms', 'guest', 'invoice.items', 'invoice.payments'])->findOrFail($id);
        $result = DB::transaction(function () use ($reservation, $validated) {
            $baseLoyaltyCount = (int) ($validated['loyalty_count'] ?? $reservation->guest?->loyalty_count ?? 0);
            $customerPhone = PhoneNumber::normalize($validated['customer_phone'] ?? $reservation->customer_phone ?? $reservation->client_phone ?? null);
            $phoneNumber = PhoneNumber::normalize($validated['phone_number'] ?? $customerPhone ?? null);

            $reservation->update([
                'client_name' => $validated['full_name'],
                'customer_phone' => $customerPhone,
                'client_phone' => $customerPhone ?? $reservation->client_phone,
                'status' => 'arrive',
            ]);

            $guest = Guest::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'phone_number' => $phoneNumber,
                    'id_document_number' => $validated['id_document_number'] ?? $validated['id_number'],
                    'loyalty_count' => $baseLoyaltyCount,
                    'full_name' => $validated['full_name'],
                    'date_of_birth' => $validated['date_of_birth'],
                    'sex' => $validated['sex'],
                    'passport_valid_from' => $validated['passport_valid_from'] ?? null,
                    'passport_valid_until' => $validated['passport_valid_until'] ?? null,
                    'id_type' => $validated['id_type'],
                    'id_number' => $validated['id_number'],
                ],
            );

            ReservationAudit::create([
                'reservation_id' => $reservation->id,
                'action' => 'check_in',
                'actor_name' => $validated['checked_in_by_name'] ?? null,
                'actor_role' => $validated['checked_in_by_role'] ?? null,
                'details' => [
                    'guest_name' => $validated['full_name'],
                    'phone_number' => $phoneNumber,
                    'id_type' => $validated['id_type'],
                ],
            ]);

            return [
                'guest' => $guest,
                'reservation' => $reservation->refresh()->load('rooms', 'guest'),
            ];
        });

        return response()->json([
            'message' => 'Check-in réussi',
            ...$result,
        ]);
    }

    public function getFolio(int $id): JsonResponse
    {
        $reservation = Reservation::with(['rooms', 'guest', 'invoice.items', 'invoice.payments'])->findOrFail($id);
        $invoice = $reservation->invoice ?: $this->ensureOpenFolio($reservation);

        return response()->json($this->folioPayload($invoice));
    }

    public function addInvoiceItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'type' => 'required|in:room,extra,deposit',
            'amount_ariary' => 'required|integer|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        $invoice = Invoice::findOrFail($id);
        if ($invoice->status === 'finalized') {
            return response()->json(['message' => 'Facture finalisée, ajout impossible.'], 400);
        }

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $validated['description'],
            'type' => $validated['type'],
            'amount_ariary' => $validated['amount_ariary'],
            'quantity' => $validated['quantity'],
        ]);

        $this->recalculateInvoice($invoice);

        return response()->json([
            'message' => 'Ligne ajoutée avec succès',
            'invoice' => $this->folioPayload($invoice->refresh()),
        ]);
    }

    public function addPayment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount_ariary' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:Espèces,Carte Bancaire,Mobile Money,Chèque,Virement',
            'reference' => 'nullable|string|max:120',
            'processed_by_name' => 'nullable|string|max:120',
            'processed_by_role' => 'nullable|string|in:admin,receptionist',
        ]);

        $result = DB::transaction(function () use ($validated, $id) {
            $invoice = Invoice::with(['payments', 'reservation.guest'])->lockForUpdate()->findOrFail($id);
            $previousStatus = $invoice->status;

            if ($invoice->status === 'finalized') {
                throw ValidationException::withMessages([
                    'invoice' => 'Facture finalisée, paiement impossible.',
                ]);
            }

            $remainingAmount = max(0, (int) $invoice->balance_amount_ariary);
            if ($remainingAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount_ariary' => 'Cette facture est déjà soldée.',
                ]);
            }

            if ((int) $validated['amount_ariary'] > $remainingAmount) {
                throw ValidationException::withMessages([
                    'amount_ariary' => 'Le paiement dépasse le reste à payer.',
                ]);
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount_ariary' => $validated['amount_ariary'],
                'payment_method' => $validated['payment_method'],
                'payment_context' => 'payment',
                'reference' => $validated['reference'] ?? null,
                'processed_by_name' => $validated['processed_by_name'] ?? null,
                'processed_by_role' => $validated['processed_by_role'] ?? null,
            ]);

            ReservationAudit::create([
                'reservation_id' => $invoice->reservation_id,
                'action' => 'payment',
                'actor_name' => $validated['processed_by_name'] ?? null,
                'actor_role' => $validated['processed_by_role'] ?? null,
                'details' => [
                    'amount_ariary' => (int) $validated['amount_ariary'],
                    'payment_method' => $validated['payment_method'],
                    'reference' => $validated['reference'] ?? null,
                ],
            ]);

            $invoice = $this->syncInvoiceAfterPayment($invoice);

            if ($previousStatus !== 'paid' && $invoice->status === 'paid') {
                $guest = $invoice->reservation?->guest;
                if ($guest) {
                    $guest->increment('loyalty_count');
                }
            }

            return [
                'payment' => $payment,
                'invoice' => $this->folioPayload($invoice),
            ];
        });

        return response()->json([
            'message' => 'Paiement enregistré',
            ...$result,
        ]);
    }

    public function addDeposit(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount_ariary' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:Espèces,Carte Bancaire,Mobile Money,Chèque,Virement',
            'reference' => 'nullable|string|max:120',
            'processed_by_name' => 'nullable|string|max:120',
            'processed_by_role' => 'nullable|string|in:admin,receptionist',
        ]);

        $result = DB::transaction(function () use ($validated, $id) {
            $reservation = Reservation::with(['invoice.payments', 'guest'])->lockForUpdate()->findOrFail($id);
            $invoice = $reservation->invoice ?: $this->ensureOpenFolio($reservation);
            $previousStatus = $invoice->status;

            $remainingAmount = max(0, (int) $invoice->balance_amount_ariary);
            if ($remainingAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount_ariary' => 'Cette facture est déjà soldée.',
                ]);
            }

            if ((int) $validated['amount_ariary'] > $remainingAmount) {
                throw ValidationException::withMessages([
                    'amount_ariary' => 'Le paiement dépasse le reste à payer.',
                ]);
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount_ariary' => $validated['amount_ariary'],
                'payment_method' => $validated['payment_method'],
                'payment_context' => 'deposit',
                'reference' => $validated['reference'] ?? null,
                'processed_by_name' => $validated['processed_by_name'] ?? null,
                'processed_by_role' => $validated['processed_by_role'] ?? null,
            ]);

            ReservationAudit::create([
                'reservation_id' => $reservation->id,
                'action' => 'deposit',
                'actor_name' => $validated['processed_by_name'] ?? null,
                'actor_role' => $validated['processed_by_role'] ?? null,
                'details' => [
                    'amount_ariary' => (int) $validated['amount_ariary'],
                    'payment_method' => $validated['payment_method'],
                    'reference' => $validated['reference'] ?? null,
                ],
            ]);

            $invoice = $this->syncInvoiceAfterPayment($invoice);

            if ($previousStatus !== 'paid' && $invoice->status === 'paid') {
                $guest = $invoice->reservation?->guest;
                if ($guest) {
                    $guest->increment('loyalty_count');
                }
            }

            return [
                'payment' => $payment,
                'invoice' => $this->folioPayload($invoice),
            ];
        });

        return response()->json([
            'message' => 'Acompte enregistré',
            ...$result,
        ]);
    }

    public function generatePdf(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'pricing_mode' => 'nullable|in:fixed,ai',
            'discount_mode' => 'nullable|in:percent,amount',
            'discount_value' => 'nullable|numeric|min:0',
            'actor_role' => 'nullable|string|in:admin,receptionist',
            'document_type' => 'nullable|in:facture,proforma',
        ]);
        $documentType = $validated['document_type'] ?? 'facture';

        if (
            ($validated['discount_mode'] ?? null) !== null
            || ($validated['discount_value'] ?? null) !== null
        ) {
            if (($validated['actor_role'] ?? 'receptionist') !== 'admin') {
                return response()->json([
                    'message' => 'Seul un administrateur peut appliquer une remise.',
                ], 403);
            }
        }

        $invoice = Invoice::with(['items', 'payments', 'reservation.guest', 'reservation.rooms'])->findOrFail($id);

        DB::transaction(function () use ($invoice, $validated, $documentType) {
            $invoice->refresh();
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = $this->nextInvoiceNumber();
                $invoice->save();
            }

            $invoice->update([
                'document_type' => $documentType,
            ]);
            $this->applyInvoiceDiscount($invoice, $validated['discount_mode'] ?? null, $validated['discount_value'] ?? null);
            $this->ensureInvoicePdf($invoice->refresh()->load(['items', 'payments', 'reservation.guest', 'reservation.rooms']), $documentType);
        });

        return response()->json([
            'message' => 'Facture générée avec succès',
            'invoice' => $this->folioPayload($invoice->refresh()),
            'pdf_url' => url("/api/invoices/{$invoice->id}/pdf"),
        ]);
    }

    public function downloadPdf(int $id): StreamedResponse
    {
        $invoice = Invoice::findOrFail($id);
        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download(
            $invoice->pdf_path,
            ($invoice->invoice_number ?? 'facture') . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function sendInvoiceEmail(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:190',
        ]);

        $invoice = Invoice::with('reservation.guest')->findOrFail($id);
        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

        Mail::raw(
            "Bonjour,\n\nVeuillez trouver ci-joint votre facture {$invoice->invoice_number}.\n\nCordialement,\nKamoro Hotel",
            function ($message) use ($validated, $invoice) {
                $message
                    ->to($validated['email'])
                    ->subject("Facture {$invoice->invoice_number}")
                    ->attachData(
                        Storage::disk('local')->get($invoice->pdf_path),
                        "{$invoice->invoice_number}.pdf",
                        ['mime' => 'application/pdf'],
                    );
            },
        );

        return response()->json(['message' => 'Facture envoyée par email']);
    }

    private function ensureOpenFolio(Reservation $reservation): Invoice
    {
        $invoice = Invoice::firstOrCreate(
            ['reservation_id' => $reservation->id],
            ['status' => 'open'],
        );

        if ($invoice->items()->where('type', 'room')->doesntExist()) {
            $this->seedRoomItems($invoice, $reservation);
        }

        if ($invoice->status !== 'finalized') {
            $this->syncReservationExtras($invoice, $reservation);
        }

        $this->recalculateInvoice($invoice);

        return $invoice->refresh()->load(['items', 'payments', 'reservation.guest']);
    }

    private function seedRoomItems(Invoice $invoice, Reservation $reservation): void
    {
        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);
        $nights = max(1, $checkIn->diffInDays($checkOut));

        foreach ($reservation->rooms as $room) {
            $pricePerNight = (int) ($room->pivot->price_snapshot_ariary ?? $room->base_price_ariary);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Chambre {$room->room_number} ({$room->type}) - {$nights} nuit(s)",
                'type' => 'room',
                'amount_ariary' => $pricePerNight,
                'quantity' => $nights,
            ]);
        }
    }

    private function syncReservationExtras(Invoice $invoice, Reservation $reservation): void
    {
        $invoice->items()
            ->whereIn('description', ['Lit supplémentaire', 'Matelas supplémentaire'])
            ->delete();

        if ((int) $reservation->extra_beds > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Lit supplémentaire',
                'type' => 'extra',
                'amount_ariary' => self::EXTRA_BED_PRICE_ARIARY,
                'quantity' => (int) $reservation->extra_beds,
            ]);
        }

        if ((int) $reservation->extra_mattresses > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Matelas supplémentaire',
                'type' => 'extra',
                'amount_ariary' => self::EXTRA_MATTRESS_PRICE_ARIARY,
                'quantity' => (int) $reservation->extra_mattresses,
            ]);
        }
    }

    private function recalculateInvoice(Invoice $invoice): void
    {
        $items = $invoice->items()->get();
        $subtotal = (int) $items->sum(fn (InvoiceItem $item) => $item->amount_ariary * $item->quantity);
        $discountAmount = (int) $invoice->discount_amount_ariary;
        $total = max(0, $subtotal - $discountAmount);

        $invoice->update([
            'tax_amount_ariary' => 0,
            'total_amount_ariary' => $total,
        ]);

        $this->updatePaymentStatus($invoice->refresh());
    }

    private function updatePaymentStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'finalized') {
            return;
        }

        $paid = (int) $invoice->payments()->sum('amount_ariary');
        $status = match (true) {
            $paid <= 0 => 'open',
            $paid < (int) $invoice->total_amount_ariary => 'partial',
            default => 'paid',
        };

        $invoice->update(['status' => $status]);
    }

    private function nextInvoiceNumber(): string
    {
        $year = now()->year;
        $count = Invoice::query()
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'like', "FACT-{$year}-%")
            ->lockForUpdate()
            ->count();

        return sprintf('FACT-%d-%04d', $year, $count + 1);
    }

    private function folioPayload(Invoice $invoice): array
    {
        $invoice->load(['items', 'payments', 'reservation.guest']);

        return [
            'id' => $invoice->id,
            'reservation_id' => $invoice->reservation_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'document_type' => $invoice->document_type ?? 'facture',
            'total_amount_ariary' => (int) $invoice->total_amount_ariary,
            'discount_mode' => $invoice->discount_mode,
            'discount_value' => $invoice->discount_value,
            'discount_amount_ariary' => (int) $invoice->discount_amount_ariary,
            'deposit_amount_ariary' => (int) $invoice->deposit_amount_ariary,
            'paid_amount_ariary' => $invoice->paid_amount_ariary,
            'balance_amount_ariary' => $invoice->balance_amount_ariary,
            'pdf_url' => $invoice->pdf_path ? url("/api/invoices/{$invoice->id}/pdf") : null,
            'finalized_at' => optional($invoice->finalized_at)->toDateTimeString(),
            'guest' => $invoice->reservation?->guest,
            'items' => $invoice->items
                ->where('type', '!=', 'tax')
                ->map(fn (InvoiceItem $item) => [
                'id' => $item->id,
                'description' => $item->description,
                'type' => $item->type,
                'amount_ariary' => (int) $item->amount_ariary,
                'quantity' => (int) $item->quantity,
                'line_total_ariary' => (int) $item->amount_ariary * (int) $item->quantity,
            ])->values(),
            'payments' => $invoice->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'amount_ariary' => (int) $payment->amount_ariary,
                'payment_method' => $payment->payment_method,
                'payment_context' => $payment->payment_context ?? 'payment',
                'reference' => $payment->reference,
                'processed_by_name' => $payment->processed_by_name,
                'processed_by_role' => $payment->processed_by_role,
                'created_at' => optional($payment->created_at)->toDateTimeString(),
            ])->values(),
        ];
    }

    private function invoiceHtml(Invoice $invoice, string $documentType = 'facture'): string
    {
        $guestName = e($invoice->reservation->guest->full_name ?? $invoice->reservation->client_name);
        $contactParts = array_filter([
            $invoice->reservation->customer_phone ?: $invoice->reservation->client_phone ?: null,
            $invoice->reservation->customer_email ?: null,
        ], fn ($value) => filled($value) && $value !== 'N/A');
        $contactLine = $contactParts ? e(implode(' | ', $contactParts)) : '';
        $invoiceNumber = e($invoice->invoice_number);
        $checkIn = $invoice->reservation->check_in_date->format('d/m/Y');
        $checkOut = $invoice->reservation->check_out_date->format('d/m/Y');
        $paidAmount = (int) $invoice->paid_amount_ariary;
        $balanceAmount = (int) $invoice->balance_amount_ariary;
        $depositAmount = (int) $invoice->deposit_amount_ariary;
        $visibleItems = $invoice->items->where('type', '!=', 'tax');
        $subtotal = (int) $visibleItems->sum(fn (InvoiceItem $item) => $item->amount_ariary * $item->quantity);
        $discountAmount = (int) $invoice->discount_amount_ariary;
        $showDiscount = $discountAmount > 0;
        $isProforma = $documentType === 'proforma';
        $documentLabel = $isProforma ? 'Facture proforma' : 'Facture de séjour';
        $amountLabel = $isProforma ? 'facture proforma' : 'facture';
        $logoDataUri = $this->hotelLogoDataUri();
        $accentColor = '#d10f0f';
        $accentSoft = $isProforma ? '#fff7f7' : '#fff1f1';
        $accentText = '#111111';
        $badgeBg = '#f6e2e2';
        $badgeText = '#9f1d1d';
        $paymentStatus = $balanceAmount > 0 ? 'Non soldée' : 'Payée';
        $paymentNotice = $balanceAmount > 0
            ? 'Facture pas encore payée intégralement'
            : 'Facture réglée intégralement';
        $rows = '';
        $paymentRows = '';

        foreach ($visibleItems as $item) {
            $lineTotal = $item->quantity * $item->amount_ariary;
            $rows .= '<tr>'
                . '<td>' . e($item->description) . '</td>'
                . '<td>' . $item->quantity . '</td>'
                . '<td>' . number_format($item->amount_ariary, 0, ',', ' ') . '</td>'
                . '<td>' . number_format($lineTotal, 0, ',', ' ') . '</td>'
                . '</tr>';
        }

        foreach ($invoice->payments as $payment) {
            $reference = $payment->reference ? e($payment->reference) : '-';
            $processedBy = $payment->processed_by_name ? e($payment->processed_by_name) : '-';
            $processedRole = $payment->processed_by_role ? e($payment->processed_by_role) : '-';
            $paymentContext = ($payment->payment_context ?? 'payment') === 'deposit' ? 'Acompte' : 'Paiement';
            $paymentRows .= '<tr>'
                . '<td>' . optional($payment->created_at)->format('d/m/Y H:i') . '</td>'
                . '<td>' . e($payment->payment_method) . '</td>'
                . '<td>' . $paymentContext . '</td>'
                . '<td>' . $processedBy . ' (' . $processedRole . ')</td>'
                . '<td>' . $reference . '</td>'
                . '<td>' . number_format($payment->amount_ariary, 0, ',', ' ') . '</td>'
                . '</tr>';
        }

        if ($paymentRows === '') {
            $paymentRows = '<tr><td colspan="6">Aucun paiement enregistré</td></tr>';
        }

        return "
            <html>
            <head>
                <meta charset='utf-8'>
                <style>
                    body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 28px; }
                    .document-ribbon { margin-bottom: 12px; padding: 9px 12px; background: {$accentSoft}; border: 1px solid {$accentColor}; color: {$accentText}; border-radius: 10px; text-align: center; font-size: 11px; font-weight: bold; letter-spacing: 0.8px; }
                    .topbar { display: table; width: 100%; border-bottom: 2px solid {$accentColor}; padding-bottom: 14px; margin-bottom: 18px; }
                    .brand { display: table-cell; width: 62%; vertical-align: top; }
                    .brand-logo { width: 155px; height: auto; display: block; margin-bottom: 4px; }
                    .brand-fallback { margin: 0; color: {$accentText}; font-size: 22px; letter-spacing: 0.4px; font-weight: bold; }
                    .brand .subtitle { color: #64748b; font-size: 11px; margin-top: 4px; }
                    .meta { display: table-cell; width: 38%; vertical-align: top; text-align: right; }
                    .pill { display: inline-block; padding: 6px 10px; border-radius: 999px; background: {$badgeBg}; color: {$badgeText}; font-weight: bold; font-size: 11px; }
                    .pill.unpaid { background: #fef3c7; color: #92400e; }
                    .info-grid { width: 100%; margin-bottom: 18px; }
                    .info-grid td { vertical-align: top; padding: 0; border: 0; }
                    .box { border: 1px solid #dbe4ea; border-radius: 8px; padding: 12px 14px; background: #fff; }
                    .box-title { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 6px; }
                    table.lines { width: 100%; border-collapse: collapse; margin-top: 4px; }
                    table.lines th, table.lines td { border-bottom: 1px solid #dbe4ea; padding: 9px 8px; text-align: left; }
                    table.lines th { background-color: #f8fafc; color: #0f172a; font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; }
                    table.lines td.num, table.lines th.num { text-align: right; }
                    .section-title { margin: 18px 0 8px; color: {$accentText}; font-size: 13px; font-weight: bold; }
                    .summary-wrap { width: 100%; margin-top: 16px; }
                    .summary { width: 42%; margin-left: auto; border: 1px solid #dbe4ea; border-radius: 10px; padding: 12px 14px; }
                    .summary-row { display: table; width: 100%; margin-bottom: 6px; }
                    .summary-row .label { display: table-cell; color: #475569; }
                    .summary-row .value { display: table-cell; text-align: right; }
                    .summary-row.total { margin-top: 8px; padding-top: 8px; border-top: 1px solid #cbd5e1; font-weight: bold; font-size: 13px; color: #0f172a; }
                    .summary-row.discount .value { color: #b91c1c; }
                    .summary-row.deposit .value { color: #0f766e; }
                    .notice { margin: 12px 0 18px; padding: 10px 12px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; border-radius: 8px; }
                    .deposit-note { margin-top: 8px; color: #0f766e; font-weight: bold; }
                </style>
            </head>
            <body>
                " . ($isProforma ? "<div class='document-ribbon'>DOCUMENT PROFORMA - NON VALABLE POUR COMPTABILISATION FINALE</div>" : "") . "
                <div class='topbar'>
                    <div class='brand'>
                        " . ($logoDataUri
                            ? "<img class='brand-logo' src='{$logoDataUri}' alt='Kamoro Hotel'>"
                            : "<div class='brand-fallback'>KAMORO HOTEL</div>") . "
                        <div class='subtitle'>{$documentLabel}</div>
                    </div>
                    <div class='meta'>
                        <div style='margin-bottom: 8px; font-weight: bold; color: {$accentText};'>" . ($isProforma ? 'Proforma n° ' : 'Facture n° ') . "{$invoiceNumber}</div>
                        <span class='pill " . ($balanceAmount > 0 ? 'unpaid' : '') . "'>{$paymentStatus}</span>
                    </div>
                </div>
                <div class='notice'>{$paymentNotice}</div>
                <table class='info-grid'>
                    <tr>
                        <td style='width: 56%; padding-right: 10px;'>
                            <div class='box'>
                                <div class='box-title'>Client</div>
                                <strong>{$guestName}</strong><br>
                                " . ($contactLine ? "Contact : {$contactLine}<br>" : '') . "
                                Séjour du {$checkIn} au {$checkOut}
                            </div>
                        </td>
                        <td>
                            <div class='box'>
                                <div class='box-title'>Récapitulatif</div>
                                Total prestations: " . number_format($subtotal, 0, ',', ' ') . " Ar<br>
                                " . ($showDiscount ? 'Remise: ' . number_format($discountAmount, 0, ',', ' ') . ' Ar' : '') . "
                            </div>
                        </td>
                    </tr>
                </table>
                <table class='lines'>
                    <thead>
                        <tr><th>Description</th><th class='num'>Qté</th><th class='num'>PU (Ar)</th><th class='num'>Total (Ar)</th></tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
                <div class='section-title'>Paiements</div>
                <table class='lines'>
                    <thead>
                        <tr><th>Date</th><th>Méthode</th><th>Type</th><th>Traité par</th><th>Référence</th><th class='num'>Montant (Ar)</th></tr>
                    </thead>
                    <tbody>{$paymentRows}</tbody>
                </table>
                <div class='summary-wrap'>
                    <div class='summary'>
                        <div class='summary-row'>
                            <div class='label'>Sous-total</div>
                            <div class='value'>" . number_format($subtotal, 0, ',', ' ') . " Ar</div>
                        </div>
                        " . ($depositAmount > 0 ? "
                        <div class='summary-row deposit'>
                            <div class='label'>Acompte versé</div>
                            <div class='value'>- " . number_format($depositAmount, 0, ',', ' ') . " Ar</div>
                        </div>
                        " : '') . "
                        " . ($showDiscount ? "
                        <div class='summary-row discount'>
                            <div class='label'>Remise</div>
                            <div class='value'>- " . number_format($discountAmount, 0, ',', ' ') . " Ar</div>
                        </div>
                        " : '') . "
                        <div class='summary-row total'>
                            <div class='label'>Total facture</div>
                            <div class='value'>" . number_format($invoice->total_amount_ariary, 0, ',', ' ') . " Ar</div>
                        </div>
                        <div class='summary-row'>
                            <div class='label'>Total payé</div>
                            <div class='value'>" . number_format($paidAmount, 0, ',', ' ') . " Ar</div>
                        </div>
                        <div class='summary-row total'>
                            <div class='label'>Reste à payer</div>
                            <div class='value'>" . number_format($balanceAmount, 0, ',', ' ') . " Ar</div>
                        </div>
                    </div>
                </div>
                " . ($depositAmount > 0 ? "<div class='deposit-note'>Acompte déjà versé : " . number_format($depositAmount, 0, ',', ' ') . " Ar</div>" : '') . "
                <div style='margin-top: 18px; font-weight: bold; text-transform: uppercase;'>
                    Arrêtée la présente {$amountLabel} à la somme de : " . e($this->amountInWords($invoice->total_amount_ariary)) . " (" . number_format($invoice->total_amount_ariary, 0, ',', ' ') . ") Ariary
                </div>
            </body>
            </html>
        ";
    }

    private function amountInWords(int $amount): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);
        $words = $formatter->format($amount) ?: (string) $amount;
        return mb_strtoupper(trim($words), 'UTF-8');
    }

    private function hotelLogoDataUri(): ?string
    {
        $root = dirname(base_path());
        $matches = glob($root . DIRECTORY_SEPARATOR . 'Capture*.png');
        $logoPath = $matches[0] ?? null;
        if (!$logoPath || !is_file($logoPath)) {
            return null;
        }

        $mime = mime_content_type($logoPath) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    private function ensureInvoicePdf(Invoice $invoice, string $documentType = 'facture'): void
    {
        $invoice->refresh();
        if (!$invoice->invoice_number) {
            $invoice->invoice_number = $this->nextInvoiceNumber();
            $invoice->save();
        }

        $invoice->load(['items', 'payments', 'reservation.guest', 'reservation.rooms']);
        $pdf = Pdf::loadHTML($this->invoiceHtml($invoice, $documentType));
        $path = "invoices/{$invoice->invoice_number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $invoice->update([
            'document_type' => $documentType,
            'pdf_path' => $path,
        ]);
    }

    private function syncInvoiceAfterPayment(Invoice $invoice): Invoice
    {
        $invoice->refresh();
        $depositAmount = (int) $invoice->payments()->where('payment_context', 'deposit')->sum('amount_ariary');

        $invoice->update([
            'deposit_amount_ariary' => $depositAmount,
        ]);

        $this->updatePaymentStatus($invoice->refresh());
        $invoice = $invoice->refresh()->load('reservation.guest', 'payments');
        $this->ensureInvoicePdf($invoice, $invoice->document_type ?? 'facture');

        return $invoice->refresh()->load('reservation.guest', 'payments');
    }

    private function applyInvoiceDiscount(Invoice $invoice, ?string $mode, mixed $value): void
    {
        $mode = in_array($mode, ['percent', 'amount'], true) ? $mode : null;
        $numericValue = is_numeric($value) ? (float) $value : null;
        $subtotal = (int) $invoice->items()->get()->sum(fn (InvoiceItem $item) => $item->amount_ariary * $item->quantity);

        $discountAmount = 0;
        if ($mode && $numericValue !== null && $numericValue > 0) {
            $discountAmount = $mode === 'percent'
                ? (int) round($subtotal * ($numericValue / 100))
                : (int) round($numericValue);
        }

        $discountAmount = min(max(0, $discountAmount), max(0, $subtotal));

        if (
            Schema::hasColumn('invoices', 'discount_mode')
            && Schema::hasColumn('invoices', 'discount_value')
            && Schema::hasColumn('invoices', 'discount_amount_ariary')
        ) {
            $invoice->update([
                'discount_mode' => $discountAmount > 0 ? $mode : null,
                'discount_value' => $discountAmount > 0 ? $numericValue : null,
                'discount_amount_ariary' => $discountAmount,
            ]);
        }

        $this->recalculateInvoice($invoice);
    }
}
