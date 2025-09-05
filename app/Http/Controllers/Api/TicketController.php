<?php

/**
 * TODO: Ticket API Controller
 * 
 * Requirements from specification:
 * - POST /tickets – create new ticket
 * - GET /tickets – list with filter, search, pagination params
 * - GET /tickets/{id} – get ticket detail
 * - PATCH /tickets/{id} – update status, category, note
 * - POST /tickets/{id}/classify – dispatch queued AI job
 * - GET /stats – JSON for dashboard charts
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Jobs\ClassifyTicket;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $tickets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = Ticket::create($request->validated());

        return response()->json($ticket, 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validated();
        
        // Track if category was manually changed
        if (isset($data['category']) && $data['category'] !== $ticket->category) {
            $data['manually_categorized'] = true;
        }

        $ticket->update($data);

        return response()->json($ticket);
    }

    public function classify(Ticket $ticket): JsonResponse
    {
        try {
            ClassifyTicket::dispatch($ticket);

            return response()->json([
                'message' => 'Classification job queued successfully',
                'ticket_id' => $ticket->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue classification job', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to queue classification job',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total_tickets' => Ticket::count(),
            'by_status' => Ticket::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_category' => Ticket::whereNotNull('category')
                ->select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'classified_tickets' => Ticket::whereNotNull('category')->count(),
            'average_confidence' => Ticket::whereNotNull('confidence')->avg('confidence'),
        ];

        return response()->json($stats);
    }
}
