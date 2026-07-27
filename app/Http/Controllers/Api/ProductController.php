<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::latest()->get();

        $products->transform(function ($product) {
            if ($product->gambar && !str_starts_with($product->gambar, 'http')) {
                $product->gambar = asset('storage/' . $product->gambar);
            }
            return $product;
        });

        return response()->json([
            'data' => $products
        ]);
    }

    public function show(Product $product)
    {
        if ($product->gambar && !str_starts_with($product->gambar, 'http')) {
            $product->gambar = asset('storage/' . $product->gambar);
        }

        return response()->json([
            'data' => $product
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_product' => 'required|string|max:150',
            'jenis_ukiran' => 'nullable|string|max:100',
            'ukuran' => 'nullable|string|max:100',
            'bahan' => 'nullable|string|max:100',
            'motif' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'gambar' => 'nullable|image|max:2048',
            'estimasi_harga' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('gambar')) {
            // Simpan hanya path relatifnya (misal: products/xxx.jpg)
            $data['gambar'] = $request->file('gambar')->store('products', 'public');
        }

        $product = Product::create($data);

        // Ubah response path ke URL utuh untuk ditampilkan
        if ($product->gambar) {
            $product->gambar = asset('storage/' . $product->gambar);
        }

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'nama_product' => 'sometimes|string|max:150',
            'jenis_ukiran' => 'nullable|string|max:100',
            'ukuran' => 'nullable|string|max:100',
            'bahan' => 'nullable|string|max:100',
            'motif' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'gambar' => 'nullable|image|max:2048',
            'estimasi_harga' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('gambar')) {
            // Hapus gambar lama jika ada
            if ($product->gambar) {
                Storage::disk('public')->delete(str_replace(asset('storage/'), '', $product->gambar));
            }
            $data['gambar'] = $request->file('gambar')->store('products', 'public');
        }

        $product->update($data);

        $productResponse = $product->fresh();
        if ($productResponse->gambar && !str_starts_with($productResponse->gambar, 'http')) {
            $productResponse->gambar = asset('storage/' . $productResponse->gambar);
        }

        return response()->json([
            'message' => 'Produk berhasil diperbarui',
            'data' => $productResponse,
        ]);
    }

    public function destroy(Product $product)
    {
        if ($product->gambar) {
            Storage::disk('public')->delete(str_replace(asset('storage/'), '', $product->gambar));
        }

        $product->delete();

        return response()->json(['message' => 'Produk berhasil dihapus']);
    }
}
