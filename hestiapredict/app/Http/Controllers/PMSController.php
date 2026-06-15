<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class PMSController extends Controller
{
    /**
     * POST /api/reservations/{id}/checkin
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'id_type' => 'required|in:CIN,Passeport,Permis',
            'id_number' => 'required|string|max:100',
            'id_photo' => 'nullable|image|max:5120', // Max 5MB
        ]);

        $reservation = Reservation::with('rooms')->findOrFail($id);

        if ($reservation->status === 'arrive') {
            return response()->json(['message' => 'Réservation déjà en statut "Arrivée"'], 400);
        }

        $photoPath = null;
        if ($request->hasFile('id_photo')) {
            $photoPath = $request->file('id_photo')->store('id_photos', 'public');
        }

        // 1. Update reservation status
        $reservation->update(['status' => 'arrive']);

        // 2. Create Guest profile
        $guest = Guest::create([
            'reservation_id' => $reservation->id,
            'full_name' => $validated['full_name'],
            'date_of_birth' => $validated['date_of_birth'],
            'id_type' => $validated['id_type'],
            'id_number' => $validated['id_number'],
            'id_photo_path' => $photoPath,
        ]);

        // 3. Generate Initial Invoice
        $invoiceNumber = 'FACT-' . date('Y') . '-' . str_pad($reservation->id, 4, '0', STR_PAD_LEFT);
        
        $invoice = Invoice::firstOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'invoice_number' => $invoiceNumber,
                'status' => 'open',
            ]
        );

        // Only add room items if the invoice is new (no items yet)
        if ($invoice->items()->count() === 0) {
            $checkInDate = Carbon::parse($reservation->check_in_date);
            $checkOutDate = Carbon::parse($reservation->check_out_date);
            $nights = max(1, $checkInDate->diffInDays($checkOutDate));

            $totalRoomsAmount = 0;
            $touristTaxPerNight = 2000; // Taxe de séjour arbitraire par chambre par nuit

            foreach ($reservation->rooms as $room) {
                $pricePerNight = $room->pivot->price_snapshot_ariary ?? $room->base_price_ariary;
                $roomTotal = $pricePerNight * $nights;
                
                // Add Room Item
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Chambre {$room->room_number} ({$room->type}) - $nights nuit(s)",
                    'type' => 'room',
                    'amount_ariary' => $roomTotal,
                    'quantity' => 1,
                ]);

                // Add Tourist Tax
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Taxe de séjour - Chambre {$room->room_number}",
                    'type' => 'tax',
                    'amount_ariary' => $touristTaxPerNight * $nights,
                    'quantity' => 1,
                ]);

                $totalRoomsAmount += $roomTotal + ($touristTaxPerNight * $nights);
            }

            // Calcul de TVA sur les chambres (ex: 20%)
            $tva = intval($totalRoomsAmount * 0.20);
            
            $invoice->update([
                'total_amount_ariary' => $totalRoomsAmount,
                'tax_amount_ariary' => $tva, // Just for reference
            ]);
        }

        return response()->json([
            'message' => 'Check-in réussi',
            'guest' => $guest,
            'invoice' => $invoice->load('items'),
        ]);
    }

    /**
     * GET /api/reservations/{id}/folio
     */
    public function getFolio(int $id): JsonResponse
    {
        $invoice = Invoice::with(['items', 'payments', 'reservation.guest'])
            ->where('reservation_id', $id)
            ->first();

        if (!$invoice) {
            return response()->json(['message' => 'Aucune facture trouvée pour cette réservation'], 404);
        }

        return response()->json($invoice);
    }

    /**
     * POST /api/invoices/{id}/items
     */
    public function addInvoiceItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'type' => 'required|in:room,tax,extra',
            'amount_ariary' => 'required|integer|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Facture déjà payée, impossible d\'ajouter des extras.'], 400);
        }

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $validated['description'],
            'type' => $validated['type'],
            'amount_ariary' => $validated['amount_ariary'],
            'quantity' => $validated['quantity'],
        ]);

        $invoice->increment('total_amount_ariary', $item->amount_ariary * $item->quantity);

        return response()->json([
            'message' => 'Extra ajouté avec succès',
            'item' => $item,
            'invoice' => $invoice->refresh()->load('items'),
        ]);
    }

    /**
     * POST /api/invoices/{id}/payments
     */
    public function addPayment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount_ariary' => 'required|integer|min:1',
            'payment_method' => 'required|string|max:50',
        ]);

        $invoice = Invoice::with('payments')->findOrFail($id);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount_ariary' => $validated['amount_ariary'],
            'payment_method' => $validated['payment_method'],
        ]);

        $totalPaid = $invoice->payments()->sum('amount_ariary');
        if ($totalPaid >= $invoice->total_amount_ariary) {
            $invoice->update(['status' => 'paid']);
        }

        return response()->json([
            'message' => 'Paiement enregistré',
            'payment' => $payment,
            'invoice' => $invoice->refresh()->load(['items', 'payments']),
        ]);
    }

    /**
     * POST /api/invoices/{id}/generate-pdf
     */
    public function generatePdf(int $id)
    {
        $invoice = Invoice::with(['items', 'payments', 'reservation.guest', 'reservation.rooms'])->findOrFail($id);

        // Mark as closed/paid if not already
        if ($invoice->status !== 'paid') {
            // Ideally we check if balance is 0, but for check-out we'll close it
            $invoice->update(['status' => 'paid']);
        }

        // HTML content for the PDF (Minimalist)
        $html = "
            <html>
            <head>
                <style>
                    body { font-family: 'Helvetica', sans-serif; color: #333; }
                    .header { text-align: center; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
                    .details { margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f4f4f4; }
                    .totals { text-align: right; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>Hestia Hotel - Facture</h1>
                    <p>Référence: {$invoice->invoice_number}</p>
                </div>
                <div class='details'>
                    <p><strong>Client:</strong> " . ($invoice->reservation->guest->full_name ?? $invoice->reservation->client_name) . "</p>
                    <p><strong>Arrivée:</strong> {$invoice->reservation->check_in_date->format('d/m/Y')}</p>
                    <p><strong>Départ:</strong> {$invoice->reservation->check_out_date->format('d/m/Y')}</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Qté</th>
                            <th>Montant (Ar)</th>
                            <th>Total (Ar)</th>
                        </tr>
                    </thead>
                    <tbody>";
        
        foreach ($invoice->items as $item) {
            $lineTotal = $item->quantity * $item->amount_ariary;
            $html .= "<tr>
                        <td>{$item->description}</td>
                        <td>{$item->quantity}</td>
                        <td>" . number_format($item->amount_ariary, 0, ',', ' ') . "</td>
                        <td>" . number_format($lineTotal, 0, ',', ' ') . "</td>
                      </tr>";
        }

        $html .= "  </tbody>
                </table>
                <div class='totals'>
                    <p><strong>Total Facture:</strong> " . number_format($invoice->total_amount_ariary, 0, ',', ' ') . " Ar</p>
                    <p>Total Payé: " . number_format($invoice->payments->sum('amount_ariary'), 0, ',', ' ') . " Ar</p>
                </div>
            </body>
            </html>
        ";

        $pdf = Pdf::loadHTML($html);
        
        $filename = "invoices/{$invoice->invoice_number}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        return response()->json([
            'message' => 'Facture générée avec succès',
            'pdf_url' => url("storage/$filename"),
        ]);
    }
}
