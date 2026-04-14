<?php

namespace App\Exports;

use App\Models\Item_in;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BarangMasukExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Item_in::with('item')
            ->get()
            ->map(function ($row) {
                return [
                    'ID' => $row->id,
                    'Nama Barang' => $row->item->name,
                    'Supplier' => $row->supplier->name,
                    'Tanggal Masuk' => $row->created_at->format('d-m-Y'),
                    'Jumlah' => $row->quantity,
                    'Satuan' => $row->item->unit->name,
                    'Harga Satuan(Rp)' => 'Rp ' . number_format($row->item->price, 0, ',', '.'),
                    'Total Harga(Rp)' => 'Rp ' . number_format($row->total_price, 0, ',', '.'),
                ];
            });
    }

    public function headings(): array
    {
        return ['ID', 'Nama Barang', 'Supplier', 'Tanggal Masuk', 'Jumlah', 'Satuan', 'Harga Satuan(Rp)', 'Total Harga(Rp)'];
    }
}
