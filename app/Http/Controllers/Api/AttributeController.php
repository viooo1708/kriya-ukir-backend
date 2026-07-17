<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttributeController extends Controller
{
    /**
     * Ambil daftar opsi berdasarkan kategori (type).
     * Jika 'type' tidak dikirim, kembalikan semua kategori sekaligus (dikelompokkan).
     */
    public function index(Request $request)
    {
        if ($request->filled('type')) {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:motif,jenis_ukiran,bahan,ukuran',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = Attribute::type($request->type)->orderBy('value')->get();

            return response()->json(['data' => $data]);
        }

        // Tanpa filter type -> kembalikan semua, dikelompokkan per kategori
        $all = Attribute::orderBy('value')->get()->groupBy('type');

        return response()->json(['data' => $all]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:motif,jenis_ukiran,bahan,ukuran',
            'value' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Cegah duplikat (case-insensitive)
        $existing = Attribute::type($request->type)
            ->whereRaw('LOWER(value) = ?', [strtolower(trim($request->value))])
            ->first();

        if ($existing) {
            return response()->json(['data' => $existing]);
        }

        $attribute = Attribute::create([
            'type' => $request->type,
            'value' => trim($request->value),
        ]);

        return response()->json(['data' => $attribute], 201);
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();

        return response()->json(['message' => 'Opsi berhasil dihapus']);
    }
}
