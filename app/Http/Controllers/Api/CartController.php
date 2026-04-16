<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(): JsonResponse
    {
        if ($authError = $this->authorizePegawaiUser()) {
            return $authError;
        }

        $cart = $this->getActiveCart();

        return response()->json([
            'success' => true,
            'message' => 'Keranjang berhasil diambil.',
            'data' => $cart ? $this->transformCart($cart) : null,
            'count_this_week' => $this->getWeeklyRequestCount(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($authError = $this->authorizePegawaiUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $cart = DB::transaction(function () use ($validated) {
                $item = Item::lockForUpdate()->findOrFail($validated['item_id']);

                if ($item->stock <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Stok {$item->name} sudah habis."],
                    ]);
                }

                $cart = Cart::firstOrCreate([
                    'user_id' => Auth::id(),
                    'status' => 'active',
                ]);

                $cartItem = CartItem::firstOrNew([
                    'cart_id' => $cart->id,
                    'item_id' => $item->id,
                ]);

                $newQuantity = ($cartItem->quantity ?? 0) + $validated['quantity'];
                if ($newQuantity > $item->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => [
                            "Jumlah melebihi stok {$item->name} (tersisa {$item->stock}).",
                        ],
                    ]);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->status = $cartItem->status ?? 'pending';
                $cartItem->save();

                return $this->getActiveCart();
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Barang berhasil dimasukkan ke keranjang.',
            'data' => $cart ? $this->transformCart($cart) : null,
            'count_this_week' => $this->getWeeklyRequestCount(),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        if ($authError = $this->authorizePegawaiUser()) {
            return $authError;
        }

        $cart = Cart::with(['cartItems.item'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail keranjang berhasil diambil.',
            'data' => $this->transformCart($cart),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($authError = $this->authorizePegawaiUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $cart = DB::transaction(function () use ($validated, $id) {
                $cartItem = CartItem::with(['cart', 'item'])->findOrFail($id);
                $cart = $cartItem->cart;

                if ($cart->user_id !== Auth::id() || $cart->status !== 'active') {
                    return null;
                }

                if ($validated['quantity'] > $cartItem->item->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => [
                            "Stok tidak mencukupi! Sisa stok: {$cartItem->item->stock}",
                        ],
                    ]);
                }

                $cartItem->quantity = $validated['quantity'];
                $cartItem->save();

                return $this->getActiveCart();
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Akses tidak sah.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jumlah barang berhasil diubah.',
            'data' => $this->transformCart($cart),
            'count_this_week' => $this->getWeeklyRequestCount(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        if ($authError = $this->authorizePegawaiUser()) {
            return $authError;
        }

        $result = DB::transaction(function () use ($id) {
            $cartItem = CartItem::with('cart')->findOrFail($id);
            $cart = $cartItem->cart;

            if ($cart->user_id !== Auth::id() || $cart->status !== 'active') {
                return false;
            }

            $cartItem->delete();

            if ($cart->cartItems()->count() === 0) {
                $cart->delete();
                return null;
            }

            return $this->getActiveCart();
        });

        if ($result === false) {
            return response()->json([
                'success' => false,
                'message' => 'Akses tidak sah.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk dihapus dari keranjang.',
            'data' => $result ? $this->transformCart($result) : null,
            'count_this_week' => $this->getWeeklyRequestCount(),
        ]);
    }

    public function submit(string $id): JsonResponse
    {
        if ($authError = $this->authorizePegawaiUser()) {
            return $authError;
        }

        try {
            $cart = DB::transaction(function () use ($id) {
                $cart = Cart::with('cartItems.item')
                    ->where('id', $id)
                    ->where('user_id', Auth::id())
                    ->where('status', 'active')
                    ->first();

                if (! $cart) {
                    return false;
                }

                if ($cart->cartItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => ['Keranjang masih kosong.'],
                    ]);
                }

                foreach ($cart->cartItems as $cartItem) {
                    $item = Item::lockForUpdate()->findOrFail($cartItem->item_id);

                    if ($item->stock <= 0) {
                        throw ValidationException::withMessages([
                            'cart' => ["Stok {$item->name} sudah habis. Silakan hapus dari keranjang."],
                        ]);
                    }

                    if ($cartItem->quantity > $item->stock) {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Stok {$item->name} tidak mencukupi (tersedia: {$item->stock}, diminta: {$cartItem->quantity}).",
                            ],
                        ]);
                    }
                }

                $cart->update(['status' => 'pending']);
                return $cart->fresh(['cartItems.item']);
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($cart === false) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang aktif tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan berhasil diajukan, menunggu persetujuan admin.',
            'data' => $this->transformCart($cart),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        if ($authError = $this->authorizePegawaiUser()) {
            return $authError;
        }

        $status = $request->get('status');
        $query = Cart::withCount('cartItems')
            ->with(['cartItems.item'])
            ->where('user_id', Auth::id())
            ->where('status', '!=', 'active');

        if ($status && $status !== 'all') {
            if ($status === 'approved') {
                $query->whereIn('status', ['approved', 'approved_partially']);
            } else {
                $query->where('status', $status);
            }
        }

        $carts = $query->latest()->get();

        $statusCounts = [
            'all' => Cart::where('user_id', Auth::id())->where('status', '!=', 'active')->count(),
            'pending' => Cart::where('user_id', Auth::id())->where('status', 'pending')->count(),
            'approved' => Cart::where('user_id', Auth::id())->whereIn('status', ['approved', 'approved_partially'])->count(),
            'approved_partially' => Cart::where('user_id', Auth::id())->where('status', 'approved_partially')->count(),
            'rejected' => Cart::where('user_id', Auth::id())->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Riwayat permintaan berhasil diambil.',
            'data' => $carts->map(fn (Cart $cart) => $this->transformCart($cart))->values(),
            'status_counts' => $statusCounts,
        ]);
    }

    private function getWeeklyRequestCount(): int
    {
        $now = Carbon::now('Asia/Jakarta');
        $daysToSubtract = ($now->dayOfWeek === Carbon::SUNDAY) ? 6 : $now->dayOfWeek - 1;
        $startOfWeek = $now->copy()->subDays($daysToSubtract)->startOfDay();
        $endOfWeek = $startOfWeek->copy()->addDays(6)->endOfDay();

        return Cart::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->count();
    }

    private function getActiveCart(): ?Cart
    {
        return Cart::with(['cartItems.item'])
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();
    }

    private function transformCart(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'status' => $cart->status,
            'created_at' => optional($cart->created_at)?->toIso8601String(),
            'updated_at' => optional($cart->updated_at)?->toIso8601String(),
            'items_count' => $cart->cartItems->count(),
            'total_quantity' => $cart->cartItems->sum('quantity'),
            'items' => $cart->cartItems->map(function (CartItem $cartItem) {
                return [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'status' => $cartItem->status,
                    'rejection_reason' => $cartItem->rejection_reason,
                    'item' => [
                        'id' => $cartItem->item?->id,
                        'name' => $cartItem->item?->name,
                        'stock' => $cartItem->item?->stock,
                        'price' => $cartItem->item?->price,
                        'image' => $cartItem->item?->image,
                    ],
                ];
            })->values(),
        ];
    }

    private function authorizePegawaiUser(): ?JsonResponse
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = Auth::user();

        if ($user->role !== 'pegawai') {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akses API ini hanya untuk pegawai.',
            ], 403);
        }

        if ($user->is_banned) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun pegawai ini sedang diblokir.',
            ], 403);
        }

        return null;
    }
}
