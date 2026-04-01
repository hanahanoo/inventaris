<?php
namespace App\Http\Controllers\Role\admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Item_out;
use App\Models\Item;
use App\Models\Guest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItemoutController extends Controller
{

    /**
     * Tampilkan daftar item keluar (pegawai & tamu)
     */
    public function index(Request $request)
    {
        $search = $request->get('q');

        // 🔹 Query untuk approvedItems dengan pagination database
        $approvedItemsQuery = Cart::with(['cartItems' => function ($q) {
                $q->whereNull('scanned_at')
                ->where(function ($query) {
                    $query->where('status', 'approved')
                            ->orWhereNull('status');
                })
                ->with('item');
            }, 'user'])
            ->whereIn('status', ['approved', 'approved_partially'])
            ->whereHas('cartItems', function ($q) {
                $q->whereNull('scanned_at')
                ->where(function ($query) {
                    $query->where('status', 'approved')
                            ->orWhereNull('status');
                });
            });

        // 🔍 Filter pencarian
        if ($search) {
            $approvedItemsQuery->where(function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('cartItems.item', function ($itemQuery) use ($search) {
                    $itemQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        // ✅ Pagination database yang benar
        $approvedItems = $approvedItemsQuery->latest()->paginate(10);

        // 🔹 Guest items (jika masih perlu)
        $guestItemOuts = Guest::with(['guestCart.guestCartItems.item'])
            ->whereHas('guestCart.guestCartItems')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('role.admin.itemout', [
            'approvedItems' => $approvedItems, // ← Pagination normal
            'guestItemOuts' => $guestItemOuts,
            'search' => $search,
        ]);
    }

    /**
     * Scan item berdasarkan barcode.
     */
   public function scan(Request $request, $cartId)
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);

        $cart = Cart::with('cartItems.item')->findOrFail($cartId);
        $barcode = trim($request->barcode);

        // Cari item berdasarkan barcode
        $cartItem = $cart->cartItems->first(fn($ci) => optional($ci->item)->code === $barcode);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'status'  => 'invalid',
                'message' => '❌ Kode / QR tidak sesuai dengan barang dalam permintaan ini.',
            ], 200);
        }

        // Kalau item ini sudah discan di database (hasil release sebelumnya)
        if ($cartItem->scanned_at) {
            return response()->json([
                'success' => false,
                'status'  => 'duplicate',
                'message' => '⚠️ Barang ini sudah pernah dipindai sebelumnya.',
            ], 200);
        }

        // 🚫 Jangan update DB di sini — hanya kirim respon validasi
        return response()->json([
            'success' => true,
            'status'  => 'valid',
            'message' => "✅ Barang {$cartItem->item->name} cocok dengan daftar.",
            'item' => [
                'id'        => $cartItem->item->id,
                'name'      => $cartItem->item->name,
                'code'      => $cartItem->item->code,
                'quantity'  => $cartItem->quantity,
            ],
        ]);
    }

    /**
     * Release barang keluar.
     */
    public function release(Request $request, $cartId)
    {
        Log::info('DEBUG release payload', $request->all());

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::with('cartItems')->findOrFail($cartId);
        $scannedItems = $request->input('items', []);

        DB::beginTransaction();

        try {
            $insufficientItems = [];

            // 1. VALIDASI STOK SEMUA BARANG (Loop Pertama)
            foreach ($scannedItems as $sItem) {
                $item = Item::where('id', $sItem['id'])->lockForUpdate()->first();
                $qty = (int) $sItem['quantity'];

                if (!$item || $item->stock < $qty) {
                    $insufficientItems[] = [
                        'name' => $item ? $item->name : 'Unknown Item',
                        'requested' => $qty,
                        'available' => $item ? $item->stock : 0
                    ];
                }
            }

            // Jika ada yang kurang, stop di sini dan kirim SEMUA daftar barangnya
            if (count($insufficientItems) > 0) {
                DB::rollBack();
                $itemNames = collect($insufficientItems)->pluck('name')->implode(', ');
                
                return response()->json([
                    'success' => false,
                    'error_type' => 'INSUFFICIENT_STOCK',
                    'problem_items' => $insufficientItems, 
                    'message' => "Stok untuk barang berikut tidak mencukupi: " . $itemNames
                ], 422);
            }
            foreach ($scannedItems as $sItem) {
                $item = Item::find($sItem['id']);
                $qty = (int) $sItem['quantity'];

                // Cek duplikasi record item_out
                $existingItemOut = Item_out::where('cart_id', $cart->id)
                    ->where('item_id', $item->id)
                    ->first();

                if (!$existingItemOut) {
                    $itemOut = new Item_out();
                    $itemOut->cart_id = $cart->id;
                    $itemOut->item_id = $item->id;
                    $itemOut->quantity = $qty;
                    $itemOut->unit_id = $item->unit_id;
                    $itemOut->released_at = now();
                    $itemOut->approved_by = Auth::id();
                    $itemOut->save();
                    
                    // Kurangi stok barang di sini (Opsional, tergantung flow abang)
                    // $item->decrement('stock', $qty); 
                }

                // Update scanned_at pada cart_item
                CartItem::where('cart_id', $cart->id)
                    ->where('item_id', $item->id)
                    ->whereNull('scanned_at')
                    ->update(['scanned_at' => now()]);
            }

            // ✅ PERBAIKAN: Hanya update picked_up_at, status tetap 'approved'
           $cart->update(['picked_up_at' => now()]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✅ Semua barang berhasil dikeluarkan dan dicatat.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Release error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses release: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, $cartId)
    {
        Log::info("🚀 REJECT PROCESS START - Cart ID: {$cartId}");
        DB::beginTransaction();
        try {
            $cart = Cart::with('cartItems.item')->findOrFail($cartId);
            $rejectedNames = [];

            foreach ($cart->cartItems as $cartItem) {
                if ($cartItem->item->stock < $cartItem->quantity) {
                    $rejectedNames[] = $cartItem->item->name;
                    
                    DB::table('cart_items')->where('id', $cartItem->id)->update([
                        'status' => 'rejected',
                        'rejection_reason' => 'Stok tidak mencukupi saat release.',
                        'scanned_at' => null,
                        'updated_at' => now()
                    ]);
                }
            }

            $remainingItems = DB::table('cart_items')
                ->where('cart_id', $cartId)
                ->where('status', '!=', 'rejected')
                ->count();

            if ($remainingItems == 0) {
                $cart->update(['status' => 'rejected']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'can_continue' => $remainingItems > 0, // Flag untuk JS
                'message' => "Barang [" . implode(', ', $rejectedNames) . "] telah ditolak karena stok habis. " . 
                            ($remainingItems > 0 ? "Silakan proses sisa barang lainnya." : "Seluruh keranjang telah ditutup.")
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cek apakah semua item sudah discan.
     */
    public function checkAllScanned($cartId)
    {
        $cart = Cart::with('cartItems')->findOrFail($cartId);
        $allScanned = $cart->cartItems->every(fn($i) => $i->scanned_at);

        return response()->json(['all_scanned' => $allScanned]);
    }

    /**
     * Tampilkan struk langsung di browser.
     */
    public function struk($id)
    {
        $cart = Cart::with(['user', 'cartItems.item'])->findOrFail($id);
        $itemOut = Item_out::where('cart_id', $cart->id)->get();

        $pdf = Pdf::loadView('role.admin.export.struk', compact('cart', 'itemOut'));

        return $pdf->stream('struk-pemesanan-' . $cart->id . '.pdf');
    }

    /**
     * Download struk dalam bentuk PDF.
     */
    public function generateStruk($cartId)
    {
        $cart = Cart::with(['cartItems.item', 'user'])->findOrFail($cartId);
        $itemOut = Item_out::where('cart_id', $cartId)->get();

        $pdf = Pdf::loadView('role.admin.export.struk', compact('cart', 'itemOut'));

        return $pdf->download('struk_cart_' . $cart->id . '.pdf');
    }

    // Placeholder method bawaan resource controller
    public function create() {}
    public function store(Request $request) {}
    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}
