<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;

class ProductController extends Controller
{
    /**
     * Menampilkan katalog produk (dapat diakses pelanggan & owner).
     */
    public function index()
    {
        $products = Product::latest()->get();

        $products->transform(function ($product) {

            if ($product->gambar) {

                $product->gambar = url($product->gambar);

            }

            return $product;
        });

        return response()->json([
            'data' => $products
        ]);
    }

    /**
     * Menampilkan detail satu produk.
     */
    public function show(Product $product)
    {
        if ($product->gambar) {

            $product->gambar = url($product->gambar);

        }

        return response()->json([
            'data' => $product
        ]);
    }

    /**
     * Menambahkan produk baru (khusus owner).
     */
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
            $path = $request->file('gambar')->store('products', 'public');
            $data['gambar'] = url(Storage::url($path));
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product,
        ], 201);
    }

    /**
     * Mengedit produk (khusus owner).
     */
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
            $path = $request->file('gambar')->store('products', 'public');
            $data['gambar'] = url(Storage::url($path));
        }

        $product->update($data);

        return response()->json([
            'message' => 'Produk berhasil diperbarui',
            'data' => $product->fresh(),
        ]);
    }

    /**
     * Menghapus produk (khusus owner).
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Produk berhasil dihapus']);
    }
}
