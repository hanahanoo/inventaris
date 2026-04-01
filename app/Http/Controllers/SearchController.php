<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Category;
use App\Models\Guest;

class SearchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('q');
        $categoryId = $request->input('category_id');

        $items = Item::with('category')
            ->when(!$query, function ($q) {
                $q->where('stock', '>', 0);
            })
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            })
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->latest()
            ->paginate(12);

        $categories = Category::all();

        // JIKA REQUEST ADALAH AJAX
        if ($request->ajax()) {
            return view('role.pegawai.partials.product_list', compact('items'))->render();
        }

        return view('role.pegawai.produk', compact('items', 'categories'))
            ->with('search', $query);
    }


     /**
     * 🔍 Pencarian tamu (untuk admin)
     */
    public function searchGuests(Request $request)
    {
        $query = $request->input('q');

        $guests = Guest::with('creator')
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%")
                        ->orWhereHas('creator', function ($creator) use ($query) {
                            $creator->where('name', 'LIKE', "%{$query}%");
                        });
                });
            })
            ->latest()
            ->paginate(10);

        return view('role.admin.guest', compact('guests'))
            ->with('search', $query);
    }



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
