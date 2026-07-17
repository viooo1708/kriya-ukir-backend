<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Menampilkan seluruh pelanggan.
     */
    public function index()
    {
        $users = User::where('role', 'pelanggan')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'data' => $users
        ]);
    }

    /**
     * Detail pelanggan.
     */
    public function show(User $user)
    {
        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Memperbarui data pelanggan.
     */
    public function update(Request $request, User $user)
    {
        // 1. Validasi input dari frontend
        $request->validate([
            'nama'   => 'required|string|max:150',
            'no_hp'  => 'nullable|string|max:20',
            'role'   => 'required|string|in:pelanggan,owner',
            'alamat' => 'nullable|string',
        ]);

        // 2. Update data user
        $user->update([
            'nama'   => $request->nama,
            'no_hp'  => $request->no_hp,
            'role'   => $request->role,
            'alamat' => $request->alamat,
        ]);

        // 3. Kembalikan respon sukses JSON
        return response()->json([
            'message' => 'Data pelanggan berhasil diperbarui.',
            'data'    => $user
        ]);
    }

    /**
     * Hapus pelanggan.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'Pelanggan berhasil dihapus.'
        ]);
    }
}
